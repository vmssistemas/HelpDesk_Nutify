<?php
require_once 'includes/header_treinamentos.php';

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
$coluna_ordenacao = 't.id';
$direcao_ordenacao = 'DESC';

// Configura ordenação
if ($ordenacao) {
    list($coluna, $direcao) = explode('_', $ordenacao);
    $colunas_validas = ['id', 'titulo', 'cliente', 'data', 'responsavel', 'status'];
    
    if (in_array($coluna, $colunas_validas)) {
        $coluna_ordenacao = $coluna === 'cliente' ? 'c.nome' : 
                           ($coluna === 'responsavel' ? 'r.nome' : 
                           ($coluna === 'status' ? 's.nome' : 
                           ($coluna === 'data' ? 't.data_treinamento' : 't.' . $coluna)));
        $direcao_ordenacao = strtoupper($direcao) === 'ASC' ? 'ASC' : 'DESC';
    }
}

// Busca dados para filtros
$status_list = getTreinamentosStatus();
$clientes_list = getClientes();
$equipe_list = getEquipe();

// Busca treinamentos filtrados
$query = "SELECT t.*, s.nome as status_nome, s.cor as status_cor,
                 c.nome as cliente_nome, c.contrato as cliente_contrato,
                 p.nome as plano_nome, u.nome as usuario_nome,
                 r.nome as responsavel_nome
          FROM treinamentos t
          LEFT JOIN treinamentos_status s ON t.status_id = s.id
          LEFT JOIN clientes c ON t.cliente_id = c.id
          LEFT JOIN planos p ON t.plano_id = p.id
          LEFT JOIN usuarios u ON t.usuario_id = u.id
          LEFT JOIN usuarios r ON t.responsavel_id = r.id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($filtro_status)) {
    $placeholders = implode(',', array_fill(0, count($filtro_status), '?'));
    $query .= " AND t.status_id IN ($placeholders)";
    $params = array_merge($params, $filtro_status);
    $types .= str_repeat('i', count($filtro_status));
}

if ($filtro_responsavel) {
    $query .= " AND t.responsavel_id = ?";
    $params[] = $filtro_responsavel;
    $types .= "i";
}
if ($filtro_plano) {
    $query .= " AND t.plano_id = ?";
    $params[] = $filtro_plano;
    $types .= "i";
}

if ($filtro_tipo) {
    $query .= " AND t.tipo_id = ?";
    $params[] = $filtro_tipo;
    $types .= "i";
}

if ($filtro_cliente) {
    $query .= " AND t.cliente_id = ?";
    $params[] = $filtro_cliente;
    $types .= "i";
}

