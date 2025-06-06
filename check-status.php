<?php
header('Content-Type: application/json');
require_once 'config/config.php';
require_once 'config/cielo.php';

if (!isset($_POST['product_id'])) {
    echo json_encode(['success' => false, 'message' => 'ID do produto não fornecido']);
    exit;
}

$product_id = $_POST['product_id'];
$cielo = new Cielo();
$result = $cielo->checkPaymentStatus($product_id);

if ($result['success']) {
    $response = [
        'success' => true,
        'status' => $result['status']
    ];
    
    // Adiciona informações da transação se disponível
    if (isset($result['transaction'])) {
        $transaction = $result['transaction'];
        $response['transaction'] = [
            'id' => $transaction['id'] ?? '',
            'status' => $transaction['status'] ?? '',
            'amount' => $transaction['amount'] ?? '',
            'payment_method' => $transaction['payment']['type'] ?? '',
            'created_at' => $transaction['created_at'] ?? '',
            'updated_at' => $transaction['updated_at'] ?? ''
        ];
    }
    
    echo json_encode($response);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao verificar status do pagamento'
    ]);
} 