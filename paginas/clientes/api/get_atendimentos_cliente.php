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
    // Query para contar o total de atendimentos
    $queryCount = "SELECT COUNT(*) as total FROM registros_atendimento WHERE cliente_id = ?";
    $stmtCount = $conn->prepare($queryCount);
    $stmtCount->bind_param("i", $clienteId);
    $stmtCount->execute();
    $totalResult = $stmtCount->get_result()->fetch_assoc();
    $totalAtendimentos = $totalResult['total'];
    
    // Query para contar atendimentos recentes (últimos 30 dias)
    $queryRecentes = "SELECT COUNT(*) as recentes FROM registros_atendimento 
                      WHERE cliente_id = ? AND data_atendimento >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
    $stmtRecentes = $conn->prepare($queryRecentes);
    $stmtRecentes->bind_param("i", $clienteId);
    $stmtRecentes->execute();
    $recentesResult = $stmtRecentes->get_result()->fetch_assoc();
    $recentes = $recentesResult['recentes'];
    
    // Query para buscar os atendimentos com paginação
    $query = "SELECT ra.id, ra.data_atendimento, ra.descricao,
                     ma.nome as menu_nome, sma.nome as submenu_nome,
                     te.descricao as tipo_erro_descricao, u.email as usuario_email,
                     nd.nome as nivel_dificuldade_nome, nd.cor as nivel_dificuldade_cor
              FROM registros_atendimento ra
              JOIN menu_atendimento ma ON ra.menu_id = ma.id
              JOIN submenu_atendimento sma ON ra.submenu_id = sma.id
              JOIN tipo_erro te ON ra.tipo_erro_id = te.id
              JOIN usuarios u ON ra.usuario_id = u.id
              JOIN niveis_dificuldade nd ON ra.nivel_dificuldade_id = nd.id
              WHERE ra.cliente_id = ?
              ORDER BY ra.data_atendimento DESC
              LIMIT ? OFFSET ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $clienteId, $perPage, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    $atendimentos = $result->fetch_all(MYSQLI_ASSOC);
    
    // Limpar tags HTML das descrições
    foreach ($atendimentos as &$atendimento) {
        $atendimento['descricao'] = strip_tags($atendimento['descricao']);
    }
    
    echo json_encode([
        'total' => $totalAtendimentos,
        'recentes' => $recentes,
        'atendimentos' => $atendimentos
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>