<?php
session_start();
require_once '../config/config.php';
require_once '../config/database.php';

// Verifica se recebeu o token
$token = $_GET['token'] ?? null;

if (!$token) {
    die('Token não fornecido');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Busca a sessão pelo token
    $stmt = $conn->prepare("
        SELECT u.id, u.nome, u.email, u.nivel_acesso
        FROM wp_sessions ws
        INNER JOIN usuarios u ON ws.user_id = u.id
        WHERE ws.token = ? AND ws.created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $stmt->execute([$token]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$usuario) {
        die('Sessão inválida ou expirada');
    }
    
    // Remove o token usado
    $stmt = $conn->prepare("DELETE FROM wp_sessions WHERE token = ?");
    $stmt->execute([$token]);
    
    // Inicia a sessão no sistema
    $_SESSION['user_id'] = $usuario['id'];
    $_SESSION['nome'] = $usuario['nome'];
    $_SESSION['email'] = $usuario['email'];
    $_SESSION['nivel_acesso'] = $usuario['nivel_acesso'];
    
    // Redireciona para o dashboard
    header('Location: ../dashboard.php');
    exit;
    
} catch (Exception $e) {
    error_log("Erro no callback WordPress: " . $e->getMessage());
    die('Erro ao processar login');
} 