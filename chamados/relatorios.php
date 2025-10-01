<?php
require_once 'includes/header.php';


// Função para determinar a cor com base na taxa de eficiência
function getCorEficiencia($taxa) {
    if ($taxa >= 70) return '#28a745'; // Verde
    if ($taxa >= 51) return '#ffc107'; // Amarelo
    return '#dc3545'; // Vermelho
}

// Função para determinar a cor com base na taxa de retorno
function getCorRetorno($taxa) {
    if ($taxa <= 30) return '#28a745'; // Verde
    if ($taxa <= 49) return '#ffc107'; // Amarelo
    return '#dc3545'; // Vermelho
}

// Função para gerar o HTML do indicador com cor dinâmica
function getIndicadorColorido($valor, $cor, $titulo, $subtitulo = '') {
    return '
    <div class="indicador-comentarios" style="background: linear-gradient(135deg, ' . $cor . ' 0%, ' . escurecerCor($cor, 20) . ' 100%);">
        <h3>' . $titulo . '</h3>
        <p class="valor">' . number_format($valor, 2, ',', '.') . '%</p>
        <small>' . $subtitulo . '</small>
    </div>';
}

// Função auxiliar para escurecer uma cor
function escurecerCor($cor, $percentual) {
    // Converte cor HEX para RGB
    list($r, $g, $b) = sscanf($cor, "#%02x%02x%02x");
    
    // Escurece a cor
    $r = max(0, min(255, $r - $r * $percentual / 100));
    $g = max(0, min(255, $g - $g * $percentual / 100));
    $b = max(0, min(255, $b - $b * $percentual / 100));
    
    // Converte de volta para HEX
    return sprintf("#%02x%02x%02x", $r, $g, $b);
}

// Filtros de período
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$filtro_status = isset($_GET['status']) ? (is_array($_GET['status']) ? $_GET['status'] : [$_GET['status']]) : [];
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_prioridade = isset($_GET['prioridade']) ? $_GET['prioridade'] : '';
$filtro_responsavel = isset($_GET['responsavel']) ? $_GET['responsavel'] : '';
$filtro_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$filtro_sprint = isset($_GET['sprint']) ? $_GET['sprint'] : '';
$filtro_release = isset($_GET['release']) ? $_GET['release'] : '';
$filtro_tipo_comentario = isset($_GET['tipo_comentario']) ? $_GET['tipo_comentario'] : '';
$filtro_menu = isset($_GET['menu']) ? $_GET['menu'] : '';
$filtro_submenu = isset($_GET['submenu']) ? $_GET['submenu'] : '';

// Construir cláusula WHERE
$where_conditions = [];
$where_params = [];
$types = '';

// Filtros básicos
if (!empty($filtro_data_inicio) && !empty($filtro_data_fim)) {
    $where_conditions[] = "DATE(c.data_criacao) BETWEEN ? AND ?";
    $where_params[] = $filtro_data_inicio;
    $where_params[] = $filtro_data_fim;
    $types .= 'ss';
}
if (!empty($filtro_status) && !in_array('', $filtro_status)) {
    // Remove valores vazios do array
    $filtro_status = array_filter($filtro_status, function($value) { return $value !== ''; });
    
    if (!empty($filtro_status)) {
        $placeholders = str_repeat('?,', count($filtro_status) - 1) . '?';
        $where_conditions[] = "c.status_id IN ($placeholders)";
        foreach ($filtro_status as $status) {
            $where_params[] = $status;
            $types .= 's';
        }
    }
}
if (!empty($filtro_tipo)) {
    $where_conditions[] = "c.tipo_id = ?";
    $where_params[] = $filtro_tipo;
    $types .= 's';
}
if (!empty($filtro_prioridade)) {
    $where_conditions[] = "c.prioridade_id = ?";
    $where_params[] = $filtro_prioridade;
    $types .= 's';
}
if (!empty($filtro_responsavel)) {
    if ($filtro_responsavel == 'sem') {
        $where_conditions[] = "c.responsavel_id IS NULL";
    } else {
        $where_conditions[] = "c.responsavel_id = ?";
        $where_params[] = $filtro_responsavel;
        $types .= 's';
    }
}
if (!empty($filtro_cliente)) {
    $where_conditions[] = "c.cliente_id = ?";
    $where_params[] = $filtro_cliente;
    $types .= 's';
}
if (!empty($filtro_sprint)) {
    $where_conditions[] = "c.sprint_id = ?";
    $where_params[] = $filtro_sprint;
    $types .= 's';
}

if (!empty($filtro_menu)) {
    $where_conditions[] = "c.menu_id = ?";
    $where_params[] = $filtro_menu;
    $types .= 's';
}

if (!empty($filtro_submenu)) {
    $where_conditions[] = "c.submenu_id = ?";
    $where_params[] = $filtro_submenu;
    $types .= 's';
}
if (!empty($filtro_release)) {
    $where_conditions[] = "c.release_id = ?";
    $where_params[] = $filtro_release;
    $types .= 's';
}

// FILTRO CORRIGIDO: Tipo de comentário
$join_comentarios = '';
$where_conditions_comentarios = [];
$where_params_comentarios = [];
$types_comentarios = '';

if (!empty($filtro_tipo_comentario)) {
    $join_comentarios = "JOIN chamados_comentarios cc ON c.id = cc.chamado_id";
    $where_conditions_comentarios[] = "cc.tipo_comentario = ?";
    $where_params_comentarios[] = $filtro_tipo_comentario;
    $types_comentarios .= 's';
}

// Construir as cláusulas WHERE
$where_clause_sem_comentarios = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
$where_clause = !empty($where_conditions) || !empty($where_conditions_comentarios) ? 'WHERE ' . implode(' AND ', array_merge($where_conditions, $where_conditions_comentarios)) : '';

// Parâmetros para consultas sem comentários
$where_params_sem_comentarios = $where_params;
$types_sem_comentarios = $types;

// Parâmetros para consultas com comentários
$where_params = array_merge($where_params, $where_params_comentarios);
$types .= $types_comentarios;

// 1. Total de chamados no período - MODIFICADO para usar COUNT DISTINCT
$query_total = "SELECT COUNT(DISTINCT c.id) as total FROM chamados c $join_comentarios $where_clause";
$stmt_total = $conn->prepare($query_total);
if (!empty($where_params)) {
    $stmt_total->bind_param($types, ...$where_params);
}
$stmt_total->execute();
$result_total = $stmt_total->get_result();
$total_chamados = $result_total->fetch_assoc()['total'];



// 2. Chamados por status - CORRIGIDO
$query_por_status = "SELECT cs.nome, cs.cor, COUNT(DISTINCT c.id) as total 
                    FROM chamados c 
                    $join_comentarios
                    JOIN chamados_status cs ON c.status_id = cs.id 
                    $where_clause 
                    GROUP BY cs.id, cs.nome, cs.cor 
                    ORDER BY total DESC";
$stmt_por_status = $conn->prepare($query_por_status);
if (!empty($where_params)) {
    $stmt_por_status->bind_param($types, ...$where_params);
}
$stmt_por_status->execute();
$result_por_status = $stmt_por_status->get_result();
$chamados_por_status = $result_por_status->fetch_all(MYSQLI_ASSOC);

// Adicionar porcentagem para cada status
foreach ($chamados_por_status as &$status) {
    $status['percentual'] = $total_chamados > 0 ? round(($status['total'] / $total_chamados) * 100, 2) : 0;
}
unset($status);



$query_por_menu = "SELECT 
    COALESCE(m.nome, 'Sem menu') as nome, 
    COUNT(DISTINCT c.id) as total 
FROM chamados c 
$join_comentarios
LEFT JOIN menu_atendimento m ON c.menu_id = m.id 
$where_clause 
GROUP BY m.id, m.nome 
ORDER BY total DESC";
$stmt_por_menu = $conn->prepare($query_por_menu);
if (!empty($where_params)) {
    $stmt_por_menu->bind_param($types, ...$where_params);
}
$stmt_por_menu->execute();
$result_por_menu = $stmt_por_menu->get_result();
$chamados_por_menu = $result_por_menu->fetch_all(MYSQLI_ASSOC);

// Adicionar porcentagem para cada menu
foreach ($chamados_por_menu as &$menu) {
    $menu['percentual'] = $total_chamados > 0 ? round(($menu['total'] / $total_chamados) * 100, 2) : 0;
}
unset($menu);

// 12. Chamados por Submenu - NOVA CONSULTA
$query_por_submenu = "SELECT 
    COALESCE(sm.nome, 'Sem submenu') as nome, 
    COUNT(DISTINCT c.id) as total 
FROM chamados c 
$join_comentarios
LEFT JOIN submenu_atendimento sm ON c.submenu_id = sm.id 
$where_clause 
GROUP BY sm.id, sm.nome 
ORDER BY total DESC";
$stmt_por_submenu = $conn->prepare($query_por_submenu);
if (!empty($where_params)) {
    $stmt_por_submenu->bind_param($types, ...$where_params);
}
$stmt_por_submenu->execute();
$result_por_submenu = $stmt_por_submenu->get_result();
$chamados_por_submenu = $result_por_submenu->fetch_all(MYSQLI_ASSOC);

// Adicionar porcentagem para cada submenu
foreach ($chamados_por_submenu as &$submenu) {
    $submenu['percentual'] = $total_chamados > 0 ? round(($submenu['total'] / $total_chamados) * 100, 2) : 0;
}
unset($submenu);

// 3. Chamados por tipo - CORRIGIDO
$query_por_tipo = "SELECT ct.nome, ct.icone, COUNT(DISTINCT c.id) as total 
                  FROM chamados c 
                  $join_comentarios
                  JOIN chamados_tipos ct ON c.tipo_id = ct.id 
                  $where_clause 
                  GROUP BY ct.id, ct.nome, ct.icone 
                  ORDER BY total DESC";
$stmt_por_tipo = $conn->prepare($query_por_tipo);
if (!empty($where_params)) {
    $stmt_por_tipo->bind_param($types, ...$where_params);
}
$stmt_por_tipo->execute();
$result_por_tipo = $stmt_por_tipo->get_result();
$chamados_por_tipo = $result_por_tipo->fetch_all(MYSQLI_ASSOC);

