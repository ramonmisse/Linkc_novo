<?php
/*
Plugin Name: Sistema Cielo Sync
Description: Sincronização de usuários com o sistema de links Cielo
Version: 1.0
Author: Seu Nome
*/

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Configurações do sistema
define('SISTEMA_API_URL', 'https://seu-dominio.com/geralinkcielo/api/usuarios');  // Altere conforme necessário

// Hook para criação/atualização de usuário
add_action('user_register', 'sincronizar_usuario_sistema');
add_action('profile_update', 'sincronizar_usuario_sistema');
add_action('set_user_role', 'sincronizar_usuario_sistema', 10, 2);

function sincronizar_usuario_sistema($user_id, $role = null) {
    $user = get_userdata($user_id);
    if (!$user) return;
    
    // Obtém todas as roles do usuário
    $roles = $user->roles;
    
    // Mapeia o nível de acesso
    $nivel_acesso = 'usuario'; // padrão
    if (in_array('administrator', $roles)) {
        $nivel_acesso = 'admin';
    } elseif (in_array('editor', $roles)) {
        $nivel_acesso = 'editor';
    } elseif (in_array('revendedora', $roles)) {
        $nivel_acesso = 'usuario';
    } else {
        return; // Não sincroniza outros tipos de usuário
    }
    
    // Prepara os dados
    $dados = array(
        'wp_user_id' => $user_id,
        'email' => $user->user_email,
        'nome' => $user->display_name,
        'nivel_acesso' => $nivel_acesso
    );
    
    // Envia para o sistema
    $response = wp_remote_post(SISTEMA_API_URL . '/sync.php', array(
        'body' => $dados,
        'timeout' => 15,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        )
    ));
    
    if (is_wp_error($response)) {
        error_log('Erro ao sincronizar usuário com sistema Cielo: ' . $response->get_error_message());
    }
}

// Adiciona role de revendedora
function adicionar_role_revendedora() {
    add_role(
        'revendedora',
        'Revendedora',
        array(
            'read' => true,
            'edit_posts' => false,
            'delete_posts' => false
        )
    );
}
register_activation_hook(__FILE__, 'adicionar_role_revendedora'); 