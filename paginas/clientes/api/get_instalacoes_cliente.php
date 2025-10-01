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
    // Query para contar o total de instalações
    $queryCount = "SELECT COUNT(*) as total FROM instalacoes WHERE cliente_id = ?";
    $stmtCount = $conn->prepare($queryCount);
    $stmtCount->bind_param("i", $clienteId);
    $stmtCount->execute();
    $totalResult = $stmtCount->get_result()->fetch_assoc();
    $totalInstalacoes = $totalResult['total'];
    
    // Query para contar instalações concluídas
    $queryConcluidas = "SELECT COUNT(*) as concluidas FROM instalacoes 
                        WHERE cliente_id = ? AND status_id = 3";
    $stmtConcluidas = $conn->prepare($queryConcluidas);
    $stmtConcluidas->bind_param("i", $clienteId);
    $stmtConcluidas->execute();
    $concluidasResult = $stmtConcluidas->get_result()->fetch_assoc();
    $concluidas = $concluidasResult['concluidas'];
    
    // Query para contar instalações agendadas
    $queryAgendadas = "SELECT COUNT(*) as agendadas FROM instalacoes 
                       WHERE cliente_id = ? AND status_id = 1";
    $stmtAgendadas = $conn->prepare($queryAgendadas);
    $stmtAgendadas->bind_param("i", $clienteId);
    $stmtAgendadas->execute();
    $agendadasResult = $stmtAgendadas->get_result()->fetch_assoc();
    $agendadas = $agendadasResult['agendadas'];
    
    // Query para buscar as instalações com paginação
    $query = "SELECT i.id, i.titulo, i.tipo_id, i.data_instalacao, 
                     s.id as status_id, s.nome as status_nome, s.cor as status_cor,
                     p.nome as plano_nome, r.nome as responsavel_nome
              FROM instalacoes i
              LEFT JOIN instalacoes_status s ON i.status_id = s.id
              LEFT JOIN planos p ON i.plano_id = p.id
              LEFT JOIN usuarios r ON i.responsavel_id = r.id
              WHERE i.cliente_id = ?
              ORDER BY i.data_instalacao DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $clienteId, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $instalacoes = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'total' => $totalInstalacoes,
        'concluidas' => $concluidas,
        'agendadas' => $agendadas,
        'instalacoes' => $instalacoes
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>