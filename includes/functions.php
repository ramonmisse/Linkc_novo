<?php
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'config/cielo.php';

function calculateInterest($amount, $installments) {
    if ($installments >= 4 && $installments <= 6) {
        return $amount * 0.04; // 4% interest
    }
    return 0;
}

function calculateFinalAmount($amount, $installments) {
    return $amount + calculateInterest($amount, $installments);
}

function createPaymentLink($user_id, $amount, $installments, $description = '') {
    try {
        $database = new Database();
        $db = $database->getConnection();
        $cielo = new CieloAPI();
        
        $final_amount = calculateFinalAmount($amount, $installments);
        $interest = calculateInterest($amount, $installments);
        
        // Create payment link with Cielo
        $cielo_response = $cielo->createPaymentLink($amount, $installments, $description);
        
        if (!$cielo_response['success']) {
            $detailed_error = $cielo_response['error'];
            if (isset($cielo_response['http_code'])) {
                $detailed_error .= " (HTTP: " . $cielo_response['http_code'] . ")";
            }
            if (isset($cielo_response['raw_response'])) {
                error_log("Cielo Full Response: " . $cielo_response['raw_response']);
            }
            return array('success' => false, 'message' => $detailed_error);
        }
        
        // Save to database
        $query = "INSERT INTO payment_links (user_id, valor_original, valor_juros, valor_final, parcelas, link_url, payment_id, status, status_cielo, descricao, created_at) 
                  VALUES (:user_id, :valor_original, :valor_juros, :valor_final, :parcelas, :link_url, :payment_id, 'Aguardando Pagamento', :status_cielo, :descricao, NOW())";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':valor_original', $amount);
        $stmt->bindParam(':valor_juros', $interest);
        $stmt->bindParam(':valor_final', $final_amount);
        $stmt->bindParam(':parcelas', $installments);
        $stmt->bindParam(':link_url', $cielo_response['link']);
        $stmt->bindParam(':payment_id', $cielo_response['payment_id']);
        $stmt->bindParam(':status_cielo', $cielo_response['status']);
        $stmt->bindParam(':descricao', $description);
        
        if ($stmt->execute()) {
            return array(
                'success' => true,
                'link_id' => $db->lastInsertId(),
                'link_url' => $cielo_response['link']
            );
        }
        
        return array('success' => false, 'message' => 'Erro ao salvar link no banco de dados');
    } catch (Exception $e) {
        error_log("Create payment link error: " . $e->getMessage());
        return array('success' => false, 'message' => 'Erro interno do sistema');
    }
}

function getPaymentLinks($user_id, $nivel_acesso) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        if (in_array($nivel_acesso, ['admin', 'editor'])) {
            // Admin and editor see all links
            $query = "SELECT pl.*, u.nome as user_name FROM payment_links pl 
                      LEFT JOIN usuarios u ON pl.user_id = u.id 
                      ORDER BY pl.created_at DESC";
            $stmt = $db->prepare($query);
        } else {
            // Regular users see only their links
            $query = "SELECT * FROM payment_links WHERE user_id = :user_id ORDER BY created_at DESC";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
        }
        
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (Exception $e) {
        error_log("Get payment links error: " . $e->getMessage());
        return array();
    }
}

function updatePaymentStatus($link_id, $status, $user_level) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Only admin and editor can update status
        if (!in_array($user_level, ['admin', 'editor'])) {
            return array('success' => false, 'message' => 'Acesso negado');
        }
        
        // Check current status
        $query = "SELECT status FROM payment_links WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $link_id);
        $stmt->execute();
        $current = $stmt->fetch();
        
        if (!$current) {
            return array('success' => false, 'message' => 'Link não encontrado');
        }
        
        // Only allow changing to "Crédito Gerado" if current status is "Pago"
        if ($status === 'Crédito Gerado' && $current['status'] !== 'Pago') {
            return array('success' => false, 'message' => 'Só é possível alterar para "Crédito Gerado" quando o status for "Pago"');
        }
        
        $query = "UPDATE payment_links SET status = :status, updated_at = NOW() WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':id', $link_id);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Status atualizado com sucesso');
        }
        
        return array('success' => false, 'message' => 'Erro ao atualizar status');
    } catch (Exception $e) {
        error_log("Update payment status error: " . $e->getMessage());
        return array('success' => false, 'message' => 'Erro interno do sistema');
    }
}

function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Aguardando Pagamento':
            return 'bg-warning';
        case 'Pago':
            return 'bg-success';
        case 'Crédito Gerado':
            return 'bg-primary';
        default:
            return 'bg-secondary';
    }
}
?>
