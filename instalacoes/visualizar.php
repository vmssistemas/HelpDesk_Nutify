<?php
date_default_timezone_set('America/Sao_Paulo');
require_once 'includes/header_instalacoes.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$instalacao_id = (int)$_GET['id'];
$instalacao = getInstalacaoById($instalacao_id);

if (!$instalacao) {
    header("Location: index.php");
    exit();
}

$checklist = getInstalacoesChecklist($instalacao_id);
$status_list = getInstalacoesStatus();
$equipe_list = getEquipe();
$comentarios = getInstalacaoComentarios($instalacao_id);
$agendamentos = getInstalacaoAgendamentos($instalacao_id);

// Processar atualização de status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status'])) {
    $status_id = (int)$_POST['status_id'];
    
    $query = "UPDATE instalacoes SET status_id = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $status_id, $instalacao_id);
    
    if ($stmt->execute()) {
        if ($status_id == 3) { // Concluído
            $conn->query("UPDATE instalacoes SET data_conclusao = NOW() WHERE id = $instalacao_id");
        }
        $_SESSION['success'] = "Status atualizado com sucesso!";
        header("Location: visualizar.php?id=$instalacao_id");
        exit();
    }
}

// Processar atualização de checklist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_checklist'])) {
    if (isset($_POST['checklist']) && is_array($_POST['checklist'])) {
        foreach ($_POST['checklist'] as $item_id => $data) {
            $dados = [
                'concluido' => isset($data['concluido']) ? 1 : 0,
                'horas' => (float)($data['horas'] ?? 0),
                'observacao' => trim($data['observacao'] ?? '')
            ];
            
            atualizarItemChecklist($instalacao_id, $item_id, $dados);
        }
        
        $_SESSION['success'] = "Checklist atualizado com sucesso!";
        header("Location: visualizar.php?id=$instalacao_id");
        exit();
    }
}

// Processar novo comentário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_comentario'])) {
    $comentario = trim($_POST['novo_comentario']);
    
    if (!empty($comentario)) {
        adicionarComentario($instalacao_id, $usuario_id, $comentario);
        $_SESSION['success'] = "Comentário adicionado com sucesso!";
        header("Location: visualizar.php?id=$instalacao_id");
        exit();
    }
}

// Processar novo agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_agendamento'])) {
    $data_agendada = $_POST['data_agendada'];
    $horas = (float)$_POST['horas'];
    $observacao = trim($_POST['observacao']);
    $usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : $usuario_id;
    
    if (!empty($data_agendada)) {
        if (adicionarAgendamento($instalacao_id, $data_agendada, $horas, $observacao, $usuario_id)) {
            $_SESSION['success'] = "Agendamento adicionado com sucesso!";
            header("Location: visualizar.php?id=$instalacao_id");
            exit();
        }
    }
}

// Processar atualização de status do agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status_agendamento'])) {
    $agendamento_id = (int)$_POST['agendamento_id'];
    $status = $_POST['status'];
    $motivo_cancelamento = ($status == 'cancelado') ? $_POST['motivo_cancelamento'] : null;
    
    if (atualizarStatusAgendamento($agendamento_id, $status, $motivo_cancelamento)) {
        $_SESSION['success'] = "Status do agendamento atualizado!";
        header("Location: visualizar.php?id=$instalacao_id");
        exit();
    }
}

// Processar exclusão de agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_agendamento'])) {
    $agendamento_id = (int)$_POST['agendamento_id'];
    
    if (excluirAgendamento($agendamento_id)) {
        $_SESSION['success'] = "Agendamento excluído com sucesso!";
        header("Location: visualizar.php?id=$instalacao_id");
        exit();
    }
}

