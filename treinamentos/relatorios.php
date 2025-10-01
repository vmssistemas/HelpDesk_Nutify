<?php
require_once 'includes/header_treinamentos.php';

// Filtros de período
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : '';
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : '';
$filtro_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$filtro_responsavel = isset($_GET['responsavel']) ? $_GET['responsavel'] : '';
$filtro_status = isset($_GET['status']) ? (is_array($_GET['status']) ? $_GET['status'] : [$_GET['status']]) : [];
$filtro_tipo = isset($_GET['tipo']) ? $_GET['tipo'] : '';
$filtro_plano = isset($_GET['plano']) ? $_GET['plano'] : '';

// Configuração de paginação
$registros_por_pagina = 20;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Construir cláusula WHERE
$where_conditions = [];
if (!empty($filtro_data_inicio) && !empty($filtro_data_fim)) {
    $where_conditions[] = "DATE(t.data_criacao) BETWEEN '$filtro_data_inicio' AND '$filtro_data_fim'";
}
if (!empty($filtro_cliente)) {
    $where_conditions[] = "c.id = '$filtro_cliente'";
}
if (!empty($filtro_responsavel)) {
    if ($filtro_responsavel == 'sem') {
        $where_conditions[] = "t.responsavel_id IS NULL";
    } else {
        $where_conditions[] = "t.responsavel_id = '$filtro_responsavel'";
    }
}
if (!empty($filtro_status) && !in_array('', $filtro_status)) {
    // Remove valores vazios do array
    $filtro_status = array_filter($filtro_status, function($value) { return $value !== ''; });
    
    if (!empty($filtro_status)) {
        $status_ids = implode(',', array_map('intval', $filtro_status));
        $where_conditions[] = "t.status_id IN ($status_ids)";
    }
}
if (!empty($filtro_tipo)) {
    $where_conditions[] = "t.tipo_id = '$filtro_tipo'";
}
if (!empty($filtro_plano)) {
    $where_conditions[] = "t.plano_id = '$filtro_plano'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 1. Total de treinamentos no período
$query_total = "SELECT COUNT(*) as total FROM treinamentos t";
if (!empty($filtro_cliente)) {
    $query_total .= " JOIN clientes c ON t.cliente_id = c.id";
}
$query_total .= " $where_clause";
$result_total = $conn->query($query_total);
$total_treinamentos = $result_total->fetch_assoc()['total'];

// Calcular o total de páginas
$total_paginas = ceil($total_treinamentos / $registros_por_pagina);

// 2. Treinamentos por status
$query_por_status = "SELECT ts.nome, ts.cor, COUNT(*) as total 
                     FROM treinamentos t 
                     JOIN treinamentos_status ts ON t.status_id = ts.id";
if (!empty($filtro_cliente)) {
    $query_por_status .= " JOIN clientes c ON t.cliente_id = c.id";
}
$query_por_status .= " $where_clause 
                     GROUP BY ts.id, ts.nome, ts.cor 
                     ORDER BY ts.ordem";
$result_por_status = $conn->query($query_por_status);
$treinamentos_por_status = $result_por_status->fetch_all(MYSQLI_ASSOC);

// Adicionar porcentagem para cada status
foreach ($treinamentos_por_status as &$status) {
    $status['percentual'] = $total_treinamentos > 0 ? round(($status['total'] / $total_treinamentos) * 100, 2) : 0;
}
unset($status);

// 3. Treinamentos por tipo
$query_por_tipo = "SELECT tt.nome, COUNT(*) as total 
                   FROM treinamentos t 
                   JOIN treinamentos_tipos tt ON t.tipo_id = tt.id";
if (!empty($filtro_cliente)) {
    $query_por_tipo .= " JOIN clientes c ON t.cliente_id = c.id";
}
$query_por_tipo .= " $where_clause 
                   GROUP BY tt.id, tt.nome 
                   ORDER BY total DESC";
$result_por_tipo = $conn->query($query_por_tipo);
$treinamentos_por_tipo = $result_por_tipo->fetch_all(MYSQLI_ASSOC);

// 4. Análise de sobrecarga por técnico (responsável)
$query_sobrecarga = "SELECT 
                        u.nome as responsavel_nome,
                        COUNT(*) as total_treinamentos,
                        COUNT(CASE WHEN t.status_id = 1 THEN 1 END) as pendentes,
                        COUNT(CASE WHEN t.status_id = 2 THEN 1 END) as em_andamento,
                        COUNT(CASE WHEN t.status_id = 3 THEN 1 END) as concluidos,
                        COUNT(CASE WHEN t.status_id = 4 THEN 1 END) as cancelados,
                        COUNT(DISTINCT c.id) as clientes_distintos
                     FROM treinamentos t 
                     LEFT JOIN usuarios u ON t.responsavel_id = u.id 
                     LEFT JOIN clientes c ON t.cliente_id = c.id 
                     $where_clause 
                     GROUP BY u.id, u.nome 
                     ORDER BY total_treinamentos DESC";
$result_sobrecarga = $conn->query($query_sobrecarga);
$sobrecarga_tecnicos = $result_sobrecarga->fetch_all(MYSQLI_ASSOC);

// 5. Treinamentos por dia
$query_por_dia = "SELECT DATE_FORMAT(t.data_criacao, '%d-%m-%Y') as data, COUNT(*) as total 
                  FROM treinamentos t";
if (!empty($filtro_cliente)) {
    $query_por_dia .= " JOIN clientes c ON t.cliente_id = c.id";
}
$query_por_dia .= " $where_clause 
                  GROUP BY DATE(t.data_criacao) 
                  ORDER BY DATE(t.data_criacao)";
$result_por_dia = $conn->query($query_por_dia);
$treinamentos_por_dia = $result_por_dia->fetch_all(MYSQLI_ASSOC);

// 6. Top 10 clientes com mais treinamentos
$query_top_clientes = "SELECT CONCAT(c.contrato, ' - ', c.nome) as cliente_nome, COUNT(*) as total 
                       FROM treinamentos t 
                       JOIN clientes c ON t.cliente_id = c.id 
                       $where_clause 
                       GROUP BY c.id, c.nome, c.contrato 
                       ORDER BY total DESC 
                       LIMIT 10";
$result_top_clientes = $conn->query($query_top_clientes);
$top_clientes = $result_top_clientes->fetch_all(MYSQLI_ASSOC);

// 7. Treinamentos por plano
$query_por_plano = "SELECT p.nome, COUNT(*) as total 
                    FROM treinamentos t 
                    JOIN planos p ON t.plano_id = p.id";
if (!empty($filtro_cliente)) {
    $query_por_plano .= " JOIN clientes c ON t.cliente_id = c.id";
}
$query_por_plano .= " $where_clause 
                    GROUP BY p.id, p.nome 
                    ORDER BY total DESC";
$result_por_plano = $conn->query($query_por_plano);
$treinamentos_por_plano = $result_por_plano->fetch_all(MYSQLI_ASSOC);

// 8. Evolução mensal dos treinamentos por tipo
$query_evolucao_mensal = "SELECT 
                             DATE_FORMAT(t.data_criacao, '%Y-%m') as mes_ano,
                             DATE_FORMAT(t.data_criacao, '%m/%Y') as mes_ano_formatado,
                             tt.nome as tipo_nome,
                             COUNT(*) as total
                          FROM treinamentos t 
                          JOIN treinamentos_tipos tt ON t.tipo_id = tt.id";
if (!empty($filtro_cliente)) {
    $query_evolucao_mensal .= " JOIN clientes c ON t.cliente_id = c.id";
}
$query_evolucao_mensal .= " $where_clause 
                          GROUP BY DATE_FORMAT(t.data_criacao, '%Y-%m'), tt.id, tt.nome 
                          ORDER BY mes_ano, tt.nome";
$result_evolucao_mensal = $conn->query($query_evolucao_mensal);
$evolucao_mensal_raw = $result_evolucao_mensal->fetch_all(MYSQLI_ASSOC);

// Organizar dados para o gráfico de evolução mensal
$meses = [];
$tipos_evolucao = [];
$evolucao_mensal = [];

foreach ($evolucao_mensal_raw as $row) {
    $mes = $row['mes_ano_formatado'];
    $tipo = $row['tipo_nome'];
    
    if (!in_array($mes, $meses)) {
        $meses[] = $mes;
    }
    
    if (!in_array($tipo, $tipos_evolucao)) {
        $tipos_evolucao[] = $tipo;
    }
    
    $evolucao_mensal[$tipo][$mes] = $row['total'];
}

// Preencher valores ausentes com 0
foreach ($tipos_evolucao as $tipo) {
    foreach ($meses as $mes) {
        if (!isset($evolucao_mensal[$tipo][$mes])) {
            $evolucao_mensal[$tipo][$mes] = 0;
        }
    }
}

// 9. Listagem completa de treinamentos com paginação
$query_treinamentos = "SELECT t.*, 
                              CONCAT(c.contrato, ' - ', c.nome) AS cliente_nome_completo,
                              ts.nome AS status_nome, 
                              ts.cor AS status_cor,
                              tt.nome AS tipo_nome, 
                              u.nome AS responsavel_nome,
                              p.nome AS plano_nome
                       FROM treinamentos t
                       LEFT JOIN clientes c ON t.cliente_id = c.id
                       LEFT JOIN treinamentos_status ts ON t.status_id = ts.id
                       LEFT JOIN treinamentos_tipos tt ON t.tipo_id = tt.id
                       LEFT JOIN usuarios u ON t.responsavel_id = u.id
                       LEFT JOIN planos p ON t.plano_id = p.id
                       $where_clause 
                       ORDER BY t.data_criacao DESC
                       LIMIT $offset, $registros_por_pagina";
$result_treinamentos = $conn->query($query_treinamentos);
$treinamentos = $result_treinamentos->fetch_all(MYSQLI_ASSOC);

// Preparar dados para os filtros
$query_status = "SELECT * FROM treinamentos_status ORDER BY nome";
$result_status = $conn->query($query_status);
$status_list = $result_status->fetch_all(MYSQLI_ASSOC);

$query_tipos = "SELECT * FROM treinamentos_tipos ORDER BY nome";
$result_tipos = $conn->query($query_tipos);
$tipos_list = $result_tipos->fetch_all(MYSQLI_ASSOC);

$query_clientes = "SELECT id, CONCAT(IFNULL(CONCAT(contrato, ' - '), ''), nome) as nome_completo FROM clientes ORDER BY nome";
$result_clientes = $conn->query($query_clientes);
$clientes_filtro = $result_clientes->fetch_all(MYSQLI_ASSOC);

$query_responsaveis = "SELECT id, nome FROM usuarios ORDER BY nome";
$result_responsaveis = $conn->query($query_responsaveis);
$responsaveis_list = $result_responsaveis->fetch_all(MYSQLI_ASSOC);

// Buscar dados para filtros (manter compatibilidade)
$query_clientes_compat = "SELECT id, CONCAT(contrato, ' - ', nome) AS nome_completo FROM clientes ORDER BY nome";
$result_clientes_compat = $conn->query($query_clientes_compat);
$clientes_filtro_compat = $result_clientes_compat->fetch_all(MYSQLI_ASSOC);

$query_responsaveis_compat = "SELECT * FROM usuarios ORDER BY nome";
$result_responsaveis_compat = $conn->query($query_responsaveis_compat);
$responsaveis_filtro = $result_responsaveis_compat->fetch_all(MYSQLI_ASSOC);

$query_status_compat = "SELECT * FROM treinamentos_status ORDER BY ordem";
$result_status_compat = $conn->query($query_status_compat);
$status_filtro = $result_status_compat->fetch_all(MYSQLI_ASSOC);

$query_tipos_compat = "SELECT * FROM treinamentos_tipos ORDER BY nome";
$result_tipos_compat = $conn->query($query_tipos_compat);
$tipos_filtro = $result_tipos_compat->fetch_all(MYSQLI_ASSOC);

$query_planos = "SELECT * FROM planos ORDER BY nome";
$result_planos = $conn->query($query_planos);
$planos_filtro = $result_planos->fetch_all(MYSQLI_ASSOC);

// Calcular média de treinamentos por dia
$primeiro_treinamento = $conn->query("SELECT MIN(data_criacao) as primeira_data FROM treinamentos t" . (!empty($filtro_cliente) ? " JOIN clientes c ON t.cliente_id = c.id" : "") . " $where_clause")->fetch_assoc()['primeira_data'];
$dias_periodo = 1;
if ($primeiro_treinamento) {
    $data_inicio = new DateTime($primeiro_treinamento);
    $data_fim = new DateTime();
    $dias_periodo = max(1, $data_inicio->diff($data_fim)->days);
}
$media_por_dia = $total_treinamentos > 0 ? round($total_treinamentos / $dias_periodo, 1) : 0;

// Calcular estatísticas gerais
$treinamentos_pendentes = 0;
$treinamentos_em_andamento = 0;
$treinamentos_concluidos = 0;
$treinamentos_cancelados = 0;

foreach ($treinamentos_por_status as $status) {
    switch ($status['nome']) {
        case 'Pendente':
            $treinamentos_pendentes = $status['total'];
            break;
        case 'Em Andamento':
            $treinamentos_em_andamento = $status['total'];
            break;
        case 'Concluído':
            $treinamentos_concluidos = $status['total'];
            break;
        case 'Cancelado':
            $treinamentos_cancelados = $status['total'];
            break;
    }
}

$taxa_conclusao_geral = $total_treinamentos > 0 ? round(($treinamentos_concluidos / $total_treinamentos) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Treinamentos</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        main {
            padding: 20px;
            max-width: 100%;
            margin: 20px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-left: 20px;
            margin-right: 20px;
        }

        .filtros {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .filtros-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .filtros h2 {
            margin: 0;
            font-size: 18px;
            color: #023324;
        }

        .filtros-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .filtros-actions button {
            background-color: #023324;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }

        .filtros-actions button:hover {
            background-color: #034d3a;
        }

        .filtros-actions .limpar-filtros {
            background-color: #dc3545;
            color: white;
            text-decoration: none;
            padding: 8px 16px;
            border-radius: 4px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: background-color 0.3s;
        }

        .filtros-actions .limpar-filtros:hover {
            background-color: #c82333;
        }

        .filtros-form {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 10px;
        }
        
        .filtro-group-full {
            grid-column: 1 / -1;
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 15px;
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
            padding: 8px 15px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
            transition: background-color 0.3s ease;
            width: auto;
        }

        .filtro-group button:hover {
            background-color: #034d3a;
        }

        .limpar-filtros {
            color: #8CC053;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s ease;
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

        .graficos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .grafico {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            height: 400px;
        }

        .grafico h3 {
            margin-top: 0;
            color: #023324;
            font-size: 16px;
            margin-bottom: 20px;
        }

        .grafico canvas {
            max-height: 300px !important;
            height: 300px !important;
        }

        .tabela-sobrecarga {
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-bottom: 30px;
        }

        .tabela-sobrecarga h3 {
            margin-top: 0;
            color: #023324;
            font-size: 18px;
            margin-bottom: 20px;
        }

        .table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }

        .table th,
        .table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }

        .table th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #023324;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        .badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background-color: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background-color: #f8d7da;
            color: #721c24;
        }

        .badge-info {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .paginacao {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
           
            border: 1px solid #e9ecef;
        }

        .paginacao-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
           
            font-size: 14px;
            color: #6c757d;
        }

        .pagina-atual {
            font-weight: 600;
            color: #023324;
        }

        .total-registros {
            font-style: italic;
        }

        .paginacao-controles {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 5px;
            flex-wrap: wrap;
        }

        .btn-paginacao {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            background-color: white;
            color: #023324;
            text-decoration: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.2s ease;
            min-width: 40px;
            justify-content: center;
        }

        .btn-paginacao:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
            color: #023324;
            text-decoration: none;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .btn-paginacao.atual {
            background-color: #023324;
            color: white;
            border-color: #023324;
            font-weight: 600;
            cursor: default;
        }

        .btn-paginacao.atual:hover {
            background-color: #023324;
            transform: none;
            box-shadow: none;
        }

        .paginas-numeradas {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 0 10px;
        }

        .paginacao-ellipsis {
            padding: 8px 4px;
            color: #6c757d;
            font-weight: bold;
        }

        /* Responsividade da paginação */
        @media (max-width: 768px) {
            .paginacao-info {
                flex-direction: column;
                gap: 5px;
                text-align: center;
            }
            
            .paginacao-controles {
                gap: 3px;
            }
            
            .btn-paginacao {
                padding: 6px 8px;
                font-size: 12px;
                min-width: 35px;
            }
            
            .paginas-numeradas {
                margin: 0 5px;
            }
        }

        /* Scroll suave para âncora */
        html {
            scroll-behavior: smooth;
        }

        #paginacao-treinamentos {
            scroll-margin-top: 20px;
        }

        /* Filtros preenchidos */
        .filtro-preenchido {
            background-color: #e8f5e8 !important;
            border-color: #28a745 !important;
            color: #155724 !important;
        }

        .filtros-form select.filtro-preenchido {
            background-color: #e8f5e8 !important;
            border-color: #28a745 !important;
            color: #155724 !important;
        }

        .filtros-form input.filtro-preenchido {
            background-color: #e8f5e8 !important;
            border-color: #28a745 !important;
            color: #155724 !important;
        }

        /* Filtro de Cliente */
        .filtro-cliente {
            position: relative;
        }

        .filtro-cliente input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }

        .filtro-cliente.filtro-preenchido input {
            background-color: #e8f5e8;
            border-color: #28a745;
            color: #155724;
        }

        .cliente-opcoes {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: none;
        }

        .cliente-opcoes .opcao {
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            line-height: 1.4;
        }

        .cliente-opcoes .opcao:hover {
            background-color: #f8f9fa;
        }

        .cliente-opcoes .opcao:last-child {
            border-bottom: none;
        }

        /* Filtro de Status */
        .filtro-status {
            position: relative;
        }

        .status-dropdown {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: white;
            cursor: pointer;
            font-size: 14px;
        }

        .status-dropdown:hover {
            border-color: #aaa;
        }

        .status-opcoes {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            border-radius: 0 0 4px 4px;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .status-opcao {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f0f0f0;
            margin: 0;
        }

        .status-opcao:hover {
            background-color: #f8f9fa;
        }

        .status-opcao:last-child {
            border-bottom: none;
        }

        .status-opcao input {
            margin-right: 8px;
        }

        .status-opcao span {
            font-weight: 500;
        }

        @media (max-width: 768px) {
            .filtros-form {
                grid-template-columns: 1fr;
            }
            
            .graficos {
                grid-template-columns: 1fr;
            }
            
            .indicadores {
                grid-template-columns: 1fr;
            }

            .cliente-opcoes,
            .status-opcoes {
                position: fixed;
                left: 10px;
                right: 10px;
                top: auto;
                max-height: 300px;
            }

            .filtros-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }

            .filtros-actions {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <main>
        <h1 style="color: #023324; margin-bottom: 30px;">
            <i class="fas fa-chart-bar"></i> Relatórios de Treinamentos
        </h1>

        <!-- Filtros -->
        <div class="filtros">
            <div class="filtros-header">
                <h2><i class="fas fa-filter"></i> Filtros</h2>
                <div class="filtros-actions">
                    <button type="submit" form="filtros-form"><i class="fas fa-search"></i> Filtrar</button>
                    <a href="relatorios.php" class="limpar-filtros"><i class="fas fa-times"></i> Limpar Filtros</a>
                </div>
            </div>
            <form method="GET" class="filtros-form" id="filtros-form">
                <div class="filtro-group">
                    <label for="data_inicio">Data Início</label>
                    <input type="date" id="data_inicio" name="data_inicio" value="<?= $filtro_data_inicio ?>">
                </div>
                
                <div class="filtro-group">
                    <label for="data_fim">Data Fim</label>
                    <input type="date" id="data_fim" name="data_fim" value="<?= $filtro_data_fim ?>">
                </div>
                
                <div class="filtro-group">
                    <label for="cliente">Cliente</label>
                    <div class="filtro-cliente">
                        <input type="text" id="cliente-search" placeholder="Digite para buscar cliente..." 
                               value="<?php 
                                   if (!empty($filtro_cliente)) {
                                       foreach ($clientes_filtro as $cliente) {
                                           if ($cliente['id'] == $filtro_cliente) {
                                               echo htmlspecialchars($cliente['nome_completo']);
                                               break;
                                           }
                                       }
                                   }
                               ?>" 
                               class="<?= !empty($filtro_cliente) ? 'filtro-preenchido' : '' ?>">
                        <div class="cliente-opcoes" id="cliente-opcoes" style="display: none;">
                            <div class="opcao" data-value="">Todos os clientes</div>
                            <?php foreach ($clientes_filtro as $cliente): ?>
                                <div class="opcao" data-value="<?= $cliente['id'] ?>">
                                    <?= htmlspecialchars($cliente['nome_completo']) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <select id="cliente" name="cliente" style="display: none;">
                            <option value="">Todos</option>
                            <?php foreach ($clientes_filtro as $cliente): ?>
                                <option value="<?= $cliente['id'] ?>" <?= $filtro_cliente == $cliente['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cliente['nome_completo']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="filtro-group">
                    <label for="responsavel">Responsável</label>
                    <select id="responsavel" name="responsavel">
                        <option value="">Todos</option>
                        <option value="sem" <?= $filtro_responsavel == 'sem' ? 'selected' : '' ?>>Sem responsável</option>
                        <?php foreach ($responsaveis_list as $responsavel): ?>
                            <option value="<?= $responsavel['id'] ?>" <?= $filtro_responsavel == $responsavel['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($responsavel['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filtro-group">
                    <label for="status">Status</label>
                    <div class="filtro-status">
                        <div class="status-dropdown" onclick="toggleStatusDropdown()">
                            <span id="status-selected">
                                <?php 
                                if (empty($filtro_status) || (count($filtro_status) == 1 && $filtro_status[0] == '')) {
                                    echo 'Todos os status';
                                } else {
                                    $selected_names = [];
                                    foreach ($status_list as $status) {
                                        if (in_array($status['id'], $filtro_status)) {
                                            $selected_names[] = $status['nome'];
                                        }
                                    }
                                    if (count($selected_names) > 2) {
                                        echo count($selected_names) . ' status selecionados';
                                    } else {
                                        echo implode(', ', $selected_names);
                                    }
                                }
                                ?>
                            </span>
                            <i class="fas fa-chevron-down"></i>
                        </div>
                        <div class="status-opcoes" id="status-opcoes" style="display: none;">
                            <?php foreach ($status_list as $status): ?>
                                <label class="status-opcao">
                                    <input type="checkbox" name="status[]" value="<?= $status['id'] ?>" 
                                           <?= in_array($status['id'], $filtro_status) ? 'checked' : '' ?>>
                                    <span style="color: <?= $status['cor'] ?>;">
                                        <?= htmlspecialchars($status['nome']) ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <div class="filtro-group">
                    <label for="tipo">Tipo</label>
                    <select id="tipo" name="tipo">
                        <option value="">Todos</option>
                        <?php foreach ($tipos_list as $tipo): ?>
                            <option value="<?= $tipo['id'] ?>" <?= $filtro_tipo == $tipo['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tipo['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
        </div>

        <!-- Indicadores Principais -->
        <div class="indicadores">
            <div class="indicador">
                <h3>Total de Treinamentos</h3>
                <div class="valor"><?= number_format($total_treinamentos) ?></div>
            </div>
            <div class="indicador">
                <h3>Em Andamento</h3>
                <div class="valor"><?= number_format($treinamentos_em_andamento) ?></div>
            </div>
            <div class="indicador">
                <h3>Concluídos</h3>
                <div class="valor"><?= number_format($treinamentos_concluidos) ?></div>
            </div>
            <div class="indicador">
                <h3>Taxa de Conclusão</h3>
                <div class="valor"><?= $taxa_conclusao_geral ?>%</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="graficos">
            <div class="grafico">
                <h3>Treinamentos por Status</h3>
                <canvas id="graficoStatus" width="400" height="200"></canvas>
            </div>
            
            <div class="grafico">
                <h3>Treinamentos por Tipo</h3>
                <canvas id="graficoTipo" width="400" height="200"></canvas>
            </div>
        </div>

        <!-- Gráfico de Evolução Mensal -->
        <div class="grafico" style="margin-bottom: 30px;">
            <h3>Evolução Mensal dos Treinamentos por Tipo</h3>
            <canvas id="graficoEvolucaoMensal" width="400" height="200"></canvas>
        </div>

        <!-- Análise de Sobrecarga por Técnico -->
        <div class="tabela-sobrecarga">
            <h3><i class="fas fa-users"></i> Análise de Sobrecarga por Técnico</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Técnico</th>
                            <th>Total</th>
                            <th>Agendado</th>
                            <th>Em Andamento</th>
                            <th>Concluídos</th>
                            <th>Taxa Conclusão</th>
                            <th>Clientes Distintos</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sobrecarga_tecnicos as $tecnico): ?>
                            <?php 
                                $taxa_conclusao_tecnico = $tecnico['total_treinamentos'] > 0 ? 
                                    round(($tecnico['concluidos'] / $tecnico['total_treinamentos']) * 100, 2) : 0;
                                
                                $sobrecarga_nivel = '';
                                $sobrecarga_classe = '';
                                
                                if ($tecnico['em_andamento'] >= 10) {
                                    $sobrecarga_nivel = 'Alta Sobrecarga';
                                    $sobrecarga_classe = 'badge-danger';
                                } elseif ($tecnico['em_andamento'] >= 5) {
                                    $sobrecarga_nivel = 'Sobrecarga Moderada';
                                    $sobrecarga_classe = 'badge-warning';
                                } else {
                                    $sobrecarga_nivel = 'Normal';
                                    $sobrecarga_classe = 'badge-success';
                                }
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($tecnico['responsavel_nome'] ?: 'Sem responsável') ?></td>
                                <td><?= $tecnico['total_treinamentos'] ?></td>
                                <td><?= $tecnico['pendentes'] ?></td>
                                <td><strong><?= $tecnico['em_andamento'] ?></strong></td>
                                <td><?= $tecnico['concluidos'] ?></td>
                                <td><?= $taxa_conclusao_tecnico ?>%</td>
                                <td><?= $tecnico['clientes_distintos'] ?></td>
                                <td><span class="badge <?= $sobrecarga_classe ?>"><?= $sobrecarga_nivel ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Listagem de Treinamentos -->
        <div class="tabela-sobrecarga">
            <h3><i class="fas fa-list"></i> Listagem de Treinamentos</h3>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Título</th>
                            <th>Cliente</th>
                            <th>Tipo</th>
                            <th>Status</th>
                            <th>Responsável</th>
                            <th>Data Criação</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($treinamentos as $treinamento): ?>
                            <tr>
                                <td><?= $treinamento['id'] ?></td>
                                <td><?= htmlspecialchars($treinamento['titulo']) ?></td>
                                <td><?= htmlspecialchars($treinamento['cliente_nome_completo'] ?: 'N/A') ?></td>
                                <td><?= htmlspecialchars($treinamento['tipo_nome']) ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?= $treinamento['status_cor'] ?>; color: white;">
                                        <?= htmlspecialchars($treinamento['status_nome']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($treinamento['responsavel_nome'] ?: 'Sem responsável') ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($treinamento['data_criacao'])) ?></td>
                                <td>
                                    <a href="visualizar.php?id=<?= $treinamento['id'] ?>" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye"></i> Ver
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
                <div class="paginacao" id="paginacao-treinamentos">
                    <div class="paginacao-info">
                        <span class="pagina-atual">Página <?= $pagina_atual ?> de <?= $total_paginas ?></span>
                        <span class="total-registros">(<?= number_format($total_treinamentos) ?> treinamentos)</span>
                    </div>
                    
                    <div class="paginacao-controles">
                        <!-- Primeira página -->
                        <?php if ($pagina_atual > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => 1])) ?>#paginacao-treinamentos" class="btn-paginacao" title="Primeira página">
                                <i class="fas fa-angle-double-left"></i>
                            </a>
                        <?php endif; ?>
                        
                        <!-- Página anterior -->
                        <?php if ($pagina_atual > 1): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual - 1])) ?>#paginacao-treinamentos" class="btn-paginacao">
                                <i class="fas fa-angle-left"></i> Anterior
                            </a>
                        <?php endif; ?>
                        
                        <!-- Páginas numeradas (navegação inteligente de 3 em 3) -->
                        <div class="paginas-numeradas">
                            <?php
                            // Calcular o range de páginas a mostrar
                            $inicio_range = max(1, $pagina_atual - 1);
                            $fim_range = min($total_paginas, $pagina_atual + 1);
                            
                            // Se estamos no início, mostrar mais páginas à frente
                            if ($pagina_atual <= 2) {
                                $fim_range = min($total_paginas, 3);
                            }
                            
                            // Se estamos no final, mostrar mais páginas atrás
                            if ($pagina_atual >= $total_paginas - 1) {
                                $inicio_range = max(1, $total_paginas - 2);
                            }
                            
                            // Mostrar "..." se há páginas antes do range
                            if ($inicio_range > 1) {
                                echo '<a href="?' . http_build_query(array_merge($_GET, ['pagina' => 1])) . '#paginacao-treinamentos" class="btn-paginacao">1</a>';
                                if ($inicio_range > 2) {
                                    echo '<span class="paginacao-ellipsis">...</span>';
                                }
                            }
                            
                            // Mostrar páginas do range
                            for ($i = $inicio_range; $i <= $fim_range; $i++):
                                if ($i == $pagina_atual): ?>
                                    <span class="btn-paginacao atual"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $i])) ?>#paginacao-treinamentos" class="btn-paginacao"><?= $i ?></a>
                                <?php endif;
                            endfor;
                            
                            // Mostrar "..." se há páginas depois do range
                            if ($fim_range < $total_paginas) {
                                if ($fim_range < $total_paginas - 1) {
                                    echo '<span class="paginacao-ellipsis">...</span>';
                                }
                                echo '<a href="?' . http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) . '#paginacao-treinamentos" class="btn-paginacao">' . $total_paginas . '</a>';
                            }
                            ?>
                        </div>
                        
                        <!-- Próxima página -->
                        <?php if ($pagina_atual < $total_paginas): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $pagina_atual + 1])) ?>#paginacao-treinamentos" class="btn-paginacao">
                                Próxima <i class="fas fa-angle-right"></i>
                            </a>
                        <?php endif; ?>
                        
                        <!-- Última página -->
                        <?php if ($pagina_atual < $total_paginas): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['pagina' => $total_paginas])) ?>#paginacao-treinamentos" class="btn-paginacao" title="Última página">
                                <i class="fas fa-angle-double-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        // Filtro de Cliente - Busca Inteligente
        document.addEventListener('DOMContentLoaded', function() {
            const clienteSearch = document.getElementById('cliente-search');
            const clienteOpcoes = document.getElementById('cliente-opcoes');
            const clienteSelect = document.getElementById('cliente');
            
            if (clienteSearch && clienteOpcoes && clienteSelect) {
                // Mostrar opções ao focar no input
                clienteSearch.addEventListener('focus', function() {
                    clienteOpcoes.style.display = 'block';
                    filterClienteOptions(this.value.toLowerCase());
                });
                
                // Filtrar opções conforme digita
                clienteSearch.addEventListener('input', function() {
                    const searchTerm = this.value.toLowerCase();
                    filterClienteOptions(searchTerm);
                    clienteOpcoes.style.display = 'block';
                    
                    // Limpar seleção se não há correspondência exata
                    if (searchTerm === '') {
                        clienteSelect.value = '';
                        this.classList.remove('filtro-preenchido');
                    }
                });
                
                // Selecionar opção
                clienteOpcoes.addEventListener('click', function(e) {
                    if (e.target.classList.contains('opcao')) {
                        const value = e.target.getAttribute('data-value');
                        const text = e.target.textContent;
                        
                        clienteSelect.value = value;
                        clienteSearch.value = text;
                        clienteOpcoes.style.display = 'none';
                        
                        // Adicionar classe de filtro preenchido
                        if (value) {
                            clienteSearch.classList.add('filtro-preenchido');
                        } else {
                            clienteSearch.classList.remove('filtro-preenchido');
                        }
                    }
                });
                
                // Fechar opções ao clicar fora
                document.addEventListener('click', function(e) {
                    if (!clienteSearch.parentElement.contains(e.target)) {
                        clienteOpcoes.style.display = 'none';
                    }
                });
                
                function filterClienteOptions(searchTerm) {
                    const opcoes = clienteOpcoes.querySelectorAll('.opcao');
                    opcoes.forEach(function(opcao) {
                        const text = opcao.textContent.toLowerCase();
                        if (text.includes(searchTerm)) {
                            opcao.style.display = 'block';
                        } else {
                            opcao.style.display = 'none';
                        }
                    });
                }
            }
        });
        
        // Filtro de Status - Seleção Múltipla
        function toggleStatusDropdown() {
            const statusOpcoes = document.getElementById('status-opcoes');
            if (statusOpcoes && (statusOpcoes.style.display === 'none' || statusOpcoes.style.display === '')) {
                statusOpcoes.style.display = 'block';
            } else if (statusOpcoes) {
                statusOpcoes.style.display = 'none';
            }
        }
        
        // Atualizar texto do dropdown de status
        document.addEventListener('DOMContentLoaded', function() {
            const statusCheckboxes = document.querySelectorAll('input[name="status[]"]');
            const statusSelected = document.getElementById('status-selected');
            
            if (statusCheckboxes.length > 0 && statusSelected) {
                statusCheckboxes.forEach(function(checkbox) {
                    checkbox.addEventListener('change', function() {
                        updateStatusText();
                    });
                });
                
                // Fechar dropdown ao clicar fora
                document.addEventListener('click', function(e) {
                    const statusDropdown = document.querySelector('.status-dropdown');
                    const statusOpcoes = document.getElementById('status-opcoes');
                    
                    if (statusDropdown && statusOpcoes && 
                        !statusDropdown.contains(e.target) && 
                        !statusOpcoes.contains(e.target)) {
                        statusOpcoes.style.display = 'none';
                    }
                });
                
                function updateStatusText() {
                    const checkedBoxes = document.querySelectorAll('input[name="status[]"]:checked');
                    const statusSelected = document.getElementById('status-selected');
                    
                    if (statusSelected) {
                        if (checkedBoxes.length === 0) {
                            statusSelected.textContent = 'Todos os status';
                        } else if (checkedBoxes.length > 2) {
                            statusSelected.textContent = checkedBoxes.length + ' status selecionados';
                        } else {
                            const names = Array.from(checkedBoxes).map(function(checkbox) {
                                return checkbox.nextElementSibling ? checkbox.nextElementSibling.textContent : '';
                            });
                            statusSelected.textContent = names.join(', ');
                        }
                    }
                }
                
                // Inicializar texto do status
                updateStatusText();
            }
        });

        // Gráfico de Status
        const ctxStatus = document.getElementById('graficoStatus').getContext('2d');
        const statusData = <?= json_encode($treinamentos_por_status) ?>;
        
        new Chart(ctxStatus, {
            type: 'doughnut',
            data: {
                labels: statusData.map(item => item.nome),
                datasets: [{
                    data: statusData.map(item => item.total),
                    backgroundColor: statusData.map(item => item.cor),
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
                                const value = context.parsed || 0;
                                const statusItem = statusData[context.dataIndex];
                                const percentage = statusItem.percentual || 0;
                                return label + ': ' + value + ' (' + percentage + '%)';
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de Tipos
        const ctxTipo = document.getElementById('graficoTipo').getContext('2d');
        const tipoData = <?= json_encode($treinamentos_por_tipo) ?>;
        
        // Cores diferentes para cada coluna
        const coresTipo = [
            '#023324', '#8CC053', '#FF6B6B', '#4ECDC4', '#45B7D1', 
            '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F',
            '#FF8C42', '#6C5CE7', '#A29BFE', '#FD79A8', '#FDCB6E',
            '#E17055', '#00B894', '#00CEC9', '#74B9FF', '#81ECEC'
        ];
        
        new Chart(ctxTipo, {
            type: 'bar',
            data: {
                labels: tipoData.map(item => item.nome),
                datasets: [{
                    label: 'Treinamentos',
                    data: tipoData.map(item => item.total),
                    backgroundColor: tipoData.map((item, index) => coresTipo[index % coresTipo.length]),
                    borderColor: tipoData.map((item, index) => coresTipo[index % coresTipo.length]),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': ' + context.parsed.y;
                            }
                        }
                    }
                }
            }
        });

        // Gráfico de Evolução Mensal
        const ctxEvolucao = document.getElementById('graficoEvolucaoMensal').getContext('2d');
        
        // Cores para diferentes tipos
        const cores = [
            '#023324', '#8CC053', '#FF6B6B', '#4ECDC4', '#45B7D1', 
            '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8', '#F7DC6F'
        ];
        
        const datasets = [];
        <?php foreach ($tipos_evolucao as $index => $tipo): ?>
        datasets.push({
            label: '<?= addslashes($tipo) ?>',
            data: [<?= implode(',', array_map(function($mes) use ($evolucao_mensal, $tipo) { 
                return $evolucao_mensal[$tipo][$mes] ?? 0; 
            }, $meses)) ?>],
            borderColor: cores[<?= $index ?> % cores.length],
            backgroundColor: cores[<?= $index ?> % cores.length] + '20',
            borderWidth: 2,
            fill: false,
            tension: 0.1
        });
        <?php endforeach; ?>

        new Chart(ctxEvolucao, {
            type: 'line',
            data: {
                labels: <?= json_encode($meses) ?>,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top'
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    </script>
</body>
</html>

<?php require_once 'includes/footer_treinamentos.php'; ?>