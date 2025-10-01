<?php
date_default_timezone_set('America/Sao_Paulo'); // Define o fuso horário para Brasília
require_once 'includes/header_treinamentos.php';

// Verifica se é uma requisição para manter a sessão
if (isset($_GET['manter_sessao']) && $_GET['manter_sessao'] == 1) {
    // Apenas renova a sessão e retorna resposta simples
    echo 'OK';
    exit();
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$treinamento_id = (int)$_GET['id'];
$treinamento = getTreinamentoById($treinamento_id);

if (!$treinamento) {
    header("Location: index.php");
    exit();
}

$checklist = getTreinamentosChecklist($treinamento_id);
$status_list = getTreinamentosStatus();
$equipe_list = getEquipe();
$comentarios = getTreinamentoComentarios($treinamento_id, $usuario_id);
$agendamentos = getTreinamentoAgendamentos($treinamento_id);
$totais_horas = getTotalHorasPorStatus($treinamento_id);

// Processar atualização de status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['atualizar_status'])) {
    $status_id = (int)$_POST['status_id'];
    
    $query = "UPDATE treinamentos SET status_id = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $status_id, $treinamento_id);
    
    if ($stmt->execute()) {
        if ($status_id == 3) { // Concluído
             $conn->query("UPDATE treinamentos SET data_conclusao = NOW() WHERE id = $treinamento_id");
         }
        $_SESSION['success'] = "Status atualizado com sucesso!";
        header("Location: visualizar.php?id=$treinamento_id");
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
            
            atualizarItemChecklist($treinamento_id, $item_id, $dados);
        }
        
        $_SESSION['success'] = "Checklist atualizado com sucesso!";
        header("Location: visualizar.php?id=$treinamento_id");
        exit();
    }
}

// Processar novo comentário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_comentario'])) {
    $comentario = trim($_POST['novo_comentario']);
    
    if (!empty($comentario)) {
        adicionarComentario($treinamento_id, $usuario_id, $comentario);
        $_SESSION['success'] = "Comentário adicionado com sucesso!";
        header("Location: visualizar.php?id=$treinamento_id");
        exit();
    }
}
// Processar novo agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_agendamento'])) {
    $data_agendada = $_POST['data_agendada'];
    $horas = $_POST['horas'] ?? '01:00';
    $observacao = trim($_POST['observacao'] ?? '');
    $usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : $usuario_id;
    
    // Validar formato de horas
    if (!preg_match('/^([0-9]|0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$/', $horas)) {
        $_SESSION['error'] = "Formato de horas inválido. Use HH:MM (ex: 01:30)";
        header("Location: visualizar.php?id=$treinamento_id");
        exit();
    }
    
    if (!empty($data_agendada)) {
        if (adicionarAgendamento($treinamento_id, $data_agendada, $horas, $observacao, $usuario_id)) {
            $_SESSION['success'] = "Agendamento adicionado com sucesso!";
            header("Location: visualizar.php?id=$treinamento_id");
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
        header("Location: visualizar.php?id=$treinamento_id");
        exit();
    }
}

// Processar exclusão de agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['excluir_agendamento'])) {
    $agendamento_id = (int)$_POST['agendamento_id'];
    
    if (excluirAgendamento($agendamento_id)) {
        $_SESSION['success'] = "Agendamento excluído com sucesso!";
        header("Location: visualizar.php?id=$treinamento_id");
        exit();
    }
}
// visualizar.php - Adicionar junto com os outros processamentos de POST

// Processar edição de agendamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editar_agendamento'])) {
    $agendamento_id = (int)$_POST['agendamento_id'];
    $data_agendada = $_POST['data_agendada'];
    $horas = $_POST['horas'] ?? '01:00';
    $observacao = trim($_POST['observacao'] ?? '');
    $status = $_POST['status'] ?? 'agendado';
    $motivo_cancelamento = ($status == 'cancelado') ? ($_POST['motivo_cancelamento'] ?? null) : null;
    $usuario_id = isset($_POST['usuario_id']) ? (int)$_POST['usuario_id'] : null;
    
    if (atualizarAgendamento($agendamento_id, $data_agendada, $horas, $observacao, $status, $usuario_id, $motivo_cancelamento)) {
        $_SESSION['success'] = "Agendamento atualizado com sucesso!";
        header("Location: visualizar.php?id=$treinamento_id");
        exit();
    }
}
?>

