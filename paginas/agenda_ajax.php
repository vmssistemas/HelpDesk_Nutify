<?php
session_start();

// Definir o fuso horário para São Paulo (mesmo do FullCalendar)
date_default_timezone_set('America/Sao_Paulo');

// Desabilitar exibição de erros para evitar interferir no JSON
error_reporting(0);
ini_set('display_errors', 0);

require_once '../config/db.php';

header('Content-Type: application/json');

// Verificar CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
    exit;
}

// Verificar autenticação
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit;
}

// Obter ID do usuário logado
$email = $_SESSION['email'];
$user_query = $conn->prepare("SELECT id FROM usuarios WHERE email = ?");
$user_query->bind_param("s", $email);
$user_query->execute();
$user_result = $user_query->get_result();
$user = $user_result->fetch_assoc();
$user_id = $user['id'];
$user_query->close();

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'fetch':
        fetchEvents($conn, $user_id);
        break;
    case 'create':
        createEvent($conn, $user_id);
        break;
    case 'update':
        updateEvent($conn, $user_id);
        break;
    case 'fetchDeleted':
        fetchDeletedEvents($conn);
        break;
    case 'fetchChangesHistory':
        fetchChangesHistory($conn, $user_id);
        break;
    case 'fetchEvent':
        fetchEvent($conn, $user_id);
        break;
    case 'restore':
        restoreEvent($conn, $user_id);
        break;
    case 'delete':
        deleteEvent($conn, $user_id);
        break;
    case 'history':
        getEventHistory($conn);
        break;
    case 'checkUpdates':
        checkUpdates($conn, $user_id);
        break;
    case 'setStatus':
        setStatus($conn, $user_id);
        break;
    case 'setTrainingStatus':
        setTrainingStatus($conn, $user_id);
        break;
    case 'toggleConcluido':
        toggleConcluido($conn, $user_id);
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Ação inválida']);
        break;
}

function fetchEvents($conn, $user_id) {
    $start = $_POST['start'] ?? date('Y-m-01');
    $end = $_POST['end'] ?? date('Y-m-t');
    
    // Gerar chave de cache baseada no período e timestamp de última modificação
    $cache_key = "agenda_events_" . md5($start . $end);
    
    // Verificar se existe cache válido (aumentado para 5 minutos em produção)
    $cache_file = sys_get_temp_dir() . "/" . $cache_key . ".json";
    $cache_time_file = sys_get_temp_dir() . "/" . $cache_key . "_time.txt";
    
    // Cache válido por 5 minutos (300 segundos) para melhor performance em produção
    $cache_duration = 300;
    
    // Verificar se o cache existe e é válido
    if (file_exists($cache_file) && file_exists($cache_time_file)) {
        $cache_time = (int)file_get_contents($cache_time_file);
        if ((time() - $cache_time) < $cache_duration) {
            // Verificar se houve modificações recentes na agenda
            $last_modification_query = "SELECT MAX(GREATEST(
                COALESCE(e.data_atualizacao, e.data_criacao, '1970-01-01'),
                COALESCE(h.data_acao, '1970-01-01')
            )) as last_modified
            FROM agenda_eventos e
            LEFT JOIN agenda_historico h ON e.id = h.evento_id
            WHERE (
                (e.inicio BETWEEN ? AND ?) 
                OR (e.fim BETWEEN ? AND ?) 
                OR (e.inicio <= ? AND e.fim >= ?)
            )";
            
            $mod_stmt = $conn->prepare($last_modification_query);
            $mod_stmt->bind_param("ssssss", $start, $end, $start, $end, $start, $end);
            $mod_stmt->execute();
            $mod_result = $mod_stmt->get_result();
            $mod_row = $mod_result->fetch_assoc();
            $mod_stmt->close();
            
            $last_db_modification = strtotime($mod_row['last_modified'] ?? '1970-01-01');
            
            // Se o cache é mais recente que a última modificação, usar cache
            if ($cache_time >= $last_db_modification) {
                $cached_events = file_get_contents($cache_file);
                echo $cached_events;
                return;
            }
        }
    }
    
    // Buscar TODOS os eventos para TODOS os usuários - visibilidade global
    // Incluir dados do cliente via JOIN
    // Ordenar primeiro por usuário (nome) e depois por horário de início
    // Adicionar LIMIT para evitar sobrecarga em períodos com muitos eventos
    $query = "SELECT e.*, u.nome as usuario_nome, u.cor as usuario_cor,
                     c.nome as cliente_nome, c.contrato as cliente_contrato,
                     CONCAT(COALESCE(c.contrato, ''), CASE WHEN c.contrato IS NOT NULL AND c.contrato != '' THEN ' - ' ELSE '' END, c.nome) AS cliente_nome_completo
              FROM agenda_eventos e
              LEFT JOIN usuarios u ON e.usuario_id = u.id
              LEFT JOIN clientes c ON e.cliente_id = c.id
              WHERE e.excluido = 0
              AND (
                  (e.inicio BETWEEN ? AND ?) 
                  OR (e.fim BETWEEN ? AND ?) 
                  OR (e.inicio <= ? AND e.fim >= ?)
              )
              ORDER BY u.nome ASC, e.inicio ASC
              LIMIT 1000";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssssss", $start, $end, $start, $end, $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        // Usar a cor do usuário se existir, senão usar a cor do evento
        $color = !empty($row['usuario_cor']) ? $row['usuario_cor'] : ($row['cor'] ?? '#3788d8');
        
        $events[] = [
            'id' => $row['id'],
            'title' => $row['titulo'],
            'start' => $row['inicio'],
            'end' => $row['fim'],
            'color' => $color,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'extendedProps' => [
                'descricao' => $row['descricao'],
                'tipo' => $row['tipo'],
                'usuario_id' => $row['usuario_id'],
                'usuario_nome' => $row['usuario_nome'],
                'visibilidade' => $row['visibilidade'],
                'criado_por' => $row['criado_por'],
                'concluido' => $row['concluido'] ?? 0,
                'cliente_id' => $row['cliente_id'],
                'cliente_nome' => $row['cliente_nome'],
                'cliente_contrato' => $row['cliente_contrato'],
                'cliente_nome_completo' => $row['cliente_nome_completo']
            ]
        ];
    }
    
    $stmt->close();
    
    // Salvar no cache
    $json_events = json_encode($events);
    file_put_contents($cache_file, $json_events);
    file_put_contents($cache_time_file, time());
    
    echo $json_events;
}

