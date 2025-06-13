<?php
if (!isset($_SESSION)) {
    session_start();
}

// Função auxiliar para verificar a página ativa
function isActivePage($page) {
    $current_page = basename($_SERVER['PHP_SELF']);
    $current_dir = dirname($_SERVER['PHP_SELF']);
    
    if ($page === 'usuarios.php') {
        return $current_page === $page && strpos($current_dir, '/admin') !== false;
    }
    
    return $current_page === $page;
}

// Função para gerar URLs corretas
function getBaseUrl() {
    $base_url = rtrim(dirname($_SERVER['PHP_SELF']), '/admin');
    return $base_url;
}
?>
<!-- Início do Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container-fluid">
        <a class="navbar-brand" href="<?php echo getBaseUrl(); ?>/dashboard.php">
            <i class="fas fa-credit-card me-2"></i>Sistema de Pagamento
        </a>
        
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('dashboard.php') ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>/dashboard.php">
                        <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('gerar-link.php') ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>/gerar-link.php">
                        <i class="fas fa-plus me-2"></i>Gerar Link
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('meus-links.php') ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>/meus-links.php">
                        <i class="fas fa-list me-2"></i>Meus Links
                    </a>
                </li>
                <?php if (in_array($_SESSION['nivel_acesso'], ['admin', 'editor'])): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo isActivePage('usuarios.php') ? 'active' : ''; ?>" href="<?php echo getBaseUrl(); ?>/admin/usuarios.php">
                        <i class="fas fa-users me-2"></i>Usuários
                    </a>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="navbar-nav">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['nome']); ?>
                        <?php if ($_SESSION['nivel_acesso'] !== 'usuario'): ?>
                            <span class="badge bg-warning ms-1"><?php echo strtoupper($_SESSION['nivel_acesso']); ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>/perfil.php"><i class="fas fa-user-cog me-2"></i>Meu Perfil</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="<?php echo getBaseUrl(); ?>/logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</nav> 