<div class="row">
    <div class="col-lg-8">
        <!-- Cabeçalho do Treinamento -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Treinamento #<?= $treinamento['id'] ?></h5>
                <div>
                    <a href="editar.php?id=<?= $treinamento['id'] ?>" class="btn btn-sm btn-outline-primary me-2">
                        <i class="material-icons me-1">edit</i> Editar
                    </a>
                    <span class="badge status-badge" style="background-color: <?= $treinamento['status_cor'] ?>">
                        <?= $treinamento['status_nome'] ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <h4 class="mb-3"><?= htmlspecialchars($treinamento['titulo']) ?></h4>
                
                <!-- Informações do Treinamento -->
                <div class="d-flex flex-wrap gap-3 mb-4">
                    <!-- Cliente -->
                    <?php if ($treinamento['cliente_nome']): ?>
                    <div>
                        <small class="text-muted">Cliente</small>
                        <div><?= htmlspecialchars(($treinamento['cliente_contrato'] ? $treinamento['cliente_contrato'] . ' - ' : '') . $treinamento['cliente_nome']) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Plano -->
                    <?php if ($treinamento['plano_nome']): ?>
                    <div>
                        <small class="text-muted">Plano</small>
                        <div><?= htmlspecialchars($treinamento['plano_nome']) ?></div>
                    </div>
                    <?php endif; ?>

<?php if (isset($treinamento['tipo_nome']) && $treinamento['tipo_nome']): ?>
<div>
    <small class="text-muted">Tipo</small>
    <div><?= htmlspecialchars($treinamento['tipo_nome']) ?></div>
