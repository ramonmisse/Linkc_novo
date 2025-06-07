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

// Get link ID from URL
$link_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

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
        case 'Denied':
            return '<span class="badge bg-danger">Negado</span>';
        default:
            return '<span class="badge bg-secondary">Desconhecido</span>';
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes das Transações</title>
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
                        Detalhes do Link de Pagamento
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
                                    <p><strong>ID do Link:</strong> <?php echo htmlspecialchars($link_info['id']); ?></p>
                                    <p><strong>ID do Produto:</strong> <?php echo htmlspecialchars($link_info['product_id']); ?></p>
                                    <p><strong>Descrição:</strong> <?php echo htmlspecialchars($link_info['descricao'] ?? ''); ?></p>
                                    <p><strong>Valor:</strong> <?php echo formatMoney($link_info['amount'] ?? 0); ?></p>
                                </div>
                                <div class="col-md-6">
                                    <p><strong>Data de Criação:</strong> <?php echo formatDate($link_info['created_at']); ?></p>
                                    <p>
                                        <strong>Link:</strong>
                                        <a href="<?php echo htmlspecialchars($link_info['link_url'] ?? '#'); ?>" target="_blank" class="btn btn-sm btn-primary">
                                            Abrir <i class="fas fa-external-link-alt"></i>
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
                                                <th>Cod. Autorização</th>
                                                <th>Cliente</th>
                                                <th>Valor</th>
                                                <th>Status</th>
                                                <th>Forma de Pagamento</th>
                                                <th>Parcelas</th>
                                                <th>Detalhes</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($transactions as $transaction): ?>
                                                <?php 
                                                $createdDate = $transaction['payment']['createdDate'] ?? $transaction['createdDate'] ?? null;
                                                ?>
                                                <tr>
                                                    <td><?php echo formatDate($createdDate); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['payment']['authorizationCode'] ?? 'Não disponível'); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['customer']['fullName'] ?? 'Não informado'); ?></td>
                                                    <td><?php echo formatMoney($transaction['payment']['price'] ?? $transaction['cart']['items'][0]['unitPrice'] ?? 0); ?></td>
                                                    <td><?php echo formatStatus($transaction['payment']['status'] ?? 'Desconhecido'); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['payment']['type'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($transaction['payment']['numberOfPayments'] ?? 1); ?>x</td>
                                                    <td>
                                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#transactionModal<?php echo $transaction['orderNumber']; ?>">
                                                            <i class="fas fa-info-circle"></i>
                                                        </button>
                                                    </td>
                                                </tr>

                                                <!-- Modal com detalhes da transação -->
                                                <div class="modal fade" id="transactionModal<?php echo $transaction['orderNumber']; ?>" tabindex="-1">
                                                    <div class="modal-dialog modal-lg">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Detalhes da Transação</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                <div class="row">
                                                                    <div class="col-md-6">
                                                                        <h6>Informações do Cliente</h6>
                                                                        <p><strong>Nome:</strong> <?php echo htmlspecialchars($transaction['customer']['fullName'] ?? 'Não informado'); ?></p>
                                                                        <p><strong>E-mail:</strong> <?php echo htmlspecialchars($transaction['customer']['email'] ?? 'Não informado'); ?></p>
                                                                        <p><strong>Telefone:</strong> <?php echo htmlspecialchars($transaction['customer']['phone'] ?? 'Não informado'); ?></p>
                                                                        <p><strong>CPF/CNPJ:</strong> <?php echo htmlspecialchars($transaction['customer']['identity'] ?? 'Não informado'); ?></p>
                                                                    </div>
                                                                    <div class="col-md-6">
                                                                        <h6>Informações do Pagamento</h6>
                                                                        <?php if ($transaction['payment']['type'] == 'CreditCard'): ?>
                                                                            <p><strong>Cartão:</strong> <?php echo htmlspecialchars($transaction['payment']['cardMaskedNumber'] ?? 'Não informado'); ?></p>
                                                                            <p><strong>Bandeira:</strong> <?php echo htmlspecialchars($transaction['payment']['brand'] ?? 'Não informado'); ?></p>
                                                                        <?php endif; ?>
                                                                        <p><strong>NSU:</strong> <?php echo htmlspecialchars($transaction['payment']['nsu'] ?? 'Não informado'); ?></p>
                                                                        <p><strong>TID:</strong> <?php echo htmlspecialchars($transaction['payment']['tid'] ?? 'Não informado'); ?></p>
                                                                        <p><strong>Código de Autorização:</strong> <?php echo htmlspecialchars($transaction['payment']['authorizationCode'] ?? 'Não disponível'); ?></p>
                                                                        <?php if ($transaction['payment']['status'] == 'Denied'): ?>
                                                                            <p><strong>Código de Erro:</strong> <?php echo htmlspecialchars($transaction['payment']['errorCode'] ?? 'Não informado'); ?></p>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
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