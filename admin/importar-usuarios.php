<?php
session_start();

// Define a constante da raiz do projeto se ainda não estiver definida
if (!defined('PROJECT_ROOT')) {
    define('PROJECT_ROOT', realpath(__DIR__ . '/..'));
}

require_once PROJECT_ROOT . '/includes/auth.php';
require_once PROJECT_ROOT . '/includes/functions.php';
require_once PROJECT_ROOT . '/includes/wp-config.php';
require_once PROJECT_ROOT . '/config/database.php';

// Habilita exibição de erros para debug
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log do início da execução
error_log("Iniciando importação de usuários");

// Senha padrão para todos os usuários
define('DEFAULT_PASSWORD', 'abc123');
$senha_hash = password_hash(DEFAULT_PASSWORD, PASSWORD_DEFAULT);

try {
    requireLogin();

    // Verifica se é administrador
    if ($_SESSION['nivel_acesso'] !== 'admin') {
        error_log("Tentativa de acesso não autorizado: " . $_SESSION['nivel_acesso']);
        header('Location: ../dashboard.php');
        exit;
    }

    $mensagem = '';
    $tipo_mensagem = '';

    if (isset($_POST['importar'])) {
        error_log("Iniciando processo de importação");
        
        // Testa conexão com WordPress
        try {
            error_log("Tentando conectar ao WordPress");
            $wp_db = new PDO(
                "mysql:host=" . WP_DB_HOST . ";dbname=" . WP_DB_NAME,
                WP_DB_USER,
                WP_DB_PASS,
                array(
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                )
            );
            error_log("Conexão com WordPress estabelecida");
        } catch (PDOException $e) {
            error_log("Erro na conexão WordPress: " . $e->getMessage());
            throw new Exception("Erro na conexão com WordPress: " . $e->getMessage());
        }

        // Busca usuários do WordPress
        try {
            error_log("Buscando usuários do WordPress");
            $query = "SELECT u.ID, u.user_email, u.display_name, um.meta_value as capabilities 
                     FROM " . WP_PREFIX . "users u 
                     LEFT JOIN " . WP_PREFIX . "usermeta um 
                     ON u.ID = um.user_id 
                     WHERE um.meta_key = '" . WP_PREFIX . "capabilities'";
            
            error_log("Query WordPress: " . $query);
            $stmt = $wp_db->prepare($query);
            $stmt->execute();
            $wp_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($wp_users)) {
                error_log("Nenhum usuário encontrado no WordPress");
                throw new Exception("Nenhum usuário encontrado no WordPress. Query: " . $query);
            }
            error_log("Encontrados " . count($wp_users) . " usuários no WordPress");
        } catch (PDOException $e) {
            error_log("Erro ao buscar usuários WordPress: " . $e->getMessage());
            throw new Exception("Erro ao buscar usuários do WordPress: " . $e->getMessage() . ". Query: " . $query);
        }
        
        // Conexão com nosso banco
        try {
            error_log("Conectando ao banco local");
            $db = new Database();
            $conn = $db->getConnection();
            error_log("Conexão com banco local estabelecida");
        } catch (Exception $e) {
            error_log("Erro na conexão banco local: " . $e->getMessage());
            throw new Exception("Erro na conexão com banco local: " . $e->getMessage());
        }
        
        $importados = 0;
        $atualizados = 0;
        $erros = array();
        
        foreach ($wp_users as $wp_user) {
            try {
                error_log("Processando usuário: " . $wp_user['user_email']);
                
                if (empty($wp_user['capabilities'])) {
                    error_log("Usuário sem capabilities: " . $wp_user['user_email']);
                    $erros[] = "Usuário {$wp_user['user_email']} sem capabilities";
                    continue;
                }
                
                $capabilities = unserialize($wp_user['capabilities']);
                if ($capabilities === false) {
                    error_log("Erro unserialize capabilities: " . $wp_user['user_email']);
                    $erros[] = "Erro ao unserialize capabilities do usuário {$wp_user['user_email']}";
                    continue;
                }
                
                // Mapeia o nível de acesso
                error_log("Mapeando nível de acesso para: " . $wp_user['user_email']);
                if (isset($capabilities['administrator']) && $capabilities['administrator']) {
                    $nivel_acesso = 'admin';
                } elseif (isset($capabilities['editor']) && $capabilities['editor']) {
                    $nivel_acesso = 'editor';
                } elseif (isset($capabilities['revendedora']) && $capabilities['revendedora']) {
                    $nivel_acesso = 'usuario';
                } else {
                    error_log("Usuário sem role relevante: " . $wp_user['user_email']);
                    $erros[] = "Usuário {$wp_user['user_email']} sem role relevante";
                    continue;
                }
                error_log("Nível de acesso definido como: " . $nivel_acesso);
                
                // Verifica se usuário já existe
                error_log("Verificando se usuário já existe: " . $wp_user['user_email']);
                $stmt = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
                $stmt->execute([$wp_user['user_email']]);
                $usuario_existente = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($usuario_existente) {
                    error_log("Atualizando usuário existente: " . $wp_user['user_email']);
                    // Atualiza usuário existente
                    $stmt = $conn->prepare("
                        UPDATE usuarios 
                        SET nome = ?, 
                            wp_user_id = ?,
                            nivel_acesso = ?,
                            senha_hash = ?,
                            ativo = 1
                        WHERE email = ?
                    ");
                    $stmt->execute([
                        $wp_user['display_name'],
                        $wp_user['ID'],
                        $nivel_acesso,
                        $senha_hash,
                        $wp_user['user_email']
                    ]);
                    $atualizados++;
                    error_log("Usuário atualizado com sucesso: " . $wp_user['user_email']);
                } else {
                    error_log("Inserindo novo usuário: " . $wp_user['user_email']);
                    // Insere novo usuário
                    $stmt = $conn->prepare("
                        INSERT INTO usuarios (wp_user_id, nome, email, nivel_acesso, senha_hash, ativo)
                        VALUES (?, ?, ?, ?, ?, 1)
                    ");
                    $stmt->execute([
                        $wp_user['ID'],
                        $wp_user['display_name'],
                        $wp_user['user_email'],
                        $nivel_acesso,
                        $senha_hash
                    ]);
                    $importados++;
                    error_log("Novo usuário inserido com sucesso: " . $wp_user['user_email']);
                }
            } catch (Exception $e) {
                error_log("Erro ao processar usuário {$wp_user['user_email']}: " . $e->getMessage());
                $erros[] = "Erro ao processar usuário {$wp_user['user_email']}: " . $e->getMessage();
            }
        }
        
        error_log("Importação finalizada. Novos: $importados, Atualizados: $atualizados, Erros: " . count($erros));
        $mensagem = "Importação concluída! Novos usuários: $importados, Atualizados: $atualizados\n\n";
        $mensagem .= "Senha padrão para todos os usuários: " . DEFAULT_PASSWORD . "\n";
        $mensagem .= "\nIMPORTANTE: Anote esta senha! Os usuários devem alterá-la no primeiro acesso.\n";
        
        if (!empty($erros)) {
            $mensagem .= "\nErros encontrados:\n" . implode("\n", $erros);
            error_log("Erros durante importação:\n" . implode("\n", $erros));
        }
        $tipo_mensagem = empty($erros) ? 'success' : 'warning';
    }
} catch (Exception $e) {
    error_log("Erro fatal na importação: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    $mensagem = "Erro na importação: " . $e->getMessage();
    $tipo_mensagem = 'danger';
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
    <style>
        .alert-message {
            white-space: pre-wrap;
            font-family: monospace;
        }
    </style>
</head>
<body class="bg-light">
    <?php include PROJECT_ROOT . '/includes/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Importar Usuários do WordPress</h4>
                    </div>
                    <div class="card-body">
                        <?php if ($mensagem): ?>
                            <div class="alert alert-<?php echo $tipo_mensagem; ?> alert-dismissible fade show alert-message" role="alert">
                                <?php echo nl2br(htmlspecialchars($mensagem)); ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <p>Esta ferramenta importará todos os usuários do WordPress para o sistema.</p>
                        <ul>
                            <li>Administradores do WordPress serão importados como administradores</li>
                            <li>Editores do WordPress serão importados como editores</li>
                            <li>Revendedoras do WordPress serão importados como usuários</li>
                            <li>Todos os usuários terão a senha padrão: <?php echo DEFAULT_PASSWORD; ?></li>
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