// Adicionar porcentagem para cada tipo
foreach ($chamados_por_tipo as &$tipo) {
    $tipo['percentual'] = $total_chamados > 0 ? round(($tipo['total'] / $total_chamados) * 100, 2) : 0;
}
unset($tipo);

// 4. Chamados por prioridade - CORRIGIDO
$query_por_prioridade = "SELECT cp.nome, cp.cor, COUNT(DISTINCT c.id) as total 
                        FROM chamados c 
                        $join_comentarios
                        JOIN chamados_prioridades cp ON c.prioridade_id = cp.id 
                        $where_clause 
                        GROUP BY cp.id, cp.nome, cp.cor 
                        ORDER BY total DESC";
$stmt_por_prioridade = $conn->prepare($query_por_prioridade);
if (!empty($where_params)) {
    $stmt_por_prioridade->bind_param($types, ...$where_params);
}
$stmt_por_prioridade->execute();
$result_por_prioridade = $stmt_por_prioridade->get_result();
$chamados_por_prioridade = $result_por_prioridade->fetch_all(MYSQLI_ASSOC);

// Adicionar porcentagem para cada prioridade
foreach ($chamados_por_prioridade as &$prioridade) {
    $prioridade['percentual'] = $total_chamados > 0 ? round(($prioridade['total'] / $total_chamados) * 100, 2) : 0;
}
unset($prioridade);

// 5. Chamados por responsável (incluindo "Sem responsável") - CORRIGIDO
$query_por_responsavel = "SELECT 
                            COALESCE(u.nome, 'Sem responsável') as nome, 
                            COUNT(DISTINCT c.id) as total 
                         FROM chamados c 
                         $join_comentarios
                         LEFT JOIN usuarios u ON c.responsavel_id = u.id 
                         $where_clause 
                         GROUP BY u.id, u.nome 
                         ORDER BY total DESC";
$stmt_por_responsavel = $conn->prepare($query_por_responsavel);
if (!empty($where_params)) {
    $stmt_por_responsavel->bind_param($types, ...$where_params);
}
$stmt_por_responsavel->execute();
$result_por_responsavel = $stmt_por_responsavel->get_result();
$chamados_por_responsavel = $result_por_responsavel->fetch_all(MYSQLI_ASSOC);

// Adicionar porcentagem para cada responsável
foreach ($chamados_por_responsavel as &$responsavel) {
    $responsavel['percentual'] = $total_chamados > 0 ? round(($responsavel['total'] / $total_chamados) * 100, 2) : 0;
}
unset($responsavel);

// 10. Taxa de conclusão - CORRIGIDO
$query_concluidos = "SELECT COUNT(DISTINCT c.id) as concluidos FROM chamados c 
                    $join_comentarios
                    WHERE c.status_id IN (5)";
if (!empty($where_clause)) {
    $query_concluidos .= " AND " . str_replace("WHERE ", "", $where_clause);
}

$stmt_concluidos = $conn->prepare($query_concluidos);
if (!empty($where_params)) {
    $stmt_concluidos->bind_param($types, ...$where_params);
}
$stmt_concluidos->execute();
$result_concluidos = $stmt_concluidos->get_result();
$concluidos = $result_concluidos->fetch_assoc()['concluidos'];
$taxa_conclusao = $total_chamados > 0 ? round(($concluidos / $total_chamados) * 100, 2) : 0;

// =============================================================================
// NOVAS CONSULTAS PARA ANÁLISE DE COMENTÁRIOS - CORRIGIDAS
// =============================================================================

// 1. TAXA DE EFICIÊNCIA CORRIGIDA (100% se nenhum chamado concluído teve retorno)
// Primeiro verificar se há chamados filtrados
if ($total_chamados > 0) {
    $query_taxa_eficiencia = "SELECT 
        COUNT(DISTINCT c.id) as total_concluidos,
        SUM(CASE WHEN EXISTS (
            SELECT 1 FROM chamados_comentarios cc 
            WHERE cc.chamado_id = c.id 
            AND cc.tipo_comentario = 'retorno_teste'
        ) THEN 1 ELSE 0 END) as com_retorno_teste,
        CASE 
            WHEN COUNT(DISTINCT c.id) > 0 THEN 
                100 - ROUND((SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM chamados_comentarios cc 
                    WHERE cc.chamado_id = c.id 
                    AND cc.tipo_comentario = 'retorno_teste'
                ) THEN 1 ELSE 0 END) / COUNT(DISTINCT c.id)) * 100, 2)
            ELSE 0 
        END as taxa_eficiencia
    FROM chamados c
    WHERE c.status_id = 5 " . // MODIFICADO: Apenas status 5 (concluído)
    (!empty($where_clause_sem_comentarios) ? " AND " . str_replace("WHERE ", "", $where_clause_sem_comentarios) : "");

    $stmt_taxa_eficiencia = $conn->prepare($query_taxa_eficiencia);
    if (!empty($where_params_sem_comentarios)) {
        $stmt_taxa_eficiencia->bind_param($types_sem_comentarios, ...$where_params_sem_comentarios);
    }
    $stmt_taxa_eficiencia->execute();
    $result_taxa_eficiencia = $stmt_taxa_eficiencia->get_result();
    $taxa_eficiencia_data = $result_taxa_eficiencia->fetch_assoc();
} else {
    // Se não há chamados filtrados, retornar valores zerados
    $taxa_eficiencia_data = [
        'total_concluidos' => 0,
        'com_retorno_teste' => 0,
        'taxa_eficiencia' => 0
    ];
}

// 2. TAXA DE RETORNO (Chamados com comentários de retorno de teste) - MODIFICADO: Apenas chamados concluídos
if ($total_chamados > 0) {
    $query_taxa_retorno = "SELECT 
        COUNT(DISTINCT c.id) as total_chamados,
        SUM(CASE WHEN EXISTS (
            SELECT 1 FROM chamados_comentarios cc 
            WHERE cc.chamado_id = c.id 
            AND cc.tipo_comentario = 'retorno_teste'
        ) THEN 1 ELSE 0 END) as com_retorno,
        CASE 
            WHEN COUNT(DISTINCT c.id) > 0 THEN 
                ROUND((SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM chamados_comentarios cc 
                    WHERE cc.chamado_id = c.id 
                    AND cc.tipo_comentario = 'retorno_teste'
                ) THEN 1 ELSE 0 END) / COUNT(DISTINCT c.id)) * 100, 2)
            ELSE 0 
        END as taxa_retorno
    FROM chamados c 
    WHERE c.status_id = 5 " . // MODIFICADO: Apenas status 5 (concluído)
    (!empty($where_clause_sem_comentarios) ? " AND " . str_replace("WHERE ", "", $where_clause_sem_comentarios) : "");

    $stmt_taxa_retorno = $conn->prepare($query_taxa_retorno);
    if (!empty($where_params_sem_comentarios)) {
        $stmt_taxa_retorno->bind_param($types_sem_comentarios, ...$where_params_sem_comentarios);
    }
    $stmt_taxa_retorno->execute();
    $result_taxa_retorno = $stmt_taxa_retorno->get_result();
    $taxa_retorno_data = $result_taxa_retorno->fetch_assoc();
} else {
    // Se não há chamados filtrados, retornar valores zerados
    $taxa_retorno_data = [
        'total_chamados' => 0,
        'com_retorno' => 0,
        'taxa_retorno' => 0
    ];
}

// 3. COMENTÁRIOS POR RESPONSÁVEL (Agrupado)
$query_comentarios_responsavel = "SELECT 
    COALESCE(u.nome, 'Sem responsável') as responsavel_nome,
    COUNT(cc.id) as total_comentarios,
    COUNT(DISTINCT CASE WHEN cc.tipo_comentario = 'geral' THEN cc.id END) as comentarios_geral,
    COUNT(DISTINCT CASE WHEN cc.tipo_comentario = 'analise_desenvolvimento' THEN cc.id END) as analise_desenvolvimento,
    COUNT(DISTINCT CASE WHEN cc.tipo_comentario = 'analise_teste' THEN cc.id END) as analise_teste,
    COUNT(DISTINCT CASE WHEN cc.tipo_comentario = 'retorno_teste' THEN cc.id END) as retorno_teste
FROM chamados c
LEFT JOIN usuarios u ON c.responsavel_id = u.id
LEFT JOIN chamados_comentarios cc ON c.id = cc.chamado_id
$where_clause
GROUP BY u.id, u.nome
ORDER BY total_comentarios DESC";

$stmt_comentarios_responsavel = $conn->prepare($query_comentarios_responsavel);
if (!empty($where_params)) {
    $stmt_comentarios_responsavel->bind_param($types, ...$where_params);
}
$stmt_comentarios_responsavel->execute();
$result_comentarios_responsavel = $stmt_comentarios_responsavel->get_result();
$comentarios_por_responsavel = $result_comentarios_responsavel->fetch_all(MYSQLI_ASSOC);


// 13. REINCIDÊNCIA DE COMENTÁRIOS DE RETORNO - MODIFICADO: Apenas chamados concluídos
if ($total_chamados > 0) {
    $query_reincidencia = "SELECT 
        c.responsavel_id,
        COALESCE(u.nome, 'Sem responsável') as responsavel_nome,
        COUNT(DISTINCT c.id) as total_concluidos,
        SUM(CASE WHEN retornos.quantidade_retornos > 1 THEN 1 ELSE 0 END) as chamados_com_reincidencia,
        SUM(CASE WHEN retornos.quantidade_retornos > 1 THEN retornos.quantidade_retornos - 1 ELSE 0 END) as total_reincidencias,
        CASE 
            WHEN COUNT(DISTINCT c.id) > 0 THEN 
                ROUND((SUM(CASE WHEN retornos.quantidade_retornos > 1 THEN 1 ELSE 0 END) / COUNT(DISTINCT c.id)) * 100, 2)
            ELSE 0 
        END as taxa_reincidencia
    FROM chamados c
    LEFT JOIN usuarios u ON c.responsavel_id = u.id
    LEFT JOIN (
        SELECT 
            chamado_id,
            COUNT(*) as quantidade_retornos
        FROM chamados_comentarios 
        WHERE tipo_comentario = 'retorno_teste'
        GROUP BY chamado_id
    ) retornos ON c.id = retornos.chamado_id
    WHERE c.status_id = 5 " . // MODIFICADO: Apenas status 5 (concluído)
    (!empty($where_clause_sem_comentarios) ? " AND " . str_replace("WHERE ", "", $where_clause_sem_comentarios) : "") . "
    GROUP BY c.responsavel_id, u.nome
    ORDER BY taxa_reincidencia DESC";

    $stmt_reincidencia = $conn->prepare($query_reincidencia);
    if (!empty($where_params_sem_comentarios)) {
        $stmt_reincidencia->bind_param($types_sem_comentarios, ...$where_params_sem_comentarios);
    }
    $stmt_reincidencia->execute();
    $result_reincidencia = $stmt_reincidencia->get_result();
    $reincidencia_por_responsavel = $result_reincidencia->fetch_all(MYSQLI_ASSOC);
} else {
    // Se não há chamados filtrados, retornar array vazio
    $reincidencia_por_responsavel = [];
}

