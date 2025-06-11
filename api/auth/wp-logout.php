<?php
require_once '../../config/config.php';
require_once '../../config/database.php';

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode(['success' => false, 'message' => 'Método não permitido']));
}

// Recebe os dados
$wp_user_id = $_POST['wp_user_id'] ?? null;

if (!$wp_user_id) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Dados inválidos']));
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Remove todas as sessões do usuário
    $stmt = $conn->prepare("
        DELETE ws FROM wp_sessions ws
        INNER JOIN usuarios u ON ws.user_id = u.id
        WHERE u.wp_user_id = ?
    ");
    $stmt->execute([$wp_user_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Erro no logout WordPress: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
} 