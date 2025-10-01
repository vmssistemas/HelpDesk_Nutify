<?php
require_once 'includes/header.php';

// Verifica se há parâmetros na URL ou se deve carregar o filtro padrão
if (!empty($_GET)) {
    // Armazena os filtros atuais na sessão
    $_SESSION['last_filters'] = http_build_query($_GET);
} else {
    // Primeiro tenta carregar o filtro padrão
    $query = "SELECT filtros FROM usuario_filtros WHERE usuario_id = ? AND padrao = 1 LIMIT 1";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $_SESSION['usuario_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $filtro = $result->fetch_assoc();
        $filtros = json_decode($filtro['filtros'], true);
        $_GET = $filtros;
        // Força o view mode para lista se não estiver definido
        $_GET['view'] = $_GET['view'] ?? 'lista';
        
        // Redireciona para a mesma página com os parâmetros do filtro padrão
        header("Location: index.php?" . http_build_query($_GET));
        exit();
    }
    // Se não houver filtro padrão, tenta carregar da sessão
    elseif (!empty($_SESSION['last_filters'])) {
        parse_str($_SESSION['last_filters'], $_GET);
        // Força o view mode para lista se não estiver definido
        $_GET['view'] = $_GET['view'] ?? 'lista';
        
        // Redireciona para a mesma página com os parâmetros da sessão
        header("Location: index.php?" . http_build_query($_GET));
        exit();
    }
    
    // Armazena os filtros que serão usados na sessão
    $_SESSION['last_filters'] = http_build_query($_GET);
}

// Filtros
$filtro_status = isset($_GET['status']) ? (is_array($_GET['status']) ? $_GET['status'] : [$_GET['status']]) : [1, 2, 3, 4, 7, 8, 9, 10, 11, 12];
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : null;
$filtro_prioridade = isset($_GET['prioridade']) ? $_GET['prioridade'] : null;
$filtro_responsavel = isset($_GET['responsavel']) ? (is_array($_GET['responsavel']) ? $_GET['responsavel'] : [$_GET['responsavel']]) : [];
$filtro_sprint = isset($_GET['sprint']) ? $_GET['sprint'] : null;
$filtro_marcador = isset($_GET['marcador']) ? $_GET['marcador'] : null;
$filtro_criado_por = isset($_GET['criado_por']) ? $_GET['criado_por'] : null;
$filtro_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : null;
$filtro_numero = isset($_GET['numero']) ? $_GET['numero'] : null;
$filtro_release = isset($_GET['release']) ? $_GET['release'] : null;
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : null;
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : null;
$filtro_tipo_data = isset($_GET['tipo_data']) ? $_GET['tipo_data'] : 'criacao'; // 'criacao' ou 'previsao'
$filtro_titulo = isset($_GET['titulo']) ? $_GET['titulo'] : null; // Novo filtro por título
$filtro_previsao_liberacao = isset($_GET['previsao_liberacao']) ? $_GET['previsao_liberacao'] : null; // Novo filtro por previsão de liberação
$view_mode = isset($_GET['view']) ? $_GET['view'] : 'lista';

// Paginação
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$itemsPerPage = 20;
$offset = ($page - 1) * $itemsPerPage;

// Busca todos os status
$status_list = getChamadosStatus();
$tipos_list = getChamadosTipos();
$prioridades_list = getChamadosPrioridades();
$sprints_list = getSprintsAtivas();
$equipe_list = getUsuariosEquipe();
$clientes_list = getClientes();
$releases_list = getReleasesAtivas();

// Busca chamados filtrados
// Na parte onde busca os chamados, já está incluindo os pontos_historia
$query = "SELECT c.*, s.nome as status_nome, s.cor as status_cor, 
                 t.nome as tipo_nome, t.icone as tipo_icone,
                 p.nome as prioridade_nome, p.cor as prioridade_cor,
                 u.nome as usuario_nome, r.nome as responsavel_nome,
                 cli.nome as cliente_nome, cli.contrato as cliente_contrato,
                 sp.nome as sprint_nome,
                 rel.nome as release_nome, rel.cor as release_cor
          FROM chamados c
          LEFT JOIN chamados_status s ON c.status_id = s.id
          LEFT JOIN chamados_tipos t ON c.tipo_id = t.id
          LEFT JOIN chamados_prioridades p ON c.prioridade_id = p.id
          LEFT JOIN usuarios u ON c.usuario_id = u.id
          LEFT JOIN usuarios r ON c.responsavel_id = r.id
          LEFT JOIN clientes cli ON c.cliente_id = cli.id
          LEFT JOIN chamados_sprints sp ON c.sprint_id = sp.id
          LEFT JOIN chamados_releases rel ON c.release_id = rel.id
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($filtro_status)) {
    $placeholders = implode(',', array_fill(0, count($filtro_status), '?'));
    $query .= " AND c.status_id IN ($placeholders)";
    $params = array_merge($params, $filtro_status);
    $types .= str_repeat('i', count($filtro_status));
}

if ($filtro_tipo) {
    $query .= " AND c.tipo_id = ?";
    $params[] = $filtro_tipo;
    $types .= "i";
}
if ($filtro_marcador) {
    $query .= " AND EXISTS (
        SELECT 1 FROM chamados_marcadores_vinculos v
        WHERE v.chamado_id = c.id AND v.marcador_id = ?
    )";
    $params[] = $filtro_marcador;
    $types .= "i";
}

if ($filtro_titulo) {
    $query .= " AND c.titulo LIKE ?";
    $params[] = '%' . $filtro_titulo . '%';
    $types .= "s";
}

if ($filtro_prioridade) {
    $query .= " AND c.prioridade_id = ?";
    $params[] = $filtro_prioridade;
    $types .= "i";
}

if (!empty($filtro_responsavel)) {
    // Se "Sem responsável" estiver selecionado (valor 0)
    if (in_array(0, $filtro_responsavel)) {
        // Remove o valor 0 do array para não interferir com outros filtros
        $filtro_responsavel = array_filter($filtro_responsavel, function($val) { return $val !== 0; });
        
        if (!empty($filtro_responsavel)) {
            // Se outros responsáveis também estiverem selecionados
            $placeholders = implode(',', array_fill(0, count($filtro_responsavel), '?'));
            $query .= " AND (c.responsavel_id IS NULL OR c.responsavel_id IN ($placeholders))";
            $params = array_merge($params, $filtro_responsavel);
            $types .= str_repeat('i', count($filtro_responsavel));
        } else {
            // Apenas "Sem responsável" selecionado
            $query .= " AND c.responsavel_id IS NULL";
        }
    } else {
        // Apenas responsáveis específicos selecionados
        $placeholders = implode(',', array_fill(0, count($filtro_responsavel), '?'));
        $query .= " AND c.responsavel_id IN ($placeholders)";
        $params = array_merge($params, $filtro_responsavel);
        $types .= str_repeat('i', count($filtro_responsavel));
    }
}

if ($filtro_sprint) {
    $query .= " AND c.sprint_id = ?";
    $params[] = $filtro_sprint;
    $types .= "i";
}

if ($filtro_criado_por) {
    $query .= " AND c.usuario_id = ?";
    $params[] = $filtro_criado_por;
    $types .= "i";
}
if ($filtro_cliente) {
    $query .= " AND (c.cliente_id = ? OR EXISTS (
                SELECT 1 FROM chamados_clientes cc 
                WHERE cc.chamado_id = c.id AND cc.cliente_id = ?
              ))";
    $params[] = $filtro_cliente;
    $params[] = $filtro_cliente;
    $types .= "ii";
}

if ($filtro_numero) {
    $query .= " AND c.id = ?";
    $params[] = $filtro_numero;
    $types .= "i";
}

