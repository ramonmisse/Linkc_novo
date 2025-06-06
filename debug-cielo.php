<?php
session_start();
require_once 'config/config.php';
require_once 'config/cielo.php';

// Página temporária para debug da API Cielo
if (!isset($_SESSION['user_id'])) {
    die('Acesso negado');
}

echo "<h1>Debug da API Cielo</h1>";

// Verificar configurações
echo "<h3>Configurações da API Cielo Link:</h3>";
echo "Arquivo .env: " . (file_exists('.env') ? '✓ Encontrado' : '✗ Não encontrado') . "<br>";
echo "Client ID: " . ($_ENV['CIELO_CLIENT_ID'] ?? 'NÃO CONFIGURADO') . "<br>";
echo "Client Secret: " . (empty($_ENV['CIELO_CLIENT_SECRET']) ? 'NÃO CONFIGURADO' : 'CONFIGURADO (****)') . "<br>";
echo "Ambiente: " . ($_ENV['CIELO_ENVIRONMENT'] ?? 'sandbox') . "<br>";

// Verificar se as credenciais são valores padrão
$client_id = $_ENV['CIELO_CLIENT_ID'] ?? '';
$client_secret = $_ENV['CIELO_CLIENT_SECRET'] ?? '';

if ($client_id === 'your_client_id_here' || empty($client_id)) {
    echo "<div style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000; margin: 10px 0;'>";
    echo "<strong>⚠️ ATENÇÃO:</strong> Client ID não configurado corretamente.<br>";
    echo "Edite o arquivo .env e substitua 'your_client_id_here' pelo seu Client ID real da Cielo.";
    echo "</div>";
}

if ($client_secret === 'your_client_secret_here' || empty($client_secret)) {
    echo "<div style='background: #ffeeee; padding: 10px; border: 1px solid #ff0000; margin: 10px 0;'>";
    echo "<strong>⚠️ ATENÇÃO:</strong> Client Secret não configurado corretamente.<br>";
    echo "Edite o arquivo .env e substitua 'your_client_secret_here' pelo seu Client Secret real da Cielo.";
    echo "</div>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor = 100.00; // Valor de teste
    $parcelas = 1;
    
    echo "<h3>Teste de Criação de Link:</h3>";
    echo "Valor: R$ " . number_format($valor, 2, ',', '.') . "<br>";
    echo "Parcelas: {$parcelas}x<br><br>";
    
    $cielo = new CieloAPI();
    $result = $cielo->createPaymentLink($valor, $parcelas, 'Teste Debug');
    
    echo "<h4>Resultado:</h4>";
    echo "<pre>";
    print_r($result);
    echo "</pre>";
    
    if (!$result['success']) {
        echo "<h4>Detalhes do Erro:</h4>";
        if (isset($result['http_code'])) {
            echo "HTTP Code: " . $result['http_code'] . "<br>";
        }
        if (isset($result['raw_response'])) {
            echo "Resposta Completa da API:<br>";
            echo "<textarea style='width:100%;height:200px;'>" . htmlspecialchars($result['raw_response']) . "</textarea>";
        }
    }
}

// Verificar logs de erro do PHP
echo "<h3>Últimos Logs de Erro:</h3>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    $logs = file_get_contents($error_log);
    $recent_logs = array_slice(explode("\n", $logs), -20);
    echo "<textarea style='width:100%;height:150px;'>" . htmlspecialchars(implode("\n", $recent_logs)) . "</textarea>";
} else {
    echo "Log de erros não encontrado ou não configurado.<br>";
}
?>

<form method="POST" style="margin-top: 20px;">
    <button type="submit">Testar API Cielo</button>
</form>

<p><a href="dashboard.php">Voltar ao Dashboard</a></p>