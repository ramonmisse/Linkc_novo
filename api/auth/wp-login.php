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
$email = $_POST['email'] ?? null;
$token = $_POST['token'] ?? null;

if (!$wp_user_id || !$email || !$token) {
    http_response_code(400);
    die(json_encode(['success' => false, 'message' => 'Dados inválidos']));
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Busca o usuário pelo email
    $stmt = $conn->prepare("SELECT id, nome, email, nivel_acesso FROM usuarios WHERE email = ? AND wp_user_id = ? AND ativo = 1");
    $stmt->execute([$email, $wp_user_id]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        http_response_code(404);
        die(json_encode(['success' => false, 'message' => 'Usuário não encontrado']));
    }
    
    // Armazena o token na sessão
    $stmt = $conn->prepare("INSERT INTO wp_sessions (user_id, token, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$usuario['id'], $token]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    error_log("Erro no login WordPress: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erro interno do servidor']);
} 