if ($filtro_data_inicio && $filtro_data_fim) {
    $query .= " AND DATE(t.data_treinamento) BETWEEN ? AND ?";
    $params[] = $filtro_data_inicio;
    $params[] = $filtro_data_fim;
    $types .= "ss";
} elseif ($filtro_data_inicio) {
    $query .= " AND DATE(t.data_treinamento) >= ?";
    $params[] = $filtro_data_inicio;
    $types .= "s";
} elseif ($filtro_data_fim) {
    $query .= " AND DATE(t.data_treinamento) <= ?";
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
$treinamentos = $result->fetch_all(MYSQLI_ASSOC);

// Estatísticas baseadas no SQL complexo para o mês atual
$mes_atual = date('m');
$ano_atual = date('Y');
$primeiro_dia_mes = date('Y-m-01');
$ultimo_dia_mes = date('Y-m-t');

// Substituir a query_stats atual por esta versão atualizada
$query_stats = "
WITH periodo AS (
  SELECT TIMESTAMP('$primeiro_dia_mes 00:00:00') AS data_inicio,
         TIMESTAMP('$ultimo_dia_mes 23:59:59') AS data_fim
),
-- gera dias úteis no intervalo
datas_uteis AS (
  SELECT data_inicio + INTERVAL (a.a + 10 * b.a) DAY AS data
  FROM
    (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3 UNION ALL SELECT 4
     UNION ALL SELECT 5 UNION ALL SELECT 6 UNION ALL SELECT 7 UNION ALL SELECT 8 UNION ALL SELECT 9) a,
    (SELECT 0 AS a UNION ALL SELECT 1 UNION ALL SELECT 2 UNION ALL SELECT 3) b,
    periodo
  WHERE data_inicio + INTERVAL (a.a + 10 * b.a) DAY <= data_fim
    AND WEEKDAY(data_inicio + INTERVAL (a.a + 10 * b.a) DAY) BETWEEN 0 AND 4
),
-- jornada por técnico (em segundos)
jornada_tecnica AS (
  SELECT
    u.id AS usuario_id,
    u.nome,
    d.data,
    CASE WHEN u.nome = 'Filipe' THEN 6.0 ELSE 8.5 END * 3600 AS segundos_jornada
  FROM datas_uteis d
  CROSS JOIN usuarios u
  WHERE u.id = ?
),
-- horas realizadas em segundos
horas_realizadas_sec AS (
  SELECT
    a.usuario_id,
    DATE(a.data_agendada) AS data,
    SUM(TIME_TO_SEC(a.horas)) AS segundos_realizados
  FROM treinamentos_agendamentos a
  JOIN periodo p ON a.data_agendada BETWEEN p.data_inicio AND p.data_fim
  WHERE a.status = 'realizado'
    AND a.usuario_id = ?
    AND WEEKDAY(a.data_agendada) BETWEEN 0 AND 4
  GROUP BY a.usuario_id, DATE(a.data_agendada)
),
-- horas canceladas em segundos
horas_canceladas_sec AS (
  SELECT
    a.usuario_id,
    SUM(TIME_TO_SEC(a.horas)) AS segundos_cancelados
  FROM treinamentos_agendamentos a
  JOIN periodo p ON a.data_agendada BETWEEN p.data_inicio AND p.data_fim
  WHERE a.status = 'cancelado'
    AND a.usuario_id = ?
    AND WEEKDAY(a.data_agendada) BETWEEN 0 AND 4
  GROUP BY a.usuario_id
),
-- reaproveitadas em segundos
cancelamentos_reaproveitados_sec AS (
  SELECT
    c.usuario_id,
    SUM(
      GREATEST(
        0,
        TIMESTAMPDIFF(
          SECOND,
          GREATEST(c.data_agendada, r.data_agendada),
          LEAST(
            DATE_ADD(c.data_agendada, INTERVAL TIME_TO_SEC(c.horas) SECOND),
            DATE_ADD(r.data_agendada, INTERVAL TIME_TO_SEC(r.horas) SECOND)
          )
        )
      )
    ) AS segundos_reaproveitados
  FROM treinamentos_agendamentos c
  JOIN treinamentos_agendamentos r
    ON c.usuario_id = r.usuario_id
    AND DATE(c.data_agendada) = DATE(r.data_agendada)
    AND r.status = 'realizado'
    AND TIMESTAMPDIFF(
          SECOND,
          GREATEST(c.data_agendada, r.data_agendada),
          LEAST(
            DATE_ADD(c.data_agendada, INTERVAL TIME_TO_SEC(c.horas) SECOND),
            DATE_ADD(r.data_agendada, INTERVAL TIME_TO_SEC(r.horas) SECOND)
          )
    ) > 0
  JOIN periodo p ON c.data_agendada BETWEEN p.data_inicio AND p.data_fim
  WHERE c.status = 'cancelado'
    AND c.usuario_id = ?
    AND WEEKDAY(c.data_agendada) BETWEEN 0 AND 4
  GROUP BY c.usuario_id
)
SELECT
  j.usuario_id,
  j.nome,

  -- Horas totais de jornada
  CONCAT(
    FLOOR(SUM(j.segundos_jornada) / 3600),
    ':',
    LPAD(MOD(FLOOR(SUM(j.segundos_jornada) / 60), 60), 2, '0')
  ) AS HorasJornadaTotal,

  -- Horas realizadas
  CONCAT(
    FLOOR(SUM(COALESCE(r.segundos_realizados,0)) / 3600),
    ':',
    LPAD(MOD(FLOOR(SUM(COALESCE(r.segundos_realizados,0)) / 60), 60), 2, '0')
  ) AS HorasRealizadasTotal,

  -- Horas ociosas
  CONCAT(
    FLOOR(GREATEST(0, SUM(j.segundos_jornada) - SUM(COALESCE(r.segundos_realizados,0))) / 3600),
    ':',
    LPAD(MOD(FLOOR(GREATEST(0, SUM(j.segundos_jornada) - SUM(COALESCE(r.segundos_realizados,0))) / 60), 60), 2, '0')
  ) AS HorasOciosas,

  -- Horas canceladas
  CONCAT(
    FLOOR(COALESCE(MAX(c.segundos_cancelados),0) / 3600),
    ':',
    LPAD(MOD(FLOOR(COALESCE(MAX(c.segundos_cancelados),0) / 60), 60), 2, '0')
  ) AS HorasCanceladas,

  -- Horas reaproveitadas
  CONCAT(
    FLOOR(COALESCE(MAX(cr.segundos_reaproveitados),0) / 3600),
    ':',
    LPAD(MOD(FLOOR(COALESCE(MAX(cr.segundos_reaproveitados),0) / 60), 60), 2, '0')
  ) AS HorasReaproveitadas,

  -- ValorHora
  CASE
    WHEN COALESCE(MAX(c.segundos_cancelados),0) = 0 THEN 7.00
    WHEN COALESCE(MAX(cr.segundos_reaproveitados),0) / COALESCE(MAX(c.segundos_cancelados),1) >= 0.5 THEN 7.00
    ELSE 5.00
  END AS ValorHora,

  -- TotalComissao
  ROUND(
    (SUM(COALESCE(r.segundos_realizados,0)) / 3600) *
    (CASE
        WHEN COALESCE(MAX(c.segundos_cancelados),0) = 0 THEN 7.00
        WHEN COALESCE(MAX(cr.segundos_reaproveitados),0) / COALESCE(MAX(c.segundos_cancelados),1) >= 0.5 THEN 7.00
        ELSE 5.00
    END),
    2
  ) AS TotalComissao

FROM jornada_tecnica j
LEFT JOIN horas_realizadas_sec r
  ON j.usuario_id = r.usuario_id AND j.data = r.data
LEFT JOIN horas_canceladas_sec c
  ON j.usuario_id = c.usuario_id
LEFT JOIN cancelamentos_reaproveitados_sec cr
  ON j.usuario_id = cr.usuario_id
GROUP BY j.usuario_id, j.nome";

$stmt_stats = $conn->prepare($query_stats);
$usuario_id = $_SESSION['id'];
$stmt_stats->bind_param("iiii", $usuario_id, $usuario_id, $usuario_id, $usuario_id);
$stmt_stats->execute();
$result_stats = $stmt_stats->get_result();
$stats = $result_stats->fetch_assoc();
?>
<!-- Dashboard Stats - Todos os cards na mesma linha -->
<!-- Dashboard Stats - Todos os cards na mesma linha -->
<div class="row mb-4">
    <!-- Jornada Total -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-primary h-100">
            <div class="card-body p-2">
                <i class="material-icons dashboard-icon">schedule</i>
                <h6 class="card-title mb-1">Jornada</h6>
                <h4 class="card-text mb-1"><?= $stats['HorasJornadaTotal'] ?? '00:00' ?>h</h4>
                <small class="opacity-75 d-block">Mês atual</small>
            </div>
        </div>
    </div>
    
    <!-- Horas Realizadas -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-success h-100">
            <div class="card-body p-2">
                <i class="material-icons dashboard-icon">check_circle</i>
                <h6 class="card-title mb-1">Realizadas</h6>
                <h4 class="card-text mb-1"><?= $stats['HorasRealizadasTotal'] ?? '00:00' ?>h</h4>
                <small class="opacity-75 d-block">Mês atual</small>
            </div>
        </div>
    </div>
    
    <!-- Horas Ociosas -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-warning h-100">
            <div class="card-body p-2">
                <i class="material-icons dashboard-icon">hourglass_empty</i>
                <h6 class="card-title mb-1">Ociosas</h6>
                <h4 class="card-text mb-1"><?= $stats['HorasOciosas'] ?? '00:00' ?>h</h4>
                <?php 
                    $horasJornada = isset($stats['HorasJornadaTotal']) ? 
                        (int)explode(':', $stats['HorasJornadaTotal'])[0] + 
                        ((int)explode(':', $stats['HorasJornadaTotal'])[1] / 60) : 0;
                    $horasOciosas = isset($stats['HorasOciosas']) ? 
                        (int)explode(':', $stats['HorasOciosas'])[0] + 
                        ((int)explode(':', $stats['HorasOciosas'])[1] / 60) : 0;
                    $percentualOciosidade = $horasJornada > 0 ? ($horasOciosas / $horasJornada * 100) : 0;
                ?>
                <small class="opacity-75 d-block"><?= number_format($percentualOciosidade, 2) ?>%</small>
            </div>
        </div>
    </div>
    
    <!-- Horas Canceladas -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-danger h-100">
            <div class="card-body p-2">
                <i class="material-icons dashboard-icon">cancel</i>
                <h6 class="card-title mb-1">Canceladas</h6>
                <h4 class="card-text mb-1"><?= $stats['HorasCanceladas'] ?? '00:00' ?>h</h4>
                <small class="opacity-75 d-block">Mês atual</small>
            </div>
        </div>
    </div>
    
    <!-- Horas Reaproveitadas -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-info h-100">
            <div class="card-body p-2">
                <i class="material-icons dashboard-icon">refresh</i>
                <h6 class="card-title mb-1">Reaproveitadas</h6>
                <h4 class="card-text mb-1"><?= $stats['HorasReaproveitadas'] ?? '00:00' ?>h</h4>
                <?php 
                    $horasCanceladas = isset($stats['HorasCanceladas']) ? 
                        (int)explode(':', $stats['HorasCanceladas'])[0] + 
                        ((int)explode(':', $stats['HorasCanceladas'])[1] / 60) : 0;
                    $horasReaproveitadas = isset($stats['HorasReaproveitadas']) ? 
                        (int)explode(':', $stats['HorasReaproveitadas'])[0] + 
                        ((int)explode(':', $stats['HorasReaproveitadas'])[1] / 60) : 0;
                    $percentualReaproveitado = $horasCanceladas > 0 ? ($horasReaproveitadas / $horasCanceladas * 100) : 0;
                ?>
                <small class="opacity-75 d-block"><?= number_format($percentualReaproveitado, 2) ?>%</small>
            </div>
        </div>
    </div>

    <!-- Comissão -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-warning h-100">
            <div class="card-body p-2 position-relative">
                <div class="d-flex justify-content-between align-items-start">
                    <h6 class="card-title mb-1">Comissão</h6>
                    <i id="toggle-comissao" class="material-icons cursor-pointer" style="font-size: 18px;">visibility_off</i>
                </div>
                <h4 class="card-text mb-1 comissao-value">•••••</h4>
                <small class="opacity-75 d-block valor-hora-value">•••/h</small>
                <div class="comissao-real-values d-none">
                    R$ <?= number_format($stats['TotalComissao'] ?? 0, 2, ',', '.') ?>
                </div>
                <div class="valor-hora-real-values d-none">
                    R$ <?= number_format($stats['ValorHora'] ?? 0, 2, ',', '.') ?>/h
                </div>
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
    <label for="cliente_filter" class="form-label">Cliente</label>
    <div class="custom-select">
        <input type="text" id="cliente_filter" class="form-control" 
               placeholder="Digite contrato ou nome..."
               value="<?= $filtro_cliente ? htmlspecialchars(getClienteNameById($filtro_cliente)) : '' ?>">
        <div class="dropdown-results" id="cliente_results" style="display: none;"></div>
        <select id="cliente_id" name="cliente" class="d-none">
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
                                4 => 'Módulo'
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

<!-- Lista de Treinamentos -->
<div class="card">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5 class="mb-0">Treinamentos</h5>
            <div class="d-flex align-items-center gap-2">
                <button id="btn-alteracao-massa" class="btn btn-warning" style="display: none;" onclick="abrirModalAlteracaoMassa()">
                    <i class="material-icons me-1">edit</i> Alterar Selecionados
                </button>
                <span class="badge bg-primary">Total: <?= count($treinamentos) ?></span>
            </div>
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="40">
                            <input type="checkbox" id="select-all" class="form-check-input" onchange="toggleAllCheckboxes(this)">
                        </th>
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
                    <?php if (!empty($treinamentos)): ?>
                        <?php foreach ($treinamentos as $treinamento): ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="form-check-input treinamento-checkbox" 
                                       value="<?= $treinamento['id'] ?>" 
                                       onchange="updateBulkActionButton()">
                            </td>
                            <td>#<?= $treinamento['id'] ?></td>
                           <td>
    <a href="visualizar.php?id=<?= $treinamento['id'] ?>" style="color: #0d6efd; text-decoration: underline;">
        <?= htmlspecialchars($treinamento['titulo']) ?>
    </a>
</td>
                            <td>
                                <?= $treinamento['cliente_nome'] ? 
                                    htmlspecialchars(($treinamento['cliente_contrato'] ? $treinamento['cliente_contrato'] . ' - ' : '') . $treinamento['cliente_nome']) : '-' ?>
                            </td>
                            <td><?= $treinamento['plano_nome'] ?? '-' ?></td>
                            <td><?= $treinamento['data_treinamento'] ? date('d/m/Y H:i', strtotime($treinamento['data_treinamento'])) : '-' ?></td>
                            <td><?= $treinamento['responsavel_nome'] ?? '-' ?></td>
                            <td>
                                <span class="badge status-badge" style="background-color: <?= $treinamento['status_cor'] ?>">
                                    <?= $treinamento['status_nome'] ?>
                                </span>
                            </td>
                            <td>
                                <a href="visualizar.php?id=<?= $treinamento['id'] ?>" class="btn btn-sm btn-outline-primary" title="Visualizar">
                                    <i class="material-icons">visibility</i>
                                </a>
                                <a href="editar.php?id=<?= $treinamento['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Editar">
                                    <i class="material-icons">edit</i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center text-muted py-4">Nenhum treinamento encontrado com os filtros selecionados</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal para Alteração em Massa -->
<div class="modal fade" id="modalAlteracaoMassa" tabindex="-1" aria-labelledby="modalAlteracaoMassaLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAlteracaoMassaLabel">Alteração em Massa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAlteracaoMassa">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Treinamentos Selecionados:</label>
                        <div id="treinamentosSelecionados" class="alert alert-info">
                            <span id="quantidadeSelecionados">0</span> treinamento(s) selecionado(s)
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="novoResponsavel" class="form-label">Novo Responsável</label>
                        <select id="novoResponsavel" name="responsavel_id" class="form-select" required>
                            <option value="">Selecione um responsável</option>
                            <?php foreach ($equipe_list as $membro): ?>
                                <option value="<?= $membro['id'] ?>">
                                    <?= htmlspecialchars($membro['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="material-icons me-1">edit</i> Alterar Responsável
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer_treinamentos.php'; ?>

<script>
// Função para alternar todos os checkboxes
function toggleAllCheckboxes(selectAllCheckbox) {
    const checkboxes = document.querySelectorAll('.treinamento-checkbox');
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAllCheckbox.checked;
    });
    updateBulkActionButton();
}

// Função para atualizar a visibilidade do botão de alteração em massa
function updateBulkActionButton() {
    const checkboxes = document.querySelectorAll('.treinamento-checkbox:checked');
    const botao = document.getElementById('btn-alteracao-massa');
    const contador = document.getElementById('quantidadeSelecionados');
    
    if (botao) {
        if (checkboxes.length > 0) {
            botao.style.display = 'block';
            if (contador) {
                contador.textContent = checkboxes.length;
            }
        } else {
            botao.style.display = 'none';
        }
    }
    
    // Atualizar o checkbox "selecionar todos"
    const selectAll = document.getElementById('select-all');
    const allCheckboxes = document.querySelectorAll('.treinamento-checkbox');
    const checkedCheckboxes = document.querySelectorAll('.treinamento-checkbox:checked');
    
    if (selectAll) {
        if (checkedCheckboxes.length === 0) {
            selectAll.indeterminate = false;
            selectAll.checked = false;
        } else if (checkedCheckboxes.length === allCheckboxes.length) {
            selectAll.indeterminate = false;
            selectAll.checked = true;
        } else {
            selectAll.indeterminate = true;
        }
    }
}

// Função para abrir o modal de alteração em massa
function abrirModalAlteracaoMassa() {
    const checkboxes = document.querySelectorAll('.treinamento-checkbox:checked');
    
    if (checkboxes.length === 0) {
        alert('Selecione pelo menos um treinamento para alterar.');
        return;
    }
    
    // Atualizar contador no modal
    const contador = document.getElementById('quantidadeSelecionados');
    if (contador) {
        contador.textContent = checkboxes.length;
    }
    
    // Abrir modal
    const modalElement = document.getElementById('modalAlteracaoMassa');
    if (modalElement) {
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
    }
}

// Função para processar a alteração em massa
document.addEventListener('DOMContentLoaded', function() {
    const formElement = document.getElementById('formAlteracaoMassa');
    if (formElement) {
        formElement.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const checkboxes = document.querySelectorAll('.treinamento-checkbox:checked');
            const responsavelId = document.getElementById('novoResponsavel').value;
            
            if (!responsavelId) {
                alert('Selecione um responsável.');
                return;
            }
            
            if (checkboxes.length === 0) {
                alert('Nenhum treinamento selecionado.');
                return;
            }
            
            // Coletar IDs dos treinamentos selecionados
            const treinamentoIds = Array.from(checkboxes).map(cb => parseInt(cb.value));
            
            // Desabilitar botão de submit
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="material-icons me-1">hourglass_empty</i> Processando...';
            
            // Enviar requisição
            fetch('api/alteracao_massa.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    treinamento_ids: treinamentoIds,
                    responsavel_id: parseInt(responsavelId)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(data.message);
                    // Fechar modal
                    const modalElement = document.getElementById('modalAlteracaoMassa');
                    if (modalElement) {
                        const modal = bootstrap.Modal.getInstance(modalElement);
                        if (modal) {
                            modal.hide();
                        }
                    }
                    // Recarregar página para mostrar as alterações
                    window.location.reload();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao processar a solicitação. Tente novamente.');
            })
            .finally(() => {
                // Reabilitar botão
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // Inicializar funções
    updateBulkActionButton();
});
</script>