if ($filtro_data_inicio && $filtro_data_fim) {
    if ($filtro_tipo_data === 'previsao') {
        $query .= " AND DATE(c.previsao_liberacao) BETWEEN ? AND ?";
    } else {
        $query .= " AND DATE(c.data_criacao) BETWEEN ? AND ?";
    }
    $params[] = $filtro_data_inicio;
    $params[] = $filtro_data_fim;
    $types .= "ss";
} elseif ($filtro_data_inicio) {
    if ($filtro_tipo_data === 'previsao') {
        $query .= " AND DATE(c.previsao_liberacao) >= ?";
    } else {
        $query .= " AND DATE(c.data_criacao) >= ?";
    }
    $params[] = $filtro_data_inicio;
    $types .= "s";
} elseif ($filtro_data_fim) {
    if ($filtro_tipo_data === 'previsao') {
        $query .= " AND DATE(c.previsao_liberacao) <= ?";
    } else {
        $query .= " AND DATE(c.data_criacao) <= ?";
    }
    $params[] = $filtro_data_fim;
    $types .= "s";
}

if ($filtro_release) {
    $query .= " AND c.release_id = ?";
    $params[] = $filtro_release;
    $types .= "i";
}

if ($filtro_previsao_liberacao) {
    $query .= " AND c.previsao_liberacao IS NOT NULL";
}

// Query para contar o total de chamados (sem paginação)
$query_count = "SELECT COUNT(*) as total 
          FROM chamados c
          LEFT JOIN chamados_status s ON c.status_id = s.id
          LEFT JOIN chamados_tipos t ON c.tipo_id = t.id
          LEFT JOIN chamados_prioridades p ON c.prioridade_id = p.id
          LEFT JOIN usuarios u ON c.usuario_id = u.id
          LEFT JOIN usuarios r ON c.responsavel_id = r.id
          LEFT JOIN clientes cli ON c.cliente_id = cli.id
          LEFT JOIN chamados_sprints sp ON c.sprint_id = sp.id
          LEFT JOIN chamados_releases rel ON c.release_id = rel.id
          WHERE 1=1";

// Adicionar os mesmos filtros da query principal para a contagem
if (!empty($filtro_status)) {
    $placeholders = implode(',', array_fill(0, count($filtro_status), '?'));
    $query_count .= " AND c.status_id IN ($placeholders)";
}

if ($filtro_tipo) {
    $query_count .= " AND c.tipo_id = ?";
}

if ($filtro_titulo) {
    $query_count .= " AND c.titulo LIKE ?";
}

if ($filtro_prioridade) {
    $query_count .= " AND c.prioridade_id = ?";
}

if (!empty($filtro_responsavel)) {
    if (in_array(0, $filtro_responsavel)) {
        $filtro_responsavel = array_filter($filtro_responsavel, function($val) { return $val !== 0; });
        
        if (!empty($filtro_responsavel)) {
            $placeholders = implode(',', array_fill(0, count($filtro_responsavel), '?'));
            $query_count .= " AND (c.responsavel_id IS NULL OR c.responsavel_id IN ($placeholders))";
        } else {
            $query_count .= " AND c.responsavel_id IS NULL";
        }
    } else {
        $placeholders = implode(',', array_fill(0, count($filtro_responsavel), '?'));
        $query_count .= " AND c.responsavel_id IN ($placeholders)";
    }
}

if ($filtro_marcador) {
    $query_count .= " AND EXISTS (
        SELECT 1 FROM chamados_marcadores_vinculos v
        WHERE v.chamado_id = c.id AND v.marcador_id = ?
    )";
}

if ($filtro_sprint) {
    $query_count .= " AND c.sprint_id = ?";
}

if ($filtro_criado_por) {
    $query_count .= " AND c.usuario_id = ?";
}

if ($filtro_cliente) {
    $query_count .= " AND (c.cliente_id = ? OR EXISTS (
                SELECT 1 FROM chamados_clientes cc 
                WHERE cc.chamado_id = c.id AND cc.cliente_id = ?
              ))";
}

if ($filtro_numero) {
    $query_count .= " AND c.id = ?";
}

if ($filtro_data_inicio && $filtro_data_fim) {
    if ($filtro_tipo_data === 'previsao') {
        $query_count .= " AND DATE(c.previsao_liberacao) BETWEEN ? AND ?";
    } else {
        $query_count .= " AND DATE(c.data_criacao) BETWEEN ? AND ?";
    }
} elseif ($filtro_data_inicio) {
    if ($filtro_tipo_data === 'previsao') {
        $query_count .= " AND DATE(c.previsao_liberacao) >= ?";
    } else {
        $query_count .= " AND DATE(c.data_criacao) >= ?";
    }
} elseif ($filtro_data_fim) {
    if ($filtro_tipo_data === 'previsao') {
        $query_count .= " AND DATE(c.previsao_liberacao) <= ?";
    } else {
        $query_count .= " AND DATE(c.data_criacao) <= ?";
    }
}

if ($filtro_release) {
    $query_count .= " AND c.release_id = ?";
}

if ($filtro_previsao_liberacao) {
    $query_count .= " AND c.previsao_liberacao IS NOT NULL";
}

// Executar a query de contagem usando os mesmos parâmetros
$stmt_count = $conn->prepare($query_count);
if (!empty($params)) {
    $stmt_count->bind_param($types, ...$params);
}
$stmt_count->execute();
$result_count = $stmt_count->get_result();
$total_chamados = $result_count->fetch_assoc()['total'];

$query .= " ORDER BY ";
if ($view_mode === 'kanban') {
    $query .= "FIELD(c.status_id, 1, 2, 10, 3, 7, 8, 9, 4, 12, 11, 5, 6), c.prioridade_id DESC, c.data_criacao DESC";
} else {
    $query .= "c.id DESC, FIELD(c.status_id, 1, 2, 10, 3, 7, 8, 9, 4, 12, 11, 5, 6), c.prioridade_id DESC, c.data_criacao DESC";
}

// Aplicar paginação apenas para lista
if ($view_mode !== 'kanban') {
    $query .= " LIMIT $offset, $itemsPerPage";
}

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();
$chamados = $result->fetch_all(MYSQLI_ASSOC);

// Agrupa por status para o Kanban
$chamados_por_status = [];
foreach ($chamados as $chamado) {
    $chamados_por_status[$chamado['status_id']][] = $chamado;
}

// Alterar a query de estatísticas para incluir previsão de liberação
$query_stats = "SELECT 
    (SELECT COUNT(*) FROM chamados) as total,
    (SELECT COUNT(*) FROM chamados WHERE status_id = 5) as concluidos,
    (SELECT COUNT(*) FROM chamados WHERE status_id IN (3,4,7,8,9)) as em_andamento,
    (SELECT COUNT(*) FROM chamados WHERE status_id = 11) as aplicar_cliente,
    (SELECT COUNT(*) FROM chamados WHERE previsao_liberacao IS NOT NULL) as com_previsao,
    (SELECT MIN(previsao_liberacao) FROM chamados WHERE previsao_liberacao IS NOT NULL AND previsao_liberacao >= CURDATE()) as proxima_previsao";
$stmt_stats = $conn->prepare($query_stats);
$stmt_stats->execute();
$result = $stmt_stats->get_result();

// Atualizar o array de stats
if ($result->num_rows > 0) {
    $stats = $result->fetch_assoc();
} else {
    $stats = [
        'total' => 0,
        'concluidos' => 0,
        'em_andamento' => 0,
        'aplicar_cliente' => 0,
        'com_previsao' => 0,
        'proxima_previsao' => null
    ];
}
?>

