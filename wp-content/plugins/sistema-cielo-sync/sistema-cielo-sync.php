<?php
/*
Plugin Name: Sistema Cielo Sync
Description: Sincronização de usuários e login com o sistema de links Cielo
Version: 1.0
Author: Seu Nome
*/

// Previne acesso direto
if (!defined('ABSPATH')) {
    exit;
}

// Configurações do sistema
define('SISTEMA_API_URL', 'https://revenda.dilima.com.br/geralinkcielo/api');  // URL base da API
define('SISTEMA_URL', 'https://revenda.dilima.com.br/geralinkcielo');  // URL base do sistema

// Hook para criação/atualização de usuário
add_action('user_register', 'sincronizar_usuario_sistema');
add_action('profile_update', 'sincronizar_usuario_sistema');
add_action('set_user_role', 'sincronizar_usuario_sistema', 10, 2);

// Hook para login
add_action('wp_login', 'sincronizar_login_sistema', 10, 2);

// Hook para logout
add_action('wp_logout', 'sincronizar_logout_sistema');

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
    $response = wp_remote_post(SISTEMA_API_URL . '/usuarios/sync.php', array(
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

function sincronizar_login_sistema($user_login, $user) {
    // Se for uma requisição direta do menu Sistema Cielo, processa independentemente da origem
    $is_sistema_menu = isset($_GET['page']) && $_GET['page'] === 'sistema-cielo-login';
    
    // Verifica se é uma requisição de login do WooCommerce e NÃO é do menu Sistema Cielo
    if (!$is_sistema_menu && (
        isset($_POST['woocommerce-login-nonce']) || // Login na página Minha Conta
        isset($_POST['_wpnonce']) || // Outros formulários do WooCommerce
        (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'minha-conta') !== false) || // Referrer da página Minha Conta
        (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], 'minha-conta') !== false) // URL atual é Minha Conta
    )) {
        return; // Não redireciona logins do WooCommerce
    }

    // Verifica se o usuário tem uma das roles necessárias
    $roles = $user->roles;
    $roles_permitidas = array('administrator', 'editor', 'revendedora');
    $tem_role_permitida = false;
    
    foreach ($roles_permitidas as $role) {
        if (in_array($role, $roles)) {
            $tem_role_permitida = true;
            break;
        }
    }
    
    if (!$tem_role_permitida) {
        return; // Não redireciona usuários sem as roles necessárias
    }

    // Verifica se o login foi feito na página específica para o sistema Cielo ou através do menu
    $is_sistema_login = $is_sistema_menu || 
                       isset($_POST['sistema_cielo_login']) || 
                       (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'sistema-cielo-login') !== false);

    if (!$is_sistema_login) {
        return; // Não redireciona se não for login específico para o sistema
    }

    // Gera um token único para esta sessão
    $token = wp_hash(time() . $user->ID . uniqid('', true));
    
    // Armazena o token nos dados do usuário WordPress
    update_user_meta($user->ID, 'sistema_session_token', $token);
    
    // Prepara os dados para o login
    $dados = array(
        'wp_user_id' => $user->ID,
        'email' => $user->user_email,
        'token' => $token
    );
    
    // Envia para o sistema
    $response = wp_remote_post(SISTEMA_API_URL . '/auth/wp-login.php', array(
        'body' => $dados,
        'timeout' => 15,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        )
    ));
    
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($result && isset($result['success']) && $result['success']) {
            // Redireciona para o sistema com o token
            wp_redirect(SISTEMA_URL . '/auth/wp-callback.php?token=' . $token);
            exit;
        }
    }
    
    error_log('Erro ao sincronizar login com sistema Cielo: ' . 
              (is_wp_error($response) ? $response->get_error_message() : 'Resposta inválida do servidor'));
}