function createEvent($conn, $user_id) {
    // Primeiro, buscar a cor do usuário atribuído
    $usuario_id = $_POST['usuario_id'] ?? $user_id;
    $cor_usuario = getUsuarioCor($conn, $usuario_id);
    
    // Usar horário local do servidor (Brasília)
    $data_atual = date('Y-m-d H:i:s');
    
    $data = [
        'titulo' => $_POST['titulo'] ?? '',
        'descricao' => $_POST['descricao'] ?? '',
        'inicio' => $_POST['inicio'] ?? '',
        'fim' => $_POST['fim'] ?? '',
        'cor' => $cor_usuario, // Usar a cor do usuário
        'tipo' => $_POST['tipo'] ?? '',
        'usuario_id' => $usuario_id,
        'cliente_id' => !empty($_POST['cliente_id']) ? $_POST['cliente_id'] : null,
        'visibilidade' => 'publico', // Sempre público - campo removido da interface
        'criado_por' => $user_id,
        'data_criacao' => $data_atual,
        'data_atualizacao' => $data_atual
    ];
    
    // Validações básicas
    if (empty($data['titulo'])) {
        echo json_encode(['success' => false, 'message' => 'Título é obrigatório']);
        return;
    }
    
    if (empty($data['inicio'])) {
        echo json_encode(['success' => false, 'message' => 'Data e hora de início são obrigatórias']);
        return;
    }
    
    if (empty($data['fim'])) {
        echo json_encode(['success' => false, 'message' => 'Data e hora de fim são obrigatórias']);
        return;
    }
    
    // Validação adicional: verificar se a data/hora de fim é posterior à de início
    if (!empty($data['inicio']) && !empty($data['fim'])) {
        $inicio_timestamp = strtotime($data['inicio']);
        $fim_timestamp = strtotime($data['fim']);
        
        if ($fim_timestamp <= $inicio_timestamp) {
            echo json_encode(['success' => false, 'message' => 'A data e hora de fim deve ser posterior à data e hora de início']);
            return;
        }
    }
    
    // Verificação de data retroativa - apenas admins podem editar eventos em datas passadas
    if (isDataRetroativa($data['inicio']) && !isAdmin($conn, $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Apenas administradores podem editar agendamentos em datas retroativas']);
        return;
    }
    
    // Verificação de data retroativa - apenas admins podem criar eventos em datas passadas
    if (isDataRetroativa($data['inicio']) && !isAdmin($conn, $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Apenas administradores podem criar agendamentos em datas retroativas']);
        return;
    }
    
    // Validação de conflito de horários
    $conflito = verificarConflitoHorario($conn, $usuario_id, $data['inicio'], $data['fim']);
    if ($conflito) {
        echo json_encode([
            'success' => false, 
            'message' => 'Conflito de horário detectado! Já existe um agendamento para este usuário no período de ' . 
                        date('d/m/Y H:i', strtotime($conflito['inicio'])) . ' às ' . 
                        date('d/m/Y H:i', strtotime($conflito['fim'])) . 
                        ' (' . $conflito['titulo'] . ')'
        ]);
        return;
    }
    
    // Verificar se os campos data_criacao e data_atualizacao existem na tabela
    $check_columns = $conn->query("SHOW COLUMNS FROM agenda_eventos LIKE 'data_criacao'");
    $has_data_columns = $check_columns->num_rows > 0;
    
    if ($has_data_columns) {
        $query = "INSERT INTO agenda_eventos (titulo, descricao, inicio, inicio_original, fim, cor, tipo, usuario_id, cliente_id, visibilidade, criado_por, data_criacao, data_atualizacao) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssiiisss", 
            $data['titulo'],
            $data['descricao'],
            $data['inicio'],
            $data['inicio'], // inicio_original recebe o mesmo valor de inicio na criação
            $data['fim'],
            $data['cor'],
            $data['tipo'],
            $data['usuario_id'],
            $data['cliente_id'],
            $data['visibilidade'],
            $data['criado_por'],
            $data['data_criacao'],
            $data['data_atualizacao']
        );
    } else {
        // Versão sem os campos de data
        $query = "INSERT INTO agenda_eventos (titulo, descricao, inicio, inicio_original, fim, cor, tipo, usuario_id, cliente_id, visibilidade, criado_por) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssiiis", 
            $data['titulo'],
            $data['descricao'],
            $data['inicio'],
            $data['inicio'], // inicio_original recebe o mesmo valor de inicio na criação
            $data['fim'],
            $data['cor'],
            $data['tipo'],
            $data['usuario_id'],
            $data['cliente_id'],
            $data['visibilidade'],
            $data['criado_por']
        );
    }
    
    if ($stmt->execute()) {
        $event_id = $stmt->insert_id;
        
        // Registrar no histórico
        registerHistory($conn, $event_id, 'criado', null, $user_id);
        
        // Verificar se é um evento do tipo treinamento com cliente identificado
        if ($data['tipo'] === 'tarefa' && !empty($data['cliente_id'])) {
            // Criar registro automático na agenda de treinamentos
            criarAgendamentoTreinamento($conn, $event_id, $data, $user_id);
        }
        
        // Limpar cache para forçar atualização em tempo real
        clearAgendaCache();
        
        echo json_encode(['success' => true, 'id' => $event_id]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao criar evento: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function clearAgendaCache() {
    $temp_dir = sys_get_temp_dir();
    $cache_files = glob($temp_dir . "/agenda_events_*.json");
    foreach ($cache_files as $cache_file) {
        if (file_exists($cache_file)) {
            unlink($cache_file);
        }
    }
    $cache_time_files = glob($temp_dir . "/agenda_events_*_time.txt");
    foreach ($cache_time_files as $cache_time_file) {
        if (file_exists($cache_time_file)) {
            unlink($cache_time_file);
        }
    }
}

function getUsuarioCor($conn, $usuario_id) {
    $query = "SELECT cor FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $usuario_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $usuario = $result->fetch_assoc();
    $stmt->close();
    
    return $usuario['cor'] ?? '#3788d8'; // Retorna a cor do usuário ou padrão se não existir
}

function fetchDeletedEvents($conn) {
    $query = "SELECT e.*, u.nome as usuario_nome, 
                     u2.nome as excluido_por, 
                     h.data_acao as data_exclusao
              FROM agenda_eventos e
              LEFT JOIN usuarios u ON e.usuario_id = u.id
              LEFT JOIN agenda_historico h ON e.id = h.evento_id AND h.acao = 'excluido'
              LEFT JOIN usuarios u2 ON h.usuario_id = u2.id
              WHERE e.excluido = 1
              ORDER BY h.data_acao DESC";
    
    $result = $conn->query($query);
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    echo json_encode(['success' => true, 'events' => $events]);
}

function restoreEvent($conn, $user_id) {
    $event_id = $_POST['id'] ?? 0;
    if (!$event_id) {
        echo json_encode(['success' => false, 'message' => 'ID do evento inválido']);
        return;
    }
    
    // Verificar se o usuário tem permissão para restaurar (admin ou quem excluiu)
    $query = "SELECT h.usuario_id as excluido_por 
              FROM agenda_eventos e
              JOIN agenda_historico h ON e.id = h.evento_id AND h.acao = 'excluido'
              WHERE e.id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    $stmt->close();
    
    if (!$event) {
        echo json_encode(['success' => false, 'message' => 'Evento não encontrado ou não foi excluído']);
        return;
    }
    
    if ($event['excluido_por'] != $user_id && !isAdmin($conn, $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Você não tem permissão para restaurar este evento']);
        return;
    }
    
    // Restaurar o evento
    $query = "UPDATE agenda_eventos SET excluido = 0 WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $event_id);
    
    if ($stmt->execute()) {
        // Registrar no histórico
        registerHistory($conn, $event_id, 'restaurado', null, $user_id);
        
        // Limpar cache para forçar atualização em tempo real
        clearAgendaCache();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao restaurar evento: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function updateEvent($conn, $user_id) {
    $event_id = $_POST['id'] ?? 0;
    if (!$event_id) {
        echo json_encode(['success' => false, 'message' => 'ID do evento inválido']);
        return;
    }
    
    // Primeiro, buscar o evento atual para registrar no histórico
    $current_event = getEventById($conn, $event_id);
    if (!$current_event) {
        echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
        return;
    }
    
    // Buscar a cor do novo usuário atribuído
    $novo_usuario_id = $_POST['usuario_id'] ?? $current_event['usuario_id'];
    $cor_usuario = getUsuarioCor($conn, $novo_usuario_id);
    
    // Usar horário local do servidor (Brasília)
    $data_atualizacao = date('Y-m-d H:i:s');
    
    // Normalizar datas para comparação (converter formato do input para formato do banco)
    $inicio_normalizado = $_POST['inicio'] ?? $current_event['inicio'];
    $fim_normalizado = $_POST['fim'] ?? $current_event['fim'];
    
    // Se as datas vêm do formulário (formato YYYY-MM-DDTHH:MM), converter para formato do banco (YYYY-MM-DD HH:MM:SS)
    if (isset($_POST['inicio']) && strpos($_POST['inicio'], 'T') !== false) {
        $inicio_normalizado = str_replace('T', ' ', $_POST['inicio']) . ':00';
    }
    if (isset($_POST['fim']) && strpos($_POST['fim'], 'T') !== false) {
        $fim_normalizado = str_replace('T', ' ', $_POST['fim']) . ':00';
    }
    
    $data = [
        'titulo' => !empty($_POST['titulo']) ? $_POST['titulo'] : $current_event['titulo'],
        'descricao' => isset($_POST['descricao']) ? $_POST['descricao'] : $current_event['descricao'],
        'inicio' => $inicio_normalizado,
        'fim' => $fim_normalizado,
        'cor' => $cor_usuario, // Usar a cor do novo usuário
        'tipo' => !empty($_POST['tipo']) ? $_POST['tipo'] : $current_event['tipo'],
        'usuario_id' => $novo_usuario_id,
        'cliente_id' => isset($_POST['cliente_id']) && !empty($_POST['cliente_id']) ? $_POST['cliente_id'] : $current_event['cliente_id'],
        'visibilidade' => 'publico', // Sempre público - campo removido da interface
        'data_atualizacao' => $data_atualizacao
    ];
    
    // Validação de conflito de horários (apenas se horário ou usuário mudaram)
    if ($data['inicio'] !== $current_event['inicio'] || 
        $data['fim'] !== $current_event['fim'] || 
        $data['usuario_id'] !== $current_event['usuario_id']) {
        
        $conflito = verificarConflitoHorario($conn, $novo_usuario_id, $data['inicio'], $data['fim'], $event_id);
        if ($conflito) {
            echo json_encode([
                'success' => false, 
                'message' => 'Conflito de horário detectado! Já existe um agendamento para este usuário no período de ' . 
                            date('d/m/Y H:i', strtotime($conflito['inicio'])) . ' às ' . 
                            date('d/m/Y H:i', strtotime($conflito['fim'])) . 
                            ' (' . $conflito['titulo'] . ')'
            ]);
            return;
        }
    }
    
    // Validações básicas
    if (empty($data['titulo'])) {
        echo json_encode(['success' => false, 'message' => 'Título é obrigatório']);
        return;
    }
    
    if (empty($data['inicio'])) {
        echo json_encode(['success' => false, 'message' => 'Data e hora de início são obrigatórias']);
        return;
    }
    
    if (empty($data['fim'])) {
        echo json_encode(['success' => false, 'message' => 'Data e hora de fim são obrigatórias']);
        return;
    }
    
    // Validação adicional: verificar se a data/hora de fim é posterior à de início
    if (!empty($data['inicio']) && !empty($data['fim'])) {
        $inicio_timestamp = strtotime($data['inicio']);
        $fim_timestamp = strtotime($data['fim']);
        
        if ($fim_timestamp <= $inicio_timestamp) {
            echo json_encode(['success' => false, 'message' => 'A data e hora de fim deve ser posterior à data e hora de início']);
            return;
        }
    }
    
    // Verificação de data retroativa - apenas admins podem editar eventos em datas passadas
    if (isDataRetroativa($data['inicio']) && !isAdmin($conn, $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Apenas administradores podem editar agendamentos em datas retroativas']);
        return;
    }
    
    // Verificar se o campo data_atualizacao existe na tabela
    $check_columns = $conn->query("SHOW COLUMNS FROM agenda_eventos LIKE 'data_atualizacao'");
    $has_data_column = $check_columns->num_rows > 0;
    
    if ($has_data_column) {
        $query = "UPDATE agenda_eventos 
                  SET titulo = ?, descricao = ?, inicio = ?, fim = ?, cor = ?, tipo = ?, usuario_id = ?, cliente_id = ?, visibilidade = ?, data_atualizacao = ?
                  WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssiiisi", 
            $data['titulo'],
            $data['descricao'],
            $data['inicio'],
            $data['fim'],
            $data['cor'],
            $data['tipo'],
            $data['usuario_id'],
            $data['cliente_id'],
            $data['visibilidade'],
            $data['data_atualizacao'],
            $event_id
        );
    } else {
        $query = "UPDATE agenda_eventos 
                  SET titulo = ?, descricao = ?, inicio = ?, fim = ?, cor = ?, tipo = ?, usuario_id = ?, cliente_id = ?, visibilidade = ?
                  WHERE id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssiiisi", 
            $data['titulo'],
            $data['descricao'],
            $data['inicio'],
            $data['fim'],
            $data['cor'],
            $data['tipo'],
            $data['usuario_id'],
            $data['cliente_id'],
            $data['visibilidade'],
            $event_id
        );
    }
    
    if ($stmt->execute()) {
        // Registrar no histórico apenas o que foi alterado
        $changes = [];
        
        if ($data['titulo'] !== $current_event['titulo']) {
            $changes[] = "• Título alterado de '{$current_event['titulo']}' para '{$data['titulo']}'";
        }
        
        if ($data['descricao'] !== $current_event['descricao']) {
            $changes[] = "• Descrição alterada de '{$current_event['descricao']}' para '{$data['descricao']}'";
        }
        
        if ($data['inicio'] !== $current_event['inicio']) {
            $inicio_antigo = date('d/m/Y H:i', strtotime($current_event['inicio']));
            $inicio_novo = date('d/m/Y H:i', strtotime($data['inicio']));
            $changes[] = "• Horário de início alterado de {$inicio_antigo} para {$inicio_novo}";
        }
        
        if ($data['fim'] !== $current_event['fim']) {
            $fim_antigo = date('d/m/Y H:i', strtotime($current_event['fim']));
            $fim_novo = date('d/m/Y H:i', strtotime($data['fim']));
            $changes[] = "• Horário de fim alterado de {$fim_antigo} para {$fim_novo}";
        }
        
        if ($data['tipo'] !== $current_event['tipo']) {
            $changes[] = "• Tipo alterado de '{$current_event['tipo']}' para '{$data['tipo']}'";
        }
        
        if ((string)$data['usuario_id'] !== (string)$current_event['usuario_id']) {
            // Buscar nomes dos usuários para melhor legibilidade
            $old_user_name = getUserNameById($conn, $current_event['usuario_id']);
            $new_user_name = getUserNameById($conn, $data['usuario_id']);
            
            $changes[] = "• Usuário responsável alterado de '{$old_user_name}' para '{$new_user_name}'";
        }
        
        if ((string)$data['cliente_id'] !== (string)$current_event['cliente_id']) {
            $cliente_antigo = getClienteNameById($conn, $current_event['cliente_id']);
            $cliente_novo = getClienteNameById($conn, $data['cliente_id']);
            $cliente_antigo_text = $cliente_antigo ?: 'Nenhum cliente';
            $cliente_novo_text = $cliente_novo ?: 'Nenhum cliente';
            $changes[] = "• Cliente alterado de '{$cliente_antigo_text}' para '{$cliente_novo_text}'";
        }
        
        // Removido o registro de alterações de visibilidade do histórico
        
        // Só registrar no histórico se houve alterações
        if (!empty($changes)) {
            $changes_text = implode("\n", $changes);
            registerHistory($conn, $event_id, 'editado', $changes_text, $user_id);
        }
        
        // Sincronizar agendamento de treinamento se necessário
        atualizarAgendamentoTreinamento($conn, $event_id, $data, $current_event);
        
        // Limpar cache para forçar atualização em tempo real
        clearAgendaCache();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar evento: ' . $stmt->error]);
    }
    
    $stmt->close();
}

// Função auxiliar para obter o nome do usuário pelo ID
function getUserNameById($conn, $user_id) {
    if (empty($user_id)) return 'Não atribuído';
    
    $query = "SELECT nome FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return $user ? $user['nome'] : 'Usuário desconhecido';
}

// Função auxiliar para obter o nome do cliente pelo ID
function getClienteNameById($conn, $cliente_id) {
    if (empty($cliente_id)) return '';
    
    $query = "SELECT nome, contrato FROM clientes WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        $stmt->close();
        return '';
    }
    
    $cliente = $result->fetch_assoc();
    $stmt->close();
    return ($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome'];
}

function deleteEvent($conn, $user_id) {
    $event_id = $_POST['id'] ?? 0;
    if (!$event_id) {
        echo json_encode(['success' => false, 'message' => 'ID do evento inválido']);
        return;
    }
    
    // Primeiro, buscar o evento para registrar no histórico
    $current_event = getEventById($conn, $event_id);
    if (!$current_event) {
        echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
        return;
    }
    
    // Verificação de data retroativa - apenas admins podem excluir eventos em datas passadas
    if (isDataRetroativa($current_event['inicio']) && !isAdmin($conn, $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Apenas administradores podem excluir agendamentos em datas retroativas']);
        return;
    }
    
    // Permissão removida - qualquer usuário pode excluir qualquer evento
    // Todas as alterações ficam registradas no histórico
    
    // Excluir vinculação com treinamento se existir
    excluirVinculacaoTreinamento($conn, $event_id);
    
    // Registrar no histórico antes de marcar como excluído
    // Incluir apenas informações essenciais para o histórico de exclusão
    $usuario_nome = getUserNameById($conn, $current_event['usuario_id']);
    $inicio_formatado = date('d/m/Y H:i', strtotime($current_event['inicio']));
    $fim_formatado = date('d/m/Y H:i', strtotime($current_event['fim']));
    
    $old_data_text = "Evento excluído:\n• Título: {$current_event['titulo']}\n• Período: {$inicio_formatado} às {$fim_formatado}\n• Tipo: {$current_event['tipo']}\n• Responsável: {$usuario_nome}";
    
    registerHistory($conn, $event_id, 'excluido', $old_data_text, $user_id);
    
    // Marcar como excluído em vez de apagar
    // Usar horário local do servidor (Brasília)
    $data_exclusao = date('Y-m-d H:i:s');
    
    $query = "UPDATE agenda_eventos SET excluido = 1, data_atualizacao = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("si", $data_exclusao, $event_id);
    
    if ($stmt->execute()) {
        // Limpar cache para forçar atualização em tempo real
        clearAgendaCache();
        
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao excluir evento: ' . $stmt->error]);
    }
    
    $stmt->close();
}

function getEventHistory($conn) {
    $event_id = $_POST['event_id'] ?? 0;
    if (!$event_id) {
        echo json_encode(['success' => false, 'message' => 'ID do evento inválido']);
        return;
    }
    
    $query = "SELECT h.*, u.nome as usuario_nome 
              FROM agenda_historico h
              JOIN usuarios u ON h.usuario_id = u.id
              WHERE h.evento_id = ?
              ORDER BY h.data_acao DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    while ($row = $result->fetch_assoc()) {
        $history[] = $row;
    }
    
    echo json_encode(['success' => true, 'history' => $history]);
    $stmt->close();
}

function getEventById($conn, $event_id) {
    $query = "SELECT * FROM agenda_eventos WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $event = $result->fetch_assoc();
    $stmt->close();
    return $event;
}

function registerHistory($conn, $event_id, $action, $old_data, $user_id) {
    // Usar horário local do servidor (Brasília)
    $data_acao = date('Y-m-d H:i:s');
    
    $query = "INSERT INTO agenda_historico (evento_id, acao, dados_anteriores, usuario_id, data_acao) 
              VALUES (?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("issis", $event_id, $action, $old_data, $user_id, $data_acao);
    $stmt->execute();
    $stmt->close();
}

function isAdmin($conn, $user_id) {
    $query = "SELECT admin FROM usuarios WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    return $user['admin'] == 1;
}

function isDataRetroativa($data) {
    // Converter a data para timestamp
    $data_timestamp = strtotime($data);
    // Obter o início do dia atual (00:00:00)
    $hoje_inicio = strtotime(date('Y-m-d 00:00:00'));
    
    // Retorna true se a data é anterior ao início do dia atual
    return $data_timestamp < $hoje_inicio;
}

function checkUpdates($conn, $user_id) {
    $last_timestamp = isset($_POST['last_timestamp']) ? intval($_POST['last_timestamp']) / 1000 : 0;
    // Usar timestamp sem ajuste de fuso horário
    $last_check_date = date('Y-m-d H:i:s', $last_timestamp);
    
    // Verificar se os campos data_criacao e data_atualizacao existem na tabela
    $check_columns = $conn->query("SHOW COLUMNS FROM agenda_eventos LIKE 'data_criacao'");
    $has_data_columns = $check_columns->num_rows > 0;
    
    // Buscar eventos atualizados (criados, editados ou excluídos)
    $events = [];
    $deleted_events = [];
    
    // 1. Primeiro, buscar eventos ativos (não excluídos) que foram criados ou atualizados
    // Modificar para buscar TODOS os eventos, não apenas do usuário logado
    if ($has_data_columns) {
        $query = "SELECT e.*, u.nome as usuario_nome, u.cor as usuario_cor,
                         c.nome as cliente_nome, c.contrato as cliente_contrato,
                         CONCAT(COALESCE(c.contrato, ''), CASE WHEN c.contrato IS NOT NULL AND c.contrato != '' THEN ' - ' ELSE '' END, c.nome) AS cliente_nome_completo,
                         'active' as event_status
                  FROM agenda_eventos e
                  LEFT JOIN usuarios u ON e.usuario_id = u.id
                  LEFT JOIN clientes c ON e.cliente_id = c.id
                  LEFT JOIN agenda_historico h ON e.id = h.evento_id
                  WHERE e.excluido = 0
                  AND (
                      e.data_criacao > ? OR 
                      e.data_atualizacao > ? OR
                      h.data_acao > ?
                  )
                  GROUP BY e.id
                  ORDER BY u.nome ASC, e.inicio ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $last_check_date, $last_check_date, $last_check_date);
    } else {
        // Versão alternativa usando apenas o histórico
        $query = "SELECT e.*, u.nome as usuario_nome, u.cor as usuario_cor,
                         c.nome as cliente_nome, c.contrato as cliente_contrato,
                         CONCAT(COALESCE(c.contrato, ''), CASE WHEN c.contrato IS NOT NULL AND c.contrato != '' THEN ' - ' ELSE '' END, c.nome) AS cliente_nome_completo,
                         'active' as event_status
                  FROM agenda_eventos e
                  LEFT JOIN usuarios u ON e.usuario_id = u.id
                  LEFT JOIN clientes c ON e.cliente_id = c.id
                  LEFT JOIN agenda_historico h ON e.id = h.evento_id
                  WHERE e.excluido = 0
                  AND h.data_acao > ?
                  GROUP BY e.id
                  ORDER BY u.nome ASC, e.inicio ASC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $last_check_date);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        // Usar a cor do usuário se existir, senão usar a cor do evento
        $color = !empty($row['usuario_cor']) ? $row['usuario_cor'] : ($row['cor'] ?? '#3788d8');
        
        $events[] = [
            'id' => $row['id'],
            'titulo' => $row['titulo'],
            'inicio' => $row['inicio'],
            'fim' => $row['fim'],
            'color' => $color,
            'backgroundColor' => $color,
            'borderColor' => $color,
            'status' => 'active',
            'extendedProps' => [
                'descricao' => $row['descricao'],
                'tipo' => $row['tipo'],
                'usuario_id' => $row['usuario_id'],
                'usuario_nome' => $row['usuario_nome'],
                'visibilidade' => $row['visibilidade'],
                'criado_por' => $row['criado_por'],
                'concluido' => $row['concluido'] ?? 0,
                'cliente_id' => $row['cliente_id'],
                'cliente_nome' => $row['cliente_nome'],
                'cliente_contrato' => $row['cliente_contrato'],
                'cliente_nome_completo' => $row['cliente_nome_completo']
            ]
        ];
    }
    $stmt->close();
    
    // 2. Agora, buscar eventos que foram excluídos desde o último timestamp
    // Modificar para buscar TODOS os eventos excluídos, não apenas do usuário logado
    $query = "SELECT e.id, e.titulo, h.data_acao, u.nome as usuario_nome, 'deleted' as event_status
              FROM agenda_eventos e
              JOIN agenda_historico h ON e.id = h.evento_id
              LEFT JOIN usuarios u ON e.usuario_id = u.id
              WHERE e.excluido = 1
              AND h.acao = 'excluido'
              AND h.data_acao > ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $last_check_date);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $deleted_events[] = [
            'id' => $row['id'],
            'titulo' => $row['titulo'],
            'usuario_nome' => $row['usuario_nome'],
            'status' => 'deleted',
            'data_acao' => $row['data_acao']
        ];
    }
    $stmt->close();
    
    // Limpar cache quando há atualizações
    if (!empty($events) || !empty($deleted_events)) {
        $temp_dir = sys_get_temp_dir();
        $cache_files = glob($temp_dir . "/agenda_events_*.json");
        foreach ($cache_files as $cache_file) {
            if (file_exists($cache_file)) {
                unlink($cache_file);
            }
        }
        $cache_time_files = glob($temp_dir . "/agenda_events_*_time.txt");
        foreach ($cache_time_files as $cache_time_file) {
            if (file_exists($cache_time_file)) {
                unlink($cache_time_file);
            }
        }
    }
    
    // Combinar eventos ativos e excluídos
    $all_updates = array_merge($events, $deleted_events);
    $hasUpdates = count($all_updates) > 0;
    
    // Calcular o timestamp mais recente dos eventos encontrados
    $latest_timestamp = $last_timestamp * 1000; // Converter de volta para milissegundos
    if ($hasUpdates) {
        $latest_timestamp = time() * 1000; // Usar timestamp atual se houver atualizações
    }
    
    echo json_encode([
        'success' => true,
        'hasUpdates' => $hasUpdates,
        'events' => $events,
        'deleted_events' => $deleted_events,
        'latest_timestamp' => $latest_timestamp
    ]);
}

function setStatus($conn, $user_id) {
    if (!isset($_POST['event_id']) || !isset($_POST['status']) || !isset($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
        return;
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        return;
    }
    
    $event_id = (int)$_POST['event_id'];
    $status = $_POST['status'] === 'null' ? null : (int)$_POST['status'];
    
    // Permissão removida - qualquer usuário pode alterar status de qualquer evento
    // Buscar o evento para verificar se existe e obter a data de início
    $check_query = "SELECT concluido, inicio FROM agenda_eventos WHERE id = ? AND excluido = 0";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $event_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
        $check_stmt->close();
        return;
    }
    
    $event_data = $check_result->fetch_assoc();
    $check_stmt->close();
    
    // Verificação de data retroativa - apenas admins podem alterar status de eventos em datas passadas
    if (isDataRetroativa($event_data['inicio']) && !isAdmin($conn, $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Apenas administradores podem alterar o status de agendamentos em datas retroativas']);
        return;
    }
    
    // Definir mensagem baseada no status
    if ($status === null) {
        $message = 'Status do evento removido';
        $action_text = 'removeu o status';
    } elseif ($status == 1) {
        $message = 'Evento marcado como concluído';
        $action_text = 'marcou como concluído';
    } elseif ($status == 2) {
        $message = 'Evento marcado como não concluído';
        $action_text = 'marcou como não concluído';
    } else {
        echo json_encode(['success' => false, 'message' => 'Status inválido']);
        return;
    }
    
    // Atualizar o status
    if ($status === null) {
        $update_query = "UPDATE agenda_eventos SET concluido = NULL WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $event_id);
    } else {
        $update_query = "UPDATE agenda_eventos SET concluido = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $status, $event_id);
    }
    
    if ($update_stmt->execute()) {
        // SINCRONIZAÇÃO COM TREINAMENTOS_AGENDAMENTOS
        if ($status == 1) {
            // Quando marcado como concluído (1) -> status 'realizado'
            // Buscar agendamento vinculado através do treinamento_agendamento_id
            $sync_query = "UPDATE treinamentos_agendamentos ta 
                          INNER JOIN agenda_eventos ae ON ae.treinamento_agendamento_id = ta.id 
                          SET ta.status = 'realizado', ta.data_conclusao = NOW() 
                          WHERE ae.id = ? AND ta.status != 'realizado'";
            $sync_stmt = $conn->prepare($sync_query);
            $sync_stmt->bind_param("i", $event_id);
            $sync_stmt->execute();
            $sync_stmt->close();
            
            // Também verificar vinculação reversa através do agenda_evento_id
            $sync_query2 = "UPDATE treinamentos_agendamentos 
                           SET status = 'realizado', data_conclusao = NOW() 
                           WHERE agenda_evento_id = ? AND status != 'realizado'";
            $sync_stmt2 = $conn->prepare($sync_query2);
            $sync_stmt2->bind_param("i", $event_id);
            $sync_stmt2->execute();
            $sync_stmt2->close();
        } elseif ($status == 2) {
            // Quando marcado como não concluído (2) -> status 'cancelado'
            // Buscar agendamento vinculado através do treinamento_agendamento_id
            $sync_query = "UPDATE treinamentos_agendamentos ta 
                          INNER JOIN agenda_eventos ae ON ae.treinamento_agendamento_id = ta.id 
                          SET ta.status = 'cancelado', ta.data_conclusao = NULL 
                          WHERE ae.id = ? AND ta.status != 'cancelado'";
            $sync_stmt = $conn->prepare($sync_query);
            $sync_stmt->bind_param("i", $event_id);
            $sync_stmt->execute();
            $sync_stmt->close();
            
            // Também verificar vinculação reversa através do agenda_evento_id
            $sync_query2 = "UPDATE treinamentos_agendamentos 
                           SET status = 'cancelado', data_conclusao = NULL 
                           WHERE agenda_evento_id = ? AND status != 'cancelado'";
            $sync_stmt2 = $conn->prepare($sync_query2);
            $sync_stmt2->bind_param("i", $event_id);
            $sync_stmt2->execute();
            $sync_stmt2->close();
        } elseif ($status === null) {
            // Quando status é removido (null) -> status 'agendado'
            // Buscar agendamento vinculado através do treinamento_agendamento_id
            $sync_query = "UPDATE treinamentos_agendamentos ta 
                          INNER JOIN agenda_eventos ae ON ae.treinamento_agendamento_id = ta.id 
                          SET ta.status = 'agendado', ta.data_conclusao = NULL 
                          WHERE ae.id = ? AND ta.status != 'agendado'";
            $sync_stmt = $conn->prepare($sync_query);
            $sync_stmt->bind_param("i", $event_id);
            $sync_stmt->execute();
            $sync_stmt->close();
            
            // Também verificar vinculação reversa através do agenda_evento_id
            $sync_query2 = "UPDATE treinamentos_agendamentos 
                           SET status = 'agendado', data_conclusao = NULL 
                           WHERE agenda_evento_id = ? AND status != 'agendado'";
            $sync_stmt2 = $conn->prepare($sync_query2);
            $sync_stmt2->bind_param("i", $event_id);
            $sync_stmt2->execute();
            $sync_stmt2->close();
        }
        
        // Registrar no histórico
        registerHistory($conn, $event_id, 'status_alterado', $action_text, $user_id);
        
        // Limpar cache para forçar atualização em tempo real
        clearAgendaCache();
        
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status']);
    }
    
    $update_stmt->close();
}

function toggleConcluido($conn, $user_id) {
    $event_id = $_POST['event_id'] ?? '';
    
    if (empty($event_id)) {
        echo json_encode(['success' => false, 'message' => 'ID do evento é obrigatório']);
        return;
    }
    
    // Permissão removida - qualquer usuário pode alterar status de qualquer evento
    // Buscar o evento para verificar se existe e obter a data de início
    $check_query = "SELECT concluido, inicio FROM agenda_eventos WHERE id = ? AND excluido = 0";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $event_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
        $check_stmt->close();
        return;
    }
    
    $row = $check_result->fetch_assoc();
    $current_status = $row['concluido'];
    $check_stmt->close();
    
    // Verificação de data retroativa - apenas admins podem alterar status de eventos em datas passadas
    if (isDataRetroativa($row['inicio']) && !isAdmin($conn, $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Apenas administradores podem alterar o status de agendamentos em datas retroativas']);
        return;
    }
    
    // Implementar ciclo de três estados: null -> 1 (concluído) -> 2 (não concluído) -> null
    if ($current_status === null || $current_status == 0) {
        $new_status = 1; // Marcar como concluído
    } elseif ($current_status == 1) {
        $new_status = 2; // Marcar como não concluído
    } else {
        $new_status = null; // Voltar ao estado sem marcação
    }
    $check_stmt->close();
    
    // Atualizar o status de conclusão
    if ($new_status === null) {
        $update_query = "UPDATE agenda_eventos SET concluido = NULL WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $event_id);
    } else {
        $update_query = "UPDATE agenda_eventos SET concluido = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $new_status, $event_id);
    }
    
    if ($update_stmt->execute()) {
        // Registrar no histórico
        if ($new_status === null) {
            $action = 'removido_status';
            $message = 'Status do evento removido';
        } elseif ($new_status == 1) {
            $action = 'marcado_concluido';
            $message = 'Evento marcado como concluído';
        } else {
            $action = 'marcado_nao_concluido';
            $message = 'Evento marcado como não concluído';
        }
        
        // SINCRONIZAÇÃO COM TREINAMENTOS_AGENDAMENTOS
        if ($new_status == 1) {
            // Quando marcado como concluído (1) -> status 'realizado'
            // Buscar agendamento vinculado através do treinamento_agendamento_id
            $sync_query = "UPDATE treinamentos_agendamentos ta 
                          INNER JOIN agenda_eventos ae ON ae.treinamento_agendamento_id = ta.id 
                          SET ta.status = 'realizado', ta.data_conclusao = NOW() 
                          WHERE ae.id = ? AND ta.status != 'realizado'";
            $sync_stmt = $conn->prepare($sync_query);
            $sync_stmt->bind_param("i", $event_id);
            $sync_stmt->execute();
            $sync_stmt->close();
            
            // Também verificar vinculação reversa através do agenda_evento_id
            $sync_query2 = "UPDATE treinamentos_agendamentos 
                           SET status = 'realizado', data_conclusao = NOW() 
                           WHERE agenda_evento_id = ? AND status != 'realizado'";
            $sync_stmt2 = $conn->prepare($sync_query2);
            $sync_stmt2->bind_param("i", $event_id);
            $sync_stmt2->execute();
            $sync_stmt2->close();
        } elseif ($new_status == 2) {
            // Quando marcado como não concluído (2) -> status 'cancelado'
            // Buscar agendamento vinculado através do treinamento_agendamento_id
            $sync_query = "UPDATE treinamentos_agendamentos ta 
                          INNER JOIN agenda_eventos ae ON ae.treinamento_agendamento_id = ta.id 
                          SET ta.status = 'cancelado', ta.data_conclusao = NULL 
                          WHERE ae.id = ? AND ta.status != 'cancelado'";
            $sync_stmt = $conn->prepare($sync_query);
            $sync_stmt->bind_param("i", $event_id);
            $sync_stmt->execute();
            $sync_stmt->close();
            
            // Também verificar vinculação reversa através do agenda_evento_id
            $sync_query2 = "UPDATE treinamentos_agendamentos 
                           SET status = 'cancelado', data_conclusao = NULL 
                           WHERE agenda_evento_id = ? AND status != 'cancelado'";
            $sync_stmt2 = $conn->prepare($sync_query2);
            $sync_stmt2->bind_param("i", $event_id);
            $sync_stmt2->execute();
            $sync_stmt2->close();
        } elseif ($new_status === null) {
            // Quando status é removido (null) -> status 'agendado'
            // Buscar agendamento vinculado através do treinamento_agendamento_id
            $sync_query = "UPDATE treinamentos_agendamentos ta 
                          INNER JOIN agenda_eventos ae ON ae.treinamento_agendamento_id = ta.id 
                          SET ta.status = 'agendado', ta.data_conclusao = NULL 
                          WHERE ae.id = ? AND ta.status != 'agendado'";
            $sync_stmt = $conn->prepare($sync_query);
            $sync_stmt->bind_param("i", $event_id);
            $sync_stmt->execute();
            $sync_stmt->close();
            
            // Também verificar vinculação reversa através do agenda_evento_id
            $sync_query2 = "UPDATE treinamentos_agendamentos 
                           SET status = 'agendado', data_conclusao = NULL 
                           WHERE agenda_evento_id = ? AND status != 'agendado'";
            $sync_stmt2 = $conn->prepare($sync_query2);
            $sync_stmt2->bind_param("i", $event_id);
            $sync_stmt2->execute();
            $sync_stmt2->close();
        }
        
        registerHistory($conn, $event_id, $action, null, $user_id);
        
        // Limpar cache para forçar atualização em tempo real
        clearAgendaCache();
        
        echo json_encode([
            'success' => true, 
            'concluido' => $new_status,
            'message' => $message
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar evento: ' . $update_stmt->error]);
    }
    
    $update_stmt->close();
}

// Função para verificar conflito de horários
function verificarConflitoHorario($conn, $usuario_id, $inicio, $fim, $evento_id = null) {
    // Se não há fim definido, considerar apenas o horário de início
    if (empty($fim)) {
        $fim = $inicio;
    }
    
    // Converter formato se necessário (do input datetime-local para formato do banco)
    if (strpos($inicio, 'T') !== false) {
        $inicio = str_replace('T', ' ', $inicio) . ':00';
    }
    if (strpos($fim, 'T') !== false) {
        $fim = str_replace('T', ' ', $fim) . ':00';
    }
    
    // Query para verificar conflitos de horário
    // Agora só permite agendamento no mesmo horário se o evento existente estiver marcado como "Não Concluído" (concluido = 2)
    // Bloqueia se estiver "Pendente" (concluido IS NULL) ou "Concluído" (concluido = 1)
    $query = "SELECT id, titulo, inicio, fim, concluido
              FROM agenda_eventos 
              WHERE usuario_id = ? 
              AND excluido = 0 
              AND (concluido IS NULL OR concluido != 2)
              AND (
                  -- Novo evento começa durante um evento existente
                  (? >= inicio AND ? < fim) OR
                  -- Novo evento termina durante um evento existente  
                  (? > inicio AND ? <= fim) OR
                  -- Novo evento engloba completamente um evento existente
                  (? <= inicio AND ? >= fim) OR
                  -- Eventos com mesmo horário de início
                  (? = inicio)
              )";
    
    // Se estamos editando um evento, excluir ele da verificação
    if ($evento_id) {
        $query .= " AND id != ?";
    }
    
    $stmt = $conn->prepare($query);
    
    if ($evento_id) {
        $stmt->bind_param("isssssssi", $usuario_id, $inicio, $inicio, $fim, $fim, $inicio, $fim, $inicio, $evento_id);
    } else {
        $stmt->bind_param("isssssss", $usuario_id, $inicio, $inicio, $fim, $fim, $inicio, $fim, $inicio);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $conflito = $result->fetch_assoc();
        $stmt->close();
        return $conflito;
    }
    
    $stmt->close();
    return false;
}

function fetchChangesHistory($conn, $user_id) {
    // Buscar histórico de alterações dos eventos atribuídos ao usuário logado
    // Incluindo eventos excluídos para mostrar no histórico
    $query = "SELECT h.*, e.titulo, e.inicio, e.inicio_original, e.fim, u.nome as usuario_nome
              FROM agenda_historico h
              JOIN agenda_eventos e ON h.evento_id = e.id
              JOIN usuarios u ON h.usuario_id = u.id
              WHERE e.usuario_id = ? AND h.acao != 'criado'
              ORDER BY h.data_acao DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    
    $stmt->close();
    echo json_encode(['success' => true, 'events' => $events]);
}

function fetchEvent($conn, $user_id) {
    $event_id = $_POST['event_id'] ?? 0;
    if (!$event_id) {
        echo json_encode(['success' => false, 'message' => 'ID do evento inválido']);
        return;
    }
    
    $query = "SELECT e.*, u.nome as usuario_nome, c.nome as cliente_nome
              FROM agenda_eventos e
              LEFT JOIN usuarios u ON e.usuario_id = u.id
              LEFT JOIN clientes c ON e.cliente_id = c.id
              WHERE e.id = ? AND e.excluido = 0";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $event_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $event = $result->fetch_assoc();
        $stmt->close();
        echo json_encode(['success' => true, 'event' => $event]);
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
    }
}

function criarAgendamentoTreinamento($conn, $event_id, $data_evento, $usuario_id) {
    try {
        // Verificar se é um evento de treinamento com cliente
        if ($data_evento['tipo'] !== 'tarefa' || empty($data_evento['cliente_id'])) {
            return null;
        }
        
        // CORREÇÃO: Não vincular automaticamente a um treinamento existente
        // Criar apenas o agendamento com cliente_id, sem treinamento_id
        $treinamento_id = null; // Deixar como null para não vincular a treinamento específico
        
        // Extrair data e hora do evento
        $data_inicio = new DateTime($data_evento['inicio']);
        $data_agendada = $data_inicio->format('Y-m-d H:i:s'); // Incluir hora completa
        
        // Calcular duração em horas
        $horas = '01:00:00'; // Padrão de 1 hora
        if (!empty($data_evento['fim'])) {
            $data_fim = new DateTime($data_evento['fim']);
            $diferenca = $data_inicio->diff($data_fim);
            $horas_calculadas = $diferenca->h + ($diferenca->i / 60);
            $horas = sprintf("%02d:%02d:00", floor($horas_calculadas), ($horas_calculadas - floor($horas_calculadas)) * 60);
        }
        
        // Criar observação mais limpa (não mais usada para vinculação)
        $observacao = "Sincronizado com agenda principal";
        if (!empty($data_evento['titulo'])) {
            $observacao .= " - " . $data_evento['titulo'];
        }
        if (!empty($data_evento['descricao'])) {
            $observacao .= " | " . $data_evento['descricao'];
        }
        
        // Inserir na tabela treinamentos_agendamentos APENAS com cliente_id (sem treinamento_id)
        $query_agendamento = "INSERT INTO treinamentos_agendamentos 
                             (treinamento_id, cliente_id, usuario_id, data_agendada, horas, observacao, status, agenda_evento_id, sincronizado_automaticamente) 
                             VALUES (?, ?, ?, ?, ?, ?, 'agendado', ?, TRUE)";
        $stmt_agendamento = $conn->prepare($query_agendamento);
        $stmt_agendamento->bind_param("iiisssi", $treinamento_id, $data_evento['cliente_id'], $data_evento['usuario_id'], $data_agendada, $horas, $observacao, $event_id);
        
        if ($stmt_agendamento->execute()) {
            $agendamento_id = $stmt_agendamento->insert_id;
            $stmt_agendamento->close();
            
            // Atualizar evento da agenda com a vinculação reversa
            $query_update_evento = "UPDATE agenda_eventos SET treinamento_agendamento_id = ? WHERE id = ?";
            $stmt_update = $conn->prepare($query_update_evento);
            $stmt_update->bind_param("ii", $agendamento_id, $event_id);
            $stmt_update->execute();
            $stmt_update->close();
            
            return $agendamento_id;
        }
        $stmt_agendamento->close();
        
        return null;
    } catch (Exception $e) {
        // Log do erro, mas não interrompe o fluxo principal
        error_log("Erro ao criar agendamento de treinamento: " . $e->getMessage());
        return null;
    }
}

function atualizarAgendamentoTreinamento($conn, $event_id, $data_evento, $current_event) {
    try {
        // Verificar se é um evento de treinamento com cliente
        if ($data_evento['tipo'] !== 'tarefa' || empty($data_evento['cliente_id'])) {
            return false;
        }
        
        // Buscar agendamento vinculado diretamente pelo ID do evento
        $query_buscar = "SELECT ta.id, ta.treinamento_id, ta.data_agendada, ta.horas 
                         FROM treinamentos_agendamentos ta
                         WHERE ta.agenda_evento_id = ? AND ta.sincronizado_automaticamente = TRUE";
        
        $stmt_buscar = $conn->prepare($query_buscar);
        $stmt_buscar->bind_param("i", $event_id);
        $stmt_buscar->execute();
        $result_buscar = $stmt_buscar->get_result();
        
        if ($result_buscar->num_rows === 0) {
            $stmt_buscar->close();
            // Se não existe agendamento vinculado, criar um novo
            return criarAgendamentoTreinamento($conn, $event_id, $data_evento, $_SESSION['usuario_id']);
        }
        
        $agendamento = $result_buscar->fetch_assoc();
        $stmt_buscar->close();
        
        // Calcular novos dados do evento
        $data_inicio_nova = new DateTime($data_evento['inicio']);
        $nova_data_agendada = $data_inicio_nova->format('Y-m-d H:i:s');
        
        // Calcular nova duração
        $novas_horas = '01:00:00'; // Padrão
        if (!empty($data_evento['fim'])) {
            $data_fim_nova = new DateTime($data_evento['fim']);
            $diferenca = $data_inicio_nova->diff($data_fim_nova);
            $horas_calculadas = $diferenca->h + ($diferenca->i / 60);
            $novas_horas = sprintf("%02d:%02d:00", floor($horas_calculadas), ($horas_calculadas - floor($horas_calculadas)) * 60);
        }
        
        // Atualizar observação
        $nova_observacao = "Sincronizado com agenda principal";
        if (!empty($data_evento['titulo'])) {
            $nova_observacao .= " - " . $data_evento['titulo'];
        }
        if (!empty($data_evento['descricao'])) {
            $nova_observacao .= " | " . $data_evento['descricao'];
        }
        $nova_observacao .= " (Atualizado em " . date('d/m/Y H:i') . ")";
        
        // Atualizar o agendamento
        $query_atualizar = "UPDATE treinamentos_agendamentos 
                           SET data_agendada = ?, horas = ?, observacao = ?, usuario_id = ?
                           WHERE id = ?";
        
        $stmt_atualizar = $conn->prepare($query_atualizar);
        $stmt_atualizar->bind_param("sssii", $nova_data_agendada, $novas_horas, $nova_observacao, $data_evento['usuario_id'], $agendamento['id']);
        $resultado = $stmt_atualizar->execute();
        $stmt_atualizar->close();
        
        return $resultado;
        
    } catch (Exception $e) {
        // Log do erro, mas não interrompe o fluxo principal
        error_log("Erro ao atualizar agendamento de treinamento: " . $e->getMessage());
        return false;
    }
}

function excluirVinculacaoTreinamento($conn, $event_id) {
    try {
        // Buscar agendamento vinculado ao evento
        $query_buscar = "SELECT ta.id 
                         FROM treinamentos_agendamentos ta
                         WHERE ta.agenda_evento_id = ? AND ta.sincronizado_automaticamente = TRUE";
        
        $stmt_buscar = $conn->prepare($query_buscar);
        $stmt_buscar->bind_param("i", $event_id);
        $stmt_buscar->execute();
        $result_buscar = $stmt_buscar->get_result();
        
        if ($result_buscar->num_rows > 0) {
            $agendamento = $result_buscar->fetch_assoc();
            $agendamento_id = $agendamento['id'];
            $stmt_buscar->close();
            
            // EXCLUIR o agendamento de treinamento vinculado automaticamente
            $query_excluir_agendamento = "DELETE FROM treinamentos_agendamentos 
                                         WHERE id = ? AND sincronizado_automaticamente = TRUE";
            $stmt_excluir_agendamento = $conn->prepare($query_excluir_agendamento);
            $stmt_excluir_agendamento->bind_param("i", $agendamento_id);
            $stmt_excluir_agendamento->execute();
            $stmt_excluir_agendamento->close();
            
            // Limpar treinamento_agendamento_id do evento (caso ainda exista)
            $query_limpar_evento = "UPDATE agenda_eventos 
                                   SET treinamento_agendamento_id = NULL 
                                   WHERE id = ?";
            $stmt_limpar_evento = $conn->prepare($query_limpar_evento);
            $stmt_limpar_evento->bind_param("i", $event_id);
            $stmt_limpar_evento->execute();
            $stmt_limpar_evento->close();
            
            return true;
        } else {
            $stmt_buscar->close();
            
            // Também verificar vinculação reversa através do treinamento_agendamento_id
            $query_buscar_reversa = "SELECT ae.treinamento_agendamento_id 
                                    FROM agenda_eventos ae
                                    WHERE ae.id = ? AND ae.treinamento_agendamento_id IS NOT NULL";
            
            $stmt_buscar_reversa = $conn->prepare($query_buscar_reversa);
            $stmt_buscar_reversa->bind_param("i", $event_id);
            $stmt_buscar_reversa->execute();
            $result_buscar_reversa = $stmt_buscar_reversa->get_result();
            
            if ($result_buscar_reversa->num_rows > 0) {
                $evento = $result_buscar_reversa->fetch_assoc();
                $agendamento_id = $evento['treinamento_agendamento_id'];
                $stmt_buscar_reversa->close();
                
                // Verificar se o agendamento foi criado automaticamente
                $query_verificar = "SELECT sincronizado_automaticamente FROM treinamentos_agendamentos WHERE id = ?";
                $stmt_verificar = $conn->prepare($query_verificar);
                $stmt_verificar->bind_param("i", $agendamento_id);
                $stmt_verificar->execute();
                $result_verificar = $stmt_verificar->get_result();
                
                if ($result_verificar->num_rows > 0) {
                    $agendamento_data = $result_verificar->fetch_assoc();
                    $stmt_verificar->close();
                    
                    if ($agendamento_data['sincronizado_automaticamente']) {
                        // EXCLUIR o agendamento de treinamento vinculado automaticamente
                        $query_excluir_agendamento = "DELETE FROM treinamentos_agendamentos 
                                                     WHERE id = ? AND sincronizado_automaticamente = TRUE";
                        $stmt_excluir_agendamento = $conn->prepare($query_excluir_agendamento);
                        $stmt_excluir_agendamento->bind_param("i", $agendamento_id);
                        $stmt_excluir_agendamento->execute();
                        $stmt_excluir_agendamento->close();
                    }
                } else {
                    $stmt_verificar->close();
                }
                
                return true;
            } else {
                $stmt_buscar_reversa->close();
                return false; // Não havia vinculação
            }
        }
        
    } catch (Exception $e) {
        error_log("Erro ao excluir vinculação de treinamento: " . $e->getMessage());
        return false;
    }
}

function setTrainingStatus($conn, $user_id) {
    if (!isset($_POST['event_id']) || !isset($_POST['status']) || !isset($_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Dados incompletos']);
        return;
    }
    
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        echo json_encode(['success' => false, 'message' => 'Token CSRF inválido']);
        return;
    }
    
    $event_id = (int)$_POST['event_id'];
    $status = $_POST['status'] === 'null' ? null : (int)$_POST['status'];
    $motivo_cancelamento = $_POST['motivo_cancelamento'] ?? null;
    
    // Verificar se o evento existe e é do tipo tarefa (treinamento)
    $check_query = "SELECT concluido, tipo, inicio FROM agenda_eventos WHERE id = ? AND excluido = 0";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bind_param("i", $event_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Evento não encontrado']);
        $check_stmt->close();
        return;
    }
    
    $event_data = $check_result->fetch_assoc();
    if ($event_data['tipo'] !== 'tarefa') {
        echo json_encode(['success' => false, 'message' => 'Este evento não é um treinamento']);
        $check_stmt->close();
        return;
    }
    $check_stmt->close();
    
    // Verificação de data retroativa - apenas admins podem alterar status de treinamentos em datas passadas
    if (isDataRetroativa($event_data['inicio']) && !isAdmin($conn, $user_id)) {
        echo json_encode(['success' => false, 'message' => 'Apenas administradores podem alterar o status de treinamentos em datas retroativas']);
        return;
    }
    
    // Definir mensagem baseada no status e motivo
    if ($status === null) {
        $message = 'Status do treinamento removido';
        $action_text = 'removeu o status';
    } elseif ($status == 1) {
        $message = 'Treinamento marcado como concluído';
        $action_text = 'marcou como concluído';
    } elseif ($status == 2) {
        if ($motivo_cancelamento) {
            $message = "Treinamento marcado como não concluído: {$motivo_cancelamento}";
            $action_text = "marcou como não concluído ({$motivo_cancelamento})";
        } else {
            $message = 'Treinamento marcado como não concluído';
            $action_text = 'marcou como não concluído';
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Status inválido']);
        return;
    }
    
    // Atualizar o status do evento
    if ($status === null) {
        $update_query = "UPDATE agenda_eventos SET concluido = NULL WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("i", $event_id);
    } else {
        $update_query = "UPDATE agenda_eventos SET concluido = ? WHERE id = ?";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bind_param("ii", $status, $event_id);
    }
    
    if ($update_stmt->execute()) {
        // SINCRONIZAÇÃO COM TREINAMENTOS_AGENDAMENTOS
        if ($status == 1) {
            // Quando marcado como concluído (1) -> status 'realizado'
            $sync_query = "UPDATE treinamentos_agendamentos ta 
                          INNER JOIN agenda_eventos ae ON ae.treinamento_agendamento_id = ta.id 
                          SET ta.status = 'realizado', ta.data_conclusao = NOW(), ta.motivo_cancelamento = NULL 
                          WHERE ae.id = ?";
            $sync_stmt = $conn->prepare($sync_query);
            $sync_stmt->bind_param("i", $event_id);
            $sync_stmt->execute();
            $sync_stmt->close();
            
            // Também verificar vinculação reversa através do agenda_evento_id
            $sync_query2 = "UPDATE treinamentos_agendamentos 
                           SET status = 'realizado', data_conclusao = NOW(), motivo_cancelamento = NULL 
                           WHERE agenda_evento_id = ?";
            $sync_stmt2 = $conn->prepare($sync_query2);
            $sync_stmt2->bind_param("i", $event_id);
            $sync_stmt2->execute();
            $sync_stmt2->close();
        } elseif ($status == 2) {
            // Quando marcado como não concluído (2) -> status 'cancelado' com motivo
            if ($motivo_cancelamento) {
                $sync_query = "UPDATE treinamentos_agendamentos ta 
                              INNER JOIN agenda_eventos ae ON ae.treinamento_agendamento_id = ta.id 
                              SET ta.status = 'cancelado', ta.data_conclusao = NULL, ta.motivo_cancelamento = ? 
                              WHERE ae.id = ?";
                $sync_stmt = $conn->prepare($sync_query);
                $sync_stmt->bind_param("si", $motivo_cancelamento, $event_id);
                $sync_stmt->execute();
                $sync_stmt->close();
                
                // Também verificar vinculação reversa através do agenda_evento_id
                $sync_query2 = "UPDATE treinamentos_agendamentos 
                               SET status = 'cancelado', data_conclusao = NULL, motivo_cancelamento = ? 
                               WHERE agenda_evento_id = ?";
                $sync_stmt2 = $conn->prepare($sync_query2);
                $sync_stmt2->bind_param("si", $motivo_cancelamento, $event_id);
                $sync_stmt2->execute();
                $sync_stmt2->close();
            } else {
                $sync_query = "UPDATE treinamentos_agendamentos ta 
                              INNER JOIN agenda_eventos ae ON ae.treinamento_agendamento_id = ta.id 
                              SET ta.status = 'cancelado', ta.data_conclusao = NULL, ta.motivo_cancelamento = NULL 
                              WHERE ae.id = ?";
                $sync_stmt = $conn->prepare($sync_query);
                $sync_stmt->bind_param("i", $event_id);
                $sync_stmt->execute();
                $sync_stmt->close();
                
                // Também verificar vinculação reversa através do agenda_evento_id
                $sync_query2 = "UPDATE treinamentos_agendamentos 
                               SET status = 'cancelado', data_conclusao = NULL, motivo_cancelamento = NULL 
                               WHERE agenda_evento_id = ?";
                $sync_stmt2 = $conn->prepare($sync_query2);
                $sync_stmt2->bind_param("i", $event_id);
                $sync_stmt2->execute();
                $sync_stmt2->close();
            }
        } elseif ($status === null) {
            // Quando status é removido (null) -> status 'agendado'
            $sync_query = "UPDATE treinamentos_agendamentos ta 
                          INNER JOIN agenda_eventos ae ON ae.treinamento_agendamento_id = ta.id 
                          SET ta.status = 'agendado', ta.data_conclusao = NULL, ta.motivo_cancelamento = NULL 
                          WHERE ae.id = ?";
            $sync_stmt = $conn->prepare($sync_query);
            $sync_stmt->bind_param("i", $event_id);
            $sync_stmt->execute();
            $sync_stmt->close();
            
            // Também verificar vinculação reversa através do agenda_evento_id
            $sync_query2 = "UPDATE treinamentos_agendamentos 
                           SET status = 'agendado', data_conclusao = NULL, motivo_cancelamento = NULL 
                           WHERE agenda_evento_id = ?";
            $sync_stmt2 = $conn->prepare($sync_query2);
            $sync_stmt2->bind_param("i", $event_id);
            $sync_stmt2->execute();
            $sync_stmt2->close();
        }
        
        // Registrar no histórico
        registerHistory($conn, $event_id, 'status_alterado', $action_text, $user_id);
        
        // Limpar cache para forçar atualização em tempo real
        clearAgendaCache();
        
        echo json_encode(['success' => true, 'message' => $message]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Erro ao atualizar status do treinamento']);
    }
    
    $update_stmt->close();
}