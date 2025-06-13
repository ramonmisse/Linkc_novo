<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['nome'];
$user_level = $_SESSION['nivel_acesso'];

// Get recent payment links
$result = getPaymentLinks($user_id, $user_level);
$recent_links = array_slice($result['links'] ?? [], 0, 5);

// Get statistics
$database = new Database();
$db = $database->getConnection();

$stats = array(
    'total_links' => 0,
    'total_amount' => 0,
    'paid_links' => 0,
    'pending_links' => 0
);

try {
    if (in_array($user_level, ['admin', 'editor'])) {
        $query = "SELECT COUNT(*) as total, COALESCE(SUM(valor_final), 0) as total_amount FROM payment_links";
        $stmt = $db->prepare($query);
    } else {
        $query = "SELECT COUNT(*) as total, COALESCE(SUM(valor_final), 0) as total_amount FROM payment_links WHERE user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
    }
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['total_links'] = intval($result['total'] ?? 0);
    $stats['total_amount'] = intval($result['total_amount'] ?? 0);
    
    // Get paid links count
    if (in_array($user_level, ['admin', 'editor'])) {
        $query = "SELECT COUNT(*) as paid FROM payment_links WHERE status = 'Pago'";
        $stmt = $db->prepare($query);
    } else {
        $query = "SELECT COUNT(*) as paid FROM payment_links WHERE user_id = :user_id AND status = 'Pago'";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
    }
    $stmt->execute();
    $result = $stmt->fetch();
    $stats['paid_links'] = intval($result['paid'] ?? 0);
    
    $stats['pending_links'] = $stats['total_links'] - $stats['paid_links'];
} catch (Exception $e) {
    error_log("Dashboard stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Sistema de Pagamento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'includes/navbar.php'; ?>
    
    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div class="card">
                    <div class="card-body p-3">
                        <nav class="nav flex-column">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                            <a class="nav-link" href="gerar-link.php">
                                <i class="fas fa-plus me-2"></i>Gerar Link
                            </a>
                            <a class="nav-link" href="meus-links.php">
                                <i class="fas fa-list me-2"></i>Meus Links
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="col-md-9 col-lg-10">
                <!-- Welcome Section -->
                <div class="row mb-4">
                    <div class="col-12">
                        <h1 class="h3">Bem-vindo, <?php echo htmlspecialchars($user_name); ?>!</h1>
                        <p class="text-muted">Gerencie seus links de pagamento de forma simples e eficiente.</p>
                    </div>
                </div>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $stats['total_links']; ?></h4>
                                        <p class="card-text">Total de Links</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-link fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card bg-success text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $stats['paid_links']; ?></h4>
                                        <p class="card-text">Links Pagos</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-check-circle fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card bg-warning text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo $stats['pending_links']; ?></h4>
                                        <p class="card-text">Pendentes</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-clock fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="card bg-info text-white">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h4 class="card-title"><?php echo formatCurrency($stats['total_amount']); ?></h4>
                                        <p class="card-text">Valor Total</p>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="fas fa-dollar-sign fa-2x"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">Ações Rápidas</h5>
                                <div class="d-flex gap-2">
                                    <a href="gerar-link.php" class="btn btn-primary">
                                        <i class="fas fa-plus me-2"></i>Gerar Novo Link
                                    </a>
                                    <a href="meus-links.php" class="btn btn-outline-primary">
                                        <i class="fas fa-list me-2"></i>Ver Todos os Links
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Links -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Links Recentes</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_links)): ?>
                                    <div class="text-center py-5">
                                        <i class="fas fa-link fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Nenhum link encontrado</p>
                                        <a href="gerar-link.php" class="btn btn-primary">
                                            <i class="fas fa-plus me-2"></i>Criar Primeiro Link
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Valor</th>
                                                    <th>Parcelas</th>
                                                    <th>Status</th>
                                                    <th>Data</th>
                                                    <?php if (in_array($user_level, ['admin', 'editor'])): ?>
                                                        <th>Usuário</th>
                                                    <?php endif; ?>
                                                    <th>Ações</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_links as $link): ?>
                                                    <tr>
                                                        <td>
                                                            <div>
                                                                <strong><?php echo formatCurrency($link['valor_final'] ?? 0); ?></strong>
                                                                <?php if (!empty($link['valor_juros']) && $link['valor_juros'] > 0): ?>
                                                                    <br><small class="text-muted">Original: <?php echo formatCurrency($link['valor_original'] ?? 0); ?></small>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                        <td><?php echo ($link['parcelas'] ?? 0); ?>x</td>
                                                        <td>
                                                            <span class="badge <?php echo getStatusBadgeClass($link['status'] ?? ''); ?>">
                                                                <?php echo htmlspecialchars($link['status'] ?? 'N/A'); ?>
                                                            </span>
                                                        </td>
                                                        <td><?php echo !empty($link['created_at']) ? date('d/m/Y H:i', strtotime($link['created_at'])) : 'N/A'; ?></td>
                                                        <?php if (in_array($user_level, ['admin', 'editor'])): ?>
                                                            <td><?php echo htmlspecialchars($link['nome_usuario'] ?? 'N/A'); ?></td>
                                                        <?php endif; ?>
                                                        <td>
                                                            <?php if (!empty($link['link_url'])): ?>
                                                                <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('<?php echo htmlspecialchars($link['link_url']); ?>')">
                                                                    <i class="fas fa-copy"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    <div class="text-center mt-3">
                                        <a href="meus-links.php" class="btn btn-outline-primary">Ver Todos os Links</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
</body>
</html>
