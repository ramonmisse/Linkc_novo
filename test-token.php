<?php
session_start();
require_once 'config/config.php';

// Página específica para testar token OAuth da Cielo
if (!isset($_SESSION['user_id'])) {
    die('Acesso negado - Faça login primeiro');
}

echo "<h1>Teste de Token OAuth - Cielo API</h1>";

// Mostrar configurações atuais
echo "<h3>Configurações:</h3>";
echo "Client ID: " . $_ENV['CIELO_CLIENT_ID'] . "<br>";
echo "Client Secret: " . (strlen($_ENV['CIELO_CLIENT_SECRET']) > 0 ? str_repeat('*', strlen($_ENV['CIELO_CLIENT_SECRET'])) : 'NÃO CONFIGURADO') . "<br>";
echo "Ambiente: " . $_ENV['CIELO_ENVIRONMENT'] . "<br>";

// URLs possíveis para testar
$urls_teste = [
    'URL 1 (v1)' => 'https://cieloecommerce.cielo.com.br/api/public/v1/token',
    'URL 2 (v2)' => 'https://cieloecommerce.cielo.com.br/api/public/v2/token',
    'URL 3 (oauth)' => 'https://cieloecommerce.cielo.com.br/oauth/token',
    'URL 4 (auth)' => 'https://cieloecommerce.cielo.com.br/auth/oauth/v2/token',
    'URL 5 (api.cielo)' => 'https://api.cielo.com.br/oauth/token',
    'URL 6 (sandbox)' => 'https://apisandbox.cieloecommerce.cielo.com.br/1/oauth/token'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_url'])) {
    $url_selecionada = $_POST['test_url'];
    
    echo "<h3>Testando URL: " . $url_selecionada . "</h3>";
    
    // Preparar credenciais
    $client_id = $_ENV['CIELO_CLIENT_ID'];
    $client_secret = $_ENV['CIELO_CLIENT_SECRET'];
    
    // Teste 1: Basic Auth (método mais comum OAuth)
    echo "<h4>Método 1: Basic Auth</h4>";
    testTokenRequest($url_selecionada, $client_id, $client_secret, 'basic');
    
    // Teste 2: POST fields
    echo "<h4>Método 2: POST Fields</h4>";
    testTokenRequest($url_selecionada, $client_id, $client_secret, 'post');
    
    // Teste 3: JSON Body
    echo "<h4>Método 3: JSON Body</h4>";
    testTokenRequest($url_selecionada, $client_id, $client_secret, 'json');
}

function testTokenRequest($url, $client_id, $client_secret, $method) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    switch ($method) {
        case 'basic':
            $auth_header = base64_encode($client_id . ':' . $client_secret);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: Basic ' . $auth_header
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
            break;
            
        case 'post':
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'grant_type' => 'client_credentials',
                'client_id' => $client_id,
                'client_secret' => $client_secret
            ]));
            break;
            
        case 'json':
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'grant_type' => 'client_credentials',
                'client_id' => $client_id,
                'client_secret' => $client_secret
            ]));
            break;
    }
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    echo "<div style='background: #f0f0f0; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
    echo "<strong>HTTP Code:</strong> " . $http_code . "<br>";
    
    if ($curl_error) {
        echo "<strong>cURL Error:</strong> " . $curl_error . "<br>";
    }
    
    echo "<strong>Response:</strong><br>";
    echo "<textarea style='width:100%;height:100px;'>" . htmlspecialchars($response) . "</textarea>";
    
    if ($http_code == 200) {
        $json_response = json_decode($response, true);
        if (isset($json_response['access_token'])) {
            echo "<div style='background: #d4edda; padding: 10px; margin: 10px 0; border: 1px solid #c3e6cb;'>";
            echo "<strong>✓ SUCESSO!</strong> Token obtido: " . substr($json_response['access_token'], 0, 20) . "...";
            echo "</div>";
        }
    }
    
    echo "</div>";
}
?>

<style>
body { font-family: Arial, sans-serif; margin: 20px; }
h1, h3, h4 { color: #333; }
</style>

<h3>Testar URLs da API Cielo:</h3>
<form method="POST">
    <?php foreach ($urls_teste as $nome => $url): ?>
        <label style="display: block; margin: 10px 0;">
            <input type="radio" name="test_url" value="<?= $url ?>" <?= $nome === 'URL 1 (v1)' ? 'checked' : '' ?>>
            <?= $nome ?>: <?= $url ?>
        </label>
    <?php endforeach; ?>
    
    <br>
    <button type="submit" style="padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer;">
        Testar Token OAuth
    </button>
</form>

<p><a href="dashboard.php">← Voltar ao Dashboard</a></p>