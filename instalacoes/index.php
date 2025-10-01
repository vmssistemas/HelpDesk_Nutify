<?php
require_once 'includes/header_instalacoes.php';

// Filtros (no início do arquivo)
$filtro_status = isset($_GET['status']) ? (is_array($_GET['status']) ? $_GET['status'] : [$_GET['status']]) : [1, 2]; // Padrão: status 1 e 2
$filtro_responsavel = isset($_GET['responsavel']) ? $_GET['responsavel'] : null;
$filtro_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : null;
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;
$filtro_plano = isset($_GET['plano']) ? $_GET['plano'] : null;
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'lista';

// Ordenação
$ordenacao = isset($_GET['ordenacao']) ? $_GET['ordenacao'] : 'id_desc';
$coluna_ordenacao = 'i.id';
$direcao_ordenacao = 'DESC';

// Configura ordenação
if ($ordenacao) {
    list($coluna, $direcao) = explode('_', $ordenacao);
    $colunas_validas = ['id', 'titulo', 'cliente', 'data', 'responsavel', 'status'];
    
    if (in_array($coluna, $colunas_validas)) {
        $coluna_ordenacao = $coluna === 'cliente' ? 'c.nome' : 
                           ($coluna === 'responsavel' ? 'r.nome' : 
                           ($coluna === 'status' ? 's.nome' : 
                           ($coluna === 'data' ? 'i.data_instalacao' : 'i.' . $coluna)));
        $direcao_ordenacao = strtoupper($direcao) === 'ASC' ? 'ASC' : 'DESC';
    }
}

// Busca dados para filtros
$status_list = getInstalacoesStatus();
$clientes_list = getClientes();
$equipe_list = getEquipe();

// Busca instalações filtradas
$query = "SELECT i.*, s.nome as status_nome, s.cor as status_cor,
                 c.nome as cliente_nome, c.contrato as cliente_contrato,
                 p.nome as plano_nome, u.nome as usuario_nome,
                 r.nome as responsavel_nome
          FROM instalacoes i
          LEFT JOIN instalacoes_status s ON i.status_id = s.id
          LEFT JOIN clientes c ON i.cliente_id = c.id
          LEFT JOIN planos p ON i.plano_id = p.id
          LEFT JOIN usuarios u ON i.usuario_id = u.id
          LEFT JOIN usuarios r ON i.responsavel_id = r.id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($filtro_status)) {
    $placeholders = implode(',', array_fill(0, count($filtro_status), '?'));
    $query .= " AND i.status_id IN ($placeholders)";
    $params = array_merge($params, $filtro_status);
    $types .= str_repeat('i', count($filtro_status));
}

if ($filtro_responsavel) {
    $query .= " AND i.responsavel_id = ?";
    $params[] = $filtro_responsavel;
    $types .= "i";
}
if ($filtro_plano) {
    $query .= " AND i.plano_id = ?";
    $params[] = $filtro_plano;
    $types .= "i";
}

if ($filtro_tipo) {
    $query .= " AND i.tipo_id = ?";
    $params[] = $filtro_tipo;
    $types .= "i";
}

if ($filtro_cliente) {
    $query .= " AND i.cliente_id = ?";
    $params[] = $filtro_cliente;
    $types .= "i";
}

if ($filtro_data_inicio && $filtro_data_fim) {
    $query .= " AND DATE(i.data_instalacao) BETWEEN ? AND ?";
    $params[] = $filtro_data_inicio;
    $params[] = $filtro_data_fim;
    $types .= "ss";
} elseif ($filtro_data_inicio) {
    $query .= " AND DATE(i.data_instalacao) >= ?";
    $params[] = $filtro_data_inicio;
    $types .= "s";
} elseif ($filtro_data_fim) {
    $query .= " AND DATE(i.data_instalacao) <= ?";
    $params[] = $filtro_data_fim;
    $types .= "s";
}

$query .= " ORDER BY $coluna_ordenacao $direcao_ordenacao";
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$instalacoes = $result->fetch_all(MYSQLI_ASSOC);

// Estatísticas
$query_stats = "SELECT 
    (SELECT COUNT(*) FROM instalacoes) as total,
    (SELECT COUNT(*) FROM instalacoes WHERE status_id = 3) as concluidos,
    (SELECT COUNT(*) FROM instalacoes WHERE status_id = 2) as em_andamento,
    (SELECT COUNT(*) FROM instalacoes WHERE status_id = 1) as agendados";
