<?php
require_once 'includes/header_treinamentos.php';

// Verifica se o usuário é admin
if ($is_admin != 1) {
    header("Location: index.php");
    exit();
}

// Filtros padrão
$data_inicio = $_GET['data_inicio'] ?? date('Y-m-01');
$data_fim = $_GET['data_fim'] ?? date('Y-m-d');
$usuarios_selecionados = isset($_GET['usuario_id']) && is_array($_GET['usuario_id']) ? $_GET['usuario_id'] : [];

// Parâmetros de ordenação
$order_by = $_GET['order_by'] ?? 'nome';
$order_dir = $_GET['order_dir'] ?? 'asc';

// Validar direção de ordenação
$order_dir = in_array(strtolower($order_dir), ['asc', 'desc']) ? strtolower($order_dir) : 'asc';

// Busca lista de usuários para o filtro
$usuarios = getEquipe();
?>

<style>
.form-check {
    min-width: 120px;
    white-space: nowrap;
}
.gap-2 {
    gap: 0.5rem;
}
.dropdown-menu {
    padding: 10px;
}
.dropdown-item {
    white-space: normal;
}
.form-check {
    padding-left: 1.5em;
    margin-bottom: 0.5rem;
}
.dropdown-toggle::after {
    float: right;
    margin-top: 0.5em;
}
/* Estilos melhorados para a tabela */
.table-custom {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.9rem;
}
.table-custom thead th {
    background-color: #343a40;
    color: white;
    position: sticky;
    top: 0;
    z-index: 10;
    padding: 10px 12px;
    text-align: center;
    vertical-align: middle;
    cursor: pointer;
}
.table-custom thead th:hover {
    background-color: #495057;
}
.table-custom thead th.sorted-asc::after {
    content: " ↑";
    font-size: 0.8em;
}
.table-custom thead th.sorted-desc::after {
    content: " ↓";
    font-size: 0.8em;
}
.table-custom tbody tr:nth-child(even) {
    background-color: #f8f9fa;
}
.table-custom tbody tr:nth-child(odd) {
    background-color: white;
}
.table-custom tbody tr:hover {
    background-color: #e9ecef;
}
.table-custom td {
    padding: 10px 12px;
    text-align: center;
    vertical-align: middle;
    border-bottom: 1px solid #dee2e6;
}
.table-custom .text-right {
    text-align: right;
}
.table-custom .text-left {
    text-align: left;
}
.table-custom .highlight {
    font-weight: bold;
    color: #dc3545;
}
.table-responsive {
    max-height: 600px;
    overflow-y: auto;
}
</style>

<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Relatórios de Produtividade</h5>
    </div>
    <div class="card-body">
        <form method="get" class="row g-2 align-items-end">
            <!-- Data Início - Ocupa 2 colunas -->
            <div class="col-md-2 col-sm-6">
                <label for="data_inicio" class="form-label small mb-0">Data Início</label>
                <input type="date" id="data_inicio" name="data_inicio" class="form-control form-control-sm" 
                       value="<?= $data_inicio ?>">
            </div>
            
            <!-- Data Fim - Ocupa 2 colunas -->
            <div class="col-md-2 col-sm-6">
                <label for="data_fim" class="form-label small mb-0">Data Fim</label>
                <input type="date" id="data_fim" name="data_fim" class="form-control form-control-sm" 
                       value="<?= $data_fim ?>">
            </div>
        
            
            <!-- Técnico - Dropdown com checkboxes -->
            <div class="col-md-2 col-sm-6">
                <div class="dropdown">
                    <label class="form-label small mb-0">Técnicos</label>
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle w-100 text-start" type="button" 
                            id="dropdownTecnicos" data-bs-toggle="dropdown" aria-expanded="false">
                        <?= empty($usuarios_selecionados) ? 'Todos' : (count($usuarios_selecionados) === 1 ? htmlspecialchars($usuarios[array_search($usuarios_selecionados[0], array_column($usuarios, 'id'))]['nome']) : count($usuarios_selecionados).' selecionados') ?>
                    </button>
                    <ul class="dropdown-menu p-3" aria-labelledby="dropdownTecnicos" style="width: 300px; max-height: 400px; overflow-y: auto;">
                        <li>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="todos_tecnicos" name="todos_tecnicos" 
                                       <?= empty($usuarios_selecionados) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="todos_tecnicos">Todos</label>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <?php foreach ($usuarios as $usuario): 
                            $checked = in_array($usuario['id'], $usuarios_selecionados) ? 'checked' : '';
                        ?>
                            <li>
                                <div class="form-check">
                                    <input class="form-check-input tecnico-checkbox" type="checkbox" 
                                           id="usuario_<?= $usuario['id'] ?>" name="usuario_id[]" 
                                           value="<?= $usuario['id'] ?>" <?= $checked ?>>
                                    <label class="form-check-label" for="usuario_<?= $usuario['id'] ?>">
                                        <?= htmlspecialchars($usuario['nome']) ?>
                                    </label>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>
            
            <!-- Botão Filtrar ajustado -->
            <div class="col-md-auto col-sm-6">
                <button type="submit" class="btn btn-sm btn-primary">
                    <i class="material-icons align-middle" style="font-size: 16px;">filter_alt</i> Filtrar
                </button>
            </div>

            <!-- Botão Limpar ajustado -->
            <div class="col-md-auto col-sm-6">
                <a href="comissoes.php" class="btn btn-sm btn-outline-secondary">
                    <i class="material-icons align-middle" style="font-size: 16px;">clear</i> Limpar
                </a>
            </div>
            
            <!-- Campos ocultos para ordenação -->
            <input type="hidden" name="order_by" id="order_by" value="<?= htmlspecialchars($order_by) ?>">
            <input type="hidden" name="order_dir" id="order_dir" value="<?= htmlspecialchars($order_dir) ?>">
        </form>
    </div>
