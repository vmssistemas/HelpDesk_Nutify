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
    // Query para contar o total de chamados
    $queryCount = "SELECT COUNT(*) as total FROM chamados 
                   WHERE cliente_id = ? OR EXISTS (
                       SELECT 1 FROM chamados_clientes 
                       WHERE chamado_id = chamados.id AND cliente_id = ?
                   )";
    $stmtCount = $conn->prepare($queryCount);
    $stmtCount->bind_param("ii", $clienteId, $clienteId);
    $stmtCount->execute();
    $totalResult = $stmtCount->get_result()->fetch_assoc();
    $totalChamados = $totalResult['total'];
    
    // Query para contar chamados abertos (status não concluídos)
    $queryAbertos = "SELECT COUNT(*) as abertos FROM chamados 
                     WHERE (cliente_id = ? OR EXISTS (
                         SELECT 1 FROM chamados_clientes 
                         WHERE chamado_id = chamados.id AND cliente_id = ?
                     )) AND status_id NOT IN (5, 6)";
    $stmtAbertos = $conn->prepare($queryAbertos);
    $stmtAbertos->bind_param("ii", $clienteId, $clienteId);
    $stmtAbertos->execute();
    $abertosResult = $stmtAbertos->get_result()->fetch_assoc();
    $abertos = $abertosResult['abertos'];
    
    // Query para contar chamados resolvidos
    $queryResolvidos = "SELECT COUNT(*) as resolvidos FROM chamados 
                        WHERE (cliente_id = ? OR EXISTS (
                            SELECT 1 FROM chamados_clientes 
                            WHERE chamado_id = chamados.id AND cliente_id = ?
                        )) AND status_id IN (5)";
    $stmtResolvidos = $conn->prepare($queryResolvidos);
    $stmtResolvidos->bind_param("ii", $clienteId, $clienteId);
    $stmtResolvidos->execute();
    $resolvidosResult = $stmtResolvidos->get_result()->fetch_assoc();
    $resolvidos = $resolvidosResult['resolvidos'];
    
    // Query para buscar os chamados com paginação
    $query = "SELECT c.id, c.titulo, c.data_criacao, 
                     s.id as status_id, s.nome as status_nome, 
                     p.id as prioridade_id, p.nome as prioridade_nome
              FROM chamados c
              LEFT JOIN chamados_status s ON c.status_id = s.id
              LEFT JOIN chamados_prioridades p ON c.prioridade_id = p.id
              WHERE c.cliente_id = ? OR EXISTS (
                  SELECT 1 FROM chamados_clientes 
                  WHERE chamado_id = c.id AND cliente_id = ?
              )
              ORDER BY c.data_criacao DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiii", $clienteId, $clienteId, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $chamados = $result->fetch_all(MYSQLI_ASSOC);
    
    echo json_encode([
        'total' => $totalChamados,
        'abertos' => $abertos,
        'resolvidos' => $resolvidos,
        'chamados' => $chamados
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>