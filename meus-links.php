<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];
$user_level = $_SESSION['nivel_acesso'];

// Get all payment links
$payment_links = getPaymentLinks($user_id, $user_level);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $link_id = intval($_POST['link_id']);
    $new_status = $_POST['status'];
    
    $result = updatePaymentStatus($link_id, $new_status, $user_level);
    $message = $result['message'];
    $message_type = $result['success'] ? 'success' : 'danger';
    
    // Refresh the links after update
    $payment_links = getPaymentLinks($user_id, $user_level);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Meus Links - Sistema de Pagamento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-credit-card me-2"></i>Sistema de Pagamento
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['nome']); ?>
                        <?php if ($_SESSION['nivel_acesso'] !== 'usuario'): ?>
                            <span class="badge bg-warning ms-1"><?php echo strtoupper($_SESSION['nivel_acesso']); ?></span>
                        <?php endif; ?>
                    </a>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Sair</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div class="card">
                    <div class="card-body p-3">
                        <nav class="nav flex-column">
                            <a class="nav-link" href="dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                            <a class="nav-link" href="gerar-link.php">
                                <i class="fas fa-plus me-2"></i>Gerar Link
                            </a>
                            <a class="nav-link active" href="meus-links.php">
                                <i class="fas fa-list me-2"></i>Meus Links
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="col-md-9 col-lg-10">
                <div class="row mb-4">
                    <div class="col-12 d-flex justify-content-between align-items-center">
                        <h1 class="h3">
                            <?php if (in_array($user_level, ['admin', 'editor'])): ?>
                                Todos os Links de Pagamento
                            <?php else: ?>
                                Meus Links de Pagamento
                            <?php endif; ?>
                        </h1>
                        <a href="gerar-link.php" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Novo Link
                        </a>
                    </div>
                </div>
                
                <?php if (isset($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-triangle'; ?> me-2"></i>
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <?php if (empty($payment_links)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-link fa-3x text-muted mb-3"></i>
                                <h4 class="text-muted">Nenhum link encontrado</h4>
                                <p class="text-muted">Você ainda não criou nenhum link de pagamento.</p>
                                <a href="gerar-link.php" class="btn btn-primary">
                                    <i class="fas fa-plus me-2"></i>Criar Primeiro Link
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
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
                                        <?php foreach ($payment_links as $link): ?>
                                            <tr data-product-id="<?php echo htmlspecialchars($link['product_id'] ?? ''); ?>">
                                                <td><?php echo htmlspecialchars($link['descricao'] ?? ''); ?></td>
                                                <td>R$ <?php echo number_format(($link['valor_final'] ?? 0) / 100, 2, ',', '.'); ?></td>
                                                <td><?php echo $link['parcelas']; ?>x</td>
                                                <td class="status-cell">
                                                    <div class="spinner-border spinner-border-sm" role="status">
                                                        <span class="visually-hidden">Carregando...</span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <a href="<?php echo htmlspecialchars($link['link_url'] ?? ''); ?>" target="_blank" class="btn btn-primary btn-sm">
                                                            <i class="fas fa-external-link-alt"></i> Abrir
                                                        </a>
                                                        <a href="transacoes.php?id=<?php echo $link['id']; ?>" class="btn btn-info btn-sm">
                                                            <i class="fas fa-receipt"></i> Transações
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Atualizar Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <p>Tem certeza que deseja alterar o status para <strong id="new-status-text"></strong>?</p>
                        <input type="hidden" id="status-link-id" name="link_id">
                        <input type="hidden" id="status-new-status" name="status">
                        <input type="hidden" name="update_status" value="1">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Confirmar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Details Modal -->
    <div class="modal fade" id="detailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Detalhes do Link</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="details-content"></div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        // Função para atualizar o status de um link específico
        function refreshLinkStatus(linkId) {
            fetch('update-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=check_cielo_status&link_id=${linkId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Atualiza o status na interface
                    const statusBadge = document.querySelector(`tr[data-product-id="${linkId}"] .status-cell`);
                    if (statusBadge) {
                        statusBadge.innerHTML = `
                            <div class="spinner-border spinner-border-sm" role="status">
                                <span class="visually-hidden">Carregando...</span>
                            </div>
                        `;
                    }
                    
                    // Se o status mudou para "Pago", mostra uma notificação
                    if (data.status === 'Pago') {
                        showNotification('Link pago com sucesso!', 'success');
                    }
                }
            })
            .catch(error => console.error('Erro ao atualizar status:', error));
        }

        // Função para mostrar notificações
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 end-0 m-3`;
            notification.style.zIndex = '9999';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(notification);
            
            // Remove a notificação após 5 segundos
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Inicia a verificação automática para links pendentes
        function startAutoRefresh() {
            const pendingLinks = document.querySelectorAll('tr[data-status="Aguardando Pagamento"]');
            pendingLinks.forEach(link => {
                const linkId = link.getAttribute('data-link-id');
                // Atualiza o status a cada 30 segundos
                setInterval(() => refreshLinkStatus(linkId), 30000);
            });
        }

        // Funções existentes...
        function updateStatus(linkId, newStatus) {
            document.getElementById('status-link-id').value = linkId;
            document.getElementById('status-new-status').value = newStatus;
            document.getElementById('new-status-text').textContent = newStatus;
            
            const modal = new bootstrap.Modal(document.getElementById('statusModal'));
            modal.show();
        }
        
        function showDetails(link) {
            const content = document.getElementById('details-content');
            const createdDate = new Date(link.created_at).toLocaleString('pt-BR');
            const updatedDate = link.updated_at ? new Date(link.updated_at).toLocaleString('pt-BR') : 'N/A';
            
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6>Informações Básicas</h6>
                        <p><strong>ID:</strong> #${link.id}</p>
                        <p><strong>Valor Original:</strong> R$ ${parseFloat(link.valor_original).toFixed(2).replace('.', ',')}</p>
                        <p><strong>Juros:</strong> R$ ${parseFloat(link.valor_juros).toFixed(2).replace('.', ',')}</p>
                        <p><strong>Valor Final:</strong> R$ ${parseFloat(link.valor_final).toFixed(2).replace('.', ',')}</p>
                        <p><strong>Parcelas:</strong> ${link.parcelas}x</p>
                    </div>
                    <div class="col-md-6">
                        <h6>Status e Datas</h6>
                        <p><strong>Status:</strong> <span class="badge ${getStatusBadgeClass(link.status)}">${link.status}</span></p>
                        <p><strong>Criado em:</strong> ${createdDate}</p>
                        <p><strong>Atualizado em:</strong> ${updatedDate}</p>
                        ${link.payment_id ? `<p><strong>ID Pagamento:</strong> ${link.payment_id}</p>` : ''}
                    </div>
                </div>
                ${link.descricao ? `<div class="row"><div class="col-12"><h6>Descrição</h6><p>${link.descricao}</p></div></div>` : ''}
                ${link.link_url ? `
                    <div class="row">
                        <div class="col-12">
                            <h6>Link de Pagamento</h6>
                            <div class="input-group">
                                <input type="text" class="form-control" value="${link.link_url}" readonly>
                                <button class="btn btn-outline-primary" onclick="copyToClipboard('${link.link_url}')">
                                    <i class="fas fa-copy"></i> Copiar
                                </button>
                            </div>
                        </div>
                    </div>
                ` : ''}
            `;
            
            const modal = new bootstrap.Modal(document.getElementById('detailsModal'));
            modal.show();
        }
        
        function getStatusBadgeClass(status) {
            switch (status) {
                case 'Aguardando Pagamento':
                    return 'bg-warning';
                case 'Pago':
                    return 'bg-success';
                case 'Crédito Gerado':
                    return 'bg-primary';
                default:
                    return 'bg-secondary';
            }
        }

        // Inicia a verificação automática quando a página carrega
        document.addEventListener('DOMContentLoaded', startAutoRefresh);
    </script>
</body>
</html>
