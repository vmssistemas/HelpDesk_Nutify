<?php
require_once 'includes/header_instalacoes.php';

$status_list = getInstalacoesStatus();
$clientes_list = getClientes();
$planos_list = getPlanos();
$equipe_list = getEquipe();
$tipos_list = $conn->query("SELECT id, nome FROM instalacoes_tipos ORDER BY nome")->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo']);
    $tipo_id = (int)$_POST['tipo_id'];
    $descricao = trim($_POST['descricao']);
    $cliente_id = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
    $plano_id = !empty($_POST['plano_id']) ? (int)$_POST['plano_id'] : null;
    $responsavel_id = !empty($_POST['responsavel_id']) ? (int)$_POST['responsavel_id'] : null;
    $data_instalacao = !empty($_POST['data_instalacao']) ? date('Y-m-d H:i:s', strtotime(str_replace('/', '-', $_POST['data_instalacao']))) : null;
    $status_id = (int)$_POST['status_id'];
    
    // Dados específicos por tipo
    $dados_especificos = [];
    if ($tipo_id == 2) { // Upgrade Plano
        $dados_especificos['plano_origem'] = (int)$_POST['plano_origem'];
        $dados_especificos['plano_destino'] = (int)$_POST['plano_destino'];
    } elseif ($tipo_id == 4) { // Módulo
        $dados_especificos['modulo'] = $_POST['modulo'];
    }
    
    $query = "INSERT INTO instalacoes (
                titulo, tipo_id, descricao, cliente_id, plano_id, responsavel_id, 
                data_instalacao, status_id, usuario_id
              ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "sisiiisii",
        $titulo,
        $tipo_id,
        $descricao,
        $cliente_id,
        $plano_id,
        $responsavel_id,
        $data_instalacao,
        $status_id,
        $usuario_id
    );

    if ($stmt->execute()) {
        $instalacao_id = $stmt->insert_id;
        
        // Criar checklist automaticamente conforme o tipo
        if (($tipo_id == 1 && $plano_id) || // Implantação com plano
            ($tipo_id == 2) || // Upgrade Plano
            ($tipo_id == 4)) { // Módulo
            criarChecklistParaInstalacao($instalacao_id, $plano_id, $tipo_id, $dados_especificos);
        }
        
        $_SESSION['success'] = "Instalação criada com sucesso!";
        header("Location: visualizar.php?id=$instalacao_id");
        exit();
    } else {
        $error = "Erro ao criar instalação: " . $conn->error;
    }
}
?>

<div class="card">
    <div class="card-header bg-primary text-white">
        <h5 class="mb-0"><i class="material-icons me-2">add</i> Criar Nova Instalação</h5>
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
                    <input type="text" class="form-control" id="titulo" name="titulo" required maxlength="100">
                </div>

                <div class="col-md-4">
    <label for="tipo_id" class="form-label">Tipo de Instalação *</label>
    <select id="tipo_id" name="tipo_id" class="form-select" required>
        <?php foreach ($tipos_list as $tipo): ?>
            <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
        <?php endforeach; ?>
    </select>
</div>

<div id="modulo_fields" class="row g-3 d-none">
    <div class="col-md-12">
        <label for="modulo" class="form-label">Módulo</label>
        <select id="modulo" name="modulo" class="form-select">
            <option value="Ifood">Ifood</option>
            <option value="Tray">Tray</option>
            <option value="Tiny">Tiny</option>
        </select>
    </div>
</div>

