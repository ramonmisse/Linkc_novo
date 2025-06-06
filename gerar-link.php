<?php
session_start();
require_once 'includes/auth.php';
require_once 'includes/functions.php';

requireLogin();

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $valor = floatval($_POST['valor'] ?? 0);
    $parcelas = intval($_POST['parcelas'] ?? 1);
    $descricao = trim($_POST['descricao'] ?? '');
    
    if ($valor <= 0) {
        $error_message = 'Por favor, informe um valor válido';
    } elseif ($parcelas < 1 || $parcelas > 6) {
        $error_message = 'Número de parcelas deve ser entre 1 e 6';
    } else {
        $result = createPaymentLink($_SESSION['user_id'], $valor, $parcelas, $descricao);
        if ($result['success']) {
            $success_message = 'Link de pagamento criado com sucesso!';
            $generated_link = $result['link_url'];
            error_log("Link gerado com sucesso: " . $generated_link);
        } else {
            $error_message = $result['message'];
            error_log("Erro ao gerar link: " . $result['message']);
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
                                
                                <form method="POST" id="payment-form">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="valor" class="form-label">Valor (R$)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="number" class="form-control" id="valor" name="valor" 
                                                           step="0.01" min="0.01" required
                                                           value="<?php echo htmlspecialchars($_POST['valor'] ?? ''); ?>">
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label for="parcelas" class="form-label">Parcelas</label>
                                                <select class="form-select" id="parcelas" name="parcelas" required>
                                                    <option value="">Selecione</option>
                                                    <?php for ($i = 1; $i <= 6; $i++): ?>
                                                        <option value="<?php echo $i; ?>" 
                                                                <?php echo (isset($_POST['parcelas']) && $_POST['parcelas'] == $i) ? 'selected' : ''; ?>>
                                                            <?php echo $i; ?>x
                                                            <?php if ($i >= 4): ?>
                                                                (com juros de 4%)
                                                            <?php else: ?>
                                                                (sem juros)
                                                            <?php endif; ?>
                                                        </option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="descricao" class="form-label">Descrição (Opcional)</label>
                                        <textarea class="form-control" id="descricao" name="descricao" rows="3" 
                                                  placeholder="Descrição do pagamento"><?php echo htmlspecialchars($_POST['descricao'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <div id="calculation-preview" class="alert alert-info" style="display: none;">
                                        <h6>Resumo do Pagamento:</h6>
                                        <div id="preview-content"></div>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-link me-2"></i>Gerar Link de Pagamento
                                    </button>
                                    <a href="dashboard.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Voltar
                                    </a>
                                </form>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-4">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Informações sobre Juros</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <strong>Parcelamento sem juros:</strong>
                                    <ul class="mb-2">
                                        <li>1x - À vista</li>
                                        <li>2x - Sem juros</li>
                                        <li>3x - Sem juros</li>
                                    </ul>
                                </div>
                                
                                <div>
                                    <strong>Parcelamento com juros:</strong>
                                    <ul class="mb-0">
                                        <li>4x - 4% sobre o valor total</li>
                                        <li>5x - 4% sobre o valor total</li>
                                        <li>6x - 4% sobre o valor total</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-3">
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
    
    <!-- Modal para exibir link gerado -->
    <div class="modal fade" id="linkGeneratedModal" tabindex="-1" aria-labelledby="linkGeneratedModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title" id="linkGeneratedModalLabel">
                        <i class="fas fa-check-circle me-2"></i>Link de Pagamento Gerado com Sucesso!
                    </h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-success" role="alert">
                        <i class="fas fa-info-circle me-2"></i>
                        Seu link de pagamento foi criado e está pronto para ser compartilhado!
                    </div>
                    
                    <div class="mb-3">
                        <label for="modal-generated-link" class="form-label"><strong>Link de Pagamento:</strong></label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="modal-generated-link" readonly>
                            <button class="btn btn-outline-primary" type="button" onclick="copyLinkFromModal()">
                                <i class="fas fa-copy"></i> Copiar Link
                            </button>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-secondary me-md-2" onclick="shareLink()">
                            <i class="fas fa-share-alt"></i> Compartilhar
                        </button>
                        <a href="meus-links.php" class="btn btn-info">
                            <i class="fas fa-list"></i> Ver Meus Links
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" onclick="closeModalAndReset()">
                        <i class="fas fa-plus"></i> Gerar Novo Link
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const valorInput = document.getElementById('valor');
            const parcelasSelect = document.getElementById('parcelas');
            const previewDiv = document.getElementById('calculation-preview');
            const previewContent = document.getElementById('preview-content');
            
            function updatePreview() {
                const valor = parseFloat(valorInput.value) || 0;
                const parcelas = parseInt(parcelasSelect.value) || 0;
                
                if (valor > 0 && parcelas > 0) {
                    const temJuros = parcelas >= 4;
                    const juros = temJuros ? valor * 0.04 : 0;
                    const valorFinal = valor + juros;
                    const valorParcela = valorFinal / parcelas;
                    
                    let html = `
                        <div class="row">
                            <div class="col-6">Valor Original:</div>
                            <div class="col-6"><strong>R$ ${valor.toFixed(2).replace('.', ',')}</strong></div>
                        </div>
                    `;
                    
                    if (temJuros) {
                        html += `
                            <div class="row">
                                <div class="col-6">Juros (4%):</div>
                                <div class="col-6"><strong>R$ ${juros.toFixed(2).replace('.', ',')}</strong></div>
                            </div>
                        `;
                    }
                    
                    html += `
                        <div class="row">
                            <div class="col-6">Valor Final:</div>
                            <div class="col-6"><strong>R$ ${valorFinal.toFixed(2).replace('.', ',')}</strong></div>
                        </div>
                        <div class="row">
                            <div class="col-6">Valor da Parcela:</div>
                            <div class="col-6"><strong>R$ ${valorParcela.toFixed(2).replace('.', ',')} x ${parcelas}</strong></div>
                        </div>
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
                console.log('Link gerado:', '<?php echo addslashes($generated_link); ?>');
                document.getElementById('modal-generated-link').value = '<?php echo addslashes($generated_link); ?>';
                var linkModal = new bootstrap.Modal(document.getElementById('linkGeneratedModal'));
                linkModal.show();
            <?php else: ?>
                console.log('Nenhum link gerado ou link vazio');
            <?php endif; ?>
        });
        
        // Function to copy link from modal
        function copyLinkFromModal() {
            const linkInput = document.getElementById('modal-generated-link');
            linkInput.select();
            linkInput.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                showCopySuccess();
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
            
            // Clear preview
            const previewDiv = document.getElementById('calculation-preview');
            if (previewDiv) {
                previewDiv.style.display = 'none';
            }
        }
    </script>
</body>
</html>
