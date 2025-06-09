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
        error_log("Iniciando criação de link de pagamento");
        error_log("Dados recebidos - user_id: $user_id, amount: $amount, installments: $installments, description: $description");
        
        $database = new Database();
        $db = $database->getConnection();
        $cielo = new CieloAPI();
        
        $final_amount = calculateFinalAmount($amount, $installments);
        $interest = calculateInterest($amount, $installments);
        
        error_log("Valores calculados - final_amount: $final_amount, interest: $interest");
        
        // Create payment link with Cielo
        error_log("Chamando API Cielo para criar link");
        $cielo_response = $cielo->createPaymentLink($amount, $installments, $description);
        error_log("Resposta da Cielo: " . json_encode($cielo_response));
        
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
        
        // Get detailed link information
        error_log("Buscando informações detalhadas do link");
        $link_info = $cielo->GetLinksInfo($cielo_response['payment_id']);
        error_log("Informações do link: " . json_encode($link_info));
        
        // Save to database
        error_log("Preparando para salvar no banco de dados");
        $query = "INSERT INTO payment_links (user_id, valor_original, valor_juros, valor_final, parcelas, link_url, payment_id, product_id, status, status_cielo, descricao, tipo_link, data_expiracao, url_completa, url_curta, created_at) 
                  VALUES (:user_id, :valor_original, :valor_juros, :valor_final, :parcelas, :link_url, :payment_id, :product_id, 'Aguardando Pagamento', :status_cielo, :descricao, :tipo_link, :data_expiracao, :url_completa, :url_curta, NOW())";
        
        try {
            $stmt = $db->prepare($query);
            
            // Log dos valores que serão inseridos
            $insert_values = array(
                'user_id' => $user_id,
                'valor_original' => $amount,
                'valor_juros' => $interest,
                'valor_final' => $final_amount,
                'parcelas' => $installments,
                'link_url' => $cielo_response['link'],
                'payment_id' => $cielo_response['payment_id'],
                'product_id' => $cielo_response['payment_id'], // O product_id é o mesmo que payment_id na API da Cielo
                'status_cielo' => $cielo_response['status'],
                'descricao' => $description
            );
            error_log("Valores para inserção: " . json_encode($insert_values));
            
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':valor_original', $amount);
            $stmt->bindParam(':valor_juros', $interest);
            $stmt->bindParam(':valor_final', $final_amount);
            $stmt->bindParam(':parcelas', $installments);
            $stmt->bindParam(':link_url', $cielo_response['link']);
            $stmt->bindParam(':payment_id', $cielo_response['payment_id']);
            $stmt->bindParam(':product_id', $cielo_response['payment_id']); // O product_id é o mesmo que payment_id
            $stmt->bindParam(':status_cielo', $cielo_response['status']);
            $stmt->bindParam(':descricao', $description);
            
            // Bind additional link information if available
            if ($link_info['success']) {
                error_log("Vinculando informações adicionais do link");
                $stmt->bindParam(':tipo_link', $link_info['data']['tipo_link']);
                $stmt->bindParam(':data_expiracao', $link_info['data']['data_expiracao']);
                $stmt->bindParam(':url_completa', $link_info['data']['url_completa']);
                $stmt->bindParam(':url_curta', $link_info['data']['url_curta']);
            } else {
                error_log("Informações adicionais do link não disponíveis");
                $tipo_link = null;
                $data_expiracao = null;
                $url_completa = null;
                $url_curta = null;
                $stmt->bindParam(':tipo_link', $tipo_link);
                $stmt->bindParam(':data_expiracao', $data_expiracao);
                $stmt->bindParam(':url_completa', $url_completa);
                $stmt->bindParam(':url_curta', $url_curta);
                error_log("Erro ao obter informações detalhadas do link: " . ($link_info['error'] ?? 'Erro desconhecido'));
            }
            
            error_log("Executando insert no banco de dados");
            if ($stmt->execute()) {
                error_log("Link salvo com sucesso no banco de dados");
                return array(
                    'success' => true,
                    'link_id' => $db->lastInsertId(),
                    'link_url' => $cielo_response['link']
                );
            }
            
            error_log("Erro ao executar insert no banco de dados");
            return array('success' => false, 'message' => 'Erro ao salvar link no banco de dados');
            
        } catch (PDOException $e) {
            error_log("Erro PDO ao salvar link: " . $e->getMessage());
            error_log("SQL Query: " . $query);
            return array('success' => false, 'message' => 'Erro ao salvar link no banco de dados: ' . $e->getMessage());
        }
    } catch (Exception $e) {
        error_log("Erro geral ao criar link de pagamento: " . $e->getMessage());
        error_log("Stack trace: " . $e->getTraceAsString());
        return array('success' => false, 'message' => 'Erro interno do sistema: ' . $e->getMessage());
    }
}