<!-- Dashboard Stats -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card text-white bg-primary">
            <div class="card-body">
                <i class="material-icons dashboard-icon">assessment</i>
                <h5 class="card-title">Total</h5>
                <h2 class="card-text"><?php echo $stats['total']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-success">
            <div class="card-body">
                <i class="material-icons dashboard-icon">check_circle</i>
                <h5 class="card-title">Concluídos</h5>
                <h2 class="card-text"><?php echo $stats['concluidos']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-warning">
            <div class="card-body">
                <i class="material-icons dashboard-icon">hourglass_empty</i>
                <h5 class="card-title">Em Andamento</h5>
                <h2 class="card-text"><?php echo $stats['em_andamento']; ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card text-white bg-danger">
            <div class="card-body">
                <i class="material-icons dashboard-icon">publish</i>
                <h5 class="card-title">Aplicar no Cliente</h5>
                <h2 class="card-text"><?php echo $stats['aplicar_cliente']; ?></h2>
            </div>
        </div>
    </div>
</div>



<!-- Container Fixo - Filtros + Ações -->
<div class="sticky-top-container">
    <!-- Card de Filtros -->
    <div class="card mb-0">
        <div class="card-header d-flex justify-content-between align-items-center">
        <div class="btn-group ms-2">
    <button type="button" id="gerarVersaoBtn" class="btn btn-sm btn-outline-success">
        <i class="material-icons me-1">description</i> Gerar Versão
    </button>
    <div class="btn-group">
        <button type="button" id="versaoAtualBtn" class="btn btn-sm btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="material-icons me-1">download</i> Versão atual
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#" id="downloadVersaoAtual">
                <i class="material-icons me-2">download</i> Baixar
            </a></li>
            <li><a class="dropdown-item" href="#" id="copiarLinkVersaoAtual">
                <i class="material-icons me-2">content_copy</i> Copiar Link
            </a></li>
        </ul>
    </div>
</div>
            
            <div class="ms-3 me-3 d-flex align-items-center">
    <span class="badge bg-primary">
        Total: <?php echo $total_chamados; ?>
    </span>
</div>
            <div class="btn-group">
                <a href="?view=kanban<?php 
                    echo isset($_GET['status']) ? '&' . http_build_query(['status' => $_GET['status']]) : ''; 
                    echo isset($_GET['tipo']) ? '&tipo='.$_GET['tipo'] : ''; 
                    echo isset($_GET['prioridade']) ? '&prioridade='.$_GET['prioridade'] : ''; 
                    echo isset($_GET['responsavel']) ? '&' . http_build_query(['responsavel' => $_GET['responsavel']]) : ''; 
                    echo isset($_GET['marcador']) ? '&marcador='.$_GET['marcador'] : '';
                    echo isset($_GET['sprint']) ? '&sprint='.$_GET['sprint'] : ''; 
                    echo isset($_GET['criado_por']) ? '&criado_por='.$_GET['criado_por'] : ''; 
                    echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : ''; 
                    echo isset($_GET['titulo']) ? '&titulo='.$_GET['titulo'] : '';
                    echo isset($_GET['numero']) ? '&numero='.$_GET['numero'] : ''; 
                    echo isset($_GET['data_inicio']) ? '&data_inicio='.$_GET['data_inicio'] : ''; 
                    echo isset($_GET['data_fim']) ? '&data_fim='.$_GET['data_fim'] : '';
                    echo isset($_GET['release']) ? '&release='.$_GET['release'] : '';
                    echo isset($_GET['previsao_liberacao']) ? '&previsao_liberacao='.$_GET['previsao_liberacao'] : '';
                ?>" class="btn btn-sm btn-outline-primary <?php echo $view_mode === 'kanban' ? 'active' : ''; ?>">
                    <i class="material-icons me-1">view_column</i> Kanban
                </a>
                <a href="?view=lista<?php 
                    echo isset($_GET['status']) ? '&' . http_build_query(['status' => $_GET['status']]) : ''; 
                    echo isset($_GET['tipo']) ? '&tipo='.$_GET['tipo'] : ''; 
                    echo isset($_GET['prioridade']) ? '&prioridade='.$_GET['prioridade'] : ''; 
                   echo isset($_GET['responsavel']) ? '&' . http_build_query(['responsavel' => $_GET['responsavel']]) : '';
                   echo isset($_GET['marcador']) ? '&marcador='.$_GET['marcador'] : '';
                    echo isset($_GET['sprint']) ? '&sprint='.$_GET['sprint'] : ''; 
                    echo isset($_GET['criado_por']) ? '&criado_por='.$_GET['criado_por'] : ''; 
                    echo isset($_GET['cliente']) ? '&cliente='.$_GET['cliente'] : '';
                    echo isset($_GET['titulo']) ? '&titulo='.$_GET['titulo'] : ''; 
                    echo isset($_GET['numero']) ? '&numero='.$_GET['numero'] : ''; 
                    echo isset($_GET['data_inicio']) ? '&data_inicio='.$_GET['data_inicio'] : ''; 
                    echo isset($_GET['data_fim']) ? '&data_fim='.$_GET['data_fim'] : '';
                    echo isset($_GET['release']) ? '&release='.$_GET['release'] : '';
                    echo isset($_GET['previsao_liberacao']) ? '&previsao_liberacao='.$_GET['previsao_liberacao'] : '';
                ?>" class="btn btn-sm btn-outline-primary <?php echo $view_mode === 'lista' ? 'active' : ''; ?>">
                    <i class="material-icons me-1">list</i> Lista
                </a>
            </div>
        </div>
        <div class="card-body">
            <form method="get" class="row g-3">
        <div class="col-12 d-flex justify-content-between align-items-center mb-3">
    <h6 class="mb-0">Selecione os filtros:</h6>
    <!-- Cards de filtros ativos - MODIFICADO -->
    <div class="d-flex flex-wrap gap-2 me-auto" id="activeFiltersContainer" style="margin-left: 10px;">
        <?php 
      // Status - Modifique esta parte
if (!empty($filtro_status)) {
    // Mostra o badge se:
    // 1. Não estiver mostrando todos os status possíveis (ou seja, filtro ativo)
    // 2. OU se estiver mostrando um conjunto específico diferente do padrão
    $todos_status = array_column($status_list, 'id');
    $mostrando_todos = count(array_intersect($filtro_status, $todos_status)) == count($todos_status);
    
    if (!$mostrando_todos || !empty($_GET['status'])) {
        $status_selecionados = array_map(function($id) use ($status_list) {
            foreach ($status_list as $status) {
                if ($status['id'] == $id) return $status['nome'];
            }
            return null;
        }, $filtro_status);
        
        $status_selecionados = array_filter($status_selecionados);
        $tooltip_status = implode(', ', $status_selecionados);
        echo '<span class="badge bg-primary" data-bs-toggle="tooltip" title="'.$tooltip_status.'">
            <i class="material-icons me-1" style="font-size:14px">filter_list</i> Status
        </span>';
    }
}

        // Título
