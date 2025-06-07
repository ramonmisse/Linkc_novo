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
    <style>
        .card-body {
            overflow: visible !important;
        }
        .table-responsive {
            overflow: visible !important;
        }
        .dropdown-menu {
            z-index: 1021;
        }
        .btn-group {
            position: relative;
        }
        @media (max-width: 768px) {
            .table-responsive {
                overflow-x: auto !important;
            }
            .table {
                min-width: 800px;
            }
            .btn-group {
                display: flex;
                flex-direction: column;
                gap: 0.25rem;
            }
            .btn-group .btn {
                width: 100%;
                border-radius: 0.25rem !important;
            }
            .dropdown-menu {
                width: 100%;
                position: static !important;
                margin-top: 0.25rem !important;
                transform: none !important;
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'includes/navbar.php'; ?>
    
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
                                            <th>Usuário</th>
                                            <th class="text-end">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payment_links as $link): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($link['descricao'] ?? ''); ?></td>
                                                <td>R$ <?php echo number_format(($link['valor_final'] ?? 0) / 100, 2, ',', '.'); ?></td>
                                                <td><?php echo $link['parcelas']; ?>x</td>
                                                <td>
                                                    <span class="badge <?php echo getStatusBadgeClass($link['status']); ?>">
                                                        <i class="fas <?php echo getStatusIcon($link['status']); ?> me-1"></i>
                                                        <?php echo htmlspecialchars($link['status']); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo formatDate($link['created_at']); ?></td>
                                                <td><?php echo htmlspecialchars($link['nome_usuario'] ?? 'N/A'); ?></td>
                                                <td class="text-end">
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-outline-primary btn-sm" 
                                                                onclick="copyToClipboard('<?php echo htmlspecialchars($link['link_url']); ?>')">
                                                            <i class="fas fa-copy"></i> <span class="d-none d-md-inline">Copiar Link</span>
                                                        </button>
                                                        <a href="transacoes.php?id=<?php echo $link['id']; ?>" class="btn btn-info btn-sm">
                                                            <i class="fas fa-receipt"></i> <span class="d-none d-md-inline">Transações</span>
                                                        </a>
                                                        <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" 
                                                                data-bs-toggle="dropdown">
                                                            <i class="fas fa-cog"></i> <span class="d-none d-md-inline">Status</span>
                                                        </button>
                                                        <ul class="dropdown-menu dropdown-menu-end">
                                                            <li>
                                                                <a class="dropdown-item" href="#" 
                                                                   onclick="updateStatus(<?php echo $link['id']; ?>, 'Criado')">
                                                                    <i class="fas fa-file-alt me-2"></i>Criado
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="#" 
                                                                   onclick="updateStatus(<?php echo $link['id']; ?>, 'Crédito')">
                                                                    <i class="fas fa-check-circle me-2"></i>Crédito
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="#" 
                                                                   onclick="updateStatus(<?php echo $link['id']; ?>, 'Utilizado')">
                                                                    <i class="fas fa-clock me-2"></i>Utilizado
                                                                </a>
                                                            </li>
                                                            <li>
                                                                <a class="dropdown-item" href="#" 
                                                                   onclick="updateStatus(<?php echo $link['id']; ?>, 'Inativo')">
                                                                    <i class="fas fa-ban me-2"></i>Inativo
                                                                </a>
                                                            </li>
                                                        </ul>
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
                        <p>Tem certeza que deseja alterar o status para 
                            <strong>
                                <i class="fas status-icon me-1"></i>
                                <span id="new-status-text"></span>
                            </strong>?
                        </p>
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
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                showNotification('Link copiado com sucesso!', 'success');
            }, function(err) {
                showNotification('Erro ao copiar link', 'danger');
            });
        }

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
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        function updateStatus(linkId, newStatus) {
            const statusIcons = {
                'Criado': 'fa-file-alt',
                'Crédito': 'fa-check-circle',
                'Utilizado': 'fa-clock',
                'Inativo': 'fa-ban'
            };

            document.getElementById('status-link-id').value = linkId;
            document.getElementById('status-new-status').value = newStatus;
            document.getElementById('new-status-text').textContent = newStatus;
            document.querySelector('.status-icon').className = `fas ${statusIcons[newStatus]} status-icon me-1`;
            
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
    </script>
</body>
</html>
