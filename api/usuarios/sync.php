<?php
require_once '../../includes/config.php';
require_once '../../includes/database.php';

// Verifica se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Método não permitido');
}

// Recebe os dados
$wp_user_id = $_POST['wp_user_id'] ?? null;
$email = $_POST['email'] ?? null;
$nome = $_POST['nome'] ?? null;
$nivel_acesso = $_POST['nivel_acesso'] ?? null;

if (!$wp_user_id || !$email || !$nome || !$nivel_acesso) {
    http_response_code(400);
    exit('Dados inválidos');
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Verifica se o usuário já existe (por email)
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
    $stmt->execute([$email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($usuario) {
        // Atualiza usuário existente
        $stmt = $conn->prepare("
            UPDATE usuarios 
            SET nome = ?,
                wp_user_id = ?,
                nivel_acesso = ?,
                ativo = 1
            WHERE email = ?
        ");
        $stmt->execute([
            $nome,
            $wp_user_id,
            $nivel_acesso,
            $email
        ]);
    } else {
        // Insere novo usuário
        $stmt = $conn->prepare("
            INSERT INTO usuarios (wp_user_id, nome, email, nivel_acesso, ativo)
            VALUES (?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $wp_user_id,
            $nome,
            $email,
            $nivel_acesso
        ]);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    error_log("Erro na sincronização de usuário: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erro interno']);
} 