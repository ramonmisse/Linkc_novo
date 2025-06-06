<?php
require_once 'config/config.php';
require_once 'config/database.php';

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function hasPermission($required_level) {
    if (!isLoggedIn()) {
        return false;
    }
    
    $user_level = $_SESSION['nivel_acesso'] ?? 'usuario';
    
    if ($required_level === 'admin') {
        return $user_level === 'admin';
    } elseif ($required_level === 'editor') {
        return in_array($user_level, ['admin', 'editor']);
    }
    
    return true; // All users have basic access
}

function login($email, $password) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT id, nome, email, senha_hash, nivel_acesso FROM usuarios WHERE email = :email AND ativo = 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() == 1) {
            $user = $stmt->fetch();
            
            if (password_verify($password, $user['senha_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['nome'] = $user['nome'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['nivel_acesso'] = $user['nivel_acesso'];
                
                return array('success' => true);
            }
        }
        
        return array('success' => false, 'message' => 'Email ou senha incorretos');
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        return array('success' => false, 'message' => 'Erro interno do sistema');
    }
}

function register($nome, $email, $password) {
    try {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if email already exists
        $query = "SELECT id FROM usuarios WHERE email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            return array('success' => false, 'message' => 'Email já cadastrado');
        }
        
        // Insert new user
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $query = "INSERT INTO usuarios (nome, email, senha_hash, nivel_acesso, ativo, created_at) VALUES (:nome, :email, :senha_hash, 'usuario', 1, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':nome', $nome);
        $stmt->bindParam(':email', $email);
        $stmt->bindParam(':senha_hash', $password_hash);
        
        if ($stmt->execute()) {
            return array('success' => true, 'message' => 'Usuário cadastrado com sucesso');
        }
        
        return array('success' => false, 'message' => 'Erro ao cadastrar usuário');
    } catch (Exception $e) {
        error_log("Register error: " . $e->getMessage());
        return array('success' => false, 'message' => 'Erro interno do sistema');
    }
}

function logout() {
    session_unset();
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
