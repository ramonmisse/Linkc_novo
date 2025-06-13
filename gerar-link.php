<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$success_message = '';
$error_message = '';

// Busca informações do usuário
$database = new Database();
$db = $database->getConnection();
$stmt = $db->prepare("SELECT credencial_cielo FROM usuarios WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);
$credencial = $usuario['credencial_cielo'] ?? 'matriz';

// Define configurações de parcelas baseado na credencial
$max_parcelas = 12;
$parcelas_sem_juros = $credencial === 'matriz' ? 3 : 6;
$juros_percentual = 3.19;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor = floatval($_POST['valor'] ?? 0);
    $parcelas = intval($_POST['parcelas'] ?? 1);
    $descricao = trim($_POST['descricao'] ?? '');
    
    if ($valor <= 0) {
        $error_message = 'Por favor, informe um valor válido';
    } elseif ($parcelas < 1 || $parcelas > $max_parcelas) {
        $error_message = 'Número de parcelas deve ser entre 1 e ' . $max_parcelas;
    } elseif (empty($descricao)) {
        $error_message = 'Por favor, informe uma descrição';
    } else {
        // Se tiver juros, ajusta o valor
        if ($parcelas > $parcelas_sem_juros) {
            $valor = $valor * (1 + ($juros_percentual / 100));
        }
        
        $result = createPaymentLink($_SESSION['user_id'], $valor, $parcelas, $descricao);
        if ($result['success']) {
            $success_message = 'Link de pagamento criado com sucesso!';
            $generated_link = $result['link_url'];
        } else {
            $error_message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerar Link - Sistema de Pagamento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>
    
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
                            <a class="nav-link active" href="gerar-link.php">
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
                <div class="row">
                    <div class="col-12">
                        <h1 class="h3 mb-4">Gerar Link de Pagamento</h1>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-8">
                        <div class="card">
                            <div class="card-body">
                                <?php if (!empty($success_message) && !isset($generated_link)): ?>
                                    <div class="alert alert-success" role="alert">
                                        <i class="fas fa-check-circle me-2"></i>
                                        <?php echo htmlspecialchars($success_message); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger" role="alert">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        <?php echo htmlspecialchars($error_message); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form id="payment-form" method="POST" class="needs-validation" novalidate>
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="valor" class="form-label">Valor (R$)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="number" step="0.01" min="0.01" class="form-control" id="valor" name="valor" required>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="parcelas" class="form-label">Parcelas</label>
                                                <select class="form-select" id="parcelas" name="parcelas" required>
                                                    <?php for ($i = 1; $i <= $max_parcelas; $i++): ?>
                                                        <option value="<?php echo $i; ?>">
                                                            <?php echo $i; ?>x 
                                                            <?php if ($i <= $parcelas_sem_juros): ?>
                                                                sem juros
                                                            <?php else: ?>
                                                                com <?php echo $juros_percentual; ?>% de juros
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descricao" class="form-label">Descrição (obrigatório)</label>
                                        <input type="text" class="form-control" id="descricao" name="descricao" maxlength="20" required>
                                    </div>
                                    
                                    <div id="calculation-preview" class="alert alert-info mb-3" style="display: none;">
                                        <h6 class="alert-heading">Simulação do Pagamento</h6>
                                        <div id="preview-content"></div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-link me-2"></i>Gerar Link
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Como Funciona</h6>
                            </div>
                            <div class="card-body">
                                <ol class="mb-0">
                                    <li>Preencha o valor e número de parcelas</li>
                                    <li>Clique em "Gerar Link"</li>
                                    <li>Compartilhe o link com o cliente</li>
                                    <li>Acompanhe o status na aba "Meus Links"</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Link Gerado -->
    <div class="modal fade" id="linkGeneratedModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Link Gerado com Sucesso!</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Link de Pagamento</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="modal-generated-link" readonly 
                                   value="<?php echo htmlspecialchars($generated_link ?? ''); ?>">
                            <button class="btn btn-outline-primary" onclick="copyLinkFromModal()">
                                <i class="fas fa-copy"></i> Copiar
                            </button>
                        </div>
                    </div>
                    <div class="d-flex justify-content-between">
                        <button type="button" class="btn btn-primary" onclick="shareLink()">
                            <i class="fas fa-share-alt me-2"></i>Compartilhar
                        </button>
                        <button type="button" class="btn btn-success" onclick="closeModalAndReset()">
                            <i class="fas fa-plus me-2"></i>Gerar Novo Link
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const valorInput = document.getElementById('valor');
            const parcelasSelect = document.getElementById('parcelas');
            const previewDiv = document.getElementById('calculation-preview');
            const previewContent = document.getElementById('preview-content');
            
            function updatePreview() {
                const valor = parseFloat(valorInput.value) || 0;
                const parcelas = parseInt(parcelasSelect.value) || 1;
                
                if (valor > 0) {
                    let juros = 0;
                    if (parcelas > <?php echo $parcelas_sem_juros; ?>) {
                        juros = valor * (<?php echo $juros_percentual; ?> / 100);
                        valorInput.readOnly = true;
                        valorInput.value = (valor + juros).toFixed(2);
                    } else {
                        valorInput.readOnly = false;
                    }
                    
                    const valorTotal = valor + juros;
                    const valorParcela = valorTotal / parcelas;
                    
                    let html = `
                        <p class="mb-1"><strong>Valor Original:</strong> R$ ${valor.toFixed(2)}</p>
                        ${juros > 0 ? `<p class="mb-1"><strong>Juros:</strong> R$ ${juros.toFixed(2)} (${<?php echo $juros_percentual; ?>}%)</p>` : ''}
                        <p class="mb-1"><strong>Valor Total:</strong> R$ ${valorTotal.toFixed(2)}</p>
                        <p class="mb-0"><strong>Valor da Parcela:</strong> ${parcelas}x de R$ ${valorParcela.toFixed(2)}</p>
                    `;
                    
                    previewContent.innerHTML = html;
                    previewDiv.style.display = 'block';
                } else {
                    previewDiv.style.display = 'none';
                }
            }
            
            valorInput.addEventListener('input', updatePreview);
            parcelasSelect.addEventListener('change', updatePreview);
            
            // Initial preview if values are set
            updatePreview();
            
            // Show modal if link was generated
            <?php if (isset($generated_link) && !empty($generated_link)): ?>
                document.getElementById('modal-generated-link').value = '<?php echo addslashes($generated_link); ?>';
                var linkModal = new bootstrap.Modal(document.getElementById('linkGeneratedModal'));
                linkModal.show();
            <?php endif; ?>
        });
        
        // Function to copy link from modal
        function copyLinkFromModal() {
            const linkInput = document.getElementById('modal-generated-link');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                showNotification('Link copiado com sucesso!', 'success');
            } catch (err) {
                fallbackCopyTextToClipboard(linkInput.value);
            }
        }
        
        // Function to share link
        function shareLink() {
            const link = document.getElementById('modal-generated-link').value;
            
            if (navigator.share) {
                navigator.share({
                    title: 'Link de Pagamento',
                    text: 'Realize seu pagamento através deste link:',
                    url: link
                }).catch(console.error);
            } else {
                // Fallback - copy to clipboard
                copyLinkFromModal();
            }
        }
        
        // Function to close modal and reset form
        function closeModalAndReset() {
            // Close modal
            var linkModal = bootstrap.Modal.getInstance(document.getElementById('linkGeneratedModal'));
            linkModal.hide();
            
            // Reset form
            document.getElementById('payment-form').reset();
            document.getElementById('valor').readOnly = false;
            
            // Clear preview
            const previewDiv = document.getElementById('calculation-preview');
            if (previewDiv) {
                previewDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>
