<?php
// Configurações de erro para desenvolvimento
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Carrega variáveis de ambiente se existirem
$env_file = __DIR__ . '/../.env';
error_log("Tentando carregar arquivo .env de: " . $env_file);

if (file_exists($env_file)) {
    error_log("Arquivo .env encontrado");
    $env_content = file_get_contents($env_file);
    $lines = explode("\n", $env_content);
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Pula linhas vazias e comentários
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        // Divide a linha em chave e valor
        list($key, $value) = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Remove aspas se existirem
        $value = trim($value, '"\'');
        
        // Define a variável de ambiente
        $_ENV[$key] = $value;
        putenv("$key=$value");
    }
    error_log("Variáveis carregadas do .env: " . implode(", ", array_keys($_ENV)));
} else {
    error_log("Arquivo .env não encontrado em: " . $env_file);
}

// Definir constantes usando as variáveis de ambiente
define('DB_HOST', $_ENV['DB_HOST'] ?? 'localhost');
define('DB_NAME', $_ENV['DB_NAME'] ?? 'reve_cielo');
define('DB_USER', $_ENV['DB_USER'] ?? 'reve_cielo');
define('DB_PASS', $_ENV['DB_PASS'] ?? '');

// Log das constantes definidas
error_log("Constantes definidas:");
error_log("DB_HOST: " . DB_HOST);
error_log("DB_NAME: " . DB_NAME);
error_log("DB_USER: " . DB_USER);
error_log("DB_PASS: " . (defined('DB_PASS') ? 'definido' : 'não definido'));

define('CIELO_CLIENT_ID', $_ENV['CIELO_CLIENT_ID'] ?? 'your_client_id_here');
define('CIELO_CLIENT_SECRET', $_ENV['CIELO_CLIENT_SECRET'] ?? 'your_client_secret_here');
define('CIELO_ENVIRONMENT', $_ENV['CIELO_ENVIRONMENT'] ?? 'sandbox');

// Configurações do site
if (!defined('SITE_NAME')) {
    define('SITE_NAME', $_ENV['SITE_NAME'] ?? 'Sistema de Pagamento');
}
if (!defined('SITE_URL')) {
    define('SITE_URL', $_ENV['SITE_URL'] ?? 'https://revenda.dilima.com.br/geralinkcielo');
}
if (!defined('SYSTEM_NAME')) {
    define('SYSTEM_NAME', $_ENV['SYSTEM_NAME'] ?? 'Sistema de Pagamento');
}

// Configurações de parcelas por credencial
$PARCELAS_CONFIG = [
    'matriz' => [
        'max_parcelas' => $_ENV['MATRIZ_MAX_PARCELAS'] ?? 12,
        'parcelas_sem_juros' => $_ENV['MATRIZ_PARCELAS_SEM_JUROS'] ?? 3,
        'juros_percentual' => $_ENV['MATRIZ_JUROS_PERCENTUAL'] ?? 3.19
    ],
    'filial' => [
        'max_parcelas' => $_ENV['FILIAL_MAX_PARCELAS'] ?? 12,
        'parcelas_sem_juros' => $_ENV['FILIAL_PARCELAS_SEM_JUROS'] ?? 6,
        'juros_percentual' => $_ENV['FILIAL_JUROS_PERCENTUAL'] ?? 3.19
    ]
];

// Outras configurações do sistema
$CONFIG = [
    'debug_mode' => $_ENV['DEBUG_MODE'] ?? false,
    'timezone' => $_ENV['TIMEZONE'] ?? 'America/Sao_Paulo',
    'session_timeout' => $_ENV['SESSION_TIMEOUT'] ?? 3600, // 1 hora
    'max_login_attempts' => $_ENV['MAX_LOGIN_ATTEMPTS'] ?? 5,
    'lock_time' => $_ENV['LOCK_TIME'] ?? 900 // 15 minutos
];

// Define timezone
date_default_timezone_set($CONFIG['timezone']);
?>