</div>
<?php endif; ?>
                    
                    <!-- Responsável -->
                    <?php if ($treinamento['responsavel_nome']): ?>
                    <div>
                        <small class="text-muted">Responsável</small>
                        <div>
                            <span class="avatar me-1"><?= substr($treinamento['responsavel_nome'], 0, 1) ?></span>
                            <?= htmlspecialchars($treinamento['responsavel_nome']) ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Data do Treinamento -->
                    <?php if ($treinamento['data_treinamento']): ?>
                    <div>
                        <small class="text-muted">Data do Treinamento</small>
                        <div><?= date('d/m/Y H:i', strtotime($treinamento['data_treinamento'])) ?></div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Data de Conclusão -->
                    <?php if ($treinamento['data_conclusao']): ?>
                    <div>
                        <small class="text-muted">Concluído em</small>
                        <div><?= date('d/m/Y H:i', strtotime($treinamento['data_conclusao'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Descrição -->
                <div class="mb-4">
                    <h6 class="border-bottom pb-2">Descrição</h6>
                    <div class="p-3 bg-light rounded">
                        <div class="descricao-content"><?= $treinamento['descricao'] ?></div>
                    </div>
                </div>
            </div>
        </div>
        
<!-- Checklist Interativo -->
<?php if (!empty($checklist)): ?>
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Checklist do Treinamento</h5>
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
        <h5 class="mb-0">Comentários do Treinamento</h5>
    </div>
            <div class="card-body">
                <?php foreach ($comentarios as $comentario): ?>
                <div class="comment-card mb-3 p-3 bg-light rounded" id="comment-<?= $comentario['id'] ?>">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <strong><?= htmlspecialchars($comentario['usuario_nome']) ?></strong>
                            <small class="text-muted ms-2">
                                <?= date('d/m/Y H:i', strtotime($comentario['data_criacao'])) ?>
                                <?php if ($comentario['data_atualizacao'] && $comentario['data_atualizacao'] != $comentario['data_criacao']): ?>
                                    <span class="badge bg-secondary ms-1">Editado</span>
                                <?php endif; ?>
                            </small>
                        </div>
                        <?php if ($comentario['pode_editar']): ?>
                        <div class="dropdown">
                             <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                 <i class="material-icons" style="font-size: 16px;">more_vert</i>
                             </button>
                             <ul class="dropdown-menu">
                                 <li><a class="dropdown-item comment-edit-btn" href="#" onclick="editComment(<?= $comentario['id'] ?>)">
                                     <i class="material-icons me-2" style="font-size: 16px;">edit</i>Editar
                                 </a></li>
                                 <li><a class="dropdown-item comment-delete-btn" href="#" onclick="deleteComment(<?= $comentario['id'] ?>)">
                                     <i class="material-icons me-2" style="font-size: 16px;">delete</i>Excluir
                                 </a></li>
                             </ul>
                         </div>
                        <?php endif; ?>
                    </div>
                    <div class="comment-content"><?= $comentario['comentario'] ?></div>
                    
                    <!-- Formulário de edição inline (inicialmente oculto) -->
                    <?php if ($comentario['pode_editar']): ?>
                    <div class="edit-form" id="edit-form-<?= $comentario['id'] ?>" style="display: none;">
                        <form method="POST" action="api/comentarios.php">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="comment_id" value="<?= $comentario['id'] ?>">
                            <input type="hidden" name="treinamento_id" value="<?= $treinamento_id ?>">
                            <textarea name="comentario" style="display: none;"><?= htmlspecialchars($comentario['comentario']) ?></textarea>
                            <div class="editor-edit-comment" data-comment-id="<?= $comentario['id'] ?>"><?= $comentario['comentario'] ?></div>
                            <div class="mt-2">
                                <button type="submit" class="btn btn-sm btn-success me-2">Salvar</button>
                                <button type="button" class="btn btn-sm btn-secondary" onclick="cancelEdit(<?= $comentario['id'] ?>)">Cancelar</button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($comentarios)): ?>
                    <div class="text-muted text-center py-3">
                        Nenhum comentário encontrado
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="mt-4">
                    <div class="mb-3">
                        <label for="novo_comentario" class="form-label">Adicionar Comentário</label>
                        <textarea class="form-control" id="novo_comentario" name="novo_comentario" rows="3" required style="display: none;"></textarea>
                        <div id="editorComentario"></div>
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
                                <option value="<?= $status['id'] ?>" <?= $status['id'] == $treinamento['status_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($status['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" name="atualizar_status" class="btn btn-primary w-100">
                        <i class="material-icons me-1">update</i> Atualizar
                    </button>
                </form>
                
               <!-- Dentro do card "Ações Rápidas" -->
<div class="d-grid gap-2">
    <?php if ($treinamento['status_id'] != 3): ?>
    <a href="api/treinamentos.php?action=concluir&id=<?= $treinamento['id'] ?>" class="btn btn-success mb-2">
        <i class="material-icons me-1">check</i> Marcar como Concluído
    </a>
    <?php endif; ?>
    
    <?php if ($treinamento['status_id'] != 4): ?>
    <a href="api/treinamentos.php?action=cancelar&id=<?= $treinamento['id'] ?>" class="btn btn-danger mb-2">
        <i class="material-icons me-1">cancel</i> Cancelar Treinamento
    </a>
    <?php endif; ?>
    
    <!-- Novo botão para excluir -->
    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#confirmDeleteModal">
        <i class="material-icons me-1">delete</i> Excluir Treinamento
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
                Tem certeza que deseja excluir este treinamento permanentemente? Esta ação não pode ser desfeita.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <a href="api/treinamentos.php?action=excluir&id=<?= $treinamento['id'] ?>" class="btn btn-danger">Confirmar Exclusão</a>
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
            </div>
        </div>
        
        <!-- Histórico -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Histórico</h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Criado em</small>
                            <small><?= date('d/m/Y H:i', strtotime($treinamento['data_criacao'])) ?></small>
                        </div>
                    </div>
                    <?php if ($treinamento['data_atualizacao']): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Última atualização</small>
                            <small><?= date('d/m/Y H:i', strtotime($treinamento['data_atualizacao'])) ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if ($treinamento['data_conclusao']): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <small class="text-muted">Concluído em</small>
                            <small><?= date('d/m/Y H:i', strtotime($treinamento['data_conclusao'])) ?></small>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                </div>
                
            </div>
            
        </div>
      <!-- Seção de Agendamentos - Modificada -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Agenda de Treinamentos - <?= htmlspecialchars($treinamento['cliente_nome']) ?></h5>
        <small class="text-muted">Todos os agendamentos para este cliente</small>
    </div>
    <div class="card-body">
<div class="row mb-4">
    <div class="col-12">
        <h6 class="border-bottom pb-2">Total de Horas</h6>
        <div class="status-container-wrapper">
            <div class="d-flex justify-content-around align-items-center status-indicators-container">
                <!-- Agendado -->
                <div class="status-indicator text-center" data-bs-toggle="tooltip" title="Agendado: <?php 
                    $total_agendado = isset($totais_horas['agendado']) ? 
                        formatarTimeParaExibicao($totais_horas['agendado']['total_horas']) : '00:00';
                    $count_agendado = isset($totais_horas['agendado']) ? 
                        $totais_horas['agendado']['total_agendamentos'] : 0;
                    echo "{$total_agendado} horas ({$count_agendado} agendamentos)";
                ?>">
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge-dot bg-primary mb-1"></span>
                        <small class="fw-bold"><?= $total_agendado ?></small>
                    </div>
                </div>
                
                <!-- Realizado -->
                <div class="status-indicator text-center" data-bs-toggle="tooltip" title="Realizado: <?php 
                    $total_realizado = isset($totais_horas['realizado']) ? 
                        formatarTimeParaExibicao($totais_horas['realizado']['total_horas']) : '00:00';
                    $count_realizado = isset($totais_horas['realizado']) ? 
                        $totais_horas['realizado']['total_agendamentos'] : 0;
                    echo "{$total_realizado} horas ({$count_realizado} agendamentos)";
                ?>">
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge-dot bg-success mb-1"></span>
                        <small class="fw-bold"><?= $total_realizado ?></small>
                    </div>
                </div>
                
                <!-- Cancelado -->
                <div class="status-indicator text-center" data-bs-toggle="tooltip" title="Cancelado: <?php 
                    $total_cancelado = isset($totais_horas['cancelado']) ? 
                        formatarTimeParaExibicao($totais_horas['cancelado']['total_horas']) : '00:00';
                    $count_cancelado = isset($totais_horas['cancelado']) ? 
                        $totais_horas['cancelado']['total_agendamentos'] : 0;
                    echo "{$total_cancelado} horas ({$count_cancelado} agendamentos)";
                ?>">
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge-dot bg-danger mb-1"></span>
                        <small class="fw-bold"><?= $total_cancelado ?></small>
                    </div>
                </div>
                
                <!-- Total Geral -->
                <div class="status-indicator text-center" data-bs-toggle="tooltip" title="Total Geral: <?php
                    $total_geral = '00:00';
                    $count_geral = 0;
                    if (!empty($totais_horas)) {
                        $total_segundos = 0;
                        foreach ($totais_horas as $status => $dados) {
                            if ($dados['total_horas']) {
                                list($h, $m, $s) = explode(':', $dados['total_horas']);
                                $total_segundos += ($h * 3600) + ($m * 60) + $s;
                            }
                            $count_geral += $dados['total_agendamentos'];
                        }
                        $total_geral = segundosParaHoraMinuto($total_segundos);
                    }
                    echo "{$total_geral} horas ({$count_geral} agendamentos)";
                ?>">
                    <div class="d-flex flex-column align-items-center">
                        <span class="badge-dot bg-dark mb-1"></span>
                        <small class="fw-bold"><?= $total_geral ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
        <!-- Formulário para novo agendamento (movido para cima) -->
        <!-- 
        <form method="POST" class="mb-4">
            <h6 class="border-bottom pb-2">Novo Agendamento</h6>
            <div class="row g-2">
                <div class="col-md-5">
                    <label for="data_agendada" class="form-label">Data e Hora</label>
                    <input type="datetime-local" class="form-control" id="data_agendada" name="data_agendada" required>
                </div>
<div class="col-md-3">
    <label for="horas" class="form-label">Duração (HH:MM)</label>
    <input type="text" class="form-control time-input" id="horas" name="horas" 
           placeholder="HH:MM" value="01:00" required>
    <div class="invalid-feedback"></div>
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
        --> 

        <!-- Lista de Agendamentos (agora em ordem decrescente) -->
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
    <?= $agendamento['horas_formatadas'] ?> horas
    <?php if ($agendamento['usuario_nome']): ?>
        - Responsável: <?= htmlspecialchars($agendamento['usuario_nome']) ?>
    <?php endif; ?>
    <?php if ($agendamento['treinamento_origem_id'] != $treinamento_id && $agendamento['treinamento_origem_id'] > 0): ?>
        - <span class="badge bg-info">Treinamento #<?= $agendamento['treinamento_origem_id'] ?></span>
    <?php elseif ($agendamento['treinamento_origem_id'] == 0): ?>
        - <span class="badge bg-warning">Agenda Geral</span>
    <?php endif; ?>
    <?php if ($agendamento['observacao']): ?>
        - <?= htmlspecialchars($agendamento['observacao']) ?>
    <?php endif; ?>
    <?php if ($agendamento['status'] == 'cancelado' && $agendamento['motivo_cancelamento']): ?>
        - Motivo: <?= htmlspecialchars($agendamento['motivo_cancelamento']) ?>
    <?php endif; ?>
</div>
                        <?php if ($agendamento['status'] == 'realizado' && $agendamento['data_conclusao']): ?>
                        <?php endif; ?>
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

<script>

    // Função de validação de horas
function validarHoras($horas) {
    return preg_match('/^([0-9]{1,2}):([0-5][0-9])$/', $horas);
}

// No processamento do POST (adicionar_agendamento e editar_agendamento):
if (!validarHoras($_POST['horas'])) {
    $_SESSION['error'] = "Formato de horas inválido. Use HH:MM (ex: 01:30)";
    header("Location: visualizar.php?id=$treinamento_id");
    exit();
}


 // Atualização visual apenas
    document.querySelectorAll('.checklist-item-toggle').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const itemDiv = this.closest('.checklist-item');
            
            if (this.checked) {
                itemDiv.classList.add('bg-light-success');
                itemDiv.classList.remove('bg-light');
            } else {
                itemDiv.classList.remove('bg-light-success');
                itemDiv.classList.add('bg-light');
            }
        });
    });

    // Cálculo automático do total de horas
    function calcularTotalHoras() {
        let total = 0;
        document.querySelectorAll('input[name^="checklist"][name$="[horas]"]').forEach(input => {
            total += parseFloat(input.value) || 0;
        });
        const totalElement = document.getElementById('totalHoras');
            if (totalElement) {
                totalElement.textContent = total.toFixed(2) + ' horas';
            }
    }

    // Atualizar total quando horas são alteradas
    document.querySelectorAll('input[name^="checklist"][name$="[horas]"]').forEach(input => {
        input.addEventListener('change', calcularTotalHoras);
        input.addEventListener('input', calcularTotalHoras);
    });

    // Calcular total inicial
    window.addEventListener('DOMContentLoaded', calcularTotalHoras);
