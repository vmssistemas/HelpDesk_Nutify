<?php
require_once __DIR__ . '/../../config/db.php';

function getTreinamentosStatus() {
    global $conn;
    $query = "SELECT * FROM treinamentos_status ORDER BY ordem";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getTreinamentoById($id) {
    global $conn;
    
    $query = "SELECT t.*, s.nome as status_nome, s.cor as status_cor,
                     c.nome as cliente_nome, c.contrato as cliente_contrato,
                     p.nome as plano_nome, u.nome as usuario_nome,
                     r.nome as responsavel_nome,
                     tt.nome as tipo_nome
              FROM treinamentos t
              LEFT JOIN treinamentos_status s ON t.status_id = s.id
              LEFT JOIN clientes c ON t.cliente_id = c.id
              LEFT JOIN planos p ON t.plano_id = p.id
              LEFT JOIN usuarios u ON t.usuario_id = u.id
              LEFT JOIN usuarios r ON t.responsavel_id = r.id
              LEFT JOIN treinamentos_tipos tt ON t.tipo_id = tt.id
              WHERE t.id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    return $result->fetch_assoc();
}

function getTreinamentosChecklist($treinamento_id) {
    global $conn;
    
    $query = "SELECT tc.id, tc.concluido, tc.horas, tc.observacao,
                     COALESCE(pc.item, uc.item, mc.item) as item,
                     COALESCE(pc.descricao, uc.descricao, mc.descricao) as item_descricao,
                     COALESCE(pc.ordem, uc.ordem, mc.ordem) as item_ordem
              FROM treinamentos_checklist tc
              LEFT JOIN planos_checklist pc ON tc.item_plano_id = pc.id AND tc.tipo = 'plano'
              LEFT JOIN planos_upgrade_checklist uc ON tc.item_upgrade_id = uc.id AND tc.tipo = 'upgrade'
              LEFT JOIN modulos_checklist mc ON tc.item_modulo_id = mc.id AND tc.tipo = 'modulo'
              WHERE tc.treinamento_id = ?
              ORDER BY item_ordem";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $treinamento_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function criarChecklistParaTreinamento($treinamento_id, $plano_id, $tipo_id, $dados_especificos = []) {
    global $conn;
    
    // Se for implantação, mantém o comportamento atual
    if ($tipo_id == 1) {
        $query = "SELECT id FROM planos_checklist WHERE plano_id = ? ORDER BY ordem";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $plano_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $itens = $result->fetch_all(MYSQLI_ASSOC);
        
        foreach ($itens as $item) {
            $query = "INSERT INTO treinamentos_checklist 
                     (treinamento_id, tipo, item_plano_id) 
                     VALUES (?, 'plano', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $treinamento_id, $item['id']);
            $stmt->execute();
        }
    } 
    // Se for upgrade de plano
    elseif ($tipo_id == 2) {
        $origem = $dados_especificos['plano_origem'];
        $destino = $dados_especificos['plano_destino'];
        
        $query = "SELECT id FROM planos_upgrade_checklist 
                 WHERE origem_plano_id = ? AND destino_plano_id = ? 
                 ORDER BY ordem";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $origem, $destino);
        $stmt->execute();
        $result = $stmt->get_result();
        $itens = $result->fetch_all(MYSQLI_ASSOC);
        
        foreach ($itens as $item) {
            $query = "INSERT INTO treinamentos_checklist 
                     (treinamento_id, tipo, item_upgrade_id) 
                     VALUES (?, 'upgrade', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $treinamento_id, $item['id']);
            $stmt->execute();
        }
    }
    // Se for módulo
    elseif ($tipo_id == 4) {
        $modulo = $dados_especificos['modulo'];
        
        $query = "SELECT id FROM modulos_checklist WHERE modulo = ? ORDER BY ordem";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $modulo);
        $stmt->execute();
        $result = $stmt->get_result();
        $itens = $result->fetch_all(MYSQLI_ASSOC);
        
        foreach ($itens as $item) {
            $query = "INSERT INTO treinamentos_checklist 
                     (treinamento_id, tipo, item_modulo_id) 
                     VALUES (?, 'modulo', ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $treinamento_id, $item['id']);
            $stmt->execute();
        }
    }
}