// 4. TAXA DE EFICIÊNCIA POR RESPONSÁVEL (Novo gráfico) - MODIFICADA
if ($total_chamados > 0) {
    $query_eficiencia_responsavel = "SELECT 
        COALESCE(u.nome, 'Sem responsável') as responsavel_nome,
        COUNT(DISTINCT c.id) as total_concluidos,
        SUM(CASE WHEN EXISTS (
            SELECT 1 FROM chamados_comentarios cc 
            WHERE cc.chamado_id = c.id 
            AND cc.tipo_comentario = 'retorno_teste'
        ) THEN 1 ELSE 0 END) as com_retorno_teste,
        CASE 
            WHEN COUNT(DISTINCT c.id) > 0 THEN 
                100 - ROUND((SUM(CASE WHEN EXISTS (
                    SELECT 1 FROM chamados_comentarios cc 
                    WHERE cc.chamado_id = c.id 
                    AND cc.tipo_comentario = 'retorno_teste'
                ) THEN 1 ELSE 0 END) / COUNT(DISTINCT c.id)) * 100, 2)
            ELSE 0 
        END as taxa_eficiencia
    FROM chamados c
    LEFT JOIN usuarios u ON c.responsavel_id = u.id
    WHERE c.status_id = 5 " . // MODIFICADO: Apenas status 5 (concluído)
    (!empty($where_clause_sem_comentarios) ? " AND " . str_replace("WHERE ", "", $where_clause_sem_comentarios) : "") . "
    GROUP BY u.id, u.nome
    HAVING total_concluidos > 0
    ORDER BY taxa_eficiencia DESC";

    $stmt_eficiencia_responsavel = $conn->prepare($query_eficiencia_responsavel);
    if (!empty($where_params_sem_comentarios)) {
        $stmt_eficiencia_responsavel->bind_param($types_sem_comentarios, ...$where_params_sem_comentarios);
    }
    $stmt_eficiencia_responsavel->execute();
    $result_eficiencia_responsavel = $stmt_eficiencia_responsavel->get_result();
    $eficiencia_por_responsavel = $result_eficiencia_responsavel->fetch_all(MYSQLI_ASSOC);

    // Adicionar cores para cada responsável baseado na taxa de eficiência
    foreach ($eficiencia_por_responsavel as &$responsavel) {
        $responsavel['cor'] = getCorEficiencia($responsavel['taxa_eficiencia']);
    }
    unset($responsavel);
} else {
    // Se não há chamados filtrados, retornar array vazio
    $eficiencia_por_responsavel = [];
}


// ... o restante do código permanece igual ...

// Buscar dados para filtros
$status_list = getChamadosStatus();
$tipos_list = getChamadosTipos();
$prioridades_list = getChamadosPrioridades();
$equipe_list = getUsuariosEquipe();
$clientes_list = getClientes();
$sprints_list = getSprintsAtivas();
$releases_list = getReleasesAtivas();
$menus_list = getMenusAtendimento();
$submenus_list = getSubmenusAtendimento();


// Lista de tipos de comentários para o filtro
$tipos_comentario_list = [
    ['valor' => 'geral', 'nome' => 'Geral'],
    ['valor' => 'analise_desenvolvimento', 'nome' => 'Análise de Desenvolvimento'],
    ['valor' => 'analise_teste', 'nome' => 'Análise de Teste'],
    ['valor' => 'retorno_teste', 'nome' => 'Retorno de Teste']
];

// Preparar dados para o filtro de cliente
$query_clientes = "SELECT id, CONCAT(IFNULL(CONCAT(contrato, ' - '), ''), nome) AS nome_completo FROM clientes ORDER BY nome";
$result_clientes = $conn->query($query_clientes);
$clientes_filtro = $result_clientes->fetch_all(MYSQLI_ASSOC);

// Paginação para a listagem de chamados
$page_chamados = isset($_GET['page_chamados']) ? (int)$_GET['page_chamados'] : 1;
$itemsPerPage_chamados = 10;
$offset_chamados = ($page_chamados - 1) * $itemsPerPage_chamados;

// Query para buscar os chamados filtrados
// Query para buscar os chamados filtrados - CORRIGIDA para evitar duplicação
$query_chamados = "SELECT DISTINCT c.*, 
    cs.nome as status_nome, cs.cor as status_cor,
    ct.nome as tipo_nome, ct.icone as tipo_icone,
    cp.nome as prioridade_nome, cp.cor as prioridade_cor,
    u.nome as responsavel_nome,
    cli.nome as cliente_nome, cli.contrato as cliente_contrato
FROM chamados c
LEFT JOIN chamados_status cs ON c.status_id = cs.id
LEFT JOIN chamados_tipos ct ON c.tipo_id = ct.id
LEFT JOIN chamados_prioridades cp ON c.prioridade_id = cp.id
LEFT JOIN usuarios u ON c.responsavel_id = u.id
LEFT JOIN clientes cli ON c.cliente_id = cli.id
$join_comentarios
$where_clause 
ORDER BY c.data_criacao DESC 
LIMIT $offset_chamados, $itemsPerPage_chamados";

$stmt_chamados = $conn->prepare($query_chamados);
if (!empty($where_params)) {
    $stmt_chamados->bind_param($types, ...$where_params);
}
$stmt_chamados->execute();
$result_chamados = $stmt_chamados->get_result();
$chamados_lista = $result_chamados->fetch_all(MYSQLI_ASSOC);

// Total de chamados para paginação
$query_total_chamados = "SELECT COUNT(DISTINCT c.id) as total FROM chamados c $join_comentarios $where_clause";
$stmt_total_chamados = $conn->prepare($query_total_chamados);
if (!empty($where_params)) {
    $stmt_total_chamados->bind_param($types, ...$where_params);
}
$stmt_total_chamados->execute();
$result_total_chamados = $stmt_total_chamados->get_result();
$total_chamados_lista = $result_total_chamados->fetch_assoc()['total'];
$totalPages_chamados = ceil($total_chamados_lista / $itemsPerPage_chamados);
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Chamados</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        html, body {
            overflow-x: hidden;
        }
        
        main {
            padding: 20px;
            max-width: 100%;
            margin: 20px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-left: 20px;
            margin-right: 20px;
            overflow-x: hidden;
        }

        .filtros {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .filtros h2 {
            margin-top: 0;
            font-size: 18px;
            color: #023324;
            display: inline-block;
        }

        .filtros-form {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
        }

        .filtro-group {
            margin: 4px;
        }

        .filtro-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #023324;
            font-size: 13px;
        }

        .filtros-botoes button {
            background-color: #023324;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        /* Estilos para dropdown de filtros */
        .dropdown-filter {
            position: relative;
        }

        .dropdown-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
            cursor: pointer;
            font-size: 13px;
            transition: border-color 0.3s ease;
        }

        .dropdown-header:hover {
            border-color: #023324;
        }

        .dropdown-arrow {
            font-size: 10px;
            transition: transform 0.3s ease;
        }

        .dropdown-arrow.rotated {
            transform: rotate(180deg);
        }

        /* Estilos para checkboxes */
        .checkbox-group {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            overflow-x: hidden;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            padding: 8px;
            background-color: white;
            z-index: 1000;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            /* Propriedades para isolar o scroll */
            overscroll-behavior: contain;
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }

        .checkbox-item {
            display: flex;
            align-items: center;
            padding: 2px 0;
            margin-bottom: 1px;
        }

        .checkbox-item input[type="checkbox"] {
            margin-right: 4px;
            cursor: pointer;
            width: 14px;
            height: 14px;
            flex-shrink: 0;
        }

        .checkbox-item label {
            cursor: pointer;
            margin-bottom: 0;
            font-size: 12px;
            flex: 1;
        }

        .checkbox-item:hover {
            background-color: #f0f0f0;
            border-radius: 3px;
        }


        /* relatorios.php - Adicione estes estilos no CSS */

/* Estilos para filtros preenchidos */
.filtro-group.filtro-preenchido select,
.filtro-group.filtro-preenchido input {
    border: 1px solid #023324;
    background-color: #f0fff4;
    font-weight: 600;
}

.filtro-group.filtro-preenchido label {
    color: #023324;
    font-weight: 600;
}

/* Cores diferentes para cada tipo de filtro */
.filtro-group.filtro-data-preenchido select,
.filtro-group.filtro-data-preenchido input {
    border-color: #8CC053;
    background-color: white;
}

.filtro-group.filtro-status-preenchido select {
      border-color: #8CC053;
    background-color: white;
}

.filtro-group.filtro-tipo-preenchido select {
    border-color: #8CC053;
    background-color: white;
}

.filtro-group.filtro-prioridade-preenchido select {
    border-color: #8CC053;
   background-color: white;
}

.filtro-group.filtro-responsavel-preenchido select {
      border-color: #8CC053;
    background-color: white;
}

.filtro-group.filtro-cliente-preenchido .custom-select input {
    border-color: #8CC053;
    background-color: white;
}

.filtro-group.filtro-menu-preenchido select {
   border-color: #8CC053;
    background-color: white;
}

.filtro-group.filtro-submenu-preenchido select {
    border-color: #8CC053;
    background-color: white;
}

