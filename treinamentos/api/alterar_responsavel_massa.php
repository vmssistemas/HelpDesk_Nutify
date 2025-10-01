<?php
require_once '../includes/functions_treinamentos.php';

header('Content-Type: application/json');

// Verificar se é uma requisição POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Verificar se o usuário está logado
if (!isset($_SESSION['id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Usuário não autenticado']);
    exit;
}

// Obter dados JSON da requisição
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Dados inválidos']);
    exit;
}

// Validar dados recebidos
if (!isset($input['treinamentos_ids']) || !isset($input['novo_responsavel_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Parâmetros obrigatórios não fornecidos']);
    exit;
}

$treinamentos_ids = $input['treinamentos_ids'];
$novo_responsavel_id = $input['novo_responsavel_id'];

// Validar se os IDs são arrays válidos
if (!is_array($treinamentos_ids) || empty($treinamentos_ids)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Lista de treinamentos inválida']);
    exit;
}

// Validar se o novo responsável é um ID válido
if (!is_numeric($novo_responsavel_id) || $novo_responsavel_id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID do responsável inválido']);
    exit;
}

try {
    // Verificar se o responsável existe
    $stmt_check = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
    $stmt_check->bind_param("i", $novo_responsavel_id);
    $stmt_check->execute();
    $result_check = $stmt_check->get_result();
    
    if ($result_check->num_rows === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Responsável não encontrado']);
        exit;
    }
    
    // Preparar a query de atualização
    $placeholders = implode(',', array_fill(0, count($treinamentos_ids), '?'));
    $query = "UPDATE treinamentos SET responsavel_id = ? WHERE id IN ($placeholders)";
    
    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Erro ao preparar a consulta: ' . $conn->error);
    }
    
    // Preparar os parâmetros para bind_param
    $types = 'i' . str_repeat('i', count($treinamentos_ids));
    $params = array_merge([$novo_responsavel_id], $treinamentos_ids);
    
    // Bind dos parâmetros
    $stmt->bind_param($types, ...$params);
    
    // Executar a atualização
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        
        if ($affected_rows > 0) {
            // Log da ação (opcional)
            $usuario_id = $_SESSION['id'];
            $treinamentos_str = implode(', ', $treinamentos_ids);
            
            // Inserir log de auditoria se existir tabela de logs
            $log_query = "INSERT INTO logs_sistema (usuario_id, acao, detalhes, data_hora) VALUES (?, ?, ?, NOW())";
            $stmt_log = $conn->prepare($log_query);
            
            if ($stmt_log) {
                $acao = "Alteração em massa de responsável";
                $detalhes = "Alterou responsável para ID $novo_responsavel_id nos treinamentos: $treinamentos_str";
                $stmt_log->bind_param("iss", $usuario_id, $acao, $detalhes);
                $stmt_log->execute();
            }
            
            echo json_encode([
                'success' => true, 
                'message' => "Responsável alterado com sucesso em $affected_rows treinamento(s)",
                'affected_rows' => $affected_rows
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Nenhum treinamento foi alterado. Verifique se os IDs são válidos.'
            ]);
        }
    } else {
        throw new Exception('Erro ao executar a atualização: ' . $stmt->error);
    }
    
} catch (Exception $e) {
    error_log("Erro na alteração em massa: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Erro interno do servidor: ' . $e->getMessage()
    ]);
}

// Fechar conexões
if (isset($stmt)) {
    $stmt->close();
}
if (isset($stmt_check)) {
    $stmt_check->close();
}
if (isset($stmt_log)) {
    $stmt_log->close();
}
?>