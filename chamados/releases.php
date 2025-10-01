<?php
require_once 'includes/header.php';

// Ações - verifica tanto GET quanto POST
$action = $_POST['action'] ?? $_GET['action'] ?? '';
$release_id = $_POST['id'] ?? $_GET['id'] ?? 0;

// Processar formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $data_planejada = $_POST['data_planejada'] ?: null;
    $data_lancamento = $_POST['data_lancamento'] ?: null;
    $status = $_POST['status'];
    $cor = $_POST['cor'];
    
    if ($action === 'edit' && $release_id) {
        // Verificar se a release existe antes de editar
        $check_query = "SELECT id FROM chamados_releases WHERE id = ?";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bind_param("i", $release_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows === 1) {
            // Editar release existente
            $query = "UPDATE chamados_releases SET 
                      nome = ?, descricao = ?, data_planejada = ?, data_lancamento = ?, 
                      status = ?, cor = ?
                      WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ssssssi", $nome, $descricao, $data_planejada, $data_lancamento, $status, $cor, $release_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Release atualizada com sucesso!";
                header("Location: releases.php");
                exit();
            } else {
                $error = "Erro ao atualizar release: " . $conn->error;
            }
        } else {
            $_SESSION['error'] = "Release não encontrada!";
            header("Location: releases.php");
            exit();
        }
    } else {
        // Criar nova release
        $query = "INSERT INTO chamados_releases 
                  (nome, descricao, data_planejada, data_lancamento, status, cor)
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssss", $nome, $descricao, $data_planejada, $data_lancamento, $status, $cor);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Release criada com sucesso!";
            header("Location: releases.php");
            exit();
        } else {
            $error = "Erro ao criar release: " . $conn->error;
        }
    }
}

// Excluir release
if ($action === 'delete' && $release_id) {
    // Verificar se há chamados vinculados
    $query = "SELECT COUNT(*) as total FROM chamados WHERE release_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $release_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $count = $result->fetch_assoc()['total'];
    
    if ($count > 0) {
        $_SESSION['error'] = "Não é possível excluir esta release pois existem chamados vinculados a ela.";
    } else {
        $query = "DELETE FROM chamados_releases WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $release_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Release excluída com sucesso!";
        } else {
            $_SESSION['error'] = "Erro ao excluir release: " . $conn->error;
        }
    }
    
    header("Location: releases.php");
    exit();
}

// Buscar releases
$query = "SELECT * FROM chamados_releases ORDER BY data_planejada DESC";
$result = $conn->query($query);
$releases = $result->fetch_all(MYSQLI_ASSOC);

