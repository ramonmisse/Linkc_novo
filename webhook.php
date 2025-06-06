<?php
// Webhook endpoint for Cielo notifications
require_once 'config/database.php';

// Log all incoming requests
error_log("Webhook called: " . file_get_contents('php://input'));

// Verify if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Method not allowed');
}

// Get the raw POST data
$raw_data = file_get_contents('php://input');
$data = json_decode($raw_data, true);

if (!$data) {
    http_response_code(400);
    exit('Invalid JSON');
}

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Process webhook data
    if (isset($data['PaymentId']) && isset($data['ChangeType'])) {
        $payment_id = $data['PaymentId'];
        $change_type = $data['ChangeType'];
        
        // Find the payment link by payment_id
        $query = "SELECT id, status FROM payment_links WHERE payment_id = :payment_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':payment_id', $payment_id);
        $stmt->execute();
        $link = $stmt->fetch();
        
        if ($link) {
            $new_status = 'Aguardando Pagamento';
            
            // Map change type to status
            switch ($change_type) {
                case 1: // Payment confirmed
                    $new_status = 'Pago';
                    break;
                case 2: // Payment canceled
                    $new_status = 'Cancelado';
                    break;
                default:
                    $new_status = 'Aguardando Pagamento';
                    break;
            }
            
            // Update status only if it's different
            if ($link['status'] !== $new_status) {
                $query = "UPDATE payment_links SET status = :status, updated_at = NOW() WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':status', $new_status);
                $stmt->bindParam(':id', $link['id']);
                $stmt->execute();
                
                error_log("Payment status updated for link {$link['id']}: {$new_status}");
            }
        }
    }
    
    // Return success response
    http_response_code(200);
    echo json_encode(['status' => 'success']);
    
} catch (Exception $e) {
    error_log("Webhook error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Internal server error']);
}
?>