if ($filtro_titulo) {
    echo '<span class="badge bg-info text-dark">
            <i class="material-icons me-1" style="font-size:14px">search</i> Título: '.htmlspecialchars($filtro_titulo).'
          </span>';
}
        
        // Tipo
        if ($filtro_tipo) {
            $tipo_nome = '';
            foreach ($tipos_list as $tipo) {
                if ($tipo['id'] == $filtro_tipo) {
                    $tipo_nome = $tipo['nome'];
                    break;
                }
            }
            echo '<span class="badge bg-info text-dark">
                <i class="material-icons me-1" style="font-size:14px">'.$tipo['icone'].'</i> '.$tipo_nome.'
            </span>';
        }
        
        // Prioridade
        if ($filtro_prioridade) {
            $prioridade_nome = '';
            foreach ($prioridades_list as $prioridade) {
                if ($prioridade['id'] == $filtro_prioridade) {
                    $prioridade_nome = $prioridade['nome'];
                    break;
                }
            }
            echo '<span class="badge" style="background-color:'.$prioridade['cor'].'">
                <i class="material-icons me-1" style="font-size:14px">priority_high</i> '.$prioridade_nome.'
            </span>';
        }
        
        // Responsáveis
        if (!empty($filtro_responsavel)) {
            $responsaveis_nomes = [];
            foreach ($equipe_list as $membro) {
                if (in_array($membro['id'], $filtro_responsavel)) {
                    $responsaveis_nomes[] = $membro['nome'];
                }
            }
            $tooltip_responsaveis = implode(', ', $responsaveis_nomes);
            echo '<span class="badge bg-warning text-dark" data-bs-toggle="tooltip" title="'.$tooltip_responsaveis.'">
                <i class="material-icons me-1" style="font-size:14px">person</i> Responsável
            </span>';
        }
        
        // Sprint
        if ($filtro_sprint) {
            $sprint_nome = '';
            foreach ($sprints_list as $sprint) {
                if ($sprint['id'] == $filtro_sprint) {
                    $sprint_nome = $sprint['nome'];
                    break;
                }
            }
            echo '<span class="badge bg-success">
                <i class="material-icons me-1" style="font-size:14px">date_range</i> '.$sprint_nome.'
            </span>';
        }
        
        // Marcador
        if ($filtro_marcador) {
            $marcador_nome = '';
            $marcadores_list = getMarcadoresDisponiveis();
            foreach ($marcadores_list as $marcador) {
                if ($marcador['id'] == $filtro_marcador) {
                    $marcador_nome = $marcador['nome'];
                    break;
                }
            }
            echo '<span class="badge" style="background-color:'.$marcador['cor'].'">
                <i class="material-icons me-1" style="font-size:14px">label</i> '.$marcador_nome.'
            </span>';
        }
        
        // Criado por
        if ($filtro_criado_por) {
            $criador_nome = '';
            foreach ($equipe_list as $membro) {
                if ($membro['id'] == $filtro_criado_por) {
                    $criador_nome = $membro['nome'];
                    break;
                }
            }
            echo '<span class="badge bg-secondary">
                <i class="material-icons me-1" style="font-size:14px">create</i> '.$criador_nome.'
            </span>';
        }
        
        // Cliente
        if ($filtro_cliente) {
            $cliente_nome = '';
            foreach ($clientes_list as $cliente) {
                if ($cliente['id'] == $filtro_cliente) {
                    $cliente_nome = $cliente['nome'];
                    break;
                }
            }
            echo '<span class="badge bg-dark">
                <i class="material-icons me-1" style="font-size:14px">business</i> '.$cliente_nome.'
            </span>';
        }
        
        // Número
        if ($filtro_numero) {
            echo '<span class="badge bg-primary">
                <i class="material-icons me-1" style="font-size:14px">tag</i> #'.$filtro_numero.'
            </span>';
        }
        
      if ($filtro_release) {
    $release_nome = '';
    $release_cor = '#6c757d'; // Cor padrão caso não encontre
    foreach ($releases_list as $release) {
        if ($release['id'] == $filtro_release) {
            $release_nome = $release['nome'];
            $release_cor = $release['cor'] ?? '#6c757d'; // Usa cor padrão se não existir
            break;
        }
    }
    echo '<span class="badge" style="background-color:'.$release_cor.'">
            <i class="material-icons me-1" style="font-size:14px">rocket</i> '.$release_nome.'
          </span>';
}
        
        // Data
        if ($filtro_data_inicio || $filtro_data_fim) {
            $data_text = '';
            $tipo_data_text = $filtro_tipo_data === 'previsao' ? 'Previsão' : 'Criação';
            
            if ($filtro_data_inicio && $filtro_data_fim) {
                $data_text = $tipo_data_text . ': ' . date('d/m/Y', strtotime($filtro_data_inicio)).' - '.date('d/m/Y', strtotime($filtro_data_fim));
            } elseif ($filtro_data_inicio) {
                $data_text = $tipo_data_text . ': A partir de '.date('d/m/Y', strtotime($filtro_data_inicio));
            } else {
                $data_text = $tipo_data_text . ': Até '.date('d/m/Y', strtotime($filtro_data_fim));
            }
            echo '<span class="badge bg-info text-dark">
                <i class="material-icons me-1" style="font-size:14px">calendar_today</i> '.$data_text.'
            </span>';
        }
        
        // Previsão de Liberação
        if ($filtro_previsao_liberacao) {
            echo '<span class="badge bg-info">
                <i class="material-icons me-1" style="font-size:14px">schedule</i> Com Previsão de Liberação
            </span>';
        }
        ?>
    </div>
    <div class="d-flex align-items-center gap-2">
  
        
        <button type="submit" class="btn btn-primary compact-btn me-2">
            <i class="material-icons me-1">filter_alt</i> Filtrar
        </button>
        <a href="index.php?limpar=1" class="btn btn-outline-secondary compact-btn">
            <i class="material-icons me-1">clear</i> Limpar
        </a>

              <!-- Botão de inversão de tipo de data -->
        <button type="button" id="toggleTipoData" class="btn <?php echo $filtro_tipo_data === 'previsao' ? 'btn-success' : 'btn-outline-success'; ?> compact-btn" 
                title="Alternar entre filtro por data de criação e data de previsão">
            <i class="material-icons me-1">swap_horiz</i>
            <span id="tipoDataText"><?php echo $filtro_tipo_data === 'previsao' ? 'Previsão' : 'Criação'; ?></span>
        </button>
<!-- Botão dropdown para filtros salvos -->
<div class="btn-group ms-2 dropdown-filtros">
    <button type="button" class="btn btn-sm btn-outline-primary compact-btn dropdown-toggle px-2" data-bs-toggle="dropdown" aria-expanded="false" title="Filtros Salvos">
        <i class="material-icons">bookmark</i>
    </button>
    <ul class="dropdown-menu dropdown-menu-end">
        <?php 
        $filtros_salvos = getFiltrosUsuario($_SESSION['usuario_id']);
        foreach ($filtros_salvos as $filtro): 
            $filtros = json_decode($filtro['filtros'], true);
            $url = 'index.php?' . http_build_query($filtros);
        ?>
            <li class="d-flex justify-content-between align-items-center px-2 py-1">
                <a class="dropdown-item flex-grow-1" href="<?= $url ?>">
                    <?= htmlspecialchars($filtro['nome']) ?>
                    <?php if ($filtro['compartilhado']): ?>
                        <span class="badge bg-info ms-1">Compart.</span>
                    <?php endif; ?>
                    <?php if ($filtro['padrao']): ?>
                        <span class="badge bg-success ms-1">Padrão</span>
                    <?php endif; ?>
                </a>
                <?php if ($filtro['usuario_id'] == $_SESSION['usuario_id']): ?>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-link text-primary p-0 edit-filter" 
                                data-id="<?= $filtro['id'] ?>"
                                data-nome="<?= htmlspecialchars($filtro['nome']) ?>"
                                data-compartilhado="<?= $filtro['compartilhado'] ?>"
                                data-padrao="<?= $filtro['padrao'] ?>"
                                title="Editar filtro">
                            <i class="material-icons" style="font-size:18px">edit</i>
                        </button>
                        <button class="btn btn-sm btn-link text-primary p-0 set-default-filter" 
                                data-id="<?= $filtro['id'] ?>" 
                                title="Definir como padrão">
                            <i class="material-icons" style="font-size:18px">star</i>
                        </button>
                        <button class="btn btn-sm btn-link text-danger p-0 delete-filter" 
                                data-id="<?= $filtro['id'] ?>" 
                                title="Excluir filtro">
                            <i class="material-icons" style="font-size:18px">delete</i>
                        </button>
                    </div>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
        <li><hr class="dropdown-divider"></li>
        <li>
            <a class="dropdown-item py-1 small" href="#" data-bs-toggle="modal" data-bs-target="#salvarFiltroModal">
                <i class="material-icons" style="font-size: 16px">save</i> Salvar Filtro Atual
            </a>
        </li>
    </ul>