function getPaymentLinks($user_id, $user_level) {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Se for admin ou editor, busca todos os links
    if (in_array($user_level, ['admin', 'editor'])) {
        $query = "SELECT pl.*, u.nome as nome_usuario 
                 FROM payment_links pl 
                 LEFT JOIN usuarios u ON pl.user_id = u.id 
                 ORDER BY pl.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->execute();
    } else {
        // Se for usuário (revendedora), busca apenas seus links
        $query = "SELECT pl.*, u.nome as nome_usuario 
                 FROM payment_links pl 
                 LEFT JOIN usuarios u ON pl.user_id = u.id 
                 WHERE pl.user_id = :user_id 
                 ORDER BY pl.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function updatePaymentStatus($link_id, $new_status, $user_level) {
    // Verifica se o usuário tem permissão para atualizar o status
    if (!in_array($user_level, ['admin', 'editor'])) {
        return array(
            'success' => false,
            'message' => 'Você não tem permissão para alterar o status.'
        );
    }
    
    // Verifica se o status é válido
    $valid_status = array('Criado', 'Crédito', 'Utilizado', 'Inativo');
    if (!in_array($new_status, $valid_status)) {
        return array(
            'success' => false,
            'message' => 'Status inválido.'
        );
    }
    
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        $query = "UPDATE payment_links SET status = :status WHERE id = :id";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(':status', $new_status, PDO::PARAM_STR);
        $stmt->bindParam(':id', $link_id, PDO::PARAM_INT);
        
        if ($stmt->execute()) {
            return array(
                'success' => true,
                'message' => 'Status atualizado com sucesso.'
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Erro ao atualizar status.'
            );
        }
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => 'Erro ao atualizar status: ' . $e->getMessage()
        );
    }
}

function formatCurrency($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

function formatDate($date) {
    if (empty($date)) return 'Data não disponível';
    try {
        return date('d/m/Y H:i:s', strtotime($date));
    } catch (Exception $e) {
        return 'Data não disponível';
    }
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Criado':
            return 'bg-info';
        case 'Crédito':
            return 'bg-success';
        case 'Utilizado':
            return 'bg-warning';
        case 'Inativo':
            return 'bg-danger';
        default:
            return 'bg-secondary';
    }
}

function getStatusIcon($status) {
    switch ($status) {
        case 'Criado':
            return 'fa-file-alt';
        case 'Crédito':
            return 'fa-check-circle';
        case 'Utilizado':
            return 'fa-clock';
        case 'Inativo':
            return 'fa-ban';
        default:
            return 'fa-question-circle';
    }
}

function formatMoney($value) {
    return 'R$ ' . number_format($value / 100, 2, ',', '.');
}

function getStatusClass($status) {
    switch ($status) {
        case 2: // Pago
        case 'Paid':
            return 'success';
        case 1: // Autorizado
        case 'Authorized':
            return 'info';
        case 3: // Negado
        case 'Denied':
            return 'danger';
        case 10: // Cancelado
        case 'Voided':
            return 'secondary';
        case 11: // Reembolsado
        case 'Refunded':
            return 'warning';
        case 12: // Pendente
        case 'Pending':
            return 'warning';
        case 13: // Abortado
        case 'Aborted':
            return 'danger';
        case 20: // Agendado
        case 'Scheduled':
            return 'primary';
        default:
            return 'secondary';
    }
}

function getStatusText($status) {
    switch ($status) {
        case 2:
        case 'Paid':
            return 'Pago';
        case 1:
        case 'Authorized':
            return 'Autorizado';
        case 3:
        case 'Denied':
            return 'Negado';
        case 10:
        case 'Voided':
            return 'Cancelado';
        case 11:
        case 'Refunded':
            return 'Reembolsado';
        case 12:
        case 'Pending':
            return 'Pendente';
        case 13:
        case 'Aborted':
            return 'Abortado';
        case 20:
        case 'Scheduled':
            return 'Agendado';
        default:
            return 'Aguardando';
    }
}
?>
