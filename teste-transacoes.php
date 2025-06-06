<?php
session_start();
require_once 'includes/auth.php';
require_once 'config/cielo.php';

requireLogin();

$result = null;
$error = null;
$debug_info = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['product_id'])) {
    try {
        $cielo = new CieloAPI();
        
        // Log do ID recebido
        $debug_info[] = "ID do Produto recebido: " . $_POST['product_id'];
        
        // Faz a consulta usando o método público
        $response = $cielo->checkPaymentStatus($_POST['product_id']);
        
        $debug_info[] = "Resposta do método checkPaymentStatus: " . json_encode($response);
        
        if ($response['success']) {
            $result = $response['transactions'];
            if (empty($result)) {
                $debug_info[] = "Nenhuma transação encontrada para este ID";
            }
        } else {
            $error = "Erro ao consultar transações";
            $debug_info[] = "A consulta retornou success = false";
        }
        
    } catch (Exception $e) {
        $error = "Erro: " . $e->getMessage();
        $debug_info[] = "Exception: " . $e->getMessage();
        $debug_info[] = "Stack trace: " . $e->getTraceAsString();
    }
}

// Buscar todos os IDs disponíveis no banco de dados
try {
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "SELECT id, product_id, descricao, valor_final, created_at FROM payment_links ORDER BY created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $available_links = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Erro ao buscar links disponíveis: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Transações - Cielo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0">Teste de Consulta de Transações</h4>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($available_links)): ?>
                            <div class="mb-4">
                                <h5>Links Disponíveis:</h5>
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped">
                                        <thead>
                                            <tr>
                                                <th>ID do Link</th>
                                                <th>Product ID</th>
                                                <th>Descrição</th>
                                                <th>Valor</th>
                                                <th>Data</th>
                                                <th>Ação</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($available_links as $link): ?>
                                                <tr>
                                                    <td><?php echo $link['id']; ?></td>
                                                    <td><?php echo htmlspecialchars($link['product_id'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($link['descricao'] ?? ''); ?></td>
                                                    <td>R$ <?php echo number_format(($link['valor_final'] ?? 0) / 100, 2, ',', '.'); ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($link['created_at'])); ?></td>
                                                    <td>
                                                        <form method="post" style="display: inline;">
                                                            <input type="hidden" name="product_id" value="<?php echo htmlspecialchars($link['product_id']); ?>">
                                                            <button type="submit" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-search"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endif; ?>

                        <form method="post" class="mb-4">
                            <div class="mb-3">
                                <label for="product_id" class="form-label">ID do Link/Produto</label>
                                <input type="text" class="form-control" id="product_id" name="product_id" 
                                       value="<?php echo htmlspecialchars($_POST['product_id'] ?? ''); ?>" required>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-2"></i>Consultar Transações
                            </button>
                        </form>

                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($result): ?>
                            <h5 class="mb-3">Resultado:</h5>
                            <pre class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow-y: auto;">
<?php echo htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
                            </pre>
                        <?php endif; ?>

                        <?php if (!empty($debug_info)): ?>
                            <h5 class="mb-3 mt-4">Informações de Debug:</h5>
                            <div class="bg-dark text-light p-3 rounded" style="max-height: 400px; overflow-y: auto;">
                                <?php foreach ($debug_info as $info): ?>
                                    <div class="mb-2"><?php echo htmlspecialchars($info); ?></div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 