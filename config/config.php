<?php
// Carregar variáveis de ambiente do arquivo .env
require_once __DIR__ . '/env.php';

// Configurações de erro para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Definir constantes usando as variáveis de ambiente
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'payment_system');
define('DB_USER', $_ENV['DB_USER'] ?? 'root');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

define('CIELO_CLIENT_ID', $_ENV['CIELO_CLIENT_ID'] ?? 'your_client_id_here');
define('CIELO_CLIENT_SECRET', $_ENV['CIELO_CLIENT_SECRET'] ?? 'your_client_secret_here');
define('CIELO_ENVIRONMENT', $_ENV['CIELO_ENVIRONMENT'] ?? 'sandbox');

define('SITE_URL', $_ENV['SITE_URL'] ?? 'http://localhost:5000');
define('SYSTEM_NAME', $_ENV['SYSTEM_NAME'] ?? 'Sistema de Pagamento');
?>