</div>

<?php
// Query de relatório adaptada para usar os filtros
// Substitua toda a query SQL por esta versão (cerca da linha 150)
$query = "
WITH periodo AS (
  SELECT 
    TIMESTAMP('$data_inicio 00:00:00') AS data_inicio,
    TIMESTAMP('$data_fim 23:59:59') AS data_fim
),

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

jornada_tecnica AS (
  SELECT
    u.id AS usuario_id,
    u.nome,
    d.data,
    CASE WHEN u.nome = 'Filipe' THEN 6.0 ELSE 8.5 END * 3600 AS segundos_jornada
  FROM datas_uteis d
  CROSS JOIN usuarios u
  WHERE 1=1
  " . (!empty($usuarios_selecionados) ? " AND u.id IN (" . implode(',', array_map('intval', $usuarios_selecionados)) . ")" : "") . "
),

horas_realizadas_sec AS (
  SELECT
    a.usuario_id,
    DATE(a.data_agendada) AS data,
    SUM(TIME_TO_SEC(a.horas)) AS segundos_realizados
  FROM treinamentos_agendamentos a
  JOIN periodo p ON a.data_agendada BETWEEN p.data_inicio AND p.data_fim
  WHERE a.status = 'realizado'
    " . (!empty($usuarios_selecionados) ? " AND a.usuario_id IN (" . implode(',', array_map('intval', $usuarios_selecionados)) . ")" : "") . "
    AND WEEKDAY(a.data_agendada) BETWEEN 0 AND 4
  GROUP BY a.usuario_id, DATE(a.data_agendada)
),

