<?php
require_once 'config/config.php';
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();
    echo "Conexão com banco de dados local bem sucedida!\n\n";
    
    // Testa a tabela de usuários
    $query = "SELECT id, nome, email, nivel_acesso FROM usuarios";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Usuários encontrados no sistema local: " . count($usuarios) . "\n";
    foreach ($usuarios as $usuario) {
        echo "- {$usuario['email']} ({$usuario['nivel_acesso']})\n";
    }
    
} catch (Exception $e) {
    echo "Erro na conexão com banco local: " . $e->getMessage() . "\n";
    
    // Verifica se o arquivo .env existe
    if (!file_exists('.env')) {
        echo "\nArquivo .env não encontrado!\n";
        echo "Por favor, crie o arquivo .env com as seguintes configurações:\n\n";
        echo "DB_HOST=localhost\n";
        echo "DB_NAME=payment_system\n";
        echo "DB_USER=root\n";
        echo "DB_PASS=\n";
    } else {
        echo "\nArquivo .env encontrado. Configurações atuais:\n";
        echo "DB_HOST=" . ($_ENV['DB_HOST'] ?? 'não definido') . "\n";
        echo "DB_NAME=" . ($_ENV['DB_NAME'] ?? 'não definido') . "\n";
        echo "DB_USER=" . ($_ENV['DB_USER'] ?? 'não definido') . "\n";
        echo "DB_PASS=" . (isset($_ENV['DB_PASS']) ? '[definido]' : 'não definido') . "\n";
    }
} 