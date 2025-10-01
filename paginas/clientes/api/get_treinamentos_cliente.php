<?php
require_once __DIR__ . '/../../../config/db.php';
header('Content-Type: application/json');

// Verifica se o cliente_id foi fornecido
if (!isset($_GET['cliente_id'])) {
    echo json_encode(['error' => 'Cliente ID não fornecido']);
    exit;
}

$clienteId = (int)$_GET['cliente_id'];
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = isset($_GET['per_page']) ? (int)$_GET['per_page'] : 10;
$offset = ($page - 1) * $perPage;

try {
    // Query para contar o total de treinamentos
    $queryCount = "SELECT COUNT(*) as total FROM treinamentos WHERE cliente_id = ?";
    $stmtCount = $conn->prepare($queryCount);
    $stmtCount->bind_param("i", $clienteId);
    $stmtCount->execute();
    $totalResult = $stmtCount->get_result()->fetch_assoc();
    $totalTreinamentos = $totalResult['total'];
    
    // Query para contar treinamentos concluídos
    $queryConcluidos = "SELECT COUNT(*) as concluidos FROM treinamentos 
                        WHERE cliente_id = ? AND status_id = 3"; // ID 3 = Concluído
    $stmtConcluidos = $conn->prepare($queryConcluidos);
    $stmtConcluidos->bind_param("i", $clienteId);
    $stmtConcluidos->execute();
    $concluidosResult = $stmtConcluidos->get_result()->fetch_assoc();
    $concluidos = $concluidosResult['concluidos'];
    
    // Query para contar treinamentos agendados
    $queryAgendados = "SELECT COUNT(*) as agendados FROM treinamentos 
                       WHERE cliente_id = ? AND status_id = 1"; // ID 1 = Agendado
    $stmtAgendados = $conn->prepare($queryAgendados);
    $stmtAgendados->bind_param("i", $clienteId);
    $stmtAgendados->execute();
    $agendadosResult = $stmtAgendados->get_result()->fetch_assoc();
    $agendados = $agendadosResult['agendados'];
    
    // Query para buscar os treinamentos com paginação
    $query = "SELECT t.id, t.titulo, t.data_treinamento, 
                     s.id as status_id, s.nome as status_nome, s.cor as status_cor,
                     p.nome as plano_nome,
                     u.nome as responsavel_nome,
                     CASE 
                        WHEN t.tipo_id = 1 THEN 'Implantação'
                        WHEN t.tipo_id = 2 THEN 'Upgrade'
                        WHEN t.tipo_id = 3 THEN 'Adicional'
                        WHEN t.tipo_id = 4 THEN 'Módulo'
                        ELSE 'Outro'
                     END as tipo_nome
              FROM treinamentos t
              LEFT JOIN treinamentos_status s ON t.status_id = s.id
              LEFT JOIN planos p ON t.plano_id = p.id
              LEFT JOIN usuarios u ON t.responsavel_id = u.id
              WHERE t.cliente_id = ?
              ORDER BY t.data_treinamento DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $clienteId, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $treinamentos = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'total' => $totalTreinamentos,
        'concluidos' => $concluidos,
        'agendados' => $agendados,
        'treinamentos' => $treinamentos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>