</div>
    </div>
</div>
                
                <div class="col-md-9">
                    <div class="row g-3">

                    <div class="col-md-3">
    <label for="cliente" class="form-label">Cliente</label>
    <div class="custom-select">
        <input type="text" id="cliente_filter" placeholder="Digite para filtrar..." class="form-control">
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

                        <div class="col-md-3">
    <label for="titulo" class="form-label">Título</label>
    <input type="text" id="titulo" name="titulo" class="form-control" 
           value="<?php echo $filtro_titulo ? htmlspecialchars($filtro_titulo) : ''; ?>" 
           placeholder="Buscar por título">
</div>
                        <div class="col-md-3">
                            <label for="tipo" class="form-label">Tipo</label>
                            <select id="tipo" name="tipo" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($tipos_list as $tipo): ?>
                                    <option value="<?php echo $tipo['id']; ?>" <?php echo $filtro_tipo == $tipo['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="prioridade" class="form-label">Prioridade</label>
                            <select id="prioridade" name="prioridade" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach ($prioridades_list as $prioridade): ?>
                                    <option value="<?php echo $prioridade['id']; ?>" <?php echo $filtro_prioridade == $prioridade['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($prioridade['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                <div class="col-md-3">
    <label class="form-label">Responsável</label>
    <div class="dropdown">
        <button class="form-select text-start" type="button" id="responsavelDropdown" data-bs-toggle="dropdown" aria-expanded="false">
            <?php echo !empty($filtro_responsavel) ? count($filtro_responsavel) . ' selecionados' : 'Todos'; ?>
        </button>
        <div class="dropdown-menu p-3" aria-labelledby="responsavelDropdown" style="width: 280px; max-height: 300px; overflow-y: auto;">
            <div class="form-check mb-2">
                <input class="form-check-input" type="checkbox" id="select-all-responsaveis" onclick="toggleAllResponsaveis(this)">
                <label class="form-check-label" for="select-all-responsaveis">
                    <strong>Todos</strong>
                </label>
            </div>
            <hr class="my-1">
            <!-- Adicione esta opção para "Sem responsável" -->
            <div class="form-check">
                <input class="form-check-input responsavel-checkbox" type="checkbox" name="responsavel[]" 
                       value="0" 
                       id="responsavel-0"
                       <?php echo in_array(0, $filtro_responsavel) ? 'checked' : ''; ?>>
                <label class="form-check-label" for="responsavel-0">
                    Sem responsável
                </label>
            </div>
            <?php foreach ($equipe_list as $membro): ?>
                <div class="form-check">
                    <input class="form-check-input responsavel-checkbox" type="checkbox" name="responsavel[]" 
                           value="<?php echo $membro['id']; ?>" 
                           id="responsavel-<?php echo $membro['id']; ?>"
                           <?php echo in_array($membro['id'], $filtro_responsavel) ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="responsavel-<?php echo $membro['id']; ?>">
                        <?php echo htmlspecialchars($membro['nome']); ?>
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
                        
                        <div class="col-md-3">
                            <label for="sprint" class="form-label">Sprint</label>
                            <select id="sprint" name="sprint" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach ($sprints_list as $sprint): ?>
                                    <option value="<?php echo $sprint['id']; ?>" <?php echo $filtro_sprint == $sprint['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sprint['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>



<div class="col-md-3">
    <label for="marcador" class="form-label">Marcador</label>
    <div class="custom-select">
        <input type="text" id="marcador_filter" placeholder="Digite para filtrar marcadores..." class="form-control" 
               value="<?php 
                   if ($filtro_marcador) {
                       foreach ($marcadores_list as $marcador) {
                           if ($marcador['id'] == $filtro_marcador) {
                               echo htmlspecialchars($marcador['nome']);
                               break;
                           }
                       }
                   }
               ?>">
        <div class="options" id="marcador_options">
            <div data-value="">Todos</div>
            <?php 
            $marcadores_list = getMarcadoresDisponiveis();
            foreach ($marcadores_list as $marcador): ?>
                <div data-value="<?= $marcador['id'] ?>">
                    <span class="marcador-cor" style="background-color: <?= $marcador['cor'] ?>; display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 5px;"></span>
                    <?= htmlspecialchars($marcador['nome']) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <select id="marcador" name="marcador" class="form-select d-none">
            <option value="">Todos</option>
            <?php foreach ($marcadores_list as $marcador): ?>
                <option value="<?= $marcador['id'] ?>" <?= (isset($_GET['marcador']) && $_GET['marcador'] == $marcador['id']) ? 'selected' : '' ?>>
                    <?= htmlspecialchars($marcador['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
                        
                        <div class="col-md-3">
                            <label for="criado_por" class="form-label">Criado por</label>
                            <select id="criado_por" name="criado_por" class="form-select">
                                <option value="">Todos</option>
                                <?php foreach ($equipe_list as $membro): ?>
                                    <option value="<?php echo $membro['id']; ?>" <?php echo $filtro_criado_por == $membro['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($membro['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        

                        
                        <div class="col-md-3">
                            <label for="numero" class="form-label">N° do Chamado</label>
                            <input type="number" id="numero" name="numero" class="form-control" 
                                   value="<?php echo $filtro_numero ? htmlspecialchars($filtro_numero) : ''; ?>" 
                                   placeholder="Digite o número">
                        </div>
                        <div class="col-md-3">
                            <label for="release" class="form-label">Release</label>
                            <select id="release" name="release" class="form-select">
                                <option value="">Todas</option>
                                <?php foreach ($releases_list as $release): ?>
                                    <option value="<?php echo $release['id']; ?>" <?php echo $filtro_release == $release['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($release['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="data_inicio" class="form-label">Data Início</label>
                            <input type="date" id="data_inicio" name="data_inicio" class="form-control" 
                                   value="<?php echo $filtro_data_inicio ? htmlspecialchars($filtro_data_inicio) : ''; ?>">
                        </div>

                        <div class="col-md-3">
                            <label for="data_fim" class="form-label">Data Fim</label>
                            <input type="date" id="data_fim" name="data_fim" class="form-control" 
                                   value="<?php echo $filtro_data_fim ? htmlspecialchars($filtro_data_fim) : ''; ?>">
                        </div>
                        

                    </div>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <div class="border rounded p-2 bg-light" style="max-height: 195px; overflow-y: auto;">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="select-all-status" onclick="toggleAllStatus(this)">
                            <label class="form-check-label fw-bold" for="select-all-status">Todos</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="default-status" onclick="selectDefaultStatus()">
                            <label class="form-check-label fw-bold" for="default-status">Padrão</label>
                        </div>
                        <hr class="my-1">
                        <?php foreach ($status_list as $status): ?>
                        <div class="form-check">
                            <input class="form-check-input status-checkbox" type="checkbox" name="status[]" 
                                   value="<?php echo $status['id']; ?>" 
                                   id="status-<?php echo $status['id']; ?>"
                                   <?php echo in_array($status['id'], $filtro_status) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="status-<?php echo $status['id']; ?>">
                                <?php echo htmlspecialchars($status['nome']); ?>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <input type="hidden" name="view" value="<?php echo $view_mode; ?>">
                <input type="hidden" name="tipo_data" id="tipoDataInput" value="<?php echo $filtro_tipo_data; ?>">
            </form>
        </div>
    </div>

<!-- Ações Globais -->
<div class="global-actions-container">
    <button id="globalBulkActionsBtn" class="btn btn-primary btn-sm position-relative">
        <i class="material-icons me-1">playlist_add_check</i> 
        Ações em Massa
        <span id="selectedItemsCounter" class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.8em; display: none;">
            <span id="selectedCount">0</span> selecionados
        </span>
    </button>
    
    <!-- Lembrete de Previsão de Liberação - Centro -->
    <?php if ($stats['com_previsao'] > 0): ?>
    <?php 
    // Calcular cor baseada na proximidade da data
    $cor_classe = 'text-success'; // Padrão verde para mais de 15 dias
    $cor_bg = 'bg-success';
    
    if ($stats['proxima_previsao']) {
        $hoje = new DateTime();
        $data_previsao = new DateTime($stats['proxima_previsao']);
        $diferenca_dias = $hoje->diff($data_previsao)->days;
        
        // Se a data já passou, usar vermelho
        if ($data_previsao < $hoje) {
            $cor_classe = 'text-danger';
            $cor_bg = 'bg-danger';
        }
        // Se está na mesma semana (próximos 7 dias)
        elseif ($diferenca_dias <= 7) {
            $cor_classe = 'text-danger';
            $cor_bg = 'bg-danger';
        }
        // Se está nos próximos 15 dias
        elseif ($diferenca_dias <= 15) {
            $cor_classe = 'text-warning';
            $cor_bg = 'bg-warning';
        }
        // Mais de 15 dias - verde (já definido como padrão)
    }
    ?>
    <div class="d-flex align-items-center justify-content-center flex-grow-1 <?php echo $cor_classe; ?>" 
         style="cursor: pointer;" 
         onclick="filtrarPorPrevisao()" 
         title="Clique para filtrar chamados com previsão de liberação">
        <i class="material-icons me-2">schedule</i>
        <div style="font-size: 0.99rem;">
            <strong>Lembrete:</strong> 
            <span class="badge <?php echo $cor_bg; ?> me-1"><?php echo $stats['com_previsao']; ?> chamados</span>
            <?php if ($stats['proxima_previsao']): ?>
                <span>Próxima liberação: <strong><?php echo date('d/m/Y', strtotime($stats['proxima_previsao'])); ?></strong></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>

<?php if ($view_mode === 'kanban'): ?>
    <div class="kanban-container">
        <!-- Botões de Navegação Lateral -->
        <button class="kanban-nav-btn kanban-nav-left" id="kanbanNavLeft" title="Navegar para esquerda">
            <i class="material-icons">chevron_left</i>
        </button>
        <button class="kanban-nav-btn kanban-nav-right" id="kanbanNavRight" title="Navegar para direita">
            <i class="material-icons">chevron_right</i>
        </button>
        <div class="kanban-scroll-container" id="kanbanScrollContainer">
            <div class="kanban-board">
                <?php 
                // Ordenar os status conforme a ordem desejada
                $ordered_status = [];
                foreach ([1, 2, 10, 3, 7, 8, 9, 4, 12, 11, 5, 6] as $status_id) {
                    foreach ($status_list as $status) {
                        if ($status['id'] == $status_id) {
                            $ordered_status[] = $status;
                            break;
                        }
                    }
                }
                
                // Mostrar apenas os status que estão no filtro
                foreach ($ordered_status as $status): 
                    if (in_array($status['id'], $filtro_status)): 
                        // Calcular total de pontos para este status
                        $total_pontos = 0;
                        if (isset($chamados_por_status[$status['id']])) {
                            foreach ($chamados_por_status[$status['id']] as $chamado) {
                                $total_pontos += $chamado['pontos_historia'] ? (int)$chamado['pontos_historia'] : 0;
                            }
                        }
                        ?>
                        <div class="kanban-column-wrapper">
                            <div class="kanban-column-header" style="border-left: 4px solid <?php echo $status['cor']; ?>">
                                <div class="kanban-column-title">
                                    <div class="form-check">
                                        <input class="form-check-input select-all-cards" type="checkbox" 
                                               data-status="<?php echo $status['id']; ?>" 
                                               id="select-all-<?php echo $status['id']; ?>">
                                        <label class="form-check-label" for="select-all-<?php echo $status['id']; ?>">
                                            <?php echo htmlspecialchars($status['nome']); ?>
                                        </label>
                                    </div>
                                    <div class="kanban-column-stats">
                                        <span class="kanban-count">
                                            <?php 
                                                $count = 0;
                                                if (isset($chamados_por_status[$status['id']])) {
                                                    $count = count($chamados_por_status[$status['id']]);
                                                }
                                                echo $count;
                                            ?>
                                        </span>
                                        <?php if ($total_pontos > 0): ?>
                                            <span class="kanban-points" title="Total de pontos de história">
                                              
                                                <?php echo $total_pontos; ?> pts
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="kanban-column" data-status="<?php echo $status['id']; ?>">
                                <?php if (isset($chamados_por_status[$status['id']])): ?>
                                    <?php foreach ($chamados_por_status[$status['id']] as $chamado): ?>
                                        <div class="kanban-card" data-id="<?php echo $chamado['id']; ?>">
                                            <div class="kanban-card-checkbox">
                                                <input type="checkbox" class="kanban-card-select" name="selected_cards[]" value="<?php echo $chamado['id']; ?>">
                                            </div>
                                            <div class="kanban-card-header">
                                                <span class="kanban-card-priority" style="background-color: <?php echo $chamado['prioridade_cor']; ?>">
                                                    <?php echo $chamado['prioridade_nome']; ?>
                                                </span>
                                                <span class="kanban-card-number">#<?php echo $chamado['id']; ?></span>
                                            </div>
                                            <div class="kanban-card-body">
                                                <div class="kanban-card-icon">
                                                    <i class="material-icons"><?php echo $chamado['tipo_icone']; ?></i>
                                                </div>
                                                <a href="visualizar.php?id=<?php echo $chamado['id']; ?>" class="kanban-card-title">
                                                    <?php echo htmlspecialchars($chamado['titulo']); ?>
                                                </a>
                                            </div>
                                            <!-- Novo: Tipo do chamado -->
                                            <div class="kanban-card-type">
                                                <i class="material-icons" style="font-size:14px; color:#6c757d;"><?php echo $chamado['tipo_icone']; ?></i>
                                                <span><?php echo htmlspecialchars($chamado['tipo_nome']); ?></span>
                                            </div>
                                            <?php if ($chamado['cliente_nome']): ?>
                                            <div class="kanban-card-client">
                                                <i class="material-icons">person</i>
                                                <?php echo htmlspecialchars(($chamado['cliente_contrato'] ? $chamado['cliente_contrato'] . ' - ' : '') . $chamado['cliente_nome']); ?>
                                            </div>
                                            <?php endif; ?>
                                            <div class="kanban-card-footer">
                                                <div class="kanban-card-responsible">
                                                    <?php if ($chamado['responsavel_nome']): ?>
                                                        <div class="kanban-avatar">
                                                            <?php echo substr($chamado['responsavel_nome'], 0, 1); ?>
                                                        </div>
                                                        <span><?php echo htmlspecialchars($chamado['responsavel_nome']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="kanban-card-date">
                                                    <?php echo date('d/m/Y', strtotime($chamado['data_criacao'])); ?>
                                                </div>
                                            </div>
                                            <?php if ($chamado['release_nome']): ?>
                                            <div class="kanban-card-release" style="--release-color: <?= $chamado['release_cor'] ?>">
                                                <i class="material-icons">rocket</i>
                                                <?= htmlspecialchars($chamado['release_nome']) ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if ($chamado['previsao_liberacao']): ?>
                                            <div class="kanban-card-forecast">
                                                <i class="material-icons">schedule</i>
                                                <span><?php echo date('d/m/Y', strtotime($chamado['previsao_liberacao'])); ?></span>
                                            </div>
                                            <?php endif; ?>
                                            <div class="kanban-card-points">
    <select class="form-select form-select-sm pontos-historia-select" 
            data-chamado-id="<?php echo $chamado['id']; ?>"
            title="Pontos de História">
        <option value="">-</option>
        <?php foreach (getPontosHistoriaOptions() as $option): ?>
            <option value="<?php echo $option['value']; ?>" 
                <?php echo $chamado['pontos_historia'] == $option['value'] ? 'selected' : ''; ?>>
                <?php echo $option['value']; ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="kanban-empty">
                                        <i class="material-icons">inbox</i>
                                        <span>Nenhum chamado</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php else: ?>
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                  <thead>
    <tr>
        <th width="40">
            <div class="form-check">
                <input class="form-check-input select-all-list" type="checkbox" id="select-all-list">
            </div>
        </th>
        <th>ID</th>
        <th>Título</th>
        <th>Tipo</th>
        <th>Prioridade</th>
        <th>Status</th>
        <th>Pontos</th>
        <th>Release</th>
        <th>Previsão</th>
        <th>Cliente</th>
        <th>Criado por</th>
        <th>Ações</th>
    </tr>
</thead>
<tbody>
    <?php if (!empty($chamados)): ?>
        <?php foreach ($chamados as $chamado): ?>
        <tr>
            <td>
                <div class="form-check">
                    <input class="form-check-input list-card-select" type="checkbox" 
                           name="selected_cards[]" value="<?php echo $chamado['id']; ?>"
                           data-status="<?php echo $chamado['status_id']; ?>">
                </div>
            </td>
            <td>#<?php echo $chamado['id']; ?></td>
                                <td>
                                    <i class="material-icons type-icon"><?php echo $chamado['tipo_icone']; ?></i>
                                    <a href="visualizar.php?id=<?php echo $chamado['id']; ?>"><?php echo htmlspecialchars($chamado['titulo']); ?></a>
                                </td>
                                <td><?php echo $chamado['tipo_nome']; ?></td>
                                <td>
                                    <span class="badge priority-badge" style="background-color: <?php echo $chamado['prioridade_cor']; ?>">
                                        <?php echo $chamado['prioridade_nome']; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge" style="background-color: <?php echo $chamado['status_cor']; ?>">
                                        <?php echo $chamado['status_nome']; ?>
                                    </span>
                                </td>
                                <td>
    <?php if ($chamado['pontos_historia']): ?>
        <span class="badge bg-info"><?= $chamado['pontos_historia'] ?> pts</span>
    <?php else: ?>
        -
    <?php endif; ?>
</td>
                                <td>
                                    <?php if ($chamado['release_nome']): ?>
                                        <span class="badge" style="background-color: <?php echo $chamado['release_cor']; ?>">
                                            <?php echo htmlspecialchars($chamado['release_nome']); ?>
                                        </span>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($chamado['previsao_liberacao']): ?>
                                        <i class="material-icons me-1" style="font-size: 16px; vertical-align: middle; color: #6c757d;">schedule</i>
                                        <?php echo date('d/m/Y', strtotime($chamado['previsao_liberacao'])); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($chamado['cliente_nome']): ?>
                                        <?php echo htmlspecialchars(($chamado['cliente_contrato'] ? $chamado['cliente_contrato'] . ' - ' : '') . $chamado['cliente_nome']); ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($chamado['usuario_nome']); ?></td>
                                <td>
                                    <a href="editar.php?id=<?php echo $chamado['id']; ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="material-icons" style="font-size:18px">edit</i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="12" class="text-center text-muted py-4">Nenhum chamado encontrado com os filtros selecionados</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <?php if ($view_mode !== 'kanban' && $total_chamados > $itemsPerPage): ?>
                    <!-- Navegação de Paginação -->
                    <nav aria-label="Navegação de páginas" class="mt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                Mostrando <?php echo min($offset + 1, $total_chamados); ?> a <?php echo min($offset + $itemsPerPage, $total_chamados); ?> de <?php echo $total_chamados; ?> registros
                            </div>
                            <ul class="pagination pagination-sm mb-0">
                                <?php 
                                $totalPages = ceil($total_chamados / $itemsPerPage);
                                $currentPageNum = $page;
                                
                                // Botão Anterior
                                if ($currentPageNum > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPageNum - 1])); ?>">
                                            <i class="material-icons" style="font-size: 16px;">chevron_left</i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            <i class="material-icons" style="font-size: 16px;">chevron_left</i>
                                        </span>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                // Lógica para mostrar páginas
                                $startPage = max(1, $currentPageNum - 2);
                                $endPage = min($totalPages, $currentPageNum + 2);
                                
                                // Primeira página
                                if ($startPage > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>">1</a>
                                    </li>
                                    <?php if ($startPage > 2): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                <?php endif; ?>
                                
                                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                    <li class="page-item <?php echo $i == $currentPageNum ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>"><?php echo $i; ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php
                                // Última página
                                if ($endPage < $totalPages): ?>
                                    <?php if ($endPage < $totalPages - 1): ?>
                                        <li class="page-item disabled"><span class="page-link">...</span></li>
                                    <?php endif; ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>"><?php echo $totalPages; ?></a>
                                    </li>
                                <?php endif; ?>
                                
                                <!-- Botão Próximo -->
                                <?php if ($currentPageNum < $totalPages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $currentPageNum + 1])); ?>">
                                            <i class="material-icons" style="font-size: 16px;">chevron_right</i>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            <i class="material-icons" style="font-size: 16px;">chevron_right</i>
                                        </span>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modal Ações em Massa -->
<div class="modal fade" id="bulkActionsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Ações em Massa</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="bulkActionsForm">
                    <input type="hidden" id="bulkCurrentStatus" name="current_status">
                    <input type="hidden" id="bulkSelectedCards" name="selected_cards">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="bulkNewStatus" class="form-label">Alterar Status</label>
                            <select class="form-select" id="bulkNewStatus" name="new_status">
                                <option value="">Manter status atual</option>
                                <?php foreach ($status_list as $status): ?>
                                    <option value="<?php echo $status['id']; ?>"><?php echo htmlspecialchars($status['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bulkSprint" class="form-label">Sprint</label>
                            <select class="form-select" id="bulkSprint" name="sprint_id">
                                <option value="">Manter sprint atual</option>
                                <option value="remove" style="color: #dc3545; font-weight: bold;">🗑️ Remover Sprint</option>
                                <optgroup label="Atribuir Sprint:">
                                    <?php foreach ($sprints_list as $sprint): ?>
                                        <option value="<?php echo $sprint['id']; ?>"><?php echo htmlspecialchars($sprint['nome']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bulkRelease" class="form-label">Release</label>
                            <select class="form-select" id="bulkRelease" name="release_id">
                                <option value="">Manter release atual</option>
                                <option value="remove" style="color: #dc3545; font-weight: bold;">🗑️ Remover Release</option>
                                <optgroup label="Atribuir Release:">
                                    <?php foreach ($releases_list as $release): ?>
                                        <option value="<?php echo $release['id']; ?>"><?php echo htmlspecialchars($release['nome']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bulkResponsible" class="form-label">Responsável</label>
                            <select class="form-select" id="bulkResponsible" name="responsavel_id">
                                <option value="">Manter responsável atual</option>
                                <option value="remove" style="color: #dc3545; font-weight: bold;">🗑️ Remover Responsável</option>
                                <optgroup label="Definir Responsável:">
                                    <?php foreach ($equipe_list as $membro): ?>
                                        <option value="<?php echo $membro['id']; ?>"><?php echo htmlspecialchars($membro['nome']); ?></option>
                                    <?php endforeach; ?>
                                </optgroup>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bulkPriority" class="form-label">Alterar Prioridade</label>
                            <select class="form-select" id="bulkPriority" name="prioridade_id">
                                <option value="">Manter prioridade atual</option>
                                <?php foreach ($prioridades_list as $prioridade): ?>
                                    <option value="<?php echo $prioridade['id']; ?>"><?php echo htmlspecialchars($prioridade['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label for="bulkPoints" class="form-label">Pontos de História</label>
                            <select class="form-select" id="bulkPoints" name="pontos_historia">
                                <option value="">Manter pontos atuais</option>
                                <?php foreach (getPontosHistoriaOptions() as $option): ?>
                                    <option value="<?php echo $option['value']; ?>"><?php echo $option['value']; ?> pontos</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="confirmBulkAction">Aplicar Alterações</button>
            </div>
        </div>
    </div>
</div>
<!-- Modal Versão Gerada -->
<div class="modal fade" id="versaoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Versão Gerada</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="versaoContainer" class="updates"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                <button type="button" class="btn btn-primary" onclick="copiarVersao()">
                    <i class="material-icons me-1">content_copy</i> Copiar HTML
                </button>
            </div>
        </div>
    </div>
</div>
<!-- Modal para salvar/editar filtro -->
<div class="modal fade" id="salvarFiltroModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="filtroModalTitle">Salvar Filtro</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSalvarFiltro" method="post" action="api/salvar_filtro.php">
                <div class="modal-body">
                    <input type="hidden" id="filtroId" name="filtro_id" value="">
                    <div class="mb-3">
                        <label for="nomeFiltro" class="form-label">Nome do Filtro</label>
                        <input type="text" class="form-control" id="nomeFiltro" name="nome" required>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="compartilharFiltro" name="compartilhado">
                        <label class="form-check-label" for="compartilharFiltro">
                            Compartilhar com outros usuários
                        </label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="filtroPadrao" name="padrao">
                        <label class="form-check-label" for="filtroPadrao">
                            Definir como filtro padrão
                        </label>
                    </div>
                    <!-- Campos ocultos para armazenar os parâmetros atuais -->
                    <?php foreach ($_GET as $key => $value): ?>
                        <?php if (is_array($value)): ?>
                            <?php foreach ($value as $val): ?>
                                <input type="hidden" name="<?= htmlspecialchars($key) ?>[]" value="<?= htmlspecialchars($val) ?>">
                            <?php endforeach; ?>
                        <?php else: ?>
                            <input type="hidden" name="<?= htmlspecialchars($key) ?>" value="<?= htmlspecialchars($value) ?>">
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Estilo específico para o botão Versão atual */
#versaoAtualBtn.dropdown-toggle::after {
    margin-left: 10px !important;
}

/* Garantir que o botão Versão atual tenha o mesmo estilo do Gerar Versão */
#versaoAtualBtn {
    font-weight: normal !important;
}
</style>

<script>
// Variável global para armazenar os dados da versão atual
let versaoAtualData = null;

// Função para alternar tipo de data
function toggleTipoData() {
    const tipoDataInput = document.getElementById('tipoDataInput');
    const tipoDataText = document.getElementById('tipoDataText');
    const toggleButton = document.getElementById('toggleTipoData');
    
    if (tipoDataInput.value === 'criacao') {
        tipoDataInput.value = 'previsao';
        tipoDataText.textContent = 'Previsão';
        // Muda para verde claro preenchido (btn-success)
        toggleButton.className = 'btn btn-success compact-btn';
    } else {
        tipoDataInput.value = 'criacao';
        tipoDataText.textContent = 'Criação';
        // Muda para verde escuro outline (btn-outline-success)
        toggleButton.className = 'btn btn-outline-success compact-btn';
    }
}

// Função para filtrar por previsão de liberação
function filtrarPorPrevisao() {
    // Pega os parâmetros atuais da URL
    const urlParams = new URLSearchParams(window.location.search);
    
    // Adiciona o filtro de previsão de liberação
    urlParams.set('previsao_liberacao', '1');
    
    // Redireciona com os novos parâmetros
    window.location.href = 'index.php?' + urlParams.toString();
}

// Função para buscar dados da versão atual
async function buscarVersaoAtual() {
    try {
        const response = await fetch('api/get_latest_release.php');
        const data = await response.json();
        
        if (data.success) {
             versaoAtualData = data.release;
             // Atualiza o texto do botão com o nome da versão
             document.getElementById('versaoAtualBtn').innerHTML = 
                 '<i class="material-icons me-1">download</i> Versão atual &nbsp;&nbsp;' + data.release.nome;
        } else {
            console.error('Erro ao buscar versão atual:', data.message);
            // Desabilita o botão se não houver versão lançada
            document.getElementById('versaoAtualBtn').disabled = true;
            document.getElementById('versaoAtualBtn').innerHTML = 
                '<i class="material-icons me-1">download</i> Nenhuma versão lançada';
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
        document.getElementById('versaoAtualBtn').disabled = true;
    }
}

// Função para fazer download da versão atual
function downloadVersaoAtual() {
    if (versaoAtualData && versaoAtualData.download_link) {
        // Cria um link temporário e clica nele para iniciar o download
        const link = document.createElement('a');
        link.href = versaoAtualData.download_link;
        link.download = 'NUTIFY_PDV_' + versaoAtualData.nome + '.exe';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        
        // Mostra toast de sucesso
        showToast('Download iniciado: NUTIFY_PDV_' + versaoAtualData.nome + '.exe', 'success');
    } else {
        showToast('Erro: Dados da versão não disponíveis', 'danger');
    }
}

// Função para copiar link da versão atual
async function copiarLinkVersaoAtual() {
    if (versaoAtualData && versaoAtualData.download_link) {
        try {
            await navigator.clipboard.writeText(versaoAtualData.download_link);
            showToast('Link copiado para a área de transferência!', 'success');
        } catch (err) {
            // Fallback para navegadores mais antigos
            const textArea = document.createElement('textarea');
            textArea.value = versaoAtualData.download_link;
            document.body.appendChild(textArea);
            textArea.select();
            document.execCommand('copy');
            document.body.removeChild(textArea);
            showToast('Link copiado para a área de transferência!', 'success');
        }
    } else {
        showToast('Erro: Link não disponível', 'danger');
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Busca os dados da versão atual ao carregar a página
    buscarVersaoAtual();
    
    // Event listener para o botão de alternância de tipo de data
    document.getElementById('toggleTipoData').addEventListener('click', toggleTipoData);
    
    // Event listener para o botão de download
    document.getElementById('downloadVersaoAtual').addEventListener('click', function(e) {
        e.preventDefault();
        downloadVersaoAtual();
    });
    
    // Event listener para o botão de copiar link
    document.getElementById('copiarLinkVersaoAtual').addEventListener('click', function(e) {
        e.preventDefault();
        copiarLinkVersaoAtual();
    });
});

// Exibir mensagens de sessão como notificações flutuantes
<?php if (isset($_SESSION['success'])): ?>
    showToast('<?php echo addslashes($_SESSION['success']); ?>', 'success');
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    showToast('<?php echo addslashes($_SESSION['error']); ?>', 'danger');
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>