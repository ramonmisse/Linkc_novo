<?php
require_once 'config/database.php';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // 1. Primeiro, vamos ver quais status existem atualmente
    $check_query = "SELECT DISTINCT status FROM payment_links";
    $result = $conn->query($check_query);
    echo "Status atuais encontrados:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['status'] . "<br>";
    }
    echo "<br>";

    // 2. Atualiza os status existentes para os novos valores
    $updates = [
        "UPDATE payment_links SET status = 'Criado' WHERE status IN ('Aguardando', 'Aguardando Pagamento', 'Pendente')",
        "UPDATE payment_links SET status = 'Crédito' WHERE status IN ('Pago', 'Crédito Gerado')",
        "UPDATE payment_links SET status = 'Utilizado' WHERE status IN ('Utilizado', 'Finalizado')",
        "UPDATE payment_links SET status = 'Inativo' WHERE status IN ('Cancelado', 'Expirado', 'Inativo')"
    ];

    foreach ($updates as $update_query) {
        if ($conn->query($update_query)) {
            echo "Atualização executada com sucesso: " . $update_query . "<br>";
        } else {
            echo "Erro na atualização: " . $conn->error . "<br>";
        }
    }

    // 3. Define qualquer outro status como 'Criado'
    $default_update = "UPDATE payment_links SET status = 'Criado' WHERE status NOT IN ('Criado', 'Crédito', 'Utilizado', 'Inativo')";
    if ($conn->query($default_update)) {
        echo "Status restantes definidos como 'Criado'<br>";
    }

    // 4. Agora que todos os valores estão padronizados, altera a coluna
    $alter_query = "ALTER TABLE payment_links MODIFY COLUMN status ENUM('Criado', 'Crédito', 'Utilizado', 'Inativo') NOT NULL DEFAULT 'Criado'";
    if ($conn->query($alter_query)) {
        echo "<br>Estrutura da coluna status atualizada com sucesso.<br>";
    } else {
        echo "<br>Erro ao atualizar estrutura da coluna: " . $conn->error . "<br>";
    }

    // 5. Verifica o resultado final
    $final_check = "SELECT DISTINCT status FROM payment_links";
    $result = $conn->query($final_check);
    echo "<br>Status após a atualização:<br>";
    while ($row = $result->fetch_assoc()) {
        echo "- " . $row['status'] . "<br>";
    }

    echo "<br>Atualização concluída!";

} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?> 