horas_canceladas_sec AS (
  SELECT
    a.usuario_id,
    SUM(TIME_TO_SEC(a.horas)) AS segundos_cancelados
  FROM treinamentos_agendamentos a
  JOIN periodo p ON a.data_agendada BETWEEN p.data_inicio AND p.data_fim
  WHERE a.status = 'cancelado'
    " . (!empty($usuarios_selecionados) ? " AND a.usuario_id IN (" . implode(',', array_map('intval', $usuarios_selecionados)) . ")" : "") . "
    AND WEEKDAY(a.data_agendada) BETWEEN 0 AND 4
  GROUP BY a.usuario_id
),

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
    " . (!empty($usuarios_selecionados) ? " AND c.usuario_id IN (" . implode(',', array_map('intval', $usuarios_selecionados)) . ")" : "") . "
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

  -- ValorHora (regra fixa sem critério)
  CASE
    WHEN COALESCE(MAX(c.segundos_cancelados),0) = 0 THEN 7.00
    WHEN COALESCE(MAX(cr.segundos_reaproveitados),0) / COALESCE(MAX(c.segundos_cancelados),1) >= 0.5 THEN 7.00
    ELSE 5.00
  END AS ValorHora,


  ROUND(
    COALESCE(MAX(cr.segundos_reaproveitados),0) / 
    NULLIF(COALESCE(MAX(c.segundos_cancelados),1), 0) * 100,
    2
) AS TaxaReaproveitamento,

  -- TotalComissao
  ROUND(
    (SUM(COALESCE(r.segundos_realizados,0)) / 3600) *
    (CASE
        WHEN COALESCE(MAX(c.segundos_cancelados),0) = 0 THEN 7.00
        WHEN COALESCE(MAX(cr.segundos_reaproveitados),0) / COALESCE(MAX(c.segundos_cancelados),1) >= 0.5 THEN 7.00
        ELSE 5.00
    END),
    2
  ) AS TotalComissao,

  -- Taxa de ociosidade (calculada em segundos para precisão)
  ROUND(
    GREATEST(0, SUM(j.segundos_jornada) - SUM(COALESCE(r.segundos_realizados,0))) / 
    NULLIF(SUM(j.segundos_jornada), 0) * 100,
    2
  ) AS TaxaOciosidade
   

FROM jornada_tecnica j
LEFT JOIN horas_realizadas_sec r
  ON j.usuario_id = r.usuario_id AND j.data = r.data
LEFT JOIN horas_canceladas_sec c
  ON j.usuario_id = c.usuario_id
LEFT JOIN cancelamentos_reaproveitados_sec cr
  ON j.usuario_id = cr.usuario_id
GROUP BY j.usuario_id, j.nome
ORDER BY ";

// Atualize a parte de ordenação (final da query)
switch ($order_by) {
    case 'nome':
        $query .= "j.nome $order_dir";
        break;
    case 'jornada':
        $query .= "SUM(j.segundos_jornada) $order_dir";
        break;
    case 'realizadas':
        $query .= "SUM(COALESCE(r.segundos_realizados,0)) $order_dir";
        break;
    case 'ociosas':
        $query .= "GREATEST(0, SUM(j.segundos_jornada) - SUM(COALESCE(r.segundos_realizados,0))) $order_dir";
        break;
    case 'canceladas':
        $query .= "COALESCE(MAX(c.segundos_cancelados),0) $order_dir";
        break;
    case 'nao_reaproveitadas':
        $query .= "COALESCE(MAX(cr.segundos_reaproveitados),0) $order_dir";
        break;
    case 'taxa':
        $query .= "TaxaOciosidade $order_dir";
        break;
    case 'valor_hora':
        $query .= "ValorHora $order_dir";
        break;
    case 'total_comissao':
        $query .= "TotalComissao $order_dir";
        break;
    default:
        $query .= "j.nome $order_dir";
}

$result = $conn->query($query);

$total_jornada = 0;
$total_realizadas = 0;
$total_ociosas = 0;
$total_canceladas = 0;
$total_reaproveitadas = 0;
$total_comissao = 0;

if ($result->num_rows > 0) {
    $result->data_seek(0);
    while ($row = $result->fetch_assoc()) {
        // Converter HH:MM para minutos para os cálculos
        $total_jornada += convertTimeToMinutes($row['HorasJornadaTotal']);
        $total_realizadas += convertTimeToMinutes($row['HorasRealizadasTotal']);
        $total_ociosas += convertTimeToMinutes($row['HorasOciosas']);
        $total_canceladas += convertTimeToMinutes($row['HorasCanceladas']);
        $total_reaproveitadas += convertTimeToMinutes($row['HorasReaproveitadas']);
        $total_comissao += $row['TotalComissao'];
    }
    $result->data_seek(0);
}

$taxa_ociosidade = $total_jornada > 0 ? ($total_ociosas / $total_jornada * 100) : 0;
$taxa_reaproveitamento = $total_canceladas > 0 ? ($total_reaproveitadas / $total_canceladas * 100) : 0;
?>

