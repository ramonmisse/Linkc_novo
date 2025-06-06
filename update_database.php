<?php
require_once 'config/database.php';

try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Adicionar coluna product_id
    $query = "ALTER TABLE payment_links ADD COLUMN product_id VARCHAR(255) AFTER payment_id";
    $db->exec($query);
    
    // Atualizar os registros existentes copiando o payment_id para product_id
    $query = "UPDATE payment_links SET product_id = payment_id WHERE product_id IS NULL";
    $db->exec($query);
    
    echo "Banco de dados atualizado com sucesso!\n";
    echo "1. Coluna product_id adicionada\n";
    echo "2. Dados existentes atualizados\n";
    
} catch (PDOException $e) {
    echo "Erro ao atualizar banco de dados: " . $e->getMessage() . "\n";
}
?> 