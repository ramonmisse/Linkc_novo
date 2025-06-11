<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/functions.php';
require_once '../config/database.php';

requireLogin();

// Verifica se é administrador
if ($_SESSION['nivel_acesso'] !== 'admin') {
    header('Location: ../dashboard.php');
    exit;
}

$db = new Database();
$conn = $db->getConnection();

// Processa atualização de usuário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = intval($_POST['user_id']);
    $credencial = $_POST['credencial'];
    $nivel_acesso = $_POST['nivel_acesso'];
    $ativo = isset($_POST['ativo']) ? 1 : 0;
    
    try {
        $stmt = $conn->prepare("
            UPDATE usuarios 
            SET credencial_cielo = ?, 
                nivel_acesso = ?,
                ativo = ?
            WHERE id = ?
        ");
        
        $stmt->execute([$credencial, $nivel_acesso, $ativo, $user_id]);
        $message = "Usuário atualizado com sucesso!";
        $message_type = "success";
    } catch (Exception $e) {
        $message = "Erro ao atualizar usuário: " . $e->getMessage();
        $message_type = "danger";
    }
}

// Busca todos os usuários
$stmt = $conn->query("
    SELECT u.*, 
           COUNT(pl.id) as total_links,
           SUM(CASE WHEN pl.status = 'Pago' THEN 1 ELSE 0 END) as links_pagos
    FROM usuarios u
    LEFT JOIN payment_links pl ON u.id = pl.user_id
    GROUP BY u.id
    ORDER BY u.nome
");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração de Usuários - Sistema de Pagamento</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
    <?php include '../includes/navbar.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2">
                <div class="card">
                    <div class="card-body p-3">
                        <nav class="nav flex-column">
                            <a class="nav-link" href="../dashboard.php">
                                <i class="fas fa-tachometer-alt me-2"></i>Dashboard
                            </a>
                            <a class="nav-link active" href="usuarios.php">
                                <i class="fas fa-users me-2"></i>Usuários
                            </a>
                        </nav>
                    </div>
                </div>
            </div>
            
            <!-- Main Content Area -->
            <div class="col-md-9 col-lg-10">
                <div class="row mb-4">
                    <div class="col-12">
                        <h1 class="h3">Administração de Usuários</h1>
                    </div>
                </div>
                
                <?php if (isset($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Nível</th>
                                        <th>Credencial</th>
                                        <th>Status</th>
                                        <th>Links</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usuarios as $usuario): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                            <td><?php echo htmlspecialchars($usuario['email']); ?></td>
                                            <td>
                                                <span class="badge bg-<?php echo $usuario['nivel_acesso'] === 'admin' ? 'danger' : ($usuario['nivel_acesso'] === 'editor' ? 'warning' : 'info'); ?>">
                                                    <?php echo ucfirst($usuario['nivel_acesso']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $usuario['credencial_cielo'] === 'matriz' ? 'primary' : 'secondary'; ?>">
                                                    <?php echo ucfirst($usuario['credencial_cielo']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $usuario['ativo'] ? 'success' : 'danger'; ?>">
                                                    <?php echo $usuario['ativo'] ? 'Ativo' : 'Inativo'; ?>
                                                </span>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    Total: <?php echo $usuario['total_links']; ?><br>
                                                    Pagos: <?php echo $usuario['links_pagos']; ?>
                                                </small>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                        onclick="editUser(<?php echo htmlspecialchars(json_encode($usuario)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal de Edição -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Usuário</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="edit_user_id">
                        <input type="hidden" name="update_user" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" id="edit_nome" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="edit_email" disabled>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Nível de Acesso</label>
                            <select name="nivel_acesso" class="form-select" id="edit_nivel_acesso">
                                <option value="usuario">Usuário</option>
                                <option value="editor">Editor</option>
                                <option value="admin">Administrador</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Credencial Cielo</label>
                            <select name="credencial" class="form-select" id="edit_credencial">
                                <option value="matriz">Matriz</option>
                                <option value="filial">Filial</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="ativo" id="edit_ativo">
                                <label class="form-check-label">Usuário Ativo</label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_nome').value = user.nome;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_nivel_acesso').value = user.nivel_acesso;
            document.getElementById('edit_credencial').value = user.credencial_cielo;
            document.getElementById('edit_ativo').checked = user.ativo == 1;
            
            const modal = new bootstrap.Modal(document.getElementById('editUserModal'));
            modal.show();
        }
    </script>
</body>
</html> 