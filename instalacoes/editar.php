<?php
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

$status_list = getInstalacoesStatus();
$clientes_list = getClientes();
$planos_list = getPlanos();
$equipe_list = getEquipe();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao']);
    $cliente_id = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
    $plano_id = !empty($_POST['plano_id']) ? (int)$_POST['plano_id'] : null;
    $responsavel_id = !empty($_POST['responsavel_id']) ? (int)$_POST['responsavel_id'] : null;
    $data_instalacao = !empty($_POST['data_instalacao']) ? date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $_POST['data_instalacao']))) : null;
    $status_id = (int)$_POST['status_id'];
    
    $query = "UPDATE instalacoes SET
                titulo = ?,
                descricao = ?,
                cliente_id = ?,
                plano_id = ?,
                responsavel_id = ?,
                data_instalacao = ?,
                status_id = ?
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "ssiiisii",
        $titulo,
        $descricao,
        $cliente_id,
        $plano_id,
        $responsavel_id,
        $data_instalacao,
        $status_id,
        $instalacao_id
    );

    if ($stmt->execute()) {
        $_SESSION['success'] = "Instalação atualizada com sucesso!";
        header("Location: visualizar.php?id=$instalacao_id");
        exit();
    } else {
        $error = "Erro ao atualizar instalação: " . $conn->error;
    }
}
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="material-icons me-2">edit</i> Editar Instalação #<?= $instalacao['id'] ?></h5>
    </div>
    <div class="card-body">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="row g-3">
                <!-- Título -->
                <div class="col-md-8">
                    <label for="titulo" class="form-label">Título *</label>
                    <input type="text" class="form-control" id="titulo" name="titulo" 
                           value="<?= htmlspecialchars($instalacao['titulo']) ?>" required maxlength="100">
                </div>
                
                <!-- Status -->
                <div class="col-md-4">
                    <label for="status_id" class="form-label">Status *</label>
                    <select id="status_id" name="status_id" class="form-select" required>
                        <?php foreach ($status_list as $status): ?>
                            <option value="<?= $status['id'] ?>" <?= $status['id'] == $instalacao['status_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($status['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Descrição -->
                <div class="col-12">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="5"><?= htmlspecialchars($instalacao['descricao']) ?></textarea>
                </div>
                
                <!-- Cliente -->
                <div class="col-md-4">
                    <label for="cliente_id" class="form-label">Cliente</label>
                    <select id="cliente_id" name="cliente_id" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($clientes_list as $cliente): ?>
                            <option value="<?= $cliente['id'] ?>" 
                                    data-plano="<?= htmlspecialchars($cliente['plano'] ?? '') ?>"
                                    <?= $cliente['id'] == $instalacao['cliente_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Plano (agora será preenchido automaticamente) -->
                <div class="col-md-4">
                    <label for="plano_id" class="form-label">Plano</label>
                    <select id="plano_id" name="plano_id" class="form-select" disabled>
                        <option value="">Selecione um cliente primeiro</option>
                        <?php foreach ($planos_list as $plano): ?>
                            <option value="<?= $plano['id'] ?>" <?= $plano['id'] == $instalacao['plano_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($plano['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" id="plano_id_hidden" name="plano_id" value="<?= $instalacao['plano_id'] ?>">
                </div>
                
                <!-- Responsável -->
                <div class="col-md-4">
                    <label for="responsavel_id" class="form-label">Responsável</label>
                    <select id="responsavel_id" name="responsavel_id" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($equipe_list as $membro): ?>
                            <option value="<?= $membro['id'] ?>" <?= $membro['id'] == $instalacao['responsavel_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($membro['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Data da Instalação -->
                <div class="col-md-6">
                    <label for="data_instalacao" class="form-label">Data da Instalação</label>
                    <input type="text" class="form-control datepicker" id="data_instalacao" name="data_instalacao" 
                           value="<?= $instalacao['data_instalacao'] ? date('d/m/Y H:i', strtotime($instalacao['data_instalacao'])) : '' ?>" 
                           placeholder="dd/mm/aaaa hh:mm">
                </div>
                
                <!-- Botões -->
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="material-icons me-1">save</i> Salvar
                    </button>
                    <a href="visualizar.php?id=<?= $instalacao['id'] ?>" class="btn btn-outline-secondary">
                        <i class="material-icons me-1">cancel</i> Cancelar
                    </a>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once 'includes/footer_instalacoes.php'; ?>

<script>
// Quando o cliente é selecionado, preenche automaticamente o plano
document.getElementById('cliente_id').addEventListener('change', function() {
    const clienteSelect = this;
    const planoSelect = document.getElementById('plano_id');
    const planoHidden = document.getElementById('plano_id_hidden');
    
    // Obtém o plano do cliente selecionado
    const selectedOption = clienteSelect.options[clienteSelect.selectedIndex];
    const planoId = selectedOption.getAttribute('data-plano');
    
    if (planoId) {
        // Habilita o select, define o valor e desabilita novamente
        planoSelect.disabled = false;
        planoSelect.value = planoId;
        planoSelect.disabled = true;
        
        // Define o valor no campo hidden para envio no formulário
        planoHidden.value = planoId;
    } else {
        // Se não houver plano definido, limpa o campo
        planoSelect.value = '';
        planoSelect.disabled = true;
        planoHidden.value = '';
    }
});

// Preenche o plano automaticamente ao carregar a página se já houver cliente selecionado
document.addEventListener('DOMContentLoaded', function() {
    const clienteSelect = document.getElementById('cliente_id');
    const planoSelect = document.getElementById('plano_id');
    const planoHidden = document.getElementById('plano_id_hidden');
    
    if (clienteSelect.value) {
        const selectedOption = clienteSelect.options[clienteSelect.selectedIndex];
        const planoId = selectedOption.getAttribute('data-plano');
        
        if (planoId) {
            planoSelect.disabled = false;
            planoSelect.value = planoId;
            planoSelect.disabled = true;
            planoHidden.value = planoId;
        }
    }
});
</script>