<!-- Dashboard Stats - Todos os cards na mesma linha -->
<!-- Substitua a seção de cards (cerca da linha 300) -->
<div class="row mb-4">
    <!-- Jornada Total -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-primary h-100">
            <div class="card-body p-2">
                <h6 class="card-title mb-1">Jornada</h6>
                <h4 class="card-text mb-1"><?= convertMinutesToTime($total_jornada) ?>h</h4>
                <small class="opacity-75 d-block">Período selecionado</small>
            </div>
        </div>
    </div>
    
    <!-- Horas Realizadas -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-success h-100">
            <div class="card-body p-2">
                <h6 class="card-title mb-1">Realizadas</h6>
                <h4 class="card-text mb-1"><?= convertMinutesToTime($total_realizadas) ?>h</h4>
                <small class="opacity-75 d-block"><?= number_format($total_jornada > 0 ? ($total_realizadas / $total_jornada * 100) : 0, 2) ?>%</small>
            </div>
        </div>
    </div>
    
    <!-- Horas Ociosas -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-danger h-100">
            <div class="card-body p-2">
                <h6 class="card-title mb-1">Ociosas</h6>
                <h4 class="card-text mb-1"><?= convertMinutesToTime($total_ociosas) ?>h</h4>
                <small class="opacity-75 d-block"><?= number_format($taxa_ociosidade, 2) ?>%</small>
            </div>
        </div>
    </div>
    
    <!-- Horas Canceladas -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-secondary h-100">
            <div class="card-body p-2">
                <h6 class="card-title mb-1">Canceladas</h6>
                <h4 class="card-text mb-1"><?= convertMinutesToTime($total_canceladas) ?>h</h4>
                <small class="opacity-75 d-block"><?= number_format($total_jornada > 0 ? ($total_canceladas / $total_jornada * 100) : 0, 2) ?>%</small>
            </div>
        </div>
    </div>
    
    <!-- Horas Reaproveitadas -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-info h-100">
            <div class="card-body p-2">
                <h6 class="card-title mb-1">Reaproveitadas</h6>
                <h4 class="card-text mb-1"><?= convertMinutesToTime($total_reaproveitadas) ?>h</h4>
                <small class="opacity-75 d-block"><?= number_format($taxa_reaproveitamento, 2) ?>%</small>
            </div>
        </div>
    </div>

    <!-- Comissão -->
    <div class="col-md-2 col-sm-6">
        <div class="card text-white bg-warning h-100">
            <div class="card-body p-2">
                <h6 class="card-title mb-1">Comissão</h6>
                <h4 class="card-text mb-1">R$ <?= number_format($total_comissao, 2, ',', '.') ?></h4>
                <small class="opacity-75 d-block">R$ <?= number_format($total_realizadas > 0 ? ($total_comissao / ($total_realizadas/60)) : 0, 2, ',', '.') ?>/h</small>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table-custom">
                <thead>
                    <tr>
                        <th class="text-left <?= $order_by == 'nome' ? 'sorted-'.$order_dir : '' ?>" 
                            onclick="sortTable('nome', '<?= $order_by == 'nome' && $order_dir == 'asc' ? 'desc' : 'asc' ?>')">Técnico</th>
                        <th class="<?= $order_by == 'jornada' ? 'sorted-'.$order_dir : '' ?>" 
                            onclick="sortTable('jornada', '<?= $order_by == 'jornada' && $order_dir == 'asc' ? 'desc' : 'asc' ?>')">Jornada</th>
                        <th class="<?= $order_by == 'realizadas' ? 'sorted-'.$order_dir : '' ?>" 
                            onclick="sortTable('realizadas', '<?= $order_by == 'realizadas' && $order_dir == 'asc' ? 'desc' : 'asc' ?>')">Realizadas</th>
                        <th class="<?= $order_by == 'ociosas' ? 'sorted-'.$order_dir : '' ?>" 
                            onclick="sortTable('ociosas', '<?= $order_by == 'ociosas' && $order_dir == 'asc' ? 'desc' : 'asc' ?>')">Ociosas</th>
                        <th class="<?= $order_by == 'canceladas' ? 'sorted-'.$order_dir : '' ?>" 
                            onclick="sortTable('canceladas', '<?= $order_by == 'canceladas' && $order_dir == 'asc' ? 'desc' : 'asc' ?>')">Canceladas</th>
                        <th class="<?= $order_by == 'nao_reaproveitadas' ? 'sorted-'.$order_dir : '' ?>" 
                            onclick="sortTable('nao_reaproveitadas', '<?= $order_by == 'nao_reaproveitadas' && $order_dir == 'asc' ? 'desc' : 'asc' ?>')">Reaproveitadas</th>
                        <th class="<?= $order_by == 'taxa' ? 'sorted-'.$order_dir : '' ?>" 
                            onclick="sortTable('taxa', '<?= $order_by == 'taxa' && $order_dir == 'asc' ? 'desc' : 'asc' ?>')">Taxa (%)</th>
                        <th class="<?= $order_by == 'valor_hora' ? 'sorted-'.$order_dir : '' ?>" 
                            onclick="sortTable('valor_hora', '<?= $order_by == 'valor_hora' && $order_dir == 'asc' ? 'desc' : 'asc' ?>')">Valor Hora</th>
                        <th class="<?= $order_by == 'total_comissao' ? 'sorted-'.$order_dir : '' ?>" 
                            onclick="sortTable('total_comissao', '<?= $order_by == 'total_comissao' && $order_dir == 'asc' ? 'desc' : 'asc' ?>')">Total Comissão</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_comissao = 0;
                    $row_count = 0;
                    
                    if ($result->num_rows > 0) {
                        while ($row = $result->fetch_assoc()) {
                            $total_comissao += $row['TotalComissao'];
                            $row_count++;
                            $taxa_class = $row['TaxaOciosidade'] > 20 ? 'highlight' : '';
                            ?>
                            <tr>
                                <td class="text-left"><?= htmlspecialchars($row['nome']) ?></td>
                             <td><?= $row['HorasJornadaTotal'] ?>h</td>
<td><?= $row['HorasRealizadasTotal'] ?>h</td>
<td><?= $row['HorasOciosas'] ?>h</td>
<td><?= $row['HorasCanceladas'] ?>h</td>
<td><?= $row['HorasReaproveitadas'] ?>h</td>
<td><?= $row['HorasReaproveitadas'] === '00:00' ? '0.00%' : number_format($row['TaxaReaproveitamento'], 2).'%' ?></td>
                                <td>R$ <?= number_format($row['ValorHora'], 2, ',', '.') ?></td>
                                <td>R$ <?= number_format($row['TotalComissao'], 2, ',', '.') ?></td>
                            </tr>
                            <?php
                        }
                    } else {
                        echo '<tr><td colspan="9" class="text-center text-muted py-4">Nenhum resultado encontrado</td></tr>';
                    }
                    ?>
    
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const todosCheckbox = document.getElementById('todos_tecnicos');
    const tecnicosCheckboxes = document.querySelectorAll('.tecnico-checkbox');
    const dropdownButton = document.getElementById('dropdownTecnicos');
    
    // Quando "Todos" é marcado/desmarcado
    todosCheckbox.addEventListener('change', function() {
        tecnicosCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        updateDropdownText();
    });
    
    // Quando um técnico específico é marcado/desmarcado
    tecnicosCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const allChecked = Array.from(tecnicosCheckboxes).every(cb => cb.checked);
            todosCheckbox.checked = allChecked;
            updateDropdownText();
        });
    });
    
    // Atualiza o texto do botão dropdown
    function updateDropdownText() {
        const selectedCheckboxes = Array.from(tecnicosCheckboxes).filter(cb => cb.checked);
        
        if (todosCheckbox.checked || selectedCheckboxes.length === 0 || selectedCheckboxes.length === tecnicosCheckboxes.length) {
            dropdownButton.textContent = 'Todos';
        } else if (selectedCheckboxes.length === 1) {
            const selectedName = document.querySelector(`label[for="${selectedCheckboxes[0].id}"]`).textContent;
            dropdownButton.textContent = selectedName;
        } else {
            dropdownButton.textContent = `${selectedCheckboxes.length} selecionados`;
        }
    }
    
    // Fechar o dropdown quando clicar fora
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.dropdown');
        if (!dropdown.contains(event.target)) {
            const dropdownMenu = dropdown.querySelector('.dropdown-menu');
            dropdownMenu.classList.remove('show');
        }
    });
});

function sortTable(column, direction) {
    document.getElementById('order_by').value = column;
    document.getElementById('order_dir').value = direction;
    document.querySelector('form').submit();
}
</script>

<?php require_once 'includes/footer_treinamentos.php'; ?>