<?php
require_once 'includes/header_atendimento.php';

// Filtros de período
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-01');
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$filtro_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$filtro_usuario = isset($_GET['usuario']) ? $_GET['usuario'] : '';
$filtro_grupo = isset($_GET['grupo']) ? $_GET['grupo'] : '';
$filtro_menu = isset($_GET['menu']) ? $_GET['menu'] : '';
$filtro_submenu = isset($_GET['submenu']) ? $_GET['submenu'] : '';
$filtro_tipo_erro = isset($_GET['tipo_erro']) ? $_GET['tipo_erro'] : '';
$filtro_dificuldade = isset($_GET['dificuldade']) ? $_GET['dificuldade'] : ''; // NOVO FILTRO

// Configuração de paginação
$registros_por_pagina = 20;
$pagina_atual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
$offset = ($pagina_atual - 1) * $registros_por_pagina;

// Construir cláusula WHERE
$where_conditions = [];
if (!empty($filtro_data_inicio) && !empty($filtro_data_fim)) {
    $where_conditions[] = "DATE(ra.data_atendimento) BETWEEN '$filtro_data_inicio' AND '$filtro_data_fim'";
}
if (!empty($filtro_cliente)) {
    $where_conditions[] = "c.id = '$filtro_cliente'";
}
if (!empty($filtro_usuario)) {
    $where_conditions[] = "u.id = '$filtro_usuario'";
}
if (!empty($filtro_grupo)) {
    $where_conditions[] = "c.id_grupo = '$filtro_grupo'";
}
if (!empty($filtro_menu)) {
    $where_conditions[] = "ra.menu_id = '$filtro_menu'";
}
if (!empty($filtro_submenu)) {
    $where_conditions[] = "ra.submenu_id = '$filtro_submenu'";
}
if (!empty($filtro_tipo_erro)) {
    $where_conditions[] = "ra.tipo_erro_id = '$filtro_tipo_erro'";
}
// NOVO: Filtro por nível de dificuldade
if (!empty($filtro_dificuldade)) {
    $where_conditions[] = "ra.nivel_dificuldade_id = '$filtro_dificuldade'";
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 1. Total de atendimentos no período (Otimizado)
$query_total = "SELECT COUNT(*) as total FROM registros_atendimento ra";
if (!empty($filtro_cliente) || !empty($filtro_grupo)) {
    $query_total .= " JOIN clientes c ON ra.cliente_id = c.id";
}
if (!empty($filtro_usuario)) {
    $query_total .= " JOIN usuarios u ON ra.usuario_id = u.id";
}
$query_total .= " $where_clause";
$result_total = $conn->query($query_total);
$total_atendimentos = $result_total->fetch_assoc()['total'];

// Calcular o total de páginas
$total_paginas = ceil($total_atendimentos / $registros_por_pagina);

// 2. Atendimentos por dia (Otimizado)
$query_por_dia = "SELECT DATE_FORMAT(ra.data_atendimento, '%d-%m-%Y') as data, COUNT(*) as total 
                  FROM registros_atendimento ra";
if (!empty($filtro_cliente) || !empty($filtro_grupo)) {
    $query_por_dia .= " JOIN clientes c ON ra.cliente_id = c.id";
}
if (!empty($filtro_usuario)) {
    $query_por_dia .= " JOIN usuarios u ON ra.usuario_id = u.id";
}
$query_por_dia .= " $where_clause 
                  GROUP BY DATE(ra.data_atendimento) 
                  ORDER BY DATE(ra.data_atendimento)";
$result_por_dia = $conn->query($query_por_dia);
$atendimentos_por_dia = $result_por_dia->fetch_all(MYSQLI_ASSOC);

// 2.1. NOVO: Atendimentos por dia da semana (Otimizado)
$query_por_dia_semana = "SELECT 
                            CASE DAYOFWEEK(ra.data_atendimento)
                                WHEN 1 THEN 'Domingo'
                                WHEN 2 THEN 'Segunda-feira'
                                WHEN 3 THEN 'Terça-feira'
                                WHEN 4 THEN 'Quarta-feira'
                                WHEN 5 THEN 'Quinta-feira'
                                WHEN 6 THEN 'Sexta-feira'
                                WHEN 7 THEN 'Sábado'
                            END as dia_semana,
                            COUNT(*) as total 
                         FROM registros_atendimento ra";
if (!empty($filtro_cliente) || !empty($filtro_grupo)) {
    $query_por_dia_semana .= " JOIN clientes c ON ra.cliente_id = c.id";
}
if (!empty($filtro_usuario)) {
    $query_por_dia_semana .= " JOIN usuarios u ON ra.usuario_id = u.id";
}
$query_por_dia_semana .= " $where_clause 
                         GROUP BY DAYOFWEEK(ra.data_atendimento)
                         ORDER BY DAYOFWEEK(ra.data_atendimento)";
$result_por_dia_semana = $conn->query($query_por_dia_semana);
$atendimentos_por_dia_semana = $result_por_dia_semana->fetch_all(MYSQLI_ASSOC);



// 3. Atendimentos por submenu (limitado aos 10 principais) - Otimizado
$query_por_submenu = "SELECT sma.nome, COUNT(*) as total 
                      FROM registros_atendimento ra 
                      JOIN submenu_atendimento sma ON ra.submenu_id = sma.id";
if (!empty($filtro_cliente) || !empty($filtro_grupo)) {
    $query_por_submenu .= " JOIN clientes c ON ra.cliente_id = c.id";
}
if (!empty($filtro_usuario)) {
    $query_por_submenu .= " JOIN usuarios u ON ra.usuario_id = u.id";
}
$query_por_submenu .= " $where_clause 
                      GROUP BY sma.id, sma.nome 
                      ORDER BY total DESC 
                      LIMIT 10";
$result_por_submenu = $conn->query($query_por_submenu);
$atendimentos_por_submenu = $result_por_submenu->fetch_all(MYSQLI_ASSOC);

// 4. Atendimentos por usuário
$query_por_usuario = "SELECT u.nome, COUNT(*) as total 
                      FROM registros_atendimento ra 
                      JOIN clientes c ON ra.cliente_id = c.id 
                      JOIN usuarios u ON ra.usuario_id = u.id 
                      $where_clause 
                      GROUP BY u.id, u.nome 
                      ORDER BY total DESC";
$result_por_usuario = $conn->query($query_por_usuario);
$atendimentos_por_usuario = $result_por_usuario->fetch_all(MYSQLI_ASSOC);

// 5. Atendimentos por tipo de erro - Otimizado
$query_por_tipo_erro = "SELECT te.descricao, COUNT(*) as total 
                        FROM registros_atendimento ra 
                        JOIN tipo_erro te ON ra.tipo_erro_id = te.id";
if (!empty($filtro_cliente) || !empty($filtro_grupo)) {
    $query_por_tipo_erro .= " JOIN clientes c ON ra.cliente_id = c.id";
}
if (!empty($filtro_usuario)) {
    $query_por_tipo_erro .= " JOIN usuarios u ON ra.usuario_id = u.id";
}
$query_por_tipo_erro .= " $where_clause 
                        GROUP BY te.id, te.descricao 
                        ORDER BY total DESC 
                        LIMIT 10";
$result_por_tipo_erro = $conn->query($query_por_tipo_erro);
$atendimentos_por_tipo_erro = $result_por_tipo_erro->fetch_all(MYSQLI_ASSOC);

// 5.1. Evolução mensal de atendimentos
$query_evolucao_mensal = "SELECT 
                            DATE_FORMAT(ra.data_atendimento, '%Y-%m') as mes_ano,
                            DATE_FORMAT(ra.data_atendimento, '%m/%Y') as mes_ano_formatado,
                            COUNT(*) as total 
                          FROM registros_atendimento ra";
if (!empty($filtro_cliente) || !empty($filtro_grupo)) {
    $query_evolucao_mensal .= " JOIN clientes c ON ra.cliente_id = c.id";
}
if (!empty($filtro_usuario)) {
    $query_evolucao_mensal .= " JOIN usuarios u ON ra.usuario_id = u.id";
}
$query_evolucao_mensal .= " $where_clause 
                          GROUP BY DATE_FORMAT(ra.data_atendimento, '%Y-%m')
                          ORDER BY DATE_FORMAT(ra.data_atendimento, '%Y-%m')";
$result_evolucao_mensal = $conn->query($query_evolucao_mensal);
$evolucao_mensal = $result_evolucao_mensal->fetch_all(MYSQLI_ASSOC);

// 6. Top 10 clientes com mais atendimentos
$query_top_clientes = "SELECT CONCAT(c.contrato, ' - ', c.nome) as cliente_nome, COUNT(*) as total 
                       FROM registros_atendimento ra 
                       JOIN clientes c ON ra.cliente_id = c.id 
                       JOIN usuarios u ON ra.usuario_id = u.id 
                       $where_clause 
                       GROUP BY c.id, c.nome, c.contrato 
                       ORDER by total DESC 
                       LIMIT 10";
$result_top_clientes = $conn->query($query_top_clientes);
$top_clientes = $result_top_clientes->fetch_all(MYSQLI_ASSOC);

// 7. Atendimentos por menu
$query_por_menu = "SELECT ma.nome, COUNT(*) as total 
                   FROM registros_atendimento ra 
                   JOIN clientes c ON ra.cliente_id = c.id 
                   JOIN usuarios u ON ra.usuario_id = u.id 
                   JOIN menu_atendimento ma ON ra.menu_id = ma.id 
                   $where_clause 
                   GROUP BY ma.id, ma.nome 
                   ORDER BY total DESC";
$result_por_menu = $conn->query($query_por_menu);
$atendimentos_por_menu = $result_por_menu->fetch_all(MYSQLI_ASSOC);

// 11. Listagem completa de atendimentos com paginação
$query_atendimentos = "SELECT ra.*, 
                              CONCAT(c.contrato, ' - ', c.nome) AS cliente_nome_completo,
                              ma.nome AS menu_nome, 
                              sma.nome AS submenu_nome, 
                              te.descricao AS tipo_erro_descricao, 
                              u.nome AS usuario_nome,
                              nd.nome AS nivel_dificuldade_nome, 
                              nd.cor AS nivel_dificuldade_cor
                       FROM registros_atendimento ra
                       JOIN clientes c ON ra.cliente_id = c.id
                       JOIN menu_atendimento ma ON ra.menu_id = ma.id
                       JOIN submenu_atendimento sma ON ra.submenu_id = sma.id
                       JOIN tipo_erro te ON ra.tipo_erro_id = te.id
                       JOIN usuarios u ON ra.usuario_id = u.id
                       JOIN niveis_dificuldade nd ON ra.nivel_dificuldade_id = nd.id
                       $where_clause 
                       ORDER BY ra.data_atendimento DESC
                       LIMIT $offset, $registros_por_pagina";
$result_atendimentos = $conn->query($query_atendimentos);
$atendimentos = $result_atendimentos->fetch_all(MYSQLI_ASSOC);

// Buscar dados para filtros
$query_clientes = "SELECT id, CONCAT(contrato, ' - ', nome) AS nome_completo FROM clientes ORDER BY nome";
$result_clientes = $conn->query($query_clientes);
$clientes_filtro = $result_clientes->fetch_all(MYSQLI_ASSOC);

$query_usuarios = "SELECT * FROM usuarios ORDER BY nome";
$result_usuarios = $conn->query($query_usuarios);
$usuarios_filtro = $result_usuarios->fetch_all(MYSQLI_ASSOC);

// Buscar grupos para filtro
$query_grupos = "SELECT id, nome FROM grupos ORDER BY nome";
$result_grupos = $conn->query($query_grupos);
$grupos_filtro = $result_grupos->fetch_all(MYSQLI_ASSOC);

// Buscar menus para filtro
$query_menus = "SELECT id, nome FROM menu_atendimento ORDER BY nome";
$result_menus = $conn->query($query_menus);
$menus_filtro = $result_menus->fetch_all(MYSQLI_ASSOC);

// Buscar submenus para filtro
$query_submenus = "SELECT id, nome, menu_id FROM submenu_atendimento ORDER BY nome";
$result_submenus = $conn->query($query_submenus);
$submenus_filtro = $result_submenus->fetch_all(MYSQLI_ASSOC);

// Buscar tipos de erro para filtro
$query_tipos_erro = "SELECT id, descricao FROM tipo_erro ORDER BY descricao";
$result_tipos_erro = $conn->query($query_tipos_erro);
$tipos_erro_filtro = $result_tipos_erro->fetch_all(MYSQLI_ASSOC);

// NOVO: Buscar níveis de dificuldade para filtro
$query_dificuldades = "SELECT id, nome FROM niveis_dificuldade ORDER BY ordem";
$result_dificuldades = $conn->query($query_dificuldades);
$dificuldades_filtro = $result_dificuldades->fetch_all(MYSQLI_ASSOC);

// Calcular média de atendimentos por día
$dias_periodo = (strtotime($filtro_data_fim) - strtotime($filtro_data_inicio)) / (60 * 60 * 24) + 1;
$media_por_dia = $total_atendimentos > 0 ? round($total_atendimentos / $dias_periodo, 2) : 0;

// 8. Análise detalhada por usuário - Tipos de erro mais atendidos por cada usuário
$query_usuario_tipo_erro = "SELECT u.nome, te.descricao as tipo_erro, COUNT(*) as total 
                            FROM registros_atendimento ra 
                            JOIN clientes c ON ra.cliente_id = c.id 
                            JOIN usuarios u ON ra.usuario_id = u.id 
                            JOIN tipo_erro te ON ra.tipo_erro_id = te.id 
                            $where_clause 
                            GROUP BY u.id, u.nome, te.id, te.descricao 
                            ORDER BY u.nome, total DESC";
$result_usuario_tipo_erro = $conn->query($query_usuario_tipo_erro);
$usuario_tipo_erro = $result_usuario_tipo_erro->fetch_all(MYSQLI_ASSOC);

// 9. Análise detalhada por usuário - Clientes mais atendidos por cada usuário
$query_usuario_cliente = "SELECT u.nome, CONCAT(c.contrato, ' - ', c.nome) as cliente_nome, COUNT(*) as total 
                          FROM registros_atendimento ra 
                          JOIN clientes c ON ra.cliente_id = c.id 
                          JOIN usuarios u ON ra.usuario_id = u.id 
                          $where_clause 
                          GROUP BY u.id, u.nome, c.id, c.nome, c.contrato 
                          ORDER BY u.nome, total DESC";
$result_usuario_cliente = $conn->query($query_usuario_cliente);
$usuario_cliente = $result_usuario_cliente->fetch_all(MYSQLI_ASSOC);

// 10. Estatísticas por usuário
$query_stats_usuario = "SELECT u.nome, 
                               COUNT(*) as total_atendimentos,
                               COUNT(DISTINCT c.id) as clientes_distintos,
                               COUNT(DISTINCT te.id) as tipos_erro_distintos,
                               AVG(nd.ordem) as media_dificuldade
                        FROM registros_atendimento ra 
                        JOIN clientes c ON ra.cliente_id = c.id 
                        JOIN usuarios u ON ra.usuario_id = u.id 
                        JOIN tipo_erro te ON ra.tipo_erro_id = te.id 
                        JOIN niveis_dificuldade nd ON ra.nivel_dificuldade_id = nd.id 
                        $where_clause 
                        GROUP BY u.id, u.nome 
                        ORDER BY total_atendimentos DESC";
$result_stats_usuario = $conn->query($query_stats_usuario);
// Corrigir contagem de usuários ativos (apenas usuários com atendimentos no período)
$usuarios_ativos = count($atendimentos_por_usuario);

// 3. Distribuição por Horário
$query_por_horario = "SELECT 
                         HOUR(ra.data_atendimento) as hora,
                         COUNT(*) as total
                      FROM registros_atendimento ra 
                      JOIN clientes c ON ra.cliente_id = c.id 
                      JOIN usuarios u ON ra.usuario_id = u.id 
                      $where_clause 
                      GROUP BY HOUR(ra.data_atendimento) 
                      ORDER BY hora";
$result_por_horario = $conn->query($query_por_horario);
$atendimentos_por_horario = $result_por_horario->fetch_all(MYSQLI_ASSOC);

// 4. Evolução Mensal dos Principais Tipos de Erro
$query_evolucao_tipos = "SELECT 
                            DATE_FORMAT(ra.data_atendimento, '%m/%Y') as mes,
                            te.descricao as tipo_erro,
                            COUNT(*) as total
                         FROM registros_atendimento ra 
                         JOIN clientes c ON ra.cliente_id = c.id 
                         JOIN usuarios u ON ra.usuario_id = u.id 
                         JOIN tipo_erro te ON ra.tipo_erro_id = te.id 
                         $where_clause 
                         GROUP BY DATE_FORMAT(ra.data_atendimento, '%Y-%m'), te.id, te.descricao 
                         ORDER BY DATE_FORMAT(ra.data_atendimento, '%Y-%m'), total DESC";
$result_evolucao_tipos = $conn->query($query_evolucao_tipos);
$evolucao_tipos_erro = $result_evolucao_tipos->fetch_all(MYSQLI_ASSOC);

// 5. Clientes com Reincidência (Múltiplos Atendimentos no Mesmo Menu/Submenu)
$query_reincidencia_menu = "SELECT 
                               CONCAT(c.contrato, ' - ', c.nome) as cliente_nome,
                               ma.nome as menu_nome,
                               sma.nome as submenu_nome,
                               COUNT(*) as total_reincidencias
                            FROM registros_atendimento ra 
                            JOIN clientes c ON ra.cliente_id = c.id 
                            JOIN usuarios u ON ra.usuario_id = u.id 
                            JOIN menu_atendimento ma ON ra.menu_id = ma.id 
                            JOIN submenu_atendimento sma ON ra.submenu_id = sma.id 
                            $where_clause 
                            GROUP BY c.id, c.nome, c.contrato, ma.id, ma.nome, sma.id, sma.nome 
                            HAVING COUNT(*) > 1
                            ORDER BY total_reincidencias DESC, cliente_nome";
$result_reincidencia_menu = $conn->query($query_reincidencia_menu);
$reincidencia_menu_submenu = $result_reincidencia_menu->fetch_all(MYSQLI_ASSOC);

// 6. Ranking de Eficiência dos Usuários (Otimizado)
$query_ranking_eficiencia = "SELECT 
                                u.nome,
                                COUNT(*) as total_atendimentos,
                                AVG(nd.ordem) as media_dificuldade,
                                COUNT(DISTINCT c.id) as clientes_distintos,
                                ROUND(COUNT(*) / COUNT(DISTINCT DATE(ra.data_atendimento)), 2) as produtividade_diaria
                             FROM registros_atendimento ra 
                             JOIN clientes c ON ra.cliente_id = c.id 
                             JOIN usuarios u ON ra.usuario_id = u.id 
                             JOIN niveis_dificuldade nd ON ra.nivel_dificuldade_id = nd.id 
                             $where_clause 
                             GROUP BY u.id, u.nome 
                             HAVING COUNT(DISTINCT DATE(ra.data_atendimento)) > 0
                             ORDER BY total_atendimentos DESC";
$result_ranking_eficiencia = $conn->query($query_ranking_eficiencia);
$ranking_eficiencia_temp = $result_ranking_eficiencia->fetch_all(MYSQLI_ASSOC);

// Calcular percentual de participação usando o total já calculado
$ranking_eficiencia = [];
foreach ($ranking_eficiencia_temp as $usuario) {
    $usuario['percentual_participacao'] = $total_atendimentos > 0 ? round(($usuario['total_atendimentos'] * 100.0) / $total_atendimentos, 2) : 0;
    $ranking_eficiencia[] = $usuario;
}

// Reordenar por percentual de participação
usort($ranking_eficiencia, function($a, $b) {
    return $b['percentual_participacao'] <=> $a['percentual_participacao'];
});

// 7. Especialização dos Usuários por Tipo de Problema
$query_especializacao = "SELECT 
                            u.nome as usuario_nome,
                            te.descricao as tipo_erro,
                            COUNT(*) as total_atendimentos,
                            ROUND((COUNT(*) * 100.0) / SUM(COUNT(*)) OVER (PARTITION BY u.id), 2) as percentual_especializacao
                         FROM registros_atendimento ra 
                         JOIN clientes c ON ra.cliente_id = c.id 
                         JOIN usuarios u ON ra.usuario_id = u.id 
                         JOIN tipo_erro te ON ra.tipo_erro_id = te.id 
                         $where_clause 
                         GROUP BY u.id, u.nome, te.id, te.descricao 
                         ORDER BY u.nome, percentual_especializacao DESC";
$result_especializacao = $conn->query($query_especializacao);
$especializacao_usuarios = $result_especializacao->fetch_all(MYSQLI_ASSOC);


?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatórios de Atendimento</title>
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

        .filtros h2 {
            margin-top: 0;
            font-size: 18px;
            color: #023324;
            display: inline-block;
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

        .limpar-filtros:hover {
            color: #023324;
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
            grid-template-columns: 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }

        .grafico-full {
            grid-column: 1 / -1;
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
        }

        .graficos-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 30px;
        }

        .graficos-grid-half {
            display: grid;
            grid-template-columns: 1fr 1fr;
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

        .grafico-full h3 {
            font-size: 18px;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }

        /* ESTILOS AJUSTADOS PARA AS TABELAS */
        .tabelas-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
            width: 100%;
        }

        .tabela-wrapper {
            background-color: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            border: 1px solid #e0e0e0;
            width: 100%;
            box-sizing: border-box;
            overflow: hidden;
        }

        /* Garantir que todas as seções de tabela tenham fundo branco consistente */
        .tabelas-container {
            background: transparent;
        }
        
        .tabelas-container .tabela-wrapper {
            background-color: white !important;
        }

        .tabela-wrapper h3 {
            margin-top: 0;
            color: #023324;
            font-size: 18px;
            border-bottom: 2px solid #8CC053;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        /* Tooltip customizado */
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
            z-index: 99999;
            bottom: 100%;
            left: 50%;
            transform: translateX(-50%) translateY(-10px);
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 13px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            border: 1px solid #e0e0e0;
            pointer-events: none;
            margin-bottom: 5px;
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
        
        /* Garantir que as seções com tooltip tenham overflow visible */
        .tabela-wrapper,
        .grafico {
            overflow: visible !important;
        }
        
        .tabelas-container {
            overflow: visible !important;
        }
        
        /* Ajuste para os títulos com tooltip */
        h3.with-tooltip {
            display: flex;
            align-items: center;
        }

        .tabela-scroll {
            overflow-x: auto;
            width: 100%;
        }

        .tabela {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 100%;
        }

        .tabela th,
        .tabela td {
            padding: 10px 12px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }

        .tabela th {
            background-color: #f8f9fa;
            font-weight: 600;
            color: #023324;
            font-size: 14px;
            white-space: nowrap;
        }

        /* NOVOS ESTILOS PARA BADGES E ELEMENTOS ESPECIAIS */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            text-align: center;
            color: white;
            background-color: #6c757d;
        }

        .badge-warning {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-gold {
            background: linear-gradient(135deg, #ffd700, #ffed4e);
            color: #212529;
            box-shadow: 0 2px 4px rgba(255, 215, 0, 0.3);
        }

        .badge-silver {
            background: linear-gradient(135deg, #c0c0c0, #e8e8e8);
            color: #212529;
            box-shadow: 0 2px 4px rgba(192, 192, 192, 0.3);
        }

        .badge-bronze {
            background: linear-gradient(135deg, #cd7f32, #daa520);
            color: white;
            box-shadow: 0 2px 4px rgba(205, 127, 50, 0.3);
        }

        /* Progress Bar Styles */
        .progress-bar {
            position: relative;
            width: 100%;
            height: 20px;
            background-color: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #a8d46f, #8CC053);
            border-radius: 10px;
            transition: width 0.3s ease;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 11px;
            font-weight: 600;
            color: #212529;
            text-shadow: 0 1px 2px rgba(255, 255, 255, 0.8);
        }

        /* Melhorias visuais para tabelas especiais */
        .tabela tr:hover {
            background-color: #f8f9fa;
        }

        .tabela-wrapper.full-width {
            grid-column: 1 / -1;
        }

        .tabela tr:hover {
            background-color: #f8f9fa;
        }

        .tabela-wrapper.full-width {
            grid-column: 1 / -1;
        }

        .nivel-dificuldade {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            text-align: center;
            min-width: 80px;
            font-size: 12px;
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

        /* Estilos para a listagem de atendimentos */
        .listagem-atendimentos {
            margin-top: 40px;
        }

        .listagem-atendimentos h2 {
            color: #023324;
            font-size: 22px;
            border-bottom: 2px solid #8CC053;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }

        .descricao-completa {
            max-width: 400px;
            white-space: normal;
            word-wrap: break-word;
        }

        .descricao-completa .texto-completo {
            display: none;
        }

        .descricao-completa .texto-resumido {
            cursor: pointer;
            color: #8CC053;
        }

        .descricao-completa .texto-resumido:hover {
            color: #023324;
        }

        /* Estilos para paginação */
        .paginacao {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .paginacao a, .paginacao span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-decoration: none;
            color: #023324;
            transition: all 0.3s ease;
        }
        
        .paginacao a:hover {
            background-color: #023324;
            color: white;
        }
        
        .paginacao .ativa {
            background-color: #023324;
            color: white;
            font-weight: bold;
        }
        
        .paginacao-info {
            margin-top: 10px;
            text-align: center;
            color: #666;
            font-size: 14px;
        }

        /* NOVOS ESTILOS PARA OS BOTÕES NA MESMA LINHA */
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

        .filtros-botoes button:hover {
            background-color: #034d3a;
        }

        .filtros-botoes .limpar-filtros {
            color: #8CC053;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .filtros-botoes .limpar-filtros:hover {
            color: #023324;
        }
        
        /* Remover a div .filtro-group-full antiga */
        .filtro-group-full {
            display: none;
        }

        @media (max-width: 1200px) {
            .filtros-form {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .tabelas-container {
                grid-template-columns: 1fr;
            }
            
            .graficos-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .filtros-form {
                grid-template-columns: 1fr;
            }
            
            .graficos {
                grid-template-columns: 1fr;
            }
            
            .indicadores {
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            }
            
            .tabela-wrapper {
                padding: 15px;
            }
            
            .tabela th,
            .tabela td {
                padding: 8px 10px;
                font-size: 13px;
            }
            
            .filtros-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }
    </style>
</head>
<body>
    <main>
        <h1>Relatórios de Atendimento</h1>

        <!-- Filtros CORRIGIDOS -->
        <section class="filtros">
            <div class="filtros-header">
                <h2>Filtros</h2>
                <div class="filtros-botoes">
                    <button type="submit" form="filtros-form">Filtrar</button>
                    <a href="relatorios_atendimento.php" class="limpar-filtros">Limpar Filtros</a>
                </div>
            </div>
            <form id="filtros-form" method="GET" action="relatorios_atendimento.php" class="filtros-form">
                <div class="filtro-group">
                    <label for="data_inicio">Data Início:</label>
                    <input type="date" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                </div>

                <div class="filtro-group">
                    <label for="data_fim">Data Fim:</label>
                    <input type="date" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                </div>

                <div class="filtro-group">
                    <label for="cliente">Cliente:</label>
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
                    <label for="usuario">Usuário:</label>
                    <select id="usuario" name="usuario">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios_filtro as $usuario): ?>
                            <option value="<?php echo $usuario['id']; ?>" <?php echo ($filtro_usuario == $usuario['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($usuario['nome']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-group">
                    <label for="grupo">Grupo:</label>
                    <select id="grupo" name="grupo">
                        <option value="">Todos</option>
                        <?php foreach ($grupos_filtro as $grupo): ?>
                            <option value="<?php echo $grupo['id']; ?>" <?php echo ($filtro_grupo == $grupo['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($grupo['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-group">
                    <label for="menu">Menu:</label>
                    <select id="menu" name="menu" onchange="carregarSubmenusRelatorio()">
                        <option value="">Todos</option>
                        <?php foreach ($menus_filtro as $menu): ?>
                            <option value="<?php echo $menu['id']; ?>" <?php echo ($filtro_menu == $menu['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($menu['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-group">
                    <label for="submenu">Submenu:</label>
                    <select id="submenu" name="submenu">
                        <option value="">Todos</option>
                        <?php if (!empty($filtro_menu)): ?>
                            <?php foreach ($submenus_filtro as $submenu): ?>
                                <?php if ($submenu['menu_id'] == $filtro_menu): ?>
                                    <option value="<?php echo $submenu['id']; ?>" <?php echo ($filtro_submenu == $submenu['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($submenu['nome']); ?>
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="filtro-group">
                    <label for="tipo_erro">Tipo de Erro:</label>
                    <select id="tipo_erro" name="tipo_erro">
                        <option value="">Todos</option>
                        <?php foreach ($tipos_erro_filtro as $tipo): ?>
                            <option value="<?php echo $tipo['id']; ?>" <?php echo ($filtro_tipo_erro == $tipo['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($tipo['descricao']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- NOVO: Filtro por nível de dificuldade -->
                <div class="filtro-group">
                    <label for="dificuldade">Dificuldade:</label>
                    <select id="dificuldade" name="dificuldade">
                        <option value="">Todos</option>
                        <?php foreach ($dificuldades_filtro as $dificuldade): ?>
                            <option value="<?php echo $dificuldade['id']; ?>" <?php echo ($filtro_dificuldade == $dificuldade['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dificuldade['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Manter a página atual nos filtros -->
                <input type="hidden" name="pagina" id="pagina_input" value="1">
            </form>
        </section>

        <!-- Indicadores -->
        <section class="indicadores">
            <div class="indicador">
                <h3>Total de Atendimentos</h3>
                <p class="valor"><?php echo number_format($total_atendimentos, 0, ',', '.'); ?></p>
            </div>
            <div class="indicador">
                <h3>Média por Dia</h3>
                <p class="valor"><?php echo $media_por_dia; ?></p>
            </div>
            <div class="indicador">
                <h3>Período Analisado</h3>
                <p class="valor"><?php echo $dias_periodo; ?> dias</p>
            </div>
            <div class="indicador">
                <h3>Usuários Ativos</h3>
                <p class="valor"><?php echo $usuarios_ativos; ?></p>
            </div>
        </section>

        <!-- Ranking de Eficiência dos Usuários -->
        <section class="tabelas-container">
            <div class="tabela-wrapper full-width">
                <h3 class="with-tooltip">Ranking de Eficiência dos Usuários
                    <div class="tooltip-container">
                        <span class="tooltip-icon">ℹ</span>
                        <div class="tooltip-text">
                            Ranking baseado no percentual de atendimentos realizados por cada usuário em relação ao total de atendimentos do período. Mostra a participação de cada usuário no volume total de trabalho da equipe.
                        </div>
                    </div>
                </h3>
                <div class="tabela-scroll">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th>Posição</th>
                                <th>Usuário</th>
                                <th>Total Atendimentos</th>
                                <th>Produtividade Diária</th>
                                <th>Dificuldade Média</th>
                                <th>Clientes Distintos</th>
                                <th>% de Participação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $posicao = 1;
                            foreach ($ranking_eficiencia as $usuario): 
                            ?>
                                <tr>
                                    <td>
                                        <?php if ($posicao == 1): ?>
                                            <span class="badge badge-gold">🥇 <?php echo $posicao; ?>º</span>
                                        <?php elseif ($posicao == 2): ?>
                                            <span class="badge badge-silver">🥈 <?php echo $posicao; ?>º</span>
                                        <?php elseif ($posicao == 3): ?>
                                            <span class="badge badge-bronze">🥉 <?php echo $posicao; ?>º</span>
                                        <?php else: ?>
                                            <span class="badge"><?php echo $posicao; ?>º</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($usuario['nome']); ?></td>
                                    <td><?php echo $usuario['total_atendimentos']; ?></td>
                                    <td><?php echo $usuario['produtividade_diaria']; ?> atend./dia</td>
                                    <td><?php echo number_format($usuario['media_dificuldade'], 1); ?></td>
                                    <td><?php echo $usuario['clientes_distintos']; ?></td>
                                    <td><strong><?php echo number_format($usuario['percentual_participacao'], 2); ?>%</strong></td>
                                </tr>
                            <?php 
                            $posicao++;
                            endforeach; 
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Gráficos -->
        <section class="graficos">
            <!-- Gráfico de Evolução Mensal de Atendimentos (largura total) -->
            <div class="grafico-full">
                <h3 class="with-tooltip">Evolução de Quantidade de Atendimentos Mensal
                    <div class="tooltip-container">
                        <span class="tooltip-icon">ℹ</span>
                        <div class="tooltip-text">
                            Gráfico de linha mostrando a evolução mensal da quantidade de atendimentos. Permite identificar tendências sazonais, crescimento ou redução da demanda ao longo dos meses.
                        </div>
                    </div>
                </h3>
                <div class="chart-container">
                    <canvas id="chartEvolucaoMensal"></canvas>
                </div>
            </div>

            <!-- Gráfico de Atendimentos por Dia (largura total) -->
            <div class="grafico-full">
                <h3 class="with-tooltip">Atendimentos por Dia
                    <div class="tooltip-container">
                        <span class="tooltip-icon">ℹ</span>
                        <div class="tooltip-text">
                            Gráfico de linha mostrando a evolução diária dos atendimentos no período selecionado. Permite identificar tendências, picos de demanda e padrões de trabalho.
                        </div>
                    </div>
                </h3>
                <div class="chart-container">
                    <canvas id="chartPorDia"></canvas>
                </div>
            </div>
            
            <!-- Demais gráficos em grid -->
            <div class="graficos-grid">


                <!-- NOVO: Gráfico de Atendimentos por Dia da Semana -->
                <div class="grafico">
                    <h3 class="with-tooltip">Atendimentos por Dia da Semana
                        <div class="tooltip-container">
                            <span class="tooltip-icon">ℹ</span>
                            <div class="tooltip-text">
                                Mostra em quais dias da semana há maior demanda de atendimentos. Útil para planejamento de recursos e escalas de trabalho.
                            </div>
                        </div>
                    </h3>
                    <div class="chart-container">
                        <canvas id="chartPorDiaSemana"></canvas>
                    </div>
                </div>

                <div class="grafico">
                    <h3 class="with-tooltip">Atendimentos por Usuário
                        <div class="tooltip-container">
                            <span class="tooltip-icon">ℹ</span>
                            <div class="tooltip-text">
                                Quantidade de atendimentos realizados por cada usuário da equipe. Permite avaliar a distribuição de trabalho e produtividade individual.
                            </div>
                        </div>
                    </h3>
                    <div class="chart-container">
                        <canvas id="chartPorUsuario"></canvas>
                    </div>
                </div>

                <div class="grafico">
                    <h3 class="with-tooltip">Atendimentos por Menu
                        <div class="tooltip-container">
                            <span class="tooltip-icon">ℹ</span>
                            <div class="tooltip-text">
                                Distribuição dos atendimentos por categoria de menu do sistema. Identifica quais módulos geram mais demanda de suporte.
                            </div>
                        </div>
                    </h3>
                    <div class="chart-container">
                        <canvas id="chartPorMenu"></canvas>
                    </div>
                </div>

                <!-- Gráfico de Atendimentos por Submenu -->
                <div class="grafico">
                    <h3 class="with-tooltip">Atendimentos por Submenu
                        <div class="tooltip-container">
                            <span class="tooltip-icon">ℹ</span>
                            <div class="tooltip-text">
                                Distribuição dos atendimentos por submenu do sistema (limitado aos 10 principais). Identifica quais submenus geram mais demanda de suporte.
                            </div>
                        </div>
                    </h3>
                    <div class="chart-container">
                        <canvas id="chartPorSubmenu"></canvas>
                    </div>
                </div>

                <!-- NOVOS GRÁFICOS -->

            </div>
            
            <!-- Gráficos maiores - cada um com metade da tela -->
            <div class="graficos-grid-half">
                <div class="grafico">
                    <h3 class="with-tooltip">Distribuição por Horário
                        <div class="tooltip-container">
                            <span class="tooltip-icon">ℹ</span>
                            <div class="tooltip-text">
                                Mostra em quais horários do dia há maior concentração de atendimentos. Ajuda a identificar os períodos de pico e otimizar a disponibilidade da equipe.
                            </div>
                        </div>
                    </h3>
                    <div class="chart-container">
                        <canvas id="chartPorHorario"></canvas>
                    </div>
                </div>

                <div class="grafico">
                    <h3 class="with-tooltip">Evolução Mensal dos Principais Tipos de Erro
                        <div class="tooltip-container">
                            <span class="tooltip-icon">ℹ</span>
                            <div class="tooltip-text">
                                Acompanha a evolução temporal dos tipos de erro mais frequentes. Permite identificar tendências e avaliar a eficácia de correções implementadas.
                            </div>
                        </div>
                    </h3>
                    <div class="chart-container">
                        <canvas id="chartEvolucaoTipos"></canvas>
                    </div>
                </div>
            </div>
        </section>

        <!-- NOVOS RELATÓRIOS -->
        
        <!-- Especialização dos Usuários por Tipo de Problema -->
        <section class="tabelas-container">
            <div class="tabela-wrapper full-width">
                <h3 class="with-tooltip">Especialização dos Usuários por Tipo de Problema
                    <div class="tooltip-container">
                        <span class="tooltip-icon">ℹ</span>
                        <div class="tooltip-text">
                            Mostra em quais tipos de problemas cada usuário é mais especializado, baseado na frequência de atendimentos por categoria. Ajuda a identificar as competências específicas de cada membro da equipe.
                        </div>
                    </div>
                </h3>
                <div style="max-height: 500px; overflow-y: auto;">
                    <?php 
                    $current_user_esp = '';
                    $user_count_esp = 0;
                    foreach ($especializacao_usuarios as $item): 
                        if ($current_user_esp != $item['usuario_nome']) {
                            if ($current_user_esp != '') echo '</tbody></table></div>';
                            $current_user_esp = $item['usuario_nome'];
                            $user_count_esp++;
                            if ($user_count_esp > 8) break; // Limitar para não sobrecarregar
                            echo '<div style="margin-bottom: 20px;">';
                            echo '<h4 style="color: #023324; margin-bottom: 10px; font-weight: bold; background-color: #f8f9fa; padding: 8px 12px; border-radius: 4px; display: inline-block;">' . htmlspecialchars($current_user_esp) . '</h4>';
                            echo '<div class="tabela-scroll"><table class="tabela">';
                            echo '<thead><tr><th>Tipo de Problema</th><th>Atendimentos</th><th>% Especialização</th></tr></thead><tbody>';
                        }
                    ?>
                        <tr>
                            <td><?php echo htmlspecialchars($item['tipo_erro']); ?></td>
                            <td><?php echo $item['total_atendimentos']; ?></td>
                            <td>
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo $item['percentual_especializacao']; ?>%"></div>
                                    <span class="progress-text"><?php echo number_format($item['percentual_especializacao'], 1); ?>%</span>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if ($current_user_esp != '') echo '</tbody></table></div></div>'; ?>
                </div>
            </div>
        </section>

        <!-- Clientes com Reincidência (Múltiplos Atendimentos no Mesmo Menu/Submenu) -->
        <section class="tabelas-container">
            <div class="tabela-wrapper full-width">
                <h3 class="with-tooltip">Clientes com Reincidência (Múltiplos Atendimentos no Mesmo Menu/Submenu)
                    <div class="tooltip-container">
                        <span class="tooltip-icon">ℹ</span>
                        <div class="tooltip-text">
                            Lista de clientes que tiveram múltiplos atendimentos para o mesmo tipo de problema (mesmo menu/submenu). Indica possíveis problemas recorrentes que não foram resolvidos adequadamente ou necessitam de atenção especial.
                        </div>
                    </div>
                </h3>
                <div class="tabela-scroll" style="max-height: 400px; overflow-y: auto;">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Menu</th>
                                <th>Submenu</th>
                                <th>Reincidências</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($reincidencia_menu_submenu as $reincidencia): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($reincidencia['cliente_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($reincidencia['menu_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($reincidencia['submenu_nome']); ?></td>
                                    <td><span class="badge badge-warning"><?php echo $reincidencia['total_reincidencias']; ?>x</span></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- Tabelas - Top 10 Tipos de Erro e Top 10 Clientes -->
        <section class="tabelas-container">
            <div class="tabela-wrapper">
                <h3 class="with-tooltip">Top 10 Tipos de Erro
                    <div class="tooltip-container">
                        <span class="tooltip-icon">ℹ</span>
                        <div class="tooltip-text">
                            Ranking dos 10 tipos de erro mais frequentes nos atendimentos. Útil para identificar os problemas mais comuns e priorizar melhorias ou treinamentos específicos.
                        </div>
                    </div>
                </h3>
                <div class="tabela-scroll">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th>Tipo de Erro</th>
                                <th>Quantidade</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($atendimentos_por_tipo_erro as $tipo): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($tipo['descricao']); ?></td>
                                    <td><?php echo $tipo['total']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="tabela-wrapper">
                <h3 class="with-tooltip">Top 10 Clientes
                    <div class="tooltip-container">
                        <span class="tooltip-icon">ℹ</span>
                        <div class="tooltip-text">
                            Lista dos 10 clientes que mais solicitaram atendimentos no período. Permite identificar clientes que demandam mais suporte e podem necessitar de atenção especial ou treinamento adicional.
                        </div>
                    </div>
                </h3>
                <div class="tabela-scroll">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>Atendimentos</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($top_clientes as $cliente): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cliente['cliente_nome']); ?></td>
                                    <td><?php echo $cliente['total']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>


        <!-- Listagem Completa de Atendimentos COM DESCRIÇÕES CORRIGIDAS -->
        <section class="listagem-atendimentos">
            <h2 class="with-tooltip">Listagem Completa de Atendimentos
                <div class="tooltip-container">
                    <span class="tooltip-icon">ℹ</span>
                    <div class="tooltip-text">
                        Tabela detalhada com todos os atendimentos realizados no período selecionado. Inclui informações completas como cliente, tipo de problema, dificuldade, descrição e usuário responsável pelo atendimento.
                    </div>
                </div>
            </h2>
            <div class="tabela-wrapper full-width">
                <div class="tabela-scroll">
                    <table class="tabela">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Cliente</th>
                                <th>Menu</th>
                                <th>Submenu</th>
                                <th>Tipo</th>
                                <th>Dificuldade</th>
                                <th>Descrição</th>
                                <th>Usuário</th>
                                <th>Data</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($atendimentos as $atendimento): ?>
                                <tr>
                                    <td><?php echo $atendimento['id']; ?></td>
                                    <td><?php echo htmlspecialchars($atendimento['cliente_nome_completo']); ?></td>
                                    <td><?php echo htmlspecialchars($atendimento['menu_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($atendimento['submenu_nome']); ?></td>
                                    <td><?php echo htmlspecialchars($atendimento['tipo_erro_descricao']); ?></td>
                                    <td>
                                        <span class="nivel-dificuldade" style="background-color: <?php echo $atendimento['nivel_dificuldade_cor']; ?>">
                                            <?php echo htmlspecialchars($atendimento['nivel_dificuldade_nome']); ?>
                                        </span>
                                    </td>
                                    <td class="descricao-completa" id="descricao-<?php echo $atendimento['id']; ?>">
                                        <div class="texto-resumido">
                                            <?php 
                                            $descricao = strip_tags($atendimento['descricao']);
                                            if (strlen($descricao) > 100) {
                                                echo htmlspecialchars(substr($descricao, 0, 100)) . '... ';
                                                echo '<a href="javascript:void(0)" onclick="toggleDescricao(' . $atendimento['id'] . ')" style="color: #8CC053;">(clique para expandir)</a>';
                                            } else {
                                                echo htmlspecialchars($descricao);
                                            }
                                            ?>
                                        </div>
                                        <div class="texto-completo" style="display: none;">
                                            <?php echo htmlspecialchars($descricao); ?>
                                            <div style="margin-top: 5px;">
                                                <a href="javascript:void(0)" onclick="toggleDescricao(<?php echo $atendimento['id']; ?>)" style="color: #8CC053; font-size: 12px;">
                                                    (recolher)
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($atendimento['usuario_nome']); ?></td>
                                    <td><?php echo date('d/m/Y H:i', strtotime($atendimento['data_atendimento'])); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Paginação -->
                <?php if ($total_paginas > 1): ?>
                <div class="paginacao">
                    <?php if ($pagina_atual > 1): ?>
                        <a href="javascript:void(0)" onclick="mudarPagina(<?php echo $pagina_atual - 1; ?>)">&laquo; Anterior</a>
                    <?php endif; ?>
                    
                    <?php
                    // Mostrar até 5 páginas ao redor da atual
                    $inicio = max(1, $pagina_atual - 2);
                    $fim = min($total_paginas, $inicio + 4);
                    $inicio = max(1, $fim - 4);
                    
                    for ($i = $inicio; $i <= $fim; $i++):
                    ?>
                        <?php if ($i == $pagina_atual): ?>
                            <span class="ativa"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="javascript:void(0)" onclick="mudarPagina(<?php echo $i; ?>)"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($pagina_atual < $total_paginas): ?>
                        <a href="javascript:void(0)" onclick="mudarPagina(<?php echo $pagina_atual + 1; ?>)">Próxima &raquo;</a>
                    <?php endif; ?>
                </div>
                <div class="paginacao-info">
                    Página <?php echo $pagina_atual; ?> de <?php echo $total_paginas; ?> 
                    | Total de registros: <?php echo $total_atendimentos; ?>
                </div>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <script>
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

        // Função para carregar submenus
         function carregarSubmenusRelatorio() {
             const menuId = document.getElementById('menu').value;
             const submenuSelect = document.getElementById('submenu');
             
             // Limpar opções existentes
             submenuSelect.innerHTML = '<option value="">Todos</option>';
             
             if (menuId) {
                 // Usar os submenus já carregados do PHP
                 <?php if (!empty($submenus_filtro)): ?>
                 const submenus = <?php echo json_encode($submenus_filtro); ?>;
                 submenus.forEach(submenu => {
                     if (submenu.menu_id == menuId) {
                         const option = document.createElement('option');
                         option.value = submenu.id;
                         option.textContent = submenu.nome;
                         submenuSelect.appendChild(option);
                     }
                 });
                 <?php endif; ?>
             }
         }

        // Função para expandir/recolher descrições - VERSÃO CORRIGIDA
        function toggleDescricao(id) {
            const descricaoCompleta = document.getElementById('descricao-' + id);
            if (!descricaoCompleta) return;
            
            const textoResumido = descricaoCompleta.querySelector('.texto-resumido');
            const textoCompleto = descricaoCompleta.querySelector('.texto-completo');
            
            if (textoCompleto.style.display === 'none') {
                textoResumido.style.display = 'none';
                textoCompleto.style.display = 'block';
            } else {
                textoCompleto.style.display = 'none';
                textoResumido.style.display = 'block';
            }
        }

        // Função para mudar de página mantendo a posição de rolagem
        function mudarPagina(pagina) {
            // Atualizar o campo oculto de página
            document.getElementById('pagina_input').value = pagina;
            
            // Salvar a posição atual de rolagem
            const posicaoScroll = window.scrollY;
            sessionStorage.setItem('scrollPosRelatorios', posicaoScroll);
            
            // Enviar o formulário
            document.querySelector('.filtros-form').submit();
        }

        // Inicializar quando a página carregar
        document.addEventListener('DOMContentLoaded', function() {
            // Restaurar a posição de rolagem após o carregamento da página
            const posicaoSalva = sessionStorage.getItem('scrollPosRelatorios');
            if (posicaoSalva) {
                window.scrollTo(0, parseInt(posicaoSalva));
                sessionStorage.removeItem('scrollPosRelatorios');
            }
            
            // Configurar o filtro de cliente
            setupFilter('cliente_filter', 'cliente_options', 'cliente');
        });

        // Configuração global dos gráficos
        Chart.defaults.font.family = 'Arial, sans-serif';
        Chart.defaults.color = '#666';

        // Gráfico de Evolução Mensal de Atendimentos
        const ctxEvolucaoMensal = document.getElementById('chartEvolucaoMensal').getContext('2d');
        new Chart(ctxEvolucaoMensal, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($evolucao_mensal, 'mes_ano_formatado')); ?>,
                datasets: [{
                    label: 'Atendimentos',
                    data: <?php echo json_encode(array_column($evolucao_mensal, 'total')); ?>,
                    borderColor: '#8CC053',
                    backgroundColor: 'rgba(140, 192, 83, 0.1)',
                    borderWidth: 4,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#8CC053',
                    pointBorderColor: '#023324',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
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
                        backgroundColor: 'rgba(2, 51, 36, 0.9)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: '#8CC053',
                        borderWidth: 1,
                        callbacks: {
                            title: function(context) {
                                return 'Mês: ' + context[0].label;
                            },
                            label: function(context) {
                                return 'Total de Atendimentos: ' + context.parsed.y;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#666',
                            precision: 0
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            color: '#666',
                            maxRotation: 45
                        },
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    }
                }
            }
        });

        // Gráfico de Atendimentos por Dia
        const ctxPorDia = document.getElementById('chartPorDia').getContext('2d');
        new Chart(ctxPorDia, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($atendimentos_por_dia, 'data')); ?>,
                datasets: [{
                    label: 'Atendimentos',
                    data: <?php echo json_encode(array_column($atendimentos_por_dia, 'total')); ?>,
                    borderColor: '#023324',
                    backgroundColor: 'rgba(2, 51, 36, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });



        // Gráfico de Atendimentos por Submenu
        const ctxPorSubmenu = document.getElementById('chartPorSubmenu').getContext('2d');
        new Chart(ctxPorSubmenu, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($atendimentos_por_submenu, 'nome')); ?>,
                datasets: [{
                    label: 'Atendimentos',
                    data: <?php echo json_encode(array_column($atendimentos_por_submenu, 'total')); ?>,
                    backgroundColor: '#8CC053',
                    borderColor: '#7AB043',
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
                            title: function(context) {
                                return context[0].label; // Mostra o nome completo no tooltip
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45,
                            callback: function(value, index, values) {
                                const label = this.getLabelForValue(value);
                                // Trunca labels muito longos
                                return label.length > 15 ? label.substring(0, 15) + '...' : label;
                            }
                        }
                    }
                }
            }
        });

        // NOVO: Gráfico de Atendimentos por Dia da Semana
        const ctxPorDiaSemana = document.getElementById('chartPorDiaSemana').getContext('2d');
        const coresDiasSemana = ['#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7', '#DDA0DD', '#98D8C8'];
        new Chart(ctxPorDiaSemana, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($atendimentos_por_dia_semana, 'dia_semana')); ?>,
                datasets: [{
                    label: 'Atendimentos',
                    data: <?php echo json_encode(array_column($atendimentos_por_dia_semana, 'total')); ?>,
                    backgroundColor: coresDiasSemana,
                    borderColor: coresDiasSemana.map(cor => cor.replace('0.8', '1')),
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Gráfico de Atendimentos por Usuário
        const ctxPorUsuario = document.getElementById('chartPorUsuario').getContext('2d');
        new Chart(ctxPorUsuario, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($atendimentos_por_usuario, 'nome')); ?>,
                datasets: [{
                    label: 'Atendimentos',
                    data: <?php echo json_encode(array_column($atendimentos_por_usuario, 'total')); ?>,
                    backgroundColor: '#8CC053',
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
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45
                        }
                    }
                }
            }
        });

        // Gráfico de Atendimentos por Menu
        const ctxPorMenu = document.getElementById('chartPorMenu').getContext('2d');
        new Chart(ctxPorMenu, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($atendimentos_por_menu, 'nome')); ?>,
                datasets: [{
                    label: 'Atendimentos',
                    data: <?php echo json_encode(array_column($atendimentos_por_menu, 'total')); ?>,
                    backgroundColor: '#023324',
                    borderColor: '#8CC053',
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
                    }
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });



        // Gráfico de Distribuição por Horário
        const ctxPorHorario = document.getElementById('chartPorHorario').getContext('2d');
        // Preparar dados de 0 a 23 horas
        const horasCompletas = [];
        const atendimentosHoras = [];
        for (let i = 0; i < 24; i++) {
            horasCompletas.push(i + ':00');
            const encontrado = <?php echo json_encode($atendimentos_por_horario); ?>.find(item => item.hora == i);
            atendimentosHoras.push(encontrado ? encontrado.total : 0);
        }
        
        new Chart(ctxPorHorario, {
            type: 'line',
            data: {
                labels: horasCompletas,
                datasets: [{
                    label: 'Atendimentos',
                    data: atendimentosHoras,
                    borderColor: '#fd7e14',
                    backgroundColor: 'rgba(253, 126, 20, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });

        // Gráfico de Evolução Mensal dos Principais Tipos de Erro
        const ctxEvolucaoTipos = document.getElementById('chartEvolucaoTipos').getContext('2d');
        
        // Processar dados para o gráfico de linha múltipla
        const dadosEvolucao = <?php echo json_encode($evolucao_tipos_erro); ?>;
        
        // Ordenar meses corretamente (MM/YYYY)
        const mesesUnicos = [...new Set(dadosEvolucao.map(item => item.mes))].sort((a, b) => {
            const [mesA, anoA] = a.split('/');
            const [mesB, anoB] = b.split('/');
            const dataA = new Date(anoA, mesA - 1);
            const dataB = new Date(anoB, mesB - 1);
            return dataA - dataB;
        });
        
        const tiposUnicos = [...new Set(dadosEvolucao.map(item => item.tipo_erro))].slice(0, 5); // Top 5 tipos
        
        const cores = ['#dc3545', '#007bff', '#28a745', '#ffc107', '#6f42c1'];
        const datasets = tiposUnicos.map((tipo, index) => {
            const dadosTipo = mesesUnicos.map(mes => {
                const encontrado = dadosEvolucao.find(item => item.mes === mes && item.tipo_erro === tipo);
                return encontrado ? encontrado.total : 0;
            });
            
            return {
                label: tipo,
                data: dadosTipo,
                borderColor: cores[index],
                backgroundColor: cores[index] + '20',
                borderWidth: 2,
                fill: false,
                tension: 0.4
            };
        });
        
        new Chart(ctxEvolucaoTipos, {
            type: 'line',
            data: {
                labels: mesesUnicos,
                datasets: datasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            boxWidth: 12,
                            padding: 10
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            precision: 0
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>