function getClienteNameById($id) {
    global $conn;
    $query = "SELECT nome, contrato FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return '';
    }
    
    $cliente = $result->fetch_assoc();
    return ($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome'];
}
function getPlanosChecklist($plano_id) {
    global $conn;
    $query = "SELECT * FROM planos_checklist WHERE plano_id = ? ORDER BY ordem";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $plano_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getClientes() {
    global $conn;
    $query = "SELECT id, nome, contrato, plano FROM clientes ORDER BY nome";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getPlanos() {
    global $conn;
    $query = "SELECT id, nome FROM planos ORDER BY nome";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getEquipe() {
    global $conn;
    $query = "SELECT id, nome FROM usuarios ORDER BY nome";
    $result = $conn->query($query);
    return $result->fetch_all(MYSQLI_ASSOC);
}

function getTreinamentoComentarios($treinamento_id, $usuario_logado_id = null) {
    global $conn;
    $query = "SELECT c.*, u.nome as usuario_nome 
              FROM treinamentos_comentarios c
              JOIN usuarios u ON c.usuario_id = u.id
              WHERE c.treinamento_id = ?
              ORDER BY c.data_criacao ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $treinamento_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $comentarios = $result->fetch_all(MYSQLI_ASSOC);
    
    // Adiciona informação se o usuário pode editar cada comentário
    if ($usuario_logado_id) {
        foreach ($comentarios as &$comentario) {
            $comentario['pode_editar'] = ($comentario['usuario_id'] == $usuario_logado_id);
        }
    }
    
    return $comentarios;
}


// Adicionar estas funções ao arquivo functions_treinamentos.php

/**
 * Converte horas no formato decimal (ex: 1.5) para TIME (ex: '01:30:00')
 */
function decimalParaTime($horasDecimal) {
    if (!$horasDecimal && $horasDecimal !== 0) return '01:00:00'; // Valor padrão
    
    $horasDecimal = (float)$horasDecimal;
    $horas = floor($horasDecimal);
    $minutos = round(($horasDecimal - $horas) * 60);
    
    // Garante que não ultrapasse 59 minutos
    if ($minutos >= 60) {
        $horas += 1;
        $minutos = 0;
    }
    
    return sprintf("%02d:%02d:00", $horas, $minutos);
}

/**
 * Converte TIME (ex: '01:30:00') para decimal (ex: 1.5)
 */
function timeParaDecimal($timeStr) {
    if (!$timeStr) return 1.0; // Valor padrão 1 hora
    
    $partes = explode(':', $timeStr);
    $horas = (int)$partes[0];
    $minutos = isset($partes[1]) ? (int)$partes[1] : 0;
    
    return $horas + ($minutos / 60);
}

/**
 * Formata TIME para exibição (HH:MM)
 */
function formatarTimeParaExibicao($timeStr) {
    if (!$timeStr) return '00:00';
    
    $partes = explode(':', $timeStr);
    $horas = $partes[0];
    $minutos = isset($partes[1]) ? $partes[1] : '00';
    
    return sprintf("%02d:%02d", $horas, $minutos);
}

/**
 * Soma um array de valores TIME
 */
function somarTempos($tempos) {
    $totalSegundos = 0;
    
    foreach ($tempos as $time) {
        if (!$time) continue;
        
        $partes = explode(':', $time);
        $horas = (int)$partes[0];
        $minutos = isset($partes[1]) ? (int)$partes[1] : 0;
        $segundos = isset($partes[2]) ? (int)$partes[2] : 0;
        
        $totalSegundos += $horas * 3600 + $minutos * 60 + $segundos;
    }
    
    $horasTotal = floor($totalSegundos / 3600);
    $minutosTotal = floor(($totalSegundos % 3600) / 60);
    
    return sprintf("%02d:%02d:00", $horasTotal, $minutosTotal);
}

function adicionarComentario($treinamento_id, $usuario_id, $comentario) {
    global $conn;
    $query = "INSERT INTO treinamentos_comentarios (treinamento_id, usuario_id, comentario) VALUES (?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iis", $treinamento_id, $usuario_id, $comentario);
    return $stmt->execute();
}

function calcularTotalHorasChecklist($checklist) {
    $total = 0;
    foreach ($checklist as $item) {
        $total += $item['horas'] ?? 0;
    }
    return number_format($total, 2);
}

function atualizarItemChecklist($treinamento_id, $item_id, $dados) {
    global $conn;
    
    $query = "UPDATE treinamentos_checklist SET 
                concluido = ?,
                horas = ?,
                observacao = ?
              WHERE id = ? AND treinamento_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "idsii",
        $dados['concluido'],
        $dados['horas'],
        $dados['observacao'],
        $item_id,
        $treinamento_id
    );
    
    return $stmt->execute();
}



// functions_treinamentos.php - Adicionar esta função
function getTotalHorasPorStatus($treinamento_id) {
    global $conn;
    
    // Primeiro, buscar o cliente_id do treinamento atual
    $query_cliente = "SELECT cliente_id FROM treinamentos WHERE id = ?";
    $stmt_cliente = $conn->prepare($query_cliente);
    $stmt_cliente->bind_param("i", $treinamento_id);
    $stmt_cliente->execute();
    $result_cliente = $stmt_cliente->get_result();
    
    if ($result_cliente->num_rows === 0) {
        return [];
    }
    
    $cliente_id = $result_cliente->fetch_assoc()['cliente_id'];
    
    // Buscar totais de todos os agendamentos do cliente, incluindo os sem treinamento_id
    $query = "SELECT 
                a.status,
                SEC_TO_TIME(SUM(TIME_TO_SEC(a.horas))) as total_horas,
                COUNT(*) as total_agendamentos
              FROM treinamentos_agendamentos a
              WHERE a.cliente_id = ? 
              GROUP BY a.status";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $totais = [];
    while ($row = $result->fetch_assoc()) {
        $totais[$row['status']] = [
            'total_horas' => $row['total_horas'],
            'total_agendamentos' => $row['total_agendamentos']
        ];
    }
    
    return $totais;
}




// Adicionar ao functions_treinamentos.php

// Atualizar getTreinamentoAgendamentos para buscar por cliente ao invés de treinamento específico
function getTreinamentoAgendamentos($treinamento_id) {
    global $conn;
    
    // Primeiro, buscar o cliente_id do treinamento atual
    $query_cliente = "SELECT cliente_id FROM treinamentos WHERE id = ?";
    $stmt_cliente = $conn->prepare($query_cliente);
    $stmt_cliente->bind_param("i", $treinamento_id);
    $stmt_cliente->execute();
    $result_cliente = $stmt_cliente->get_result();
    
    if ($result_cliente->num_rows === 0) {
        return [];
    }
    
    $cliente_id = $result_cliente->fetch_assoc()['cliente_id'];
    
    // Buscar todos os agendamentos do cliente, incluindo os sem treinamento_id vinculado
    $query = "SELECT a.*, u.nome as usuario_nome, 
              TIME_FORMAT(a.horas, '%H:%i') as horas_formatadas,
              COALESCE(a.treinamento_id, 0) as treinamento_origem_id
              FROM treinamentos_agendamentos a
              LEFT JOIN usuarios u ON a.usuario_id = u.id
              WHERE a.cliente_id = ?
              ORDER BY a.data_agendada DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_all(MYSQLI_ASSOC);
}
/**
 * Converte segundos para formato HH:MM
 */
function segundosParaHoraMinuto($segundos) {
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    return sprintf("%02d:%02d", $horas, $minutos);
}

// Função auxiliar para converter HH:MM em minutos
function convertTimeToMinutes($time) {
    if (empty($time)) return 0;
    list($hours, $minutes) = explode(':', $time);
    return ($hours * 60) + $minutes;
}

// Converter minutos de volta para HH:MM
function convertMinutesToTime($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    return sprintf("%02d:%02d", $hours, $mins);
}

/**
 * Converte formato HH:MM para segundos
 */
function horaMinutoParaSegundos($horaMinuto) {
    list($horas, $minutos) = explode(':', $horaMinuto);
    return ($horas * 3600) + ($minutos * 60);
}


// Atualizar a função adicionarAgendamento
function adicionarAgendamento($treinamento_id, $data_agendada, $horas, $observacao, $usuario_id = null) {
    global $conn;
    
    // Converter horas no formato HH:MM para TIME do MySQL
    $horas_time = formatarHorasParaTime($horas);
    
    // Buscar o cliente_id do treinamento
    $query_cliente = "SELECT cliente_id FROM treinamentos WHERE id = ?";
    $stmt_cliente = $conn->prepare($query_cliente);
    $stmt_cliente->bind_param("i", $treinamento_id);
    $stmt_cliente->execute();
    $result_cliente = $stmt_cliente->get_result();
    
    $cliente_id = null;
    if ($result_cliente->num_rows > 0) {
        $cliente_id = $result_cliente->fetch_assoc()['cliente_id'];
    }
    $stmt_cliente->close();
    
    $query = "INSERT INTO treinamentos_agendamentos 
              (treinamento_id, cliente_id, usuario_id, data_agendada, horas, observacao) 
              VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiisss", $treinamento_id, $cliente_id, $usuario_id, $data_agendada, $horas_time, $observacao);
    return $stmt->execute();
}
function atualizarStatusAgendamento($agendamento_id, $status, $motivo_cancelamento = null) {
    global $conn;
    
    // Converter explicitamente os valores para o mesmo collation
    $query = "UPDATE treinamentos_agendamentos SET 
              status = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci,
              motivo_cancelamento = ?,
              data_conclusao = CASE WHEN CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'realizado' THEN NOW() ELSE NULL END
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssi", $status, $motivo_cancelamento, $status, $agendamento_id);
    return $stmt->execute();
}

function excluirAgendamento($agendamento_id) {
    global $conn;
    
    $query = "DELETE FROM treinamentos_agendamentos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $agendamento_id);
    return $stmt->execute();
}
// Adicionar ao functions_treinamentos.php

// Atualizar a função atualizarAgendamento
function atualizarAgendamento($agendamento_id, $data_agendada, $horas, $observacao, $status, $usuario_id = null, $motivo_cancelamento = null) {
    global $conn;
    
    // Converter horas no formato HH:MM para TIME do MySQL
    $horas_time = formatarHorasParaTime($horas);
    
    $query = "UPDATE treinamentos_agendamentos SET 
              data_agendada = ?,
              horas = ?,
              observacao = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci,
              status = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci,
              usuario_id = ?,
              motivo_cancelamento = CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci,
              data_conclusao = CASE WHEN CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci = 'realizado' AND status != 'realizado' THEN NOW() 
                                   WHEN CONVERT(? USING utf8mb4) COLLATE utf8mb4_unicode_ci != 'realizado' THEN NULL 
                                   ELSE data_conclusao END
              WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssssii", 
        $data_agendada, 
        $horas_time, 
        $observacao, 
        $status, 
        $usuario_id, 
        $motivo_cancelamento, 
        $status, 
        $status, 
        $agendamento_id
    );
    return $stmt->execute();
}


function formatarHorasParaTime($horas) {
    // Se já estiver no formato HH:MM
    if (preg_match('/^([0-9]{1,2}):([0-5][0-9])$/', $horas)) {
        return $horas . ':00';
    }
    
    // Se for decimal (1.5 horas)
    if (is_numeric($horas)) {
        $hours = floor($horas);
        $minutes = round(($horas - $hours) * 60);
        return sprintf("%02d:%02d:00", $hours, $minutes);
    }
    
    // Valor padrão se inválido
    return '01:00:00';
}

function getAgendamentoById($agendamento_id) {
    global $conn;
    
    $query = "SELECT * FROM treinamentos_agendamentos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $agendamento_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        return false;
    }
    
    return $result->fetch_assoc();
}
?>