.filtro-group.filtro-sprint-preenchido select {
     border-color: #8CC053;
    background-color: white;
}

.filtro-group.filtro-release-preenchido select {
    border-color: #8CC053;
   background-color: white;
}

.filtro-group.filtro-comentario-preenchido select {
      border-color: #8CC053;
   background-color: white;
}

/* Indicador visual nos labels */
.filtro-group.filtro-preenchido label::before {
    content: "✓ ";
    color: #28a745;
    font-weight: bold;
}

/* Efeito de transição suave */
.filtro-group select,
.filtro-group input {
    transition: all 0.3s ease;
}

        .filtros-botoes button:hover {
            background-color: #035a40ff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }

        .filtro-group input,
        .filtro-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            box-sizing: border-box;
        }

        .filtro-group button {
            background-color: #023324;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
            width: auto;
            height: fit-content;
            align-self: end;
        }

        .filtro-group button:hover {
            background-color: #034d3a;
        }

        .limpar-filtros {
            color: #8CC053;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
            padding: 10px 0;
            display: inline-block;
        }

        .limpar-filtros:hover {
            color: #023324;
        }

        /* Estilo para o filtro de cliente */
        .custom-select {
            position: relative;
        }
        
        .custom-select input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 13px;
            box-sizing: border-box;
        }
        
        .custom-select .options {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .custom-select .options div {
            padding: 8px;
            cursor: pointer;
            font-size: 13px;
        }
        
        .custom-select .options div:hover {
            background-color: #f0f0f0;
        }

        .filtros-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .filtros-botoes {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .indicadores {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .indicador {
            background: linear-gradient(135deg, #023324 0%, #034d3a 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(2, 51, 36, 0.3);
        }

        .indicador h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .indicador .valor {
            font-size: 32px;
            font-weight: bold;
            margin: 0;
        }

        /* Novos estilos para os indicadores de comentários */
        .indicador-comentarios {
            background: linear-gradient(135deg, #8CC053 0%, #6fa832 100%);
            color: white;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(140, 192, 83, 0.3);
        }

        .indicador-comentarios h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .indicador-comentarios .valor {
            font-size: 32px;
            font-weight: bold;
            margin: 0;
        }

        .indicador-comentarios small {
            opacity: 0.9;
            font-size: 12px;
        }

       


  .tooltip-container {
            position: relative;
            display: inline-flex;
            align-items: center;
            margin-left: 8px;
        }
        
        .tooltip-icon {
            font-size: 14px;
            color: #8CC053;
            cursor: pointer;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            
            display: flex;
            align-items: center;
            justify-content: center;
            margin-left: 5px;
            transition: all 0.2s ease;
        }
        
   
        
        .tooltip-text {
            visibility: hidden;
            width: 300px;
            background-color: white;
            color: #023324;
            text-align: left;
            border-radius: 6px;
            padding: 12px;
            position: absolute;
            z-index: 1000;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 13px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border: 1px solid #e0e0e0;
        }
        
        .tooltip-text::after {
            content: "";
            position: absolute;
            top: 100%;
            left: 50%;
            margin-left: -5px;
            border-width: 5px;
            border-style: solid;
            border-color: #e0e0e0 transparent transparent transparent;
        }
        
        .tooltip-container:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        /* Ajuste para os títulos com tooltip */
        h3.with-tooltip {
            
            align-items: center;
        }

/* Para tabelas */
.tabela-wrapper  {
    background-color: #f8f9fa;
    color: #023324;
}

        .graficos {
            display: grid;
            grid-template-columns: 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .graficos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }

        .grafico {
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }

        .grafico h3 {
            margin-top: 0;
            color: #023324;
            font-size: 18px;
            border-bottom: 2px solid #8CC053;
            padding-bottom: 10px;
        }

        /* Novos estilos para gráficos de comentários */
        .grafico-comentarios {
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
           
        }

        .grafico-comentarios h3 {
            margin-top: 0;
            color: #023324;
            font-size: 18px;
            border-bottom: 2px solid #8CC053;
            padding-bottom: 10px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }

      .tabelas-container {
    display: flex;
    flex-wrap: wrap;
    gap: 20px;
    margin-bottom: 30px;
}

        .tabela-wrapper {
            flex: 1;
    min-width: calc(50% - 20px); /* Dois por linha com gap */
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
             max-height: 650px; /* Altura máxima */
        }

        .tabela-wrapper h3 {
            margin-top: 0;
            color: #023324;
            font-size: 18px;
            border-bottom: 2px solid #8CC053;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

     .tabela-scroll {
    overflow-y: auto;
    overflow-x: hidden;
    flex: 1;
    max-height: calc(650px - 80px); /* Altura máxima menos o cabeçalho */
}

        .tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .tabela th,
        .tabela td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .tabela th {
            background-color: 'f8f9fa';
            font-weight: 600;
            color: #023324;
            font-size: 14px;
        }

        .tabela tr:hover {
            background-color: #f8f9fa;
        }

        .tabela-wrapper.full-width {
            grid-column: 1 / -1;
        }

        /* Novos estilos para tabelas de comentários */
        .tabela-comentarios {
            font-size: 14px;
        }

        .tabela-comentarios th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #023324;
        }

        .badge-tipo-comentario {
            font-size: 0.75em;
            padding: 0.35em 0.65em;
        }

        .badge-geral {
            background-color: #6c757d;
            color: white;
        }

        .badge-analise_desenvolvimento {
            background-color: #0d6efd;
            color: white;
        }

        .badge-analise_teste {
            background-color: #fd7e14;
            color: white;
        }

        .badge-retorno_teste {
            background-color: #dc3545;
            color: white;
        }

        /* Estilos para a listagem de chamados */
        .chamados-listagem {
            margin-top: 30px;
        }

        .chamados-listagem .table {
            font-size: 14px;
        }

        .chamados-listagem .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #023324;
        }

        .chamados-listagem .badge {
            font-size: 12px;
            padding: 4px 8px;
        }

        /* Paginação */
        .pagination {
            margin-bottom: 0;
            flex-wrap: wrap;
        }

        .page-link {
            color: #023324;
            border: 1px solid #dee2e6;
            margin: 2px;
        }

        .page-item.active .page-link {
            background-color: #023324;
            border-color: #023324;
            color: white;
        }

        .page-item.disabled .page-link {
            color: #6c757d;
            pointer-events: none;
            background-color: #fff;
            border-color: #dee2e6;
        }

        @media (max-width: 1200px) {
            .filtros-form {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media (max-width: 768px) {
            .filtros-form {
                grid-template-columns: 1fr;
            }
            
            .graficos-grid {
                grid-template-columns: 1fr;
            }
            
            .indicadores {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .tabelas-container {
                grid-template-columns: 1fr;
            }
            
            .filtros-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        @media (max-width: 576px) {
            .pagination {
                justify-content: center;
            }
            
            .page-item {
                margin: 1px;
            }
            
            .page-link {
                padding: 0.25rem 0.5rem;
                font-size: 0.75rem;
            }
        }
    </style>
</head>
<body>
    <main>
        <h1>Relatórios de Chamados</h1>

        <!-- Filtros -->
        <section class="filtros">
            <div class="filtros-header">
                <h2>Filtros</h2>
                <div class="filtros-botoes">
                    <button type="submit" form="filtros-form">Filtrar</button>
                    <a href="relatorios.php" class="limpar-filtros">Limpar Filtros</a>
                </div>
            </div>
            <form id="filtros-form" method="GET" action="relatorios.php" class="filtros-form">
                <div class="filtro-group">
                    <label for="data_inicio">Data Início:</label>
                    <input type="date" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                </div>

                <div class="filtro-group">
                    <label for="data_fim">Data Fim:</label>
                    <input type="date" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                </div>

                <div class="filtro-group">
                    <label>Status:</label>
                    <div class="dropdown-filter">
                        <div class="dropdown-header" onclick="toggleStatusDropdown()">
                            <span id="status-display">Selecionar Status</span>
                            <span class="dropdown-arrow">▼</span>
                        </div>
                        <div class="checkbox-group" id="status-dropdown" style="display: none;">
                            <div class="checkbox-item">
                                <input type="checkbox" id="status_todos" onchange="toggleTodosStatus()">
                                <label for="status_todos">Todos</label>
                            </div>
                            <?php foreach ($status_list as $status): ?>
                                <div class="checkbox-item">
                                    <input type="checkbox" id="status_<?php echo $status['id']; ?>" name="status[]" value="<?php echo $status['id']; ?>" 
                                           <?php echo (in_array($status['id'], $filtro_status)) ? 'checked' : ''; ?> 
                                           onchange="updateStatusSelection()">
                                    <label for="status_<?php echo $status['id']; ?>">
                                        <?php echo htmlspecialchars($status['nome']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="filtro-group">
                    <label for="tipo">Tipo:</label>
                    <select id="tipo" name="tipo">
                        <option value="">Todos</option>
                        <?php foreach ($tipos_list as $tipo): ?>
                            <option value="<?php echo $tipo['id']; ?>" <?php echo ($filtro_tipo == $tipo['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tipo['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-group">
                    <label for="prioridade">Prioridade:</label>
                    <select id="prioridade" name="prioridade">
                        <option value="">Todas</option>
                        <?php foreach ($prioridades_list as $prioridade): ?>
                            <option value="<?php echo $prioridade['id']; ?>" <?php echo ($filtro_prioridade == $prioridade['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($prioridade['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-group">
                    <label for="responsavel">Responsável:</label>
                    <select id="responsavel" name="responsavel">
                        <option value="">Todos</option>
                        <option value="sem" <?php echo ($filtro_responsavel == 'sem') ? 'selected' : ''; ?>>Sem responsável</option>
                        <?php foreach ($equipe_list as $membro): ?>
                            <option value="<?php echo $membro['id']; ?>" <?php echo ($filtro_responsavel == $membro['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($membro['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-group">
                    <label for="cliente_filter">Cliente:</label>
                    <div class="custom-select">
                        <input type="text" id="cliente_filter" placeholder="Digite para filtrar..." value="<?php
                            $cliente_nome = '';
                            if (!empty($filtro_cliente)) {
                                foreach ($clientes_filtro as $cliente) {
                                    if ($cliente['id'] == $filtro_cliente) {
                                        $cliente_nome = $cliente['nome_completo'];
                                        break;
                                    }
                                }
                            }
                            echo htmlspecialchars($cliente_nome);
                        ?>">
                        <div class="options" id="cliente_options">
                            <?php foreach ($clientes_filtro as $cliente): ?>
                                <div data-value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_completo']); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <select id="cliente" name="cliente" style="display: none;">
                            <option value="">Todos</option>
                            <?php foreach ($clientes_filtro as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo ($filtro_cliente == $cliente['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filtro-group">
                    <label for="sprint">Sprint:</label>
                    <select id="sprint" name="sprint">
                        <option value="">Todas</option>
                        <?php foreach ($sprints_list as $sprint): ?>
                            <option value="<?php echo $sprint['id']; ?>" <?php echo ($filtro_sprint == $sprint['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($sprint['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-group">
                    <label for="release">Release:</label>
                    <select id="release" name="release">
                        <option value="">Todas</option>
                        <?php foreach ($releases_list as $release): ?>
                            <option value="<?php echo $release['id']; ?>" <?php echo ($filtro_release == $release['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($release['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-group">
    <label for="menu">Menu:</label>
    <select id="menu" name="menu" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($menus_list as $menu): ?>
            <option value="<?php echo $menu['id']; ?>" <?php echo ($filtro_menu == $menu['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($menu['nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

<!-- Filtro de Submenu -->
<div class="filtro-group">
    <label for="submenu">Submenu:</label>
    <select id="submenu" name="submenu" class="form-select">
        <option value="">Todos</option>
        <?php foreach ($submenus_list as $submenu): ?>
            <option value="<?php echo $submenu['id']; ?>" <?php echo ($filtro_submenu == $submenu['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($submenu['nome']); ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

                <!-- NOVO FILTRO: Tipo de Comentário -->
                <div class="filtro-group">
                    <label for="tipo_comentario">Tipo de Comentário:</label>
                    <select id="tipo_comentario" name="tipo_comentario">
                        <option value="">Todos</option>
                        <?php foreach ($tipos_comentario_list as $tipo): ?>
                            <option value="<?php echo $tipo['valor']; ?>" <?php echo ($filtro_tipo_comentario == $tipo['valor']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tipo['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </section>

    <!-- Indicadores -->
<section class="indicadores">
    <div class="indicador">
        <h3 class="with-tooltip">Total de Chamados
            <div class="tooltip-container">
                <span class="tooltip-icon">ℹ</span>
                <div class="tooltip-text">
                    <strong>Total de Chamados</strong><br>
                    Mostra a quantidade total de chamados criados no período selecionado.
                </div>
            </div>
        </h3>
        <p class="valor"><?php echo number_format($total_chamados, 0, ',', '.'); ?></p>
    </div>
    
    <div class="indicador">
        <h3 class="with-tooltip">Taxa de Conclusão
            <div class="tooltip-container">
                <span class="tooltip-icon">ℹ</span>
                <div class="tooltip-text">
                    <strong>Taxa de Conclusão</strong><br>
                    Percentual de chamados concluídos em relação ao total.<br>
                    <strong>Cálculo:</strong> (Chamados Concluídos / Total de Chamados) × 100
                </div>
            </div>
        </h3>
        <p class="valor"><?php echo number_format($taxa_conclusao, 2, ',', '.'); ?>%</p>
    </div>
    

    
    <div class="indicador">
        <h3 class="with-tooltip">Chamados Concluídos
            <div class="tooltip-container">
                <span class="tooltip-icon">ℹ</span>
                <div class="tooltip-text">
                    <strong>Chamados Concluídos</strong><br>
                    Quantidade de chamados com status "Concluído" no período selecionado.
                </div>
            </div>
        </h3>
        <p class="valor"><?php echo $concluidos; ?></p>
    </div>

    <!-- NOVOS INDICADORES PARA COMENTÁRIOS COM CORES DINÂMICAS -->
    <?php
    // Taxa de Eficiência
    // Ajustar lógica quando não há chamados concluídos
    if ($taxa_eficiencia_data['total_concluidos'] == 0) {
        $taxa_eficiencia_final = 100; // 100% quando não há chamados concluídos
        $subtitulo_eficiencia = '0 de 0 concluídos com retorno';
    } else {
        $taxa_eficiencia_final = $taxa_eficiencia_data['taxa_eficiencia'];
        $subtitulo_eficiencia = $taxa_eficiencia_data['com_retorno_teste'] . ' de ' . 
                               $taxa_eficiencia_data['total_concluidos'] . ' concluídos com retorno';
    }
    $cor_eficiencia = getCorEficiencia($taxa_eficiencia_final);
    
    echo '<div class="indicador-comentarios" style="background: linear-gradient(135deg, ' . $cor_eficiencia . ' 0%, ' . escurecerCor($cor_eficiencia, 20) . ' 100%);">';
    echo '<h3 class="with-tooltip">Taxa de Eficiência';
    echo '<div class="tooltip-container">';
    echo '<span class="tooltip-icon">ℹ</span>';
    echo '<div class="tooltip-text">';
    echo '<strong>Taxa de Eficiência</strong><br>';
    echo 'Percentual de chamados concluídos que não retornaram para teste.<br>';
    echo '<strong>Cálculo:</strong> 100% - (Chamados com Retorno / Chamados Concluídos) × 100<br>';
    echo '<strong>Interpretação:</strong><br>';
    echo '- Verde (≥70%): Excelente desempenho<br>';
    echo '- Amarelo (51-69%): Desempenho regular<br>';
    echo '- Vermelho (≤50%): Necessita melhoria';
    echo '</div>';
    echo '</div>';
    echo '</h3>';
    echo '<p class="valor">' . number_format($taxa_eficiencia_final, 2, ',', '.') . '%</p>';
    echo '<small>' . $subtitulo_eficiencia . '</small>';
    echo '</div>';
    
    // Taxa de Retorno
    $cor_retorno = getCorRetorno($taxa_retorno_data['taxa_retorno']);
    $subtitulo_retorno = $taxa_retorno_data['com_retorno'] . ' de ' . 
                        $taxa_retorno_data['total_chamados'] . ' chamados concluídos com retorno';
    
    echo '<div class="indicador-comentarios" style="background: linear-gradient(135deg, ' . $cor_retorno . ' 0%, ' . escurecerCor($cor_retorno, 20) . ' 100%);">';
    echo '<h3 class="with-tooltip">Taxa de Retorno';
    echo '<div class="tooltip-container">';
    echo '<span class="tooltip-icon">ℹ</span>';
    echo '<div class="tooltip-text">';
    echo '<strong>Taxa de Retorno</strong><br>';
    echo 'Percentual de chamados concluídos que retornaram para teste.<br>';
    echo '<strong>Cálculo:</strong> (Chamados Concluídos com Retorno / Total de Chamados Concluídos) × 100<br>';
    echo '<strong>Interpretação:</strong><br>';
    echo '- Verde (≤30%): Baixo retorno<br>';
    echo '- Amarelo (31-49%): Retorno moderado<br>';
    echo '- Vermelho (≥50%): Alto retorno (preocupante)';
    echo '</div>';
    echo '</div>';
    echo '</h3>';
    echo '<p class="valor">' . number_format($taxa_retorno_data['taxa_retorno'], 2, ',', '.') . '%</p>';
    echo '<small>' . $subtitulo_retorno . '</small>';
    echo '</div>';
    ?>

    <!-- Indicador de Reincidência -->
<?php
// Calcular taxa geral de reincidência
$total_reincidencias_geral = 0;
$total_concluidos_geral = 0;
$total_chamados_com_reincidencia = 0;

foreach ($reincidencia_por_responsavel as $resp) {
    $total_reincidencias_geral += $resp['total_reincidencias'];
    $total_concluidos_geral += $resp['total_concluidos'];
    $total_chamados_com_reincidencia += $resp['chamados_com_reincidencia'];
}

$taxa_reincidencia_geral = $total_concluidos_geral > 0 ? 
    round(($total_chamados_com_reincidencia / $total_concluidos_geral) * 100, 2) : 0;

// Determinar cor com base na taxa (quanto menor, melhor)
function getCorReincidencia($taxa) {
    if ($taxa <= 20) return '#28a745'; // Verde
    if ($taxa <= 40) return '#ffc107'; // Amarelo
    return '#dc3545'; // Vermelho
}

$cor_reincidencia = getCorReincidencia($taxa_reincidencia_geral);
?>

<div class="indicador-comentarios" style="background: linear-gradient(135deg, <?php echo $cor_reincidencia; ?> 0%, <?php echo escurecerCor($cor_reincidencia, 20); ?> 100%);">
    <h3 class="with-tooltip">Taxa de Reincidência
        <div class="tooltip-container">
            <span class="tooltip-icon">ℹ</span>
            <div class="tooltip-text">
                <strong>Taxa de Reincidência</strong><br>
                Percentual de chamados concluídos que tiveram reincidência (mais de 1 retorno).<br>
                <strong>Cálculo:</strong> (Chamados com Reincidência / Total de Chamados Concluídos) × 100<br>
                <strong>Interpretação:</strong><br>
                - Verde (≤20%): Baixa reincidência<br>
                - Amarelo (21-40%): Reincidência moderada<br>
                - Vermelho (≥41%): Alta reincidência
            </div>
        </div>
    </h3>
    <p class="valor"><?php echo number_format($taxa_reincidencia_geral, 2, ',', '.'); ?>%</p>
    <small><?php echo $total_chamados_com_reincidencia . ' de ' . $total_concluidos_geral . ' concluídos com reincidência'; ?></small>
</div>
</section>

    <!-- Gráficos -->
<section class="graficos">
    <div class="graficos-grid">
        <div class="grafico">
            <h3 class="with-tooltip">Chamados por Status
                <div class="tooltip-container">
                    <span class="tooltip-icon">ℹ</span>
                    <div class="tooltip-text">
                        <strong>Chamados por Status</strong><br>
                        Distribuição dos chamados de acordo com seu status atual.<br>
                        Mostra quantos chamados estão em cada fase do processo.
                    </div>
                </div>
            </h3>
            <div class="chart-container">
                <canvas id="chartPorStatus"></canvas>
            </div>
        </div>

        <div class="grafico">
            <h3 class="with-tooltip">Chamados por Tipo
                <div class="tooltip-container">
                    <span class="tooltip-icon">ℹ</span>
                    <div class="tooltip-text">
                        <strong>Chamados por Tipo</strong><br>
                        Distribuição dos chamados por tipo (Bug, Melhoria, Nova Funcionalidade, etc.).<br>
                        Ajuda a identificar quais tipos de demanda são mais frequentes.
                    </div>
                </div>
            </h3>
            <div class="chart-container">
                <canvas id="chartPorTipo"></canvas>
            </div>
        </div>

        <div class="grafico">
            <h3 class="with-tooltip">Chamados por Prioridade
                <div class="tooltip-container">
                    <span class="tooltip-icon">ℹ</span>
                    <div class="tooltip-text">
                        <strong>Chamados por Prioridade</strong><br>
                        Distribuição dos chamados por nível de prioridade.<br>
                        Mostra a urgência relativa das demandas atendidas.
                    </div>
                </div>
            </h3>
            <div class="chart-container">
                <canvas id="chartPorPrioridade"></canvas>
            </div>
        </div>

        <div class="grafico">
            <h3 class="with-tooltip">Chamados por Responsável
                <div class="tooltip-container">
                    <span class="tooltip-icon">ℹ</span>
                    <div class="tooltip-text">
                        <strong>Chamados por Responsável</strong><br>
                        Distribuição de chamados por membro da equipe.<br>
                        Inclui chamados sem responsável atribuído.
                    </div>
                </div>
            </h3>
            <div class="chart-container">
                <canvas id="chartPorResponsavel"></canvas>
            </div>
        </div>
    </div>
</section>

   <!-- NOVA SEÇÃO: Gráficos de Comentários -->
<section class="graficos">
    <div class="graficos-grid">
        <!-- NOVO GRÁFICO: Taxa de Eficiência por Responsável -->
        <div class="grafico-comentarios">
            <h3 class="with-tooltip">Taxa de Eficiência por Responsável
                <div class="tooltip-container">
                    <span class="tooltip-icon">ℹ</span>
                    <div class="tooltip-text">
                        <strong>Taxa de Eficiência por Responsável</strong><br>
                        Mostra o percentual de chamados concluídos sem retorno para cada responsável.<br>
                        <strong>Cálculo:</strong> 100% - (Chamados com Retorno / Chamados Concluídos) × 100<br>
                        Valores mais altos indicam melhor qualidade no trabalho.
                    </div>
                </div>
            </h3>
            <div class="chart-container">
                <canvas id="chartEficienciaResponsavel"></canvas>
            </div>
        </div>



        

        <!-- Gráfico de Comentários por Responsável -->
        <div class="grafico-comentarios">
            <h3 class="with-tooltip">Comentários por Responsável
                <div class="tooltip-container">
                    <span class="tooltip-icon">ℹ</span>
                    <div class="tooltip-text">
                        <strong>Comentários por Responsável</strong><br>
                        Quantidade de comentários por tipo para cada responsável.<br>
                        Mostra a participação de cada membro da equipe e os tipos de interação.
                    </div>
                </div>
            </h3>
            <div class="chart-container">
                <canvas id="chartComentariosResponsavel"></canvas>
            </div>
        </div>
    </div>

    
</section>

     <!-- NOVA SEÇÃO: Tabelas de Comentários -->
<section class="tabelas-container">
    <!-- Tabela de Comentários por Responsável -->
    <div class="tabela-wrapper">
        <h3 class="with-tooltip">Comentários por Responsável
            <div class="tooltip-container">
                <span class="tooltip-icon">ℹ</span>
                <div class="tooltip-text">
                    <strong>Comentários por Responsável</strong><br>
                    Detalhamento da quantidade de comentários por tipo para cada responsável.<br>
                    <strong>Tipos:</strong><br>
                    - Geral: Comentários gerais sobre o chamado<br>
                    - Análise Dev: Análises técnicas de desenvolvimento<br>
                    - Análise Teste: Análises do time de teste<br>
                    - Retorno Teste: Comentários sobre retornos de teste
                </div>
            </div>
        </h3>
        <div class="tabela-scroll">
            <table class="tabela tabela-comentarios">
                <thead>
                    <tr>
                        <th>Responsável</th>
                        <th>Total</th>
                        <th>Geral</th>
                        <th>Análise Dev</th>
                        <th>Análise Teste</th>
                        <th>Retorno Teste</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($comentarios_por_responsavel as $responsavel): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($responsavel['responsavel_nome']); ?></td>
                            <td><?php echo $responsavel['total_comentarios']; ?></td>
                            <td><?php echo $responsavel['comentarios_geral']; ?></td>
                            <td><?php echo $responsavel['analise_desenvolvimento']; ?></td>
                            <td><?php echo $responsavel['analise_teste']; ?></td>
                            <td><?php echo $responsavel['retorno_teste']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    


    <!-- Tabela de Reincidência por Responsável -->
<div class="tabela-wrapper">
    <h3 class="with-tooltip">Reincidência por Responsável
        <div class="tooltip-container">
            <span class="tooltip-icon">ℹ</span>
            <div class="tooltip-text">
                <strong>Reincidência por Responsável</strong><br>
                Detalhamento da reincidência de retornos de teste por responsável.<br>
                <strong>Colunas:</strong><br>
                - Chamados Concluídos: Quantidade de chamados concluídos do responsável<br>
                - Com Reincidência: Chamados concluídos com mais de 1 retorno de teste<br>
                - Total Reincidências: Quantidade total de retornos extras<br>
                - Taxa: Percentual de chamados concluídos com reincidência
            </div>
        </div>
    </h3>
    <div class="tabela-scroll">
        <table class="tabela tabela-comentarios">
            <thead>
                <tr>
                    <th>Responsável</th>
                    <th>Chamados Concluídos</th>
                    <th>Com Reincidência</th>
                    <th>Total Reincidências</th>
                    <th>Taxa</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reincidencia_por_responsavel as $responsavel): 
                    $cor_taxa = getCorReincidencia($responsavel['taxa_reincidencia']);
                ?>
                    <tr>
                        <td><?php echo htmlspecialchars($responsavel['responsavel_nome']); ?></td>
                        <td><?php echo $responsavel['total_concluidos']; ?></td>
                        <td><?php echo $responsavel['chamados_com_reincidencia']; ?></td>
                        <td><?php echo $responsavel['total_reincidencias']; ?></td>
                        <td style="color: red; font-weight: bold;">
                            <?php echo number_format($responsavel['taxa_reincidencia'], 2, ',', '.'); ?>%
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

    <div class="tabela-wrapper">
        <h3 class="with-tooltip">Chamados por Menu
            <div class="tooltip-container">
                <span class="tooltip-icon">ℹ</span>
                <div class="tooltip-text">
                    <strong>Chamados por Menu</strong><br>
                    Distribuição de chamados por menu do sistema.<br>
                    Ajuda a identificar quais áreas do sistema têm mais demandas.
                </div>
            </div>
        </h3>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Menu</th>
                        <th>Total</th>
                        <th>Percentual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chamados_por_menu as $menu): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($menu['nome']); ?></td>
                            <td><?php echo $menu['total']; ?></td>
                            <td><?php echo number_format($menu['percentual'], 2, ',', '.'); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="tabela-wrapper">
        <h3 class="with-tooltip">Chamados por Submenu
            <div class="tooltip-container">
                <span class="tooltip-icon">ℹ</span>
                <div class="tooltip-text">
                    <strong>Chamados por Submenu</strong><br>
                    Distribuição de chamados por submenu do sistema.<br>
                    Permite identificar com mais precisão quais funcionalidades têm mais demandas.
                </div>
            </div>
        </h3>
        <div class="tabela-scroll">
            <table class="tabela">
                <thead>
                    <tr>
                        <th>Submenu</th>
                        <th>Total</th>
                        <th>Percentual</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($chamados_por_submenu as $submenu): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($submenu['nome']); ?></td>
                            <td><?php echo $submenu['total']; ?></td>
                            <td><?php echo number_format($submenu['percentual'], 2, ',', '.'); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

        <!-- Listagem de Chamados Filtrados -->
        <section class="tabelas-container">
            <div class="tabela-wrapper full-width">
                <h3>Chamados Filtrados</h3>
                <div class="tabela-scroll">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Título</th>
                                <th>Tipo</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                                <th>Cliente</th>
                                <th>Responsável</th>
                                <th>Criado em</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($chamados_lista)): 
                                foreach ($chamados_lista as $chamado): ?>
                                    <tr>
                                        <td>#<?php echo $chamado['id']; ?></td>
                                        <td>
                                            <a href="visualizar.php?id=<?php echo $chamado['id']; ?>">
                                                <?php echo htmlspecialchars($chamado['titulo']); ?>
                                            </a>
                                        </td>
                                        <td>
                                            <i class="material-icons" style="font-size:16px;"><?php echo $chamado['tipo_icone']; ?></i>
                                            <?php echo $chamado['tipo_nome']; ?>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: <?php echo $chamado['prioridade_cor']; ?>">
                                                <?php echo $chamado['prioridade_nome']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge" style="background-color: <?php echo $chamado['status_cor']; ?>">
                                                <?php echo $chamado['status_nome']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($chamado['cliente_nome']): ?>
                                                <?php echo htmlspecialchars(($chamado['cliente_contrato'] ? $chamado['cliente_contrato'] . ' - ' : '') . $chamado['cliente_nome']); ?>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php echo $chamado['responsavel_nome'] ? htmlspecialchars($chamado['responsavel_nome']) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php echo date('d/m/Y H:i', strtotime($chamado['data_criacao'])); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; 
                            else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-3">
                                        Nenhum chamado encontrado com os filtros selecionados
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <!-- Paginação Melhorada -->
                    <?php if ($totalPages_chamados > 1): ?>
                        <nav aria-label="Navegação de páginas" class="mt-3">
                            <ul class="pagination pagination-sm justify-content-center">
                                <?php if ($page_chamados > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page_chamados' => $page_chamados - 1])); ?>" onclick="return manterPosicaoPagina()">
                                            &laquo; Anterior
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php 
                                // Mostrar apenas algumas páginas ao redor da atual
                                $maxPagesToShow = 5;
                                $startPage = max(1, $page_chamados - floor($maxPagesToShow / 2));
                                $endPage = min($totalPages_chamados, $startPage + $maxPagesToShow - 1);
                                
                                // Ajustar se estiver no início
                                if ($endPage - $startPage + 1 < $maxPagesToShow) {
                                    $startPage = max(1, $endPage - $maxPagesToShow + 1);
                                }
                                
                                // Mostrar primeira página se não estiver visível
                                if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page_chamados' => 1])); ?>" onclick="return manterPosicaoPagina()">
                                            1
                                        </a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i == $page_chamados ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page_chamados' => $i])); ?>" onclick="return manterPosicaoPagina()">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($endPage < $totalPages_chamados): ?>
                                    <?php if ($endPage < $totalPages_chamados - 1): ?>
                                        <li class="page-item disabled">
                                            <span class="page-link">...</span>
                                        </li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page_chamados' => $totalPages_chamados])); ?>" onclick="return manterPosicaoPagina()">
                                            <?php echo $totalPages_chamados; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php if ($page_chamados < $totalPages_chamados): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page_chamados' => $page_chamados + 1])); ?>" onclick="return manterPosicaoPagina()">
                                            Próxima &raquo;
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                            <div class="text-center text-muted small">
                                Mostrando <?php echo min($offset_chamados + 1, $total_chamados_lista); ?> a 
                                <?php echo min($offset_chamados + $itemsPerPage_chamados, $total_chamados_lista); ?> de 
                                <?php echo $total_chamados_lista; ?> registros
                            </div>
                        </nav>
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </main>

    <script>
        // Configuração global dos gráficos
        Chart.defaults.font.family = 'Arial, sans-serif';
        Chart.defaults.color = '#666';

        // Gráfico de Chamados por Status
        const ctxPorStatus = document.getElementById('chartPorStatus').getContext('2d');
        new Chart(ctxPorStatus, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($chamados_por_status, 'nome')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($chamados_por_status, 'total')); ?>,
                    backgroundColor: <?php echo json_encode(array_column($chamados_por_status, 'cor')); ?>,
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = <?php echo $total_chamados; ?>;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(2) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        


        

        // Gráfico de Chamados por Tipo
        const ctxPorTipo = document.getElementById('chartPorTipo').getContext('2d');
        new Chart(ctxPorTipo, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($chamados_por_tipo, 'nome')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($chamados_por_tipo, 'total')); ?>,
                    backgroundColor: [
                        '#023324', '#8CC053', '#be5b31', '#6c757d', '#343a40',
                        '#007bff', '#6610f2', '#6f42c1', '#e83e8c', '#d63384'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw || 0;
                                const total = <?php echo $total_chamados; ?>;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(2) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de Chamados por Prioridade
        const ctxPorPrioridade = document.getElementById('chartPorPrioridade').getContext('2d');
        new Chart(ctxPorPrioridade, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($chamados_por_prioridade, 'nome')); ?>,
                datasets: [{
                    label: 'Chamados',
                    data: <?php echo json_encode(array_column($chamados_por_prioridade, 'total')); ?>,
                    backgroundColor: <?php echo json_encode(array_column($chamados_por_prioridade, 'cor')); ?>,
                    borderColor: '#023324',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw || 0;
                                const total = <?php echo $total_chamados; ?>;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(2) : 0;
                                return `Chamados: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // Gráfico de Chamados por Responsável (incluindo "Sem responsável")
        const ctxPorResponsavel = document.getElementById('chartPorResponsavel').getContext('2d');
        new Chart(ctxPorResponsavel, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($chamados_por_responsavel, 'nome')); ?>,
                datasets: [{
                    label: 'Chamados',
                    data: <?php echo json_encode(array_column($chamados_por_responsavel, 'total')); ?>,
                    backgroundColor: '#8CC053',
                    borderColor: '#023324',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                indexAxis: 'y',
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const value = context.raw || 0;
                                const total = <?php echo $total_chamados; ?>;
                                const percentage = total > 0 ? ((value / total) * 100).toFixed(2) : 0;
                                return `Chamados: ${value} (${percentage}%)`;
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });

        // NOVOS GRÁFICOS PARA COMENTÁRIOS

       // Gráfico de Taxa de Eficiência por Responsável - MODIFICADO
const ctxEficienciaResponsavel = document.getElementById('chartEficienciaResponsavel').getContext('2d');
new Chart(ctxEficienciaResponsavel, {
    type: 'bar',
    data: {
        labels: <?php echo json_encode(array_column($eficiencia_por_responsavel, 'responsavel_nome')); ?>,
        datasets: [{
            label: 'Taxa de Eficiência',
            data: <?php echo json_encode(array_column($eficiencia_por_responsavel, 'taxa_eficiencia')); ?>,
            backgroundColor: <?php echo json_encode(array_column($eficiencia_por_responsavel, 'cor')); ?>,
            borderColor: '#023324',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                display: false
            },
            tooltip: {
                callbacks: {
                    label: function(context) {
                        const value = context.raw || 0;
                        const responsavel = <?php echo json_encode(array_column($eficiencia_por_responsavel, 'responsavel_nome')); ?>[context.dataIndex];
                        const totalConcluidos = <?php echo json_encode(array_column($eficiencia_por_responsavel, 'total_concluidos')); ?>[context.dataIndex];
                        const comRetorno = <?php echo json_encode(array_column($eficiencia_por_responsavel, 'com_retorno_teste')); ?>[context.dataIndex];
                        return `${responsavel}: ${value}% (${totalConcluidos - comRetorno}/${totalConcluidos} sem retorno)`;
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                max: 100,
                title: {
                    display: true,
                    text: 'Taxa de Eficiência (%)'
                }
            },
            x: {
                title: {
                    display: true,
                    text: 'Responsável'
                }
            }
        }
    }
});

        // Gráfico de Comentários por Responsável
        const ctxComentariosResponsavel = document.getElementById('chartComentariosResponsavel').getContext('2d');
        new Chart(ctxComentariosResponsavel, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($comentarios_por_responsavel, 'responsavel_nome')); ?>,
                datasets: [
                    {
                        label: 'Geral',
                        data: <?php echo json_encode(array_column($comentarios_por_responsavel, 'comentarios_geral')); ?>,
                        backgroundColor: '#6c757d',
                        borderColor: '#6c757d',
                        borderWidth: 1
                    },
                    {
                        label: 'Análise Dev',
                        data: <?php echo json_encode(array_column($comentarios_por_responsavel, 'analise_desenvolvimento')); ?>,
                        backgroundColor: '#0d6efd',
                        borderColor: '#0d6efd',
                        borderWidth: 1
                    },
                    {
                        label: 'Análise Teste',
                        data: <?php echo json_encode(array_column($comentarios_por_responsavel, 'analise_teste')); ?>,
                        backgroundColor: '#fd7e14',
                        borderColor: '#fd7e14',
                        borderWidth: 1
                    },
                    {
                        label: 'Retorno Teste',
                        data: <?php echo json_encode(array_column($comentarios_por_responsavel, 'retorno_teste')); ?>,
                        backgroundColor: '#dc3545',
                        borderColor: '#dc3545',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    x: {
                        stacked: true
                    },
                    y: {
                        stacked: true,
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });


        

        // Função para configurar o filtro de cliente
        function setupFilter(inputId, optionsId, selectId) {
            const input = document.getElementById(inputId);
            const options = document.getElementById(optionsId);
            const select = document.getElementById(selectId);

            input.addEventListener('input', function () {
                const filter = input.value.toUpperCase();
                const divs = options.getElementsByTagName('div');

                for (let i = 0; i < divs.length; i++) {
                    const div = divs[i];
                    const text = div.textContent.toUpperCase();
                    if (text.indexOf(filter) > -1) {
                        div.style.display = '';
                    } else {
                        div.style.display = 'none';
                    }
                }

                options.style.display = 'block';
            });

            input.addEventListener('focus', function () {
                options.style.display = 'block';
            });

            input.addEventListener('blur', function () {
                setTimeout(() => {
                    options.style.display = 'none';
                }, 200);
            });

            options.addEventListener('click', function (e) {
                if (e.target.tagName === 'DIV') {
                    input.value = e.target.textContent;
                    select.value = e.target.getAttribute('data-value');
                    options.style.display = 'none';
                }
            });
        }

        // Função para manter a posição da tela ao mudar de página
        function manterPosicaoPagina() {
            // Salvar a posição atual de scroll
            sessionStorage.setItem('scrollPos', window.scrollY);
            return true;
        }

        // Inicializar quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            // Configurar o filtro de cliente
            setupFilter('cliente_filter', 'cliente_options', 'cliente');
            
            // Restaurar posição do scroll se existir
            const scrollPos = sessionStorage.getItem('scrollPos');
            if (scrollPos) {
                window.scrollTo(0, parseInt(scrollPos));
                sessionStorage.removeItem('scrollPos');
            }
            
            // Atualizar links de paginação para manter filtros e posição
            const linksPaginacao = document.querySelectorAll('.pagination a');
            linksPaginacao.forEach(link => {
                link.addEventListener('click', function(e) {
                    manterPosicaoPagina();
                });
            });
        });
    </script>
   <script>
// Dados dos submenus
const submenusData = <?php echo json_encode($submenus_list); ?>;

// Função para carregar submenus baseado no menu selecionado
function carregarSubmenus() {
    const menuId = document.getElementById('menu').value;
    const submenuSelect = document.getElementById('submenu');
    const currentSubmenu = '<?php echo $filtro_submenu; ?>';
    
    // Limpa o submenu
    submenuSelect.innerHTML = '<option value="">Todos</option>';
    
    if (menuId) {
        // Filtra submenus pelo menu selecionado
        const submenusFiltrados = submenusData.filter(submenu => submenu.menu_id == menuId);
        
        // Adiciona os submenus filtrados
        submenusFiltrados.forEach(submenu => {
            const option = document.createElement('option');
            option.value = submenu.id;
            option.textContent = submenu.nome;
            option.selected = (submenu.id == currentSubmenu);
            submenuSelect.appendChild(option);
        });
    } else {
        // Se nenhum menu selecionado, mostra todos os submenus
        submenusData.forEach(submenu => {
            const option = document.createElement('option');
            option.value = submenu.id;
            option.textContent = submenu.nome;
            option.selected = (submenu.id == currentSubmenu);
            submenuSelect.appendChild(option);
        });
    }
}

// Event listener para quando o menu mudar
document.getElementById('menu').addEventListener('change', carregarSubmenus);

// Carrega os submenus quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    carregarSubmenus();
    
    // Se já houver um menu selecionado, carrega os submenus correspondentes
    const menuSelecionado = document.getElementById('menu').value;
    if (menuSelecionado) {
        carregarSubmenus();
    }
});
// relatorios.php - Adicione este script

// Função para verificar e estilizar filtros preenchidos
function estilizarFiltrosPreenchidos() {
    const form = document.getElementById('filtros-form');
    const filtros = form.querySelectorAll('select, input');
    
    filtros.forEach(filtro => {
        const grupo = filtro.closest('.filtro-group');
        if (!grupo) return;
        
        // Remove todas as classes de preenchimento primeiro
        grupo.classList.remove('filtro-preenchido');
        grupo.classList.remove('filtro-data-preenchido');
        grupo.classList.remove('filtro-status-preenchido');
        grupo.classList.remove('filtro-tipo-preenchido');
        grupo.classList.remove('filtro-prioridade-preenchido');
        grupo.classList.remove('filtro-responsavel-preenchido');
        grupo.classList.remove('filtro-cliente-preenchido');
        grupo.classList.remove('filtro-menu-preenchido');
        grupo.classList.remove('filtro-submenu-preenchido');
        grupo.classList.remove('filtro-sprint-preenchido');
        grupo.classList.remove('filtro-release-preenchido');
        grupo.classList.remove('filtro-comentario-preenchido');
        
        // Verifica se o filtro está preenchido
        let estaPreenchido = false;
        
        if (filtro.tagName === 'SELECT') {
            estaPreenchido = filtro.value !== '';
        } else if (filtro.tagName === 'INPUT') {
            if (filtro.type === 'text' || filtro.type === 'search') {
                estaPreenchido = filtro.value.trim() !== '';
            } else if (filtro.type === 'date') {
                estaPreenchido = filtro.value !== '';
            }
        }
        
        if (estaPreenchido) {
            grupo.classList.add('filtro-preenchido');
            
            // Adiciona classe específica baseada no tipo de filtro
            switch (filtro.name) {
                case 'data_inicio':
                case 'data_fim':
                    grupo.classList.add('filtro-data-preenchido');
                    break;
                case 'status':
                    grupo.classList.add('filtro-status-preenchido');
                    break;
                case 'tipo':
                    grupo.classList.add('filtro-tipo-preenchido');
                    break;
                case 'prioridade':
                    grupo.classList.add('filtro-prioridade-preenchido');
                    break;
                case 'responsavel':
                    grupo.classList.add('filtro-responsavel-preenchido');
                    break;
                case 'cliente':
                    grupo.classList.add('filtro-cliente-preenchido');
                    break;
                case 'menu':
                    grupo.classList.add('filtro-menu-preenchido');
                    break;
                case 'submenu':
                    grupo.classList.add('filtro-submenu-preenchido');
                    break;
                case 'sprint':
                    grupo.classList.add('filtro-sprint-preenchido');
                    break;
                case 'release':
                    grupo.classList.add('filtro-release-preenchido');
                    break;
                case 'tipo_comentario':
                    grupo.classList.add('filtro-comentario-preenchido');
                    break;
            }
        }
    });
}

// Função para contar filtros ativos
function contarFiltrosAtivos() {
    const filtrosAtivos = document.querySelectorAll('.filtro-preenchido').length;
    const contador = document.getElementById('contador-filtros');
    
    if (contador) {
        contador.textContent = filtrosAtivos;
        contador.style.display = filtrosAtivos > 0 ? 'inline-block' : 'none';
    }
}

// Adicionar contador visual no título
function adicionarContadorFiltros() {
    const titulo = document.querySelector('.filtros-header h2');
    if (titulo && !document.getElementById('contador-filtros')) {
     
    }
}

// Funções para gerenciar dropdown de status
function toggleStatusDropdown() {
    const dropdown = document.getElementById('status-dropdown');
    const arrow = document.querySelector('.dropdown-arrow');
    
    if (dropdown.style.display === 'none' || dropdown.style.display === '') {
        dropdown.style.display = 'block';
        arrow.classList.add('rotated');
    } else {
        dropdown.style.display = 'none';
        arrow.classList.remove('rotated');
    }
}

// Fechar dropdown ao clicar fora
document.addEventListener('click', function(event) {
    const dropdownFilter = document.querySelector('.dropdown-filter');
    if (dropdownFilter && !dropdownFilter.contains(event.target)) {
        const dropdown = document.getElementById('status-dropdown');
        const arrow = document.querySelector('.dropdown-arrow');
        if (dropdown) {
            dropdown.style.display = 'none';
            arrow.classList.remove('rotated');
        }
    }
});

// Fechar dropdown ao fazer scroll fora da área do filtro
document.addEventListener('wheel', function(event) {
    const dropdownFilter = document.querySelector('.dropdown-filter');
    const dropdown = document.getElementById('status-dropdown');
    
    // Verificar se o dropdown está aberto
    if (dropdown && dropdown.style.display === 'block') {
        // Verificar se o mouse está fora da área do filtro
        if (dropdownFilter && !dropdownFilter.contains(event.target)) {
            const arrow = document.querySelector('.dropdown-arrow');
            dropdown.style.display = 'none';
            arrow.classList.remove('rotated');
        }
    }
}, { passive: true });

// Prevenir propagação de scroll no dropdown de status
document.addEventListener('DOMContentLoaded', function() {
    const statusDropdown = document.getElementById('status-dropdown');
    if (statusDropdown) {
        statusDropdown.addEventListener('wheel', function(e) {
            const delta = e.deltaY;
            const scrollTop = this.scrollTop;
            const scrollHeight = this.scrollHeight;
            const height = this.clientHeight;
            
            // Prevenir scroll da página quando chegamos ao topo ou fundo do dropdown
            if ((delta < 0 && scrollTop === 0) || (delta > 0 && scrollTop + height >= scrollHeight)) {
                e.preventDefault();
                e.stopPropagation();
            }
        }, { passive: false });
        
        // Também prevenir propagação de eventos de toque para dispositivos móveis
        statusDropdown.addEventListener('touchmove', function(e) {
            e.stopPropagation();
        }, { passive: true });
    }
});

// Funções para gerenciar checkboxes de status
function toggleTodosStatus() {
    const todoCheckbox = document.getElementById('status_todos');
    const statusCheckboxes = document.querySelectorAll('input[name="status[]"]');
    
    if (todoCheckbox.checked) {
        // Desmarcar todos os status individuais
        statusCheckboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
    }
    
    updateStatusSelection();
}

function updateStatusSelection() {
    const todoCheckbox = document.getElementById('status_todos');
    const statusCheckboxes = document.querySelectorAll('input[name="status[]"]');
    const checkedBoxes = document.querySelectorAll('input[name="status[]"]:checked');
    const displayElement = document.getElementById('status-display');
    
    // Se algum status específico estiver marcado, desmarcar "Todos"
    if (checkedBoxes.length > 0) {
        todoCheckbox.checked = false;
        
        // Atualizar texto de exibição
        if (checkedBoxes.length === 1) {
            displayElement.textContent = checkedBoxes[0].nextElementSibling.textContent;
        } else {
            displayElement.textContent = `${checkedBoxes.length} status selecionados`;
        }
    } else {
        // Se nenhum status específico estiver marcado, marcar "Todos"
        todoCheckbox.checked = true;
        displayElement.textContent = 'Todos';
    }
}

function updateStatusDisplay() {
    const todoCheckbox = document.getElementById('status_todos');
    const checkedBoxes = document.querySelectorAll('input[name="status[]"]:checked');
    const displayElement = document.getElementById('status-display');
    
    if (checkedBoxes.length === 0) {
        displayElement.textContent = 'Todos';
    } else if (checkedBoxes.length === 1) {
        displayElement.textContent = checkedBoxes[0].nextElementSibling.textContent;
    } else {
        displayElement.textContent = `${checkedBoxes.length} status selecionados`;
    }
}

// Inicializar quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    adicionarContadorFiltros();
    estilizarFiltrosPreenchidos();
    
    // Inicializar estado dos checkboxes de status
    const statusCheckboxes = document.querySelectorAll('input[name="status[]"]');
    const checkedBoxes = document.querySelectorAll('input[name="status[]"]:checked');
    const todoCheckbox = document.getElementById('status_todos');
    
    // Adicionar event listeners para checkboxes
    if (todoCheckbox) {
        todoCheckbox.addEventListener('change', toggleTodosStatus);
    }
    
    statusCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', updateStatusSelection);
    });
    
    // Se nenhum status específico estiver marcado, marcar "Todos"
    if (checkedBoxes.length === 0) {
        todoCheckbox.checked = true;
    }
    
    // Configurar estado inicial do dropdown
    updateStatusDisplay();
    contarFiltrosAtivos();
    
    // Observar mudanças nos filtros
    document.querySelectorAll('#filtros-form select, #filtros-form input').forEach(filtro => {
        filtro.addEventListener('change', function() {
            setTimeout(() => {
                estilizarFiltrosPreenchidos();
                contarFiltrosAtivos();
            }, 100);
        });
        
        // Para inputs de texto também
        if (filtro.type === 'text' || filtro.type === 'search') {
            filtro.addEventListener('input', function() {
                setTimeout(() => {
                    estilizarFiltrosPreenchidos();
                    contarFiltrosAtivos();
                }, 300);
            });
        }
    });
});

// Também executar após submit do formulário
document.getElementById('filtros-form')?.addEventListener('submit', function() {
    setTimeout(() => {
        estilizarFiltrosPreenchidos();
        contarFiltrosAtivos();
    }, 100);
});
</script>

</body>
</html>