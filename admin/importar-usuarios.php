<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../includes/wp-config.php';

requireLogin();

// Verifica se é administrador
if ($_SESSION['nivel_acesso'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$mensagem = '';
$tipo_mensagem = '';

if (isset($_POST['importar'])) {
    try {
        // Conexão com WordPress
        $wp_db = new PDO(
            "mysql:host=" . WP_DB_HOST . ";dbname=" . WP_DB_NAME,
            WP_DB_USER,
            WP_DB_PASS,
            array(PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8")
        );

        // Busca usuários do WordPress
        $query = "SELECT u.ID, u.user_email, u.display_name, um.meta_value as capabilities 
                 FROM " . WP_PREFIX . "users u 
                 LEFT JOIN " . WP_PREFIX . "usermeta um 
                 ON u.ID = um.user_id 
                 WHERE um.meta_key = '" . WP_PREFIX . "capabilities'";
        
        $wp_users = $wp_db->query($query)->fetchAll(PDO::FETCH_ASSOC);
        
        // Conexão com nosso banco
        $db = new Database();
        $conn = $db->getConnection();
        
        $importados = 0;
        $atualizados = 0;
        
        foreach ($wp_users as $wp_user) {
            $capabilities = unserialize($wp_user['capabilities']);
            
            // Mapeia o nível de acesso
            if (isset($capabilities['administrator']) && $capabilities['administrator']) {
                $nivel_acesso = 'admin';
            } elseif (isset($capabilities['editor']) && $capabilities['editor']) {
                $nivel_acesso = 'editor';
            } elseif (isset($capabilities['revendedora']) && $capabilities['revendedora']) {
                $nivel_acesso = 'usuario';
            } else {
                continue; // Pula usuários sem roles relevantes
            }
            
            // Verifica se usuário já existe
            $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
            $stmt->execute([$wp_user['user_email']]);
            $usuario_existente = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($usuario_existente) {
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
                    $wp_user['display_name'],
                    $wp_user['ID'],
                    $nivel_acesso,
                    $wp_user['user_email']
                ]);
                $atualizados++;
            } else {
                // Insere novo usuário
                $stmt = $conn->prepare("
                    INSERT INTO usuarios (wp_user_id, nome, email, nivel_acesso, ativo)
                    VALUES (?, ?, ?, ?, 1)
                ");
                $stmt->execute([
                    $wp_user['ID'],
                    $wp_user['display_name'],
                    $wp_user['user_email'],
                    $nivel_acesso
                ]);
                $importados++;
            }
        }
        
        $mensagem = "Importação concluída! Novos usuários: $importados, Atualizados: $atualizados";
        $tipo_mensagem = 'success';
        
    } catch (Exception $e) {
        $mensagem = "Erro na importação: " . $e->getMessage();
        $tipo_mensagem = 'danger';
        error_log("Erro na importação de usuários WordPress: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Usuários - Sistema de Pagamento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Importar Usuários do WordPress</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($mensagem): ?>
                            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show" role="alert">
                                <?php echo $mensagem; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <p>Esta ferramenta importará todos os usuários do WordPress para o sistema.</p>
                        <ul>
                            <li>Administradores do WordPress serão importados como administradores</li>
                            <li>Editores do WordPress serão importados como editores</li>
                            <li>Revendedoras do WordPress serão importados como usuários</li>
                        </ul>
                        
                        <form method="post" class="mt-4">
                            <button type="submit" name="importar" class="btn btn-primary">
                                <i class="fas fa-sync me-2"></i>Iniciar Importação
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 