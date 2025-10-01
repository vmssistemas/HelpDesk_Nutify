<?php
session_start();
require_once '../includes/functions_treinamentos.php';

header('Content-Type: application/json');

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

try {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Dados inválidos');
    }
    
    $treinamento_ids = $input['treinamento_ids'] ?? [];
    $responsavel_id = $input['responsavel_id'] ?? null;
    
    // Validações
    if (empty($treinamento_ids) || !is_array($treinamento_ids)) {
        throw new Exception('Nenhum treinamento selecionado');
    }
    
    if (empty($responsavel_id)) {
        throw new Exception('Responsável não informado');
    }
    
    // Verificar se o responsável existe
    $stmt = $conn->prepare("SELECT id FROM usuarios WHERE id = ?");
    $stmt->bind_param("i", $responsavel_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Responsável não encontrado');
    }
    
    // Preparar a query de atualização
    $placeholders = implode(',', array_fill(0, count($treinamento_ids), '?'));
    $query = "UPDATE treinamentos SET responsavel_id = ? WHERE id IN ($placeholders)";
    
    $stmt = $conn->prepare($query);
    
    // Preparar os parâmetros
    $types = 'i' . str_repeat('i', count($treinamento_ids));
    $params = array_merge([$responsavel_id], $treinamento_ids);
    
    $stmt->bind_param($types, ...$params);
    
    if ($stmt->execute()) {
        $affected_rows = $stmt->affected_rows;
        
        echo json_encode([
            'success' => true, 
            'message' => "$affected_rows treinamento(s) alterado(s) com sucesso",
            'affected_rows' => $affected_rows
        ]);
    } else {
        throw new Exception('Erro ao atualizar os treinamentos');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>