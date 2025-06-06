<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';
require_once 'config/cielo.php';
require_once 'config/database.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_level = $_SESSION['nivel_acesso'];
$error_message = '';
$link_info = null;
$transactions = array();

// Verificar se foi fornecido um ID válido
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: meus-links.php');
    exit;
}

$link_id = intval($_GET['id']);

// Buscar informações do link
try {
    $database = new Database();
    $db = $database->getConnection();
    
    // Verificar permissão para acessar o link
    if (in_array($user_level, ['admin', 'editor'])) {
        $query = "SELECT * FROM payment_links WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $link_id);
    } else {
        $query = "SELECT * FROM payment_links WHERE id = :id AND user_id = :user_id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $link_id);
        $stmt->bindParam(':user_id', $user_id);
    }
    
    $stmt->execute();
    $link_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$link_info) {
        header('Location: meus-links.php');
        exit;
    }
    
    // Buscar transações na API da Cielo
    if (!empty($link_info['product_id'])) {
        $cielo = new CieloAPI();
        $payment_status = $cielo->checkPaymentStatus($link_info['product_id']);
        
        if ($payment_status['success'] && isset($payment_status['transactions'])) {
            $transactions = $payment_status['transactions'];
        } else {
            $error_message = 'Não foi possível carregar as transações deste link.';
        }
    }
    
} catch (Exception $e) {
    error_log("Erro ao buscar transações: " . $e->getMessage());
    $error_message = 'Erro ao carregar as informações do link.';
}

function formatStatus($status) {
    switch ($status) {
        case 'Paid':
            return '<span class="badge bg-success">Pago</span>';
        case 'Pending':
            return '<span class="badge bg-warning">Pendente</span>';
        case 'Canceled':
            return '<span class="badge bg-danger">Cancelado</span>';
        default:
            return '<span class="badge bg-secondary">Desconhecido</span>';
    }
}

function formatMoney($value) {
    return 'R$ ' . number_format($value / 100, 2, ',', '.');
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transações do Link - Sistema de Pagamento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container py-4">
        <div class="row justify-content-center">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2 class="mb-0">
                        <i class="fas fa-receipt me-2"></i>
                        Transações do Link
                    </h2>
                    <a href="meus-links.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left me-2"></i>Voltar
                    </a>
                </div>
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger">
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($link_info): ?>
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Informações do Link</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <p><strong>Descrição:</strong> <?php echo htmlspecialchars($link_info['descricao'] ?? ''); ?></p>
                                    <p><strong>Valor:</strong> <?php echo formatMoney($link_info['amount'] ?? 0); ?></p>
                                    <p><strong>Parcelas:</strong> <?php echo $link_info['parcelas']; ?>x</p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Status:</strong> <?php echo formatStatus($link_info['status'] ?? ''); ?></p>
                                    <p><strong>Criado em:</strong> <?php echo date('d/m/Y H:i', strtotime($link_info['created_at'])); ?></p>
                                    <p>
                                        <strong>Link:</strong>
                                        <a href="<?php echo htmlspecialchars($link_info['link_url'] ?? '#'); ?>" target="_blank">
                                            Abrir <i class="fas fa-external-link-alt ms-1"></i>
                                        </a>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Histórico de Transações</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($transactions)): ?>
                                <div class="text-center py-5">
                                    <i class="fas fa-receipt fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Nenhuma transação encontrada</h5>
                                    <p class="text-muted mb-0">Este link ainda não possui transações registradas.</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th>ID Transação</th>
                                                <th>Valor</th>
                                                <th>Status</th>
                                                <th>Forma de Pagamento</th>
                                                <th>Parcelas</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $transaction): ?>
                                                <?php if (is_array($transaction)): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($transaction['created_at'] ?? '')); ?></td>
                                                        <td><?php echo htmlspecialchars($transaction['id'] ?? ''); ?></td>
                                                        <td><?php echo formatMoney($transaction['payment']['price'] ?? 0); ?></td>
                                                        <td>
                                                            <?php echo formatStatus($transaction['payment']['status'] ?? ''); ?>
                                                        </td>
                                                        <td><?php echo htmlspecialchars($transaction['payment']['type'] ?? ''); ?></td>
                                                        <td><?php echo ($transaction['payment']['numberOfPayments'] ?? 1); ?>x</td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 