<div id="upgrade_fields" class="row g-3 d-none">
    <div class="col-md-6">
        <label for="plano_origem" class="form-label">Plano de Origem</label>
        <select id="plano_origem" name="plano_origem" class="form-select">
            <?php foreach ($planos_list as $plano): ?>
                <option value="<?= $plano['id'] ?>"><?= htmlspecialchars($plano['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="col-md-6">
        <label for="plano_destino" class="form-label">Plano de Destino</label>
        <select id="plano_destino" name="plano_destino" class="form-select">
            <?php foreach ($planos_list as $plano): ?>
                <option value="<?= $plano['id'] ?>"><?= htmlspecialchars($plano['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
                
                <!-- Status -->
                <div class="col-md-4">
                    <label for="status_id" class="form-label">Status *</label>
                    <select id="status_id" name="status_id" class="form-select" required>
                        <?php foreach ($status_list as $status): ?>
                            <option value="<?= $status['id'] ?>"><?= htmlspecialchars($status['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Descrição -->
                <div class="col-12">
                    <label for="descricao" class="form-label">Descrição</label>
                    <textarea class="form-control" id="descricao" name="descricao" rows="5"></textarea>
                </div>
                
            <!-- Adicione este modal no início do arquivo, antes do formulário -->
<div class="modal fade" id="cadastroClienteModal" tabindex="-1" aria-labelledby="cadastroClienteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="cadastroClienteModalLabel">Cadastrar Cliente Rápido</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <iframe id="iframeCadastroCliente" src="../chamados/cadastrar_cliente_rapido.php" style="width:100%; height:600px; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>

<!-- Modifique o campo de cliente no formulário para ficar assim: -->
<div class="col-md-6 cliente-select-container">
    <div class="d-flex align-items-center">
        <label for="cliente_id" class="form-label me-2" style="cursor: pointer;">
            <span class="text-primary" data-bs-toggle="modal" data-bs-target="#cadastroClienteModal">
                Cliente <i class="material-icons" style="font-size: 16px; vertical-align: middle;">add</i>
            </span>
        </label>
    </div>
    
    <div class="custom-select">
        <input type="text" id="cliente_filter" placeholder="Digite para filtrar..." class="form-control">
        <div class="options" id="cliente_options">
            <div data-value="">Nenhum</div>
            <?php foreach (getClientes() as $cliente): ?>
                <div data-value="<?= $cliente['id'] ?>">
                    <?= htmlspecialchars(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome']) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <select id="cliente_id" name="cliente_id" class="form-select d-none">
            <option value="">Nenhum</option>
            <?php foreach (getClientes() as $cliente): ?>
                <option value="<?= $cliente['id'] ?>" data-plano="<?= $cliente['plano'] ?? '' ?>">
                    <?= htmlspecialchars(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
                
                <!-- Plano (agora será preenchido automaticamente) -->
                <div class="col-md-4">
                    <label for="plano_id" class="form-label">Plano</label>
                    <select id="plano_id" name="plano_id" class="form-select" disabled>
                        <option value="">Selecione um cliente primeiro</option>
                        <?php foreach ($planos_list as $plano): ?>
                            <option value="<?= $plano['id'] ?>"><?= htmlspecialchars($plano['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="hidden" id="plano_id_hidden" name="plano_id">
                </div>
                
                <!-- Responsável -->
                <div class="col-md-4">
                    <label for="responsavel_id" class="form-label">Responsável</label>
                    <select id="responsavel_id" name="responsavel_id" class="form-select">
                        <option value="">Selecione...</option>
                        <?php foreach ($equipe_list as $membro): ?>
                            <option value="<?= $membro['id'] ?>"><?= htmlspecialchars($membro['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Data da Instalação -->
                <div class="col-md-6">
                    <label for="data_instalacao" class="form-label">Data da Instalação</label>
                    <input type="text" class="form-control datepicker" id="data_instalacao" name="data_instalacao" placeholder="dd/mm/aaaa hh:mm">
                </div>
                
                <!-- Botões -->
                <div class="col-12 mt-4">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="material-icons me-1">save</i> Salvar
                    </button>
                    <a href="index.php" class="btn btn-outline-secondary">
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
document.getElementById('tipo_id').addEventListener('change', function() {
    const tipoId = parseInt(this.value);
    const moduloFields = document.getElementById('modulo_fields');
    const upgradeFields = document.getElementById('upgrade_fields');
    const clienteFields = document.getElementById('cliente_id');
    const planoFields = document.getElementById('plano_id');
    
    // Esconde todos os campos extras
    moduloFields.classList.add('d-none');
    upgradeFields.classList.add('d-none');
    
    // Mostra campos específicos conforme o tipo
    if (tipoId === 4) { // Módulo
        moduloFields.classList.remove('d-none');
        clienteFields.required = false;
        planoFields.required = false;
    } else if (tipoId === 2) { // Upgrade Plano
        upgradeFields.classList.remove('d-none');
        clienteFields.required = true;
        planoFields.required = true;
    } else if (tipoId === 1) { // Implantação
        clienteFields.required = true;
        planoFields.required = true;
    } else { // Adicional
        clienteFields.required = false;
        planoFields.required = false;
    }
});
</script>