function sincronizar_logout_sistema() {
    $user_id = get_current_user_id();
    if (!$user_id) return;
    
    // Remove o token de sessão
    delete_user_meta($user_id, 'sistema_session_token');
    
    // Notifica o sistema sobre o logout
    $dados = array(
        'wp_user_id' => $user_id
    );
    
    wp_remote_post(SISTEMA_API_URL . '/auth/wp-logout.php', array(
        'body' => $dados,
        'timeout' => 15,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        )
    ));
}

// Adiciona uma página de login específica para o sistema
function adicionar_pagina_login_sistema() {
    add_menu_page(
        'Login Sistema Cielo',
        'Sistema Cielo',
        'read',
        'sistema-cielo-login',
        'exibir_pagina_login_sistema',
        'dashicons-cart',
        6
    );
}
add_action('admin_menu', 'adicionar_pagina_login_sistema');

function exibir_pagina_login_sistema() {
    // Se o usuário já está logado, redireciona direto para o sistema
    if (is_user_logged_in()) {
        $user = wp_get_current_user();
        sincronizar_login_sistema($user->user_login, $user);
        return;
    }
    ?>
    <div class="wrap">
        <h1>Login Sistema Cielo</h1>
        <p>Use este formulário para acessar o sistema de links de pagamento Cielo.</p>
        <form method="post" action="<?php echo wp_login_url(); ?>">
            <input type="hidden" name="sistema_cielo_login" value="1">
            <input type="hidden" name="redirect_to" value="<?php echo SISTEMA_URL; ?>">
            <?php wp_nonce_field('sistema-cielo-login', 'sistema-cielo-nonce'); ?>
            <p>Clique no botão abaixo para entrar no sistema:</p>
            <p class="submit">
                <input type="submit" name="submit" id="submit" class="button button-primary" value="Acessar Sistema Cielo">
            </p>
        </form>
    </div>
    <?php
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

// Adiciona o shortcode para link de acesso ao sistema
function sistema_cielo_link_shortcode($atts) {
    // Se o usuário não estiver logado, retorna mensagem
    if (!is_user_logged_in()) {
        return '<p class="alert alert-warning">Você precisa estar logado para acessar o sistema.</p>';
    }
    
    $user = wp_get_current_user();
    $roles = $user->roles;
    
    // Verifica se o usuário tem uma das roles permitidas
    $roles_permitidas = array('administrator', 'editor', 'revendedora');
    $tem_role_permitida = false;
    
    foreach ($roles_permitidas as $role) {
        if (in_array($role, $roles)) {
            $tem_role_permitida = true;
            break;
        }
    }
    
    if (!$tem_role_permitida) {
        return '<p class="alert alert-warning">Você não tem permissão para acessar o sistema.</p>';
    }
    
    // Gera um token único para esta sessão
    $token = wp_hash(time() . $user->ID . uniqid('', true));
    
    // Armazena o token nos dados do usuário WordPress
    update_user_meta($user->ID, 'sistema_session_token', $token);
    
    // Prepara os dados para o login
    $dados = array(
        'wp_user_id' => $user->ID,
        'email' => $user->user_email,
        'token' => $token
    );
    
    // Envia para o sistema
    $response = wp_remote_post(SISTEMA_API_URL . '/auth/wp-login.php', array(
        'body' => $dados,
        'timeout' => 15,
        'headers' => array(
            'Content-Type' => 'application/x-www-form-urlencoded'
        )
    ));
    
    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        if ($result && isset($result['success']) && $result['success']) {
            // Retorna o link de acesso
            return '<a href="' . esc_url(SISTEMA_URL . '/auth/wp-callback.php?token=' . $token) . '" class="button button-primary">
                        <i class="dashicons dashicons-cart"></i> Acessar Sistema de Links
                    </a>';
        }
    }
    
    return '<p class="alert alert-danger">Erro ao gerar link de acesso. Por favor, tente novamente.</p>';
}
add_shortcode('sistema_cielo_link', 'sistema_cielo_link_shortcode'); 