// Buscar release específica para edição
$release_edit = null;
if ($action === 'edit' && $release_id) {
    $query = "SELECT * FROM chamados_releases WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $release_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $release_edit = $result->fetch_assoc();
    
    if (!$release_edit) {
        $_SESSION['error'] = "Release não encontrada!";
        header("Location: releases.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gerenciar Releases</title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
</head>
<body>
    <div class="container mb-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="material-icons me-2">rocket</i> Gerenciar Releases</h5>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReleaseModal">
                    <i class="material-icons me-1">add</i> Nova Release
                </button>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Nome</th>
                                <th>Status</th>
                                <th>Data Planejada</th>
                                <th>Data Lançamento</th>
                                <th>Chamados Vinculados</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($releases)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">Nenhuma release cadastrada</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($releases as $release): ?>
                                    <tr>
                                        <td>
                                            <span class="badge" style="background-color: <?= $release['cor'] ?>">
                                                <?= htmlspecialchars($release['nome']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= ucfirst(str_replace('_', ' ', $release['status'])) ?>
                                        </td>
                                        <td><?= $release['data_planejada'] ? date('d/m/Y', strtotime($release['data_planejada'])) : '-' ?></td>
                                        <td><?= $release['data_lancamento'] ? date('d/m/Y', strtotime($release['data_lancamento'])) : '-' ?></td>
                                        <td>
                                            <?php
                                                $query = "SELECT COUNT(*) as total FROM chamados WHERE release_id = ?";
                                                $stmt = $conn->prepare($query);
                                                $stmt->bind_param("i", $release['id']);
                                                $stmt->execute();
                                                $result = $stmt->get_result();
                                                $count = $result->fetch_assoc()['total'];
                                                echo $count > 0 ? $count : '-';
                                            ?>
                                        </td>
                                        <td>
                                            <a href="releases.php?action=edit&id=<?= $release['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                                <i class="material-icons">edit</i>
                                            </a>
                                            <a href="releases.php?action=delete&id=<?= $release['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir esta release?')">
                                                <i class="material-icons">delete</i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para NOVA release -->
    <div class="modal fade" id="newReleaseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Release</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="releases.php">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="newNome" class="form-label">Nome *</label>
                            <input type="text" class="form-control" id="newNome" name="nome" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="newDescricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="newDescricao" name="descricao" rows="3"></textarea>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="newDataPlanejada" class="form-label">Data Planejada</label>
                                <input type="date" class="form-control" id="newDataPlanejada" name="data_planejada">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="newDataLancamento" class="form-label">Data Lançamento</label>
                                <input type="date" class="form-control" id="newDataLancamento" name="data_lancamento">
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label for="newStatus" class="form-label">Status</label>
                                <select id="newStatus" name="status" class="form-select" required>
                                    <option value="planejado" selected>Planejado</option>
                                    <option value="em_desenvolvimento">Em Desenvolvimento</option>
                                    <option value="teste">Teste</option>
                                    <option value="lançado">Lançado</option>
                                    <option value="cancelado">Cancelado</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="newCor" class="form-label">Cor</label>
                                <input type="color" class="form-control form-control-color" id="newCor" name="cor" 
                                       value="#6c757d" title="Escolha uma cor">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Criar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if ($action === 'edit' && $release_edit): ?>
    <!-- Modal para EDITAR release (apenas quando em modo de edição) -->
    <div class="modal fade show" id="editReleaseModal" tabindex="-1" aria-hidden="false" style="display: block; padding-right: 15px;">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Release</h5>
                    <a href="releases.php" class="btn-close"></a>
                </div>
                <form method="POST" action="releases.php">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $release_edit['id'] ?>">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="editNome" class="form-label">Nome *</label>
                            <input type="text" class="form-control" id="editNome" name="nome" 
                                   value="<?= htmlspecialchars($release_edit['nome']) ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="editDescricao" class="form-label">Descrição</label>
                            <textarea class="form-control" id="editDescricao" name="descricao" rows="3"><?= htmlspecialchars($release_edit['descricao']) ?></textarea>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="editDataPlanejada" class="form-label">Data Planejada</label>
                                <input type="date" class="form-control" id="editDataPlanejada" name="data_planejada" 
                                       value="<?= $release_edit['data_planejada'] ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="editDataLancamento" class="form-label">Data Lançamento</label>
                                <input type="date" class="form-control" id="editDataLancamento" name="data_lancamento" 
                                       value="<?= $release_edit['data_lancamento'] ?>">
                            </div>
                        </div>
                        
                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label for="editStatus" class="form-label">Status</label>
                                <select id="editStatus" name="status" class="form-select" required>
                                    <option value="planejado" <?= $release_edit['status'] === 'planejado' ? 'selected' : '' ?>>Planejado</option>
                                    <option value="em_desenvolvimento" <?= $release_edit['status'] === 'em_desenvolvimento' ? 'selected' : '' ?>>Em Desenvolvimento</option>
                                    <option value="teste" <?= $release_edit['status'] === 'teste' ? 'selected' : '' ?>>Teste</option>
                                    <option value="lançado" <?= $release_edit['status'] === 'lançado' ? 'selected' : '' ?>>Lançado</option>
                                    <option value="cancelado" <?= $release_edit['status'] === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="editCor" class="form-label">Cor</label>
                                <input type="color" class="form-control form-control-color" id="editCor" name="cor" 
                                       value="<?= $release_edit['cor'] ?>" title="Escolha uma cor">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <a href="releases.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="modal-backdrop fade show"></div>
    <?php endif; ?>

    <?php 
    // Inclui o footer com scripts condicionais
    require_once 'includes/footer.php'; 
    ?>
</body>
</html>