</script>
<script>
// Formatar a data para o input datetime-local
// Formatar a data para o input datetime-local
document.addEventListener('DOMContentLoaded', function() {
    // Definir data padrão para AGORA (horário atual)
    const now = new Date();
    // Ajustar para o fuso horário local (Brasília)
    const offset = now.getTimezoneOffset() * 60000;
    const localISOTime = new Date(now - offset).toISOString().slice(0, 16);
    document.getElementById('data_agendada').value = localISOTime;
    
    // Adicionar confirmação para ações importantes
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.onsubmit = function() {
            return confirm('Tem certeza que deseja executar esta ação?');
        };
    });
});
// Habilitar/desabilitar motivo de cancelamento conforme status
const editStatusElement = document.getElementById('edit_status');
if (editStatusElement) {
    editStatusElement.addEventListener('change', function() {
        const motivoField = document.getElementById('edit_motivo_cancelamento');
        if (motivoField) {
            if (this.value === 'cancelado') {
                motivoField.disabled = false;
                motivoField.required = true;
            } else {
                motivoField.disabled = true;
                motivoField.required = false;
                motivoField.value = ''; // Limpar o valor quando não for cancelado
            }
        }
    });
    
    // Configurar estado inicial do campo
    const motivoField = document.getElementById('edit_motivo_cancelamento');
    if (motivoField) {
        if (editStatusElement.value === 'cancelado') {
            motivoField.disabled = false;
            motivoField.required = true;
        } else {
            motivoField.disabled = true;
            motivoField.required = false;
        }
    }
}
</script>
<script>
// Formatar a data para o input datetime-local
document.addEventListener('DOMContentLoaded', function() {
    // Definir data padrão para AGORA (horário atual)
    const now = new Date();
    // Ajustar para o fuso horário local (Brasília)
    const offset = now.getTimezoneOffset() * 60000;
    const localISOTime = new Date(now - offset).toISOString().slice(0, 16);
    document.getElementById('data_agendada').value = localISOTime;
    
// Função para converter HH:MM para TIME do MySQL
function formatTimeForMySQL(timeStr) {
    if (!timeStr) return '01:00:00';
    
    const parts = timeStr.split(':');
    const hours = parts[0].padStart(2, '0');
    const minutes = (parts[1] || '00').padStart(2, '0');
    return `${hours}:${minutes}:00`;
}

// Configurar o campo de horas ao abrir o modal de edição
document.querySelectorAll('.btn-editar-agendamento').forEach(btn => {
    btn.addEventListener('click', function() {
        const agendamentoId = this.getAttribute('data-id');
        
        fetch(`api/treinamentos.php?action=get_agendamento&id=${agendamentoId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const agendamento = data.agendamento;
                    
                    // Preencher o modal
                    document.getElementById('edit_agendamento_id').value = agendamento.id;
                    document.getElementById('edit_usuario_id').value = agendamento.usuario_id || <?= $usuario_id ?>;
                    
                    // Formatando a data para o input datetime-local
                    const date = new Date(agendamento.data_agendada);
                    const localISOTime = new Date(date - (date.getTimezoneOffset() * 60000)).toISOString().slice(0, 16);
                    document.getElementById('edit_data_agendada').value = localISOTime;
                    
                    // Preencher horas no formato HH:MM
                    document.getElementById('edit_horas').value = agendamento.horas;
                    document.getElementById('edit_observacao').value = agendamento.observacao || '';
                    document.getElementById('edit_status').value = agendamento.status;
                    
                    // Preencher motivo do cancelamento se existir
                    const motivoField = document.getElementById('edit_motivo_cancelamento');
                    if (motivoField) {
                        motivoField.value = agendamento.motivo_cancelamento || '';
                        
                        // Habilitar/desabilitar campo baseado no status
                        if (agendamento.status === 'cancelado') {
                            motivoField.disabled = false;
                            motivoField.required = true;
                        } else {
                            motivoField.disabled = true;
                            motivoField.required = false;
                        }
                    }
                }
            });
    });
});

// Validar e formatar o input de tempo
document.querySelectorAll('.time-input').forEach(input => {
    input.addEventListener('blur', function() {
        let value = this.value.trim();
        
        // Adiciona zeros faltantes
        if (value && value.indexOf(':') === -1) {
            value = value + ':00'; // Assume minutos zero
        }
        
        // Garante dois dígitos em horas e minutos
        if (value && value.indexOf(':') !== -1) {
            let [h, m] = value.split(':');
            h = h.padStart(2, '0');
            m = (m || '00').padStart(2, '0').substr(0, 2);
            this.value = `${h}:${m}`;
        }
        
        // Validação simples
        if (!/^[0-9]{1,2}:[0-5][0-9]$/.test(this.value)) {
            this.value = '01:00'; // Valor padrão se inválido
        }
    });
});
    
    // Adicionar confirmação para ações importantes
    document.querySelectorAll('form[onsubmit]').forEach(form => {
        form.onsubmit = function() {
            return confirm('Tem certeza que deseja executar esta ação?');
        };
    });
});
// Configurar o modal de motivo de cancelamento
document.querySelectorAll('.btn-cancelar-agendamento').forEach(btn => {
    btn.addEventListener('click', function() {
        const agendamentoId = this.getAttribute('data-id');
        document.getElementById('cancel_agendamento_id').value = agendamentoId;
    });
});

// Limpar o modal quando fechado
document.getElementById('motivoCancelamentoModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('formCancelarAgendamento').reset();
});
</script>

<!-- CKEditor para comentários -->
<script src="https://cdn.ckeditor.com/ckeditor5/39.0.1/classic/ckeditor.js"></script>
<script>
// Inicializar CKEditor para novo comentário
let editorComentario;
ClassicEditor
    .create(document.querySelector('#editorComentario'), {
        toolbar: {
            items: [
                'heading', '|',
                'bold', 'italic', '|', // Removido 'underline'
                'link', '|',
                'bulletedList', 'numberedList', '|',
                'outdent', 'indent', '|',
                'blockQuote', 'insertTable', '|',
                'undo', 'redo'
            ]
        },
        language: 'pt-br',
        link: {
            defaultProtocol: 'https://',
            decorators: {
                openInNewTab: {
                    mode: 'automatic',
                    callback: url => true,
                    attributes: {
                        target: '_blank',
                        rel: 'noopener noreferrer'
                    }
                }
            }
        },
        table: {
            contentToolbar: [
                'tableColumn',
                'tableRow',
                'mergeTableCells'
            ]
        }
    })
    .then(editor => {
        editorComentario = editor;
        
        // Sincronizar com textarea oculta
        editor.model.document.on('change:data', () => {
            document.getElementById('novo_comentario').value = editor.getData();
        });
    })
    .catch(error => {
        console.error(error);
    });

// Função para editar comentário
function editComment(commentId) {
    const commentDiv = document.getElementById('comment-' + commentId);
    if (!commentDiv) return;
    
    const contentDiv = commentDiv.querySelector('.comment-content');
    const editForm = document.getElementById('edit-form-' + commentId);
    
    if (!contentDiv || !editForm) return;
    
    // Ocultar conteúdo original e mostrar formulário de edição
    contentDiv.style.display = 'none';
    editForm.style.display = 'block';
    
    // Inicializar CKEditor para edição
    const editorDiv = editForm.querySelector('.editor-edit-comment');
    ClassicEditor
        .create(editorDiv, {
            toolbar: {
                items: [
                    'heading', '|',
                    'bold', 'italic', 'underline', '|',
                    'link', '|',
                    'bulletedList', 'numberedList', '|',
                    'outdent', 'indent', '|',
                    'blockQuote', 'insertTable', '|',
                    'undo', 'redo'
                ]
            },
            language: 'pt-br',
            link: {
                defaultProtocol: 'https://',
                decorators: {
                    openInNewTab: {
                        mode: 'automatic',
                        callback: url => true,
                        attributes: {
                            target: '_blank',
                            rel: 'noopener noreferrer'
                        }
                    }
                }
            },
            table: {
                contentToolbar: [
                    'tableColumn',
                    'tableRow',
                    'mergeTableCells'
                ]
            }
        })
        .then(editor => {
            // Sincronizar com textarea oculta
            editor.model.document.on('change:data', () => {
                editForm.querySelector('textarea[name="comentario"]').value = editor.getData();
            });
            
            // Armazenar referência do editor
            editorDiv.ckeditorInstance = editor;
        })
        .catch(error => {
            console.error(error);
        });
}

// Função para cancelar edição
function cancelEdit(commentId) {
    const commentDiv = document.getElementById('comment-' + commentId);
    const contentDiv = commentDiv.querySelector('.comment-content');
    const editForm = document.getElementById('edit-form-' + commentId);
    const editorDiv = editForm.querySelector('.editor-edit-comment');
    
    // Destruir instância do CKEditor se existir
    if (editorDiv.ckeditorInstance) {
        editorDiv.ckeditorInstance.destroy();
        delete editorDiv.ckeditorInstance;
    }
    
    // Mostrar conteúdo original e ocultar formulário de edição
    contentDiv.style.display = 'block';
    editForm.style.display = 'none';
}

// Função para excluir comentário
function deleteComment(commentId) {
    if (confirm('Tem certeza que deseja excluir este comentário?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'api/comentarios.php';
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'action';
        actionInput.value = 'delete';
        
        const commentIdInput = document.createElement('input');
        commentIdInput.type = 'hidden';
        commentIdInput.name = 'comment_id';
        commentIdInput.value = commentId;
        
        const treinamentoIdInput = document.createElement('input');
        treinamentoIdInput.type = 'hidden';
        treinamentoIdInput.name = 'treinamento_id';
        treinamentoIdInput.value = <?= $treinamento_id ?>;
        
        form.appendChild(actionInput);
        form.appendChild(commentIdInput);
        form.appendChild(treinamentoIdInput);
        
        document.body.appendChild(form);
        form.submit();
    }
}
</script>
<script>
// Sistema simples para manter a sessão ativa
// Renova a cada 5 minutos (300 segundos)
setInterval(function() {
    // Faz uma requisição invisível para manter a sessão
    fetch('?manter_sessao=1', {
        cache: 'no-store',
        headers: {
            'Cache-Control': 'no-cache'
        }
    }).then(() => {
        console.log('Sessão renovada automaticamente');
    }).catch(() => {
        // Não faz nada em caso de erro
    });
}, 300000); // 5 minutos = 300000 milissegundos
</script>
<!-- visualizar.php - Adicionar antes do fechamento da div container -->

<script>
// Configurar links da descrição para abrir em nova guia
document.addEventListener('DOMContentLoaded', function() {
    const descricaoContent = document.querySelector('.descricao-content');
    if (descricaoContent) {
        const links = descricaoContent.querySelectorAll('a');
        links.forEach(link => {
            link.setAttribute('target', '_blank');
            link.setAttribute('rel', 'noopener noreferrer');
        });
    }
});
</script>

<?php require_once 'includes/footer_treinamentos.php'; ?>