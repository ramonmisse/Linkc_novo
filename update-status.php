<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'config/cielo.php';

requireLogin();

// This script can be called via AJAX to update payment status
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $link_id = intval($_POST['link_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    
    if ($action === 'check_cielo_status' && $link_id > 0) {
        try {
            $database = new Database();
            $db = $database->getConnection();
            
            // Get payment ID
            $query = "SELECT payment_id FROM payment_links WHERE id = :id";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':id', $link_id);
            $stmt->execute();
            $link = $stmt->fetch();
            
            if ($link && !empty($link['payment_id'])) {
                $cielo = new CieloAPI();
                $status_check = $cielo->checkPaymentStatus($link['payment_id']);
                
                if ($status_check['success']) {
                    $cielo_status = $status_check['status'];
                    $new_status = 'Aguardando Pagamento';
                    
                    // Map Cielo status to our status
                    switch ($cielo_status) {
                        case 2: // Paid
                            $new_status = 'Pago';
                            break;
                        case 1: // Authorized
                        case 0: // NotFinished
                        default:
                            $new_status = 'Aguardando Pagamento';
                            break;
                    }
                    
                    // Update status in database
                    $query = "UPDATE payment_links SET status = :status, status_cielo = :status_cielo, updated_at = NOW() WHERE id = :id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':status', $new_status);
                    $stmt->bindParam(':status_cielo', $cielo_status);
                    $stmt->bindParam(':id', $link_id);
                    
                    if ($stmt->execute()) {
                        echo json_encode(['success' => true, 'status' => $new_status]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status']);
                    }
                } else {
                    echo json_encode(['success' => false, 'message' => 'Erro ao consultar status na Cielo']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Link não encontrado']);
            }
        } catch (Exception $e) {
            error_log("Update status error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Erro interno do sistema']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Parâmetros inválidos']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}
?>
