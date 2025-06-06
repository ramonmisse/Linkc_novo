<nav class="navbar navbar-expand-lg navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand" href="dashboard.php">
            <i class="fas fa-link me-2"></i>
            Sistema de Links
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-home me-1"></i> Início
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="meus-links.php">
                        <i class="fas fa-list me-1"></i> Meus Links
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="gerar-link.php">
                        <i class="fas fa-plus me-1"></i> Novo Link
                    </a>
                </li>
            </ul>
            <ul class="navbar-nav">
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i>
                        <?php echo htmlspecialchars($_SESSION['nome'] ?? 'Usuário'); ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <?php if (in_array($_SESSION['nivel_acesso'] ?? '', ['admin', 'editor'])): ?>
                            <li>
                                <a class="dropdown-item" href="admin/dashboard.php">
                                    <i class="fas fa-cog me-1"></i> Administração
                                </a>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                        <?php endif; ?>
                        <li>
                            <a class="dropdown-item" href="logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i> Sair
                            </a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav> 