<?php
require_once 'includes/header.php';

// Verifica se há chamados para transferir
if (empty($_SESSION['transferir_chamados'])) {
    header("Location: sprints.php");
    exit();
}

$dados = $_SESSION['transferir_chamados'];
$sprint_antiga = $dados['sprint_antiga'];
$sprint_nova = $dados['sprint_nova'];

// Busca informações das sprints
$sprint_antiga_info = $conn->query("SELECT nome FROM chamados_sprints WHERE id = $sprint_antiga")->fetch_assoc();
$sprint_nova_info = $conn->query("SELECT nome FROM chamados_sprints WHERE id = $sprint_nova")->fetch_assoc();

// Busca os chamados para transferir
$chamados_ids = implode(',', $dados['chamados']);
$chamados = $conn->query("
    SELECT c.id, c.titulo, s.nome as status, u.nome as responsavel
    FROM chamados c
    LEFT JOIN chamados_status s ON c.status_id = s.id
    LEFT JOIN usuarios u ON c.responsavel_id = u.id
    WHERE c.id IN ($chamados_ids)
")->fetch_all(MYSQLI_ASSOC);

// Processa o formulário de transferência
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['chamados_selecionados'])) {
    $chamados_transferir = $_POST['chamados_selecionados'];
    
    if (!empty($chamados_transferir)) {
        $ids = implode(',', $chamados_transferir);
        $conn->query("UPDATE chamados SET sprint_id = $sprint_nova WHERE id IN ($ids)");
        $_SESSION['success'] = count($chamados_transferir) . " chamados transferidos para a nova sprint!";
    }
    
    unset($_SESSION['transferir_chamados']);
    header("Location: sprints.php");
    exit();
}
?>

<div class="container mb-5">
        <div class="card">
        <div class="card-header">
            <h5 class="mb-0">Transferir Chamados para Nova Sprint</h5>
        </div>
        <div class="card-body">
            <div class="alert alert-info mb-4">
                <strong>Atenção:</strong> Ao ativar a nova sprint, foram encontrados chamados na sprint anterior 
                (<strong><?= htmlspecialchars($sprint_antiga_info['nome']) ?></strong>) que não estão concluídos. 
                Selecione abaixo quais deseja transferir para a nova sprint (<strong><?= htmlspecialchars($sprint_nova_info['nome']) ?></strong>).
            </div>
            
            <form method="POST">
                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary me-2" id="selecionarTodos">
                            <i class="material-icons me-1">check_box</i> Selecionar Todos
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="desmarcarTodos">
                            <i class="material-icons me-1">check_box_outline_blank</i> Desmarcar Todos
                        </button>
                    </div>
                    <div>
                        <span class="badge bg-primary" id="contadorSelecionados">
                            <?= count($chamados) ?> chamados selecionados
                        </span>
                    </div>
                </div>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th width="50px"></th>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Status</th>
                            <th>Responsável</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($chamados as $chamado): ?>
                        <tr>
                            <td>
                                <input type="checkbox" name="chamados_selecionados[]" value="<?= $chamado['id'] ?>" checked class="checkbox-chamado">
                            </td>
                            <td><?= $chamado['id'] ?></td>
                            <td><?= htmlspecialchars($chamado['titulo']) ?></td>
                            <td><?= htmlspecialchars($chamado['status']) ?></td>
                            <td><?= htmlspecialchars($chamado['responsavel']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <div class="d-flex justify-content-between mt-3">
                    <button type="submit" class="btn btn-primary">
                        <i class="material-icons me-1">swap_horiz</i> Transferir Selecionados
                    </button>
                    <a href="sprints.php?cancelar_transferencia=1" class="btn btn-outline-secondary">
                        <i class="material-icons me-1">cancel</i> Não Transferir Nenhum
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Elementos
    const selecionarTodosBtn = document.getElementById('selecionarTodos');
    const desmarcarTodosBtn = document.getElementById('desmarcarTodos');
    const contadorSelecionados = document.getElementById('contadorSelecionados');
    const checkboxes = document.querySelectorAll('.checkbox-chamado');
    
    // Atualiza o contador de selecionados
    function atualizarContador() {
        const selecionados = document.querySelectorAll('.checkbox-chamado:checked').length;
        contadorSelecionados.textContent = `${selecionados} chamados selecionados`;
    }
    
    // Selecionar todos os chamados
    selecionarTodosBtn.addEventListener('click', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
        });
        atualizarContador();
    });
    
    // Desmarcar todos os chamados
    desmarcarTodosBtn.addEventListener('click', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        atualizarContador();
    });
    
    // Atualizar contador quando um checkbox é alterado
    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', atualizarContador);
    });
    
    // Inicializa o contador
    atualizarContador();
    
    // Exibir mensagens de sessão como notificações flutuantes
    <?php if (isset($_SESSION['success'])): ?>
        showToast('<?php echo addslashes($_SESSION['success']); ?>', 'success');
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        showToast('<?php echo addslashes($_SESSION['error']); ?>', 'danger');
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>
});
</script>