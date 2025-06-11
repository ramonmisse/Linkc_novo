<?php
require_once 'includes/wp-config.php';

try {
    $wp_db = new PDO(
        "mysql:host=" . WP_DB_HOST . ";dbname=" . WP_DB_NAME,
        WP_DB_USER,
        WP_DB_PASS,
        array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        )
    );
    echo "Conexão com WordPress bem sucedida!";
    
    // Testa a consulta de usuários
    $query = "SELECT u.ID, u.user_email, u.display_name, um.meta_value as capabilities 
             FROM " . WP_PREFIX . "users u 
             LEFT JOIN " . WP_PREFIX . "usermeta um 
             ON u.ID = um.user_id 
             WHERE um.meta_key = '" . WP_PREFIX . "capabilities'";
    
    $stmt = $wp_db->prepare($query);
    $stmt->execute();
    $wp_users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\n\nUsuários encontrados: " . count($wp_users);
    foreach ($wp_users as $user) {
        echo "\n- " . $user['user_email'];
    }
    
} catch (PDOException $e) {
    echo "Erro na conexão com WordPress: " . $e->getMessage();
} 