$result_stats = $conn->query($query_stats);
$stats = $result_stats->fetch_assoc();
?>

<!-- Dashboard Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <h5 class="card-title">Total</h5>
                <h2 class="card-text"><?= $stats['total'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-info">
            <div class="card-body">
                <h5 class="card-title">Agendados</h5>
                <h2 class="card-text"><?= $stats['agendados'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <h5 class="card-title">Em Andamento</h5>
                <h2 class="card-text"><?= $stats['em_andamento'] ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <h5 class="card-title">Concluídos</h5>
                <h2 class="card-text"><?= $stats['concluidos'] ?></h2>
            </div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Filtros</h5>
        <div>
            <button type="submit" form="filtros-form" class="btn btn-primary compact-btn me-2">
                <i class="material-icons me-1">filter_alt</i> Filtrar
            </button>
            <a href="index.php" class="btn btn-outline-secondary compact-btn">
                <i class="material-icons me-1">clear</i> Limpar
            </a>
        </div>
    </div>
    <div class="card-body">
        <form method="get" class="row g-3" id="filtros-form">
            <div class="col-md-9">
                <div class="row g-3">
                    <!-- Novo filtro de cliente (primeiro) -->
                    <div class="col-md-4">
                        <label for="cliente" class="form-label">Cliente</label>
                        <div class="custom-select">
                            <input type="text" id="cliente_filter" placeholder="Digite para filtrar..." class="form-control" 
                                   value="<?= $filtro_cliente ? htmlspecialchars(getClienteNameById($filtro_cliente)) : '' ?>">
                            <div class="options" id="cliente_options">
                                <div data-value="">Todos</div>
                                <?php foreach ($clientes_list as $cliente): ?>
                                    <div data-value="<?= $cliente['id'] ?>">
                                        <?= htmlspecialchars(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome']) ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <select id="cliente" name="cliente" class="form-select d-none">
                                <option value="">Todos</option>
                                <?php foreach ($clientes_list as $cliente): ?>
                                    <option value="<?= $cliente['id'] ?>" <?= $filtro_cliente == $cliente['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                                </select>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label for="responsavel" class="form-label">Responsável</label>
                        <select id="responsavel" name="responsavel" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach ($equipe_list as $membro): ?>
                                <option value="<?= $membro['id'] ?>" <?= $filtro_responsavel == $membro['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($membro['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="plano" class="form-label">Plano</label>
                        <select id="plano" name="plano" class="form-select">
                            <option value="">Todos</option>
                            <?php foreach (getPlanos() as $plano): ?>
                                <option value="<?= $plano['id'] ?>" <?= (isset($_GET['plano']) && $_GET['plano'] == $plano['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($plano['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label for="tipo" class="form-label">Tipo</label>
                        <select id="tipo" name="tipo" class="form-select">
                            <option value="">Todos</option>
                            <?php 
                            $tipos = [
                                1 => 'Implantação',
                                2 => 'Upgrade',
                                3 => 'Adicional',
                                4 => 'Módulo',
                                5 => 'Cancelamento',
                                6 => 'Troca CNPJ'
                            ];
                            foreach ($tipos as $id => $nome): ?>
                                <option value="<?= $id ?>" <?= (isset($_GET['tipo']) && $_GET['tipo'] == $id) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($nome) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label for="data_inicio" class="form-label">Data Início</label>
                        <input type="date" id="data_inicio" name="data_inicio" class="form-control" 
                               value="<?= $filtro_data_inicio ?>">
                    </div>
                    
                    <div class="col-md-4">
                        <label for="data_fim" class="form-label">Data Fim</label>
                        <input type="date" id="data_fim" name="data_fim" class="form-control" 
                               value="<?= $filtro_data_fim ?>">
                    </div>
                </div>
            </div>
     <div class="col-md-3">
    <label class="form-label">Status</label>
    <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y: auto;">
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="select-all-status" onclick="toggleAllStatus(this)">
            <label class="form-check-label fw-bold" for="select-all-status">Todos</label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="checkbox" id="default-status" onclick="selectDefaultStatus()">
            <label class="form-check-label fw-bold" for="default-status">Padrão (Agendados + Em Andamento)</label>
        </div>
        <hr class="my-1">
        <?php foreach ($status_list as $status): ?>
        <div class="form-check">
            <input class="form-check-input status-checkbox" type="checkbox" name="status[]" 
                   value="<?= $status['id'] ?>" 
                   id="status-<?= $status['id'] ?>"
                   <?= in_array($status['id'], $filtro_status) ? 'checked' : '' ?>>
            <label class="form-check-label" for="status-<?= $status['id'] ?>">
                <?= htmlspecialchars($status['nome']) ?>
            </label>
        </div>
        <?php endforeach; ?>
    </div>
</div>
            
            <input type="hidden" name="ordenacao" value="<?= $ordenacao ?>">
        </form>
    </div>
</div>

<!-- Lista de Instalações -->
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Instalações</h5>
            <span class="badge bg-primary">Total: <?= count($instalacoes) ?></span>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['ordenacao' => 'id_' . ($ordenacao == 'id_asc' ? 'desc' : 'asc')])) ?>" class="text-decoration-none text-dark">
                                ID <?= $ordenacao == 'id_asc' ? '↑' : ($ordenacao == 'id_desc' ? '↓' : '') ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['ordenacao' => 'titulo_' . ($ordenacao == 'titulo_asc' ? 'desc' : 'asc')])) ?>" class="text-decoration-none text-dark">
                                Título <?= $ordenacao == 'titulo_asc' ? '↑' : ($ordenacao == 'titulo_desc' ? '↓' : '') ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['ordenacao' => 'cliente_' . ($ordenacao == 'cliente_asc' ? 'desc' : 'asc')])) ?>" class="text-decoration-none text-dark">
                                Cliente <?= $ordenacao == 'cliente_asc' ? '↑' : ($ordenacao == 'cliente_desc' ? '↓' : '') ?>
                            </a>
                        </th>
                        <th>Plano</th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['ordenacao' => 'data_' . ($ordenacao == 'data_asc' ? 'desc' : 'asc')])) ?>" class="text-decoration-none text-dark">
                                Data <?= $ordenacao == 'data_asc' ? '↑' : ($ordenacao == 'data_desc' ? '↓' : '') ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['ordenacao' => 'responsavel_' . ($ordenacao == 'responsavel_asc' ? 'desc' : 'asc')])) ?>" class="text-decoration-none text-dark">
                                Responsável <?= $ordenacao == 'responsavel_asc' ? '↑' : ($ordenacao == 'responsavel_desc' ? '↓' : '') ?>
                            </a>
                        </th>
                        <th>
                            <a href="?<?= http_build_query(array_merge($_GET, ['ordenacao' => 'status_' . ($ordenacao == 'status_asc' ? 'desc' : 'asc')])) ?>" class="text-decoration-none text-dark">
                                Status <?= $ordenacao == 'status_asc' ? '↑' : ($ordenacao == 'status_desc' ? '↓' : '') ?>
                            </a>
                        </th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($instalacoes)): ?>
                        <?php foreach ($instalacoes as $instalacao): ?>
                        <tr>
                            <td>#<?= $instalacao['id'] ?></td>
                           <td>
    <a href="visualizar.php?id=<?= $instalacao['id'] ?>" style="color: #0d6efd; text-decoration: underline;">
        <?= htmlspecialchars($instalacao['titulo']) ?>
    </a>
</td>
                            <td>
                                <?= $instalacao['cliente_nome'] ? 
                                    htmlspecialchars(($instalacao['cliente_contrato'] ? $instalacao['cliente_contrato'] . ' - ' : '') . $instalacao['cliente_nome']) : '-' ?>
                            </td>
                            <td><?= $instalacao['plano_nome'] ?? '-' ?></td>
                            <td><?= $instalacao['data_instalacao'] ? date('d/m/Y H:i', strtotime($instalacao['data_instalacao'])) : '-' ?></td>
                            <td><?= $instalacao['responsavel_nome'] ?? '-' ?></td>
                            <td>
                                <span class="badge status-badge" style="background-color: <?= $instalacao['status_cor'] ?>">
                                    <?= $instalacao['status_nome'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="visualizar.php?id=<?= $instalacao['id'] ?>" class="btn btn-sm btn-outline-primary" title="Visualizar">
                                    <i class="material-icons">visibility</i>
                                </a>
                                <a href="editar.php?id=<?= $instalacao['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar">
                                    <i class="material-icons">edit</i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">Nenhuma instalação encontrada com os filtros selecionados</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer_instalacoes.php'; ?>