// Processar edição de agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_agendamento'])) {
    $agendamento_id = (int)$_POST['agendamento_id'];
    $data_agendada = $_POST['data_agendada'];
    $horas = (float)$_POST['horas'];
    $observacao = trim($_POST['observacao']);
    $status = $_POST['status'];
    $motivo_cancelamento = ($status == 'cancelado') ? $_POST['motivo_cancelamento'] : null;
    $usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : null;
    
    if (atualizarAgendamento($agendamento_id, $data_agendada, $horas, $observacao, $status, $usuario_id, $motivo_cancelamento)) {
        $_SESSION['success'] = "Agendamento atualizado com sucesso!";
        header("Location: visualizar.php?id=$instalacao_id");
        exit();
    }
}
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Cabeçalho da Instalação -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Instalação #<?= $instalacao['id'] ?></h5>
                <div>
                    <a href="editar.php?id=<?= $instalacao['id'] ?>" class="btn btn-sm btn-outline-primary me-2">
                        <i class="material-icons me-1">edit</i> Editar
                    </a>
                    <span class="badge status-badge" style="background-color: <?= $instalacao['status_cor'] ?>">
                        <?= $instalacao['status_nome'] ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <h4 class="mb-3"><?= htmlspecialchars($instalacao['titulo']) ?></h4>
                
                <!-- Informações da Instalação -->
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <!-- Cliente -->
                    <?php if ($instalacao['cliente_nome']): ?>
                    <div>
                        <small class="text-muted">Cliente</small>
                        <div><?= htmlspecialchars(($instalacao['cliente_contrato'] ? $instalacao['cliente_contrato'] . ' - ' : '') . $instalacao['cliente_nome']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Plano -->
                    <?php if ($instalacao['plano_nome']): ?>
                    <div>
                        <small class="text-muted">Plano</small>
                        <div><?= htmlspecialchars($instalacao['plano_nome']) ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($instalacao['tipo_nome']) && $instalacao['tipo_nome']): ?>
                    <div>
                        <small class="text-muted">Tipo</small>
                        <div><?= htmlspecialchars($instalacao['tipo_nome']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Responsável -->
                    <?php if ($instalacao['responsavel_nome']): ?>
                    <div>
                        <small class="text-muted">Responsável</small>
                        <div>
                            <span class="avatar me-1"><?= substr($instalacao['responsavel_nome'], 0, 1) ?></span>
                            <?= htmlspecialchars($instalacao['responsavel_nome']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Data da Instalação -->
                    <?php if ($instalacao['data_instalacao']): ?>
                    <div>
                        <small class="text-muted">Data da Instalação</small>
                        <div><?= date('d/m/Y H:i', strtotime($instalacao['data_instalacao'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Data de Conclusão -->
                    <?php if ($instalacao['data_conclusao']): ?>
                    <div>
                        <small class="text-muted">Concluído em</small>
                        <div><?= date('d/m/Y H:i', strtotime($instalacao['data_conclusao'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Descrição -->
                <div class="mb-4">
                    <h6 class="border-bottom pb-2">Descrição</h6>
                    <div class="p-3 bg-light rounded">
                        <?= nl2br(htmlspecialchars($instalacao['descricao'])) ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Checklist Interativo -->
        <?php if (!empty($checklist)): ?>
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Checklist da Instalação</h5>
                <div class="fw-bold">
                    Total: <span id="totalHoras"><?= calcularTotalHorasChecklist($checklist) ?></span> horas
                </div>
            </div>
            <div class="card-body p-0">
                <form method="POST" id="checklistForm">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <?php foreach ($checklist as $item): ?>
                            <tr class="checklist-item align-middle <?= $item['concluido'] ? 'bg-light-success' : '' ?>">
                                <td width="30" class="ps-3">
                                    <input type="checkbox" name="checklist[<?= $item['id'] ?>][concluido]" 
                                           class="form-check-input checklist-item-toggle"
                                           id="checklist_<?= $item['id'] ?>" 
                                           value="1"
                                           <?= $item['concluido'] ? 'checked' : '' ?>>
                                </td>
                                <td>
                                    <label for="checklist_<?= $item['id'] ?>" class="form-check-label">
                                        <strong><?= htmlspecialchars($item['item']) ?></strong>
                                        <?php if ($item['item_descricao']): ?>
                                            <div class="text-muted small"><?= htmlspecialchars($item['item_descricao']) ?></div>
                                        <?php endif; ?>
                                    </label>
                                </td>
                                <td width="100" class="pe-2">
                                    <input type="number" step="0.25" class="form-control form-control-sm" 
                                           name="checklist[<?= $item['id'] ?>][horas]"
                                           value="<?= htmlspecialchars($item['horas'] ?? '0') ?>">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm" 
                                           name="checklist[<?= $item['id'] ?>][observacao]"
                                           value="<?= htmlspecialchars($item['observacao'] ?? '') ?>"
                                           placeholder="Observações">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="card-footer bg-transparent border-top-0 d-flex justify-content-end pe-3">
                        <button type="submit" name="update_checklist" class="btn btn-primary btn-sm">
                            <i class="material-icons me-1" style="font-size: 1rem;">save</i> Salvar Alterações
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Seção de Comentários -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Comentários da Instalação</h5>
            </div>
            <div class="card-body">
                <?php foreach ($comentarios as $comentario): ?>
                <div class="comment-card mb-3 p-3 bg-light rounded">
                    <div class="d-flex justify-content-between mb-2">
                        <strong><?= htmlspecialchars($comentario['usuario_nome']) ?></strong>
                        <small class="text-muted"><?= date('d/m/Y H:i', strtotime($comentario['data_criacao'])) ?></small>
                    </div>
                    <div><?= nl2br(htmlspecialchars($comentario['comentario'])) ?></div>
                </div>
                <?php endforeach; ?>
                
                <form method="POST" class="mt-4">
                    <div class="mb-3">
                        <label for="novo_comentario" class="form-label">Adicionar Comentário</label>
                        <textarea class="form-control" id="novo_comentario" name="novo_comentario" rows="3" required></textarea>
                    </div>
                    <button type="submit" name="adicionar_comentario" class="btn btn-primary">
                        <i class="material-icons me-1">send</i> Enviar Comentário
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Ações Rápidas -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Ações Rápidas</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="mb-3">
                    <div class="mb-3">
                        <label for="status_id" class="form-label">Alterar Status</label>
                        <select id="status_id" name="status_id" class="form-select" required>
                            <?php foreach ($status_list as $status): ?>
                                <option value="<?= $status['id'] ?>" <?= $status['id'] == $instalacao['status_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="atualizar_status" class="btn btn-primary w-100">
                        <i class="material-icons me-1">update</i> Atualizar
                    </button>
                </form>
                
                <div class="d-grid gap-2">
                    <?php if ($instalacao['status_id'] != 3): ?>
                    <a href="api/instalacoes.php?action=concluir&id=<?= $instalacao['id'] ?>" class="btn btn-success mb-2">
                        <i class="material-icons me-1">check</i> Marcar como Concluído
                    </a>
                    <?php endif; ?>
                    
                    <?php if ($instalacao['status_id'] != 4): ?>
                    <a href="api/instalacoes.php?action=cancelar&id=<?= $instalacao['id'] ?>" class="btn btn-danger mb-2">
                        <i class="material-icons me-1">cancel</i> Cancelar Instalação
                    </a>
                    <?php endif; ?>
                    
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
                        <i class="material-icons me-1">delete</i> Excluir Instalação
                    </button>
                </div>

                <!-- Modal de confirmação para exclusão -->
                <div class="modal fade" id="confirmDeleteModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Confirmar Exclusão</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Tem certeza que deseja excluir esta instalação permanentemente? Esta ação não pode ser desfeita.
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <a href="api/instalacoes.php?action=excluir&id=<?= $instalacao['id'] ?>" class="btn btn-danger">Confirmar Exclusão</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Histórico -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Histórico</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Criado em</small>
                            <small><?= date('d/m/Y H:i', strtotime($instalacao['data_criacao'])) ?></small>
                        </div>
                    </div>
                    <?php if ($instalacao['data_atualizacao']): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Última atualização</small>
                            <small><?= date('d/m/Y H:i', strtotime($instalacao['data_atualizacao'])) ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($instalacao['data_conclusao']): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Concluído em</small>
                            <small><?= date('d/m/Y H:i', strtotime($instalacao['data_conclusao'])) ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Seção de Agendamentos -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Agenda de Instalações</h5>
            </div>
            <div class="card-body">
                <!-- Formulário para novo agendamento -->
                <form method="POST" class="mb-4">
                    <h6 class="border-bottom pb-2">Novo Agendamento</h6>
                    <div class="row g-2">
                        <div class="col-md-5">
                            <label for="data_agendada" class="form-label">Data e Hora</label>
                            <input type="datetime-local" class="form-control" id="data_agendada" name="data_agendada" required>
                        </div>
                        <div class="col-md-3">
                            <label for="horas" class="form-label">Horas</label>
                            <input type="number" step="0.25" class="form-control" id="horas" name="horas" min="0" value="1">
                        </div>
                        <div class="col-md-4">
                            <label for="usuario_id" class="form-label">Responsável</label>
                            <select class="form-select" id="usuario_id" name="usuario_id">
                                <?php foreach ($equipe_list as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>" <?= $usuario['id'] == $usuario_id ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($usuario['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="observacao" class="form-label">Observação</label>
                            <input type="text" class="form-control" id="observacao" name="observacao">
                        </div>
                    </div>
                    <div class="d-grid mt-2">
                        <button type="submit" name="adicionar_agendamento" class="btn btn-primary btn-sm">
                            <i class="material-icons me-1" style="font-size:1rem;">add</i> Adicionar Agendamento
                        </button>
                    </div>
                </form>

                <!-- Lista de Agendamentos -->
                <h6 class="border-bottom pb-2">Agendamentos Existentes</h6>
                <div class="list-group">
                    <?php foreach ($agendamentos as $agendamento): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold">
                                    <?= date('d/m/Y H:i', strtotime($agendamento['data_agendada'])) ?>
                                    <span class="badge bg-<?= 
                                        $agendamento['status'] == 'realizado' ? 'success' : 
                                        ($agendamento['status'] == 'cancelado' ? 'danger' : 'primary') 
                                    ?> ms-2">
                                        <?= ucfirst($agendamento['status']) ?>
                                    </span>
                                </div>
                                <div class="small">
                                    <?= $agendamento['horas'] ?> horas
                                    <?php if ($agendamento['usuario_nome']): ?>
                                        - Responsável: <?= htmlspecialchars($agendamento['usuario_nome']) ?>
                                    <?php endif; ?>
                                    <?php if ($agendamento['observacao']): ?>
                                        - <?= htmlspecialchars($agendamento['observacao']) ?>
                                    <?php endif; ?>
                                    <?php if ($agendamento['status'] == 'cancelado' && $agendamento['motivo_cancelamento']): ?>
                                        - Motivo: <?= htmlspecialchars($agendamento['motivo_cancelamento']) ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-sm btn-outline-primary btn-editar-agendamento" 
                                        data-id="<?= $agendamento['id'] ?>" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editarAgendamentoModal"
                                        title="Editar">
                                    <i class="material-icons" style="font-size:1rem;">edit</i>
                                </button>
                                <?php if ($agendamento['status'] == 'agendado'): ?>
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="agendamento_id" value="<?= $agendamento['id'] ?>">
                                        <input type="hidden" name="status" value="realizado">
                                        <button type="submit" name="atualizar_status_agendamento" class="btn btn-sm btn-outline-success" title="Marcar como realizado">
                                            <i class="material-icons" style="font-size:1rem;">check</i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-sm btn-outline-danger btn-cancelar-agendamento" 
                                            data-id="<?= $agendamento['id'] ?>" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#motivoCancelamentoModal"
                                            title="Cancelar">
                                        <i class="material-icons" style="font-size:1rem;">close</i>
                                    </button>
                                <?php endif; ?>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Tem certeza que deseja excluir este agendamento?');">
                                    <input type="hidden" name="agendamento_id" value="<?= $agendamento['id'] ?>">
                                    <button type="submit" name="excluir_agendamento" class="btn btn-sm btn-outline-danger" title="Excluir">
                                        <i class="material-icons" style="font-size:1rem;">delete</i>
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($agendamentos)): ?>
                        <div class="list-group-item text-muted text-center py-3">
                            Nenhum agendamento encontrado
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Motivo do Cancelamento -->
<div class="modal fade" id="motivoCancelamentoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="formCancelarAgendamento">
                <div class="modal-header">
                    <h5 class="modal-title">Motivo do Cancelamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="agendamento_id" id="cancel_agendamento_id">
                    <input type="hidden" name="status" value="cancelado">
                    <div class="mb-3">
                        <label class="form-label">Selecione o motivo:</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="motivo_cancelamento" id="motivo_cliente" value="Não efetuado cliente" required>
                            <label class="form-check-label" for="motivo_cliente">Não efetuado cliente</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="motivo_cancelamento" id="motivo_nutify" value="Não efetuado Nutify">
                            <label class="form-check-label" for="motivo_nutify">Não efetuado Nutify</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="atualizar_status_agendamento" class="btn btn-danger">Confirmar Cancelamento</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de Edição de Agendamento -->
<div class="modal fade" id="editarAgendamentoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <div class="modal-header">
                    <h5 class="modal-title">Editar Agendamento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="agendamento_id" id="edit_agendamento_id">
                    <div class="mb-3">
                        <label for="edit_data_agendada" class="form-label">Data e Hora</label>
                        <input type="datetime-local" class="form-control" id="edit_data_agendada" name="data_agendada" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_horas" class="form-label">Horas</label>
                        <input type="number" step="0.25" class="form-control" id="edit_horas" name="horas" min="0" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit_motivo_cancelamento" class="form-label">Motivo do Cancelamento</label>
                        <select class="form-select" id="edit_motivo_cancelamento" name="motivo_cancelamento" disabled>
                            <option value="">-- Selecione --</option>
                            <option value="Não efetuado cliente">Não efetuado cliente</option>
                            <option value="Não efetuado Nutify">Não efetuado Nutify</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_observacao" class="form-label">Observação</label>
                        <input type="text" class="form-control" id="edit_observacao" name="observacao">
                    </div>
                    <div class="mb-3">
                        <label for="edit_usuario_id" class="form-label">Responsável</label>
                        <select class="form-select" id="edit_usuario_id" name="usuario_id" required>
                            <?php foreach ($equipe_list as $usuario): ?>
                                <option value="<?= $usuario['id'] ?>"><?= htmlspecialchars($usuario['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="edit_status" class="form-label">Status</label>
                        <select class="form-select" id="edit_status" name="status" required>
                            <option value="agendado">Agendado</option>
                            <option value="realizado">Realizado</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" name="editar_agendamento" class="btn btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Função para calcular total de horas
function calcularTotalHoras() {
    let total = 0;
    document.querySelectorAll('input[name^="checklist"][name$="[horas]"]').forEach(input => {
        total += parseFloat(input.value) || 0;
    });
    const totalElement = document.getElementById('totalHoras');
    if (totalElement) {
        totalElement.textContent = total.toFixed(2);
    }
}

// Função para configurar listeners do checklist
function setupChecklistListeners() {
    // Atualização visual apenas
    document.querySelectorAll('.checklist-item-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const itemDiv = this.closest('.checklist-item');
            if (itemDiv) {
                if (this.checked) {
                    itemDiv.classList.add('bg-light-success');
                    itemDiv.classList.remove('bg-light');
                } else {
                    itemDiv.classList.remove('bg-light-success');
                    itemDiv.classList.add('bg-light');
                }
            }
        });
    });

    // Atualizar total quando horas são alteradas
    document.querySelectorAll('input[name^="checklist"][name$="[horas]"]').forEach(input => {
        input.addEventListener('change', calcularTotalHoras);
        input.addEventListener('input', calcularTotalHoras);
    });
}

// Função para configurar agendamentos
function setupAgendamentos() {
    // Configurar data padrão para AGORA
    const now = new Date();
    const offset = now.getTimezoneOffset() * 60000;
    const localISOTime = new Date(now - offset).toISOString().slice(0, 16);
    const dataAgendadaInput = document.getElementById('data_agendada');
    if (dataAgendadaInput) {
        dataAgendadaInput.value = localISOTime;
    }

    // Configurar os botões de edição
    document.querySelectorAll('.btn-editar-agendamento').forEach(btn => {
        btn.addEventListener('click', function() {
            const agendamentoId = this.getAttribute('data-id');
            
            // Buscar os dados do agendamento
            fetch(`api/instalacoes.php?action=get_agendamento&id=${agendamentoId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.agendamento) {
                        const agendamento = data.agendamento;
                        
                        // Preencher o modal
                        const editAgendamentoId = document.getElementById('edit_agendamento_id');
                        const editUsuarioId = document.getElementById('edit_usuario_id');
                        const editDataAgendada = document.getElementById('edit_data_agendada');
                        const editHoras = document.getElementById('edit_horas');
                        const editObservacao = document.getElementById('edit_observacao');
                        const editStatus = document.getElementById('edit_status');
                        
                        if (editAgendamentoId) editAgendamentoId.value = agendamento.id;
                        if (editUsuarioId) editUsuarioId.value = agendamento.usuario_id || <?= $usuario_id ?>;
                        if (editDataAgendada) editDataAgendada.value = agendamento.data_agendada.slice(0, 16);
                        if (editHoras) editHoras.value = agendamento.horas;
                        if (editObservacao) editObservacao.value = agendamento.observacao || '';
                        if (editStatus) editStatus.value = agendamento.status;
                    }
                });
        });
    });

    // Configurar o modal de motivo de cancelamento
    document.querySelectorAll('.btn-cancelar-agendamento').forEach(btn => {
        btn.addEventListener('click', function() {
            const agendamentoId = this.getAttribute('data-id');
            const cancelAgendamentoId = document.getElementById('cancel_agendamento_id');
            if (cancelAgendamentoId) {
                cancelAgendamentoId.value = agendamentoId;
            }
        });
    });

    // Habilitar/desabilitar motivo de cancelamento conforme status
    const editStatus = document.getElementById('edit_status');
    const editMotivoCancelamento = document.getElementById('edit_motivo_cancelamento');
    
    if (editStatus && editMotivoCancelamento) {
        editStatus.addEventListener('change', function() {
            if (this.value === 'cancelado') {
                editMotivoCancelamento.disabled = false;
                editMotivoCancelamento.required = true;
            } else {
                editMotivoCancelamento.disabled = true;
                editMotivoCancelamento.required = false;
            }
        });
    }
}

// Configurações gerais quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', function() {
    setupChecklistListeners();
    calcularTotalHoras(); // Calcular total inicial
    setupAgendamentos();
    
    // Limpar o modal quando fechado
    const motivoCancelamentoModal = document.getElementById('motivoCancelamentoModal');
    if (motivoCancelamentoModal) {
        motivoCancelamentoModal.addEventListener('hidden.bs.modal', function() {
            const form = document.getElementById('formCancelarAgendamento');
            if (form) form.reset();
        });
    }

    // Adicionar confirmação para ações importantes
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.onsubmit = function() {
            return confirm('Tem certeza que deseja executar esta ação?');
        };
    });
});
</script>

<?php require_once 'includes/footer_instalacoes.php'; ?>