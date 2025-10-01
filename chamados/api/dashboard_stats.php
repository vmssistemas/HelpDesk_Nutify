<?php
require_once '../../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

try {
    // Busca estatísticas para o dashboard
    $query_stats = "SELECT 
        (SELECT COUNT(*) FROM chamados) as total,
        (SELECT COUNT(*) FROM chamados WHERE status_id = 5) as concluidos,
        (SELECT COUNT(*) FROM chamados WHERE status_id IN (3,4,7,8,9)) as em_andamento,
        (SELECT COUNT(*) FROM chamados WHERE status_id = 11) as aplicar_cliente";
    
    $stmt_stats = $conn->prepare($query_stats);
    $stmt_stats->execute();
    $result = $stmt_stats->get_result();
    
    if ($result->num_rows > 0) {
        $stats = $result->fetch_assoc();
        echo json_encode([
            'success' => true,
            'total' => $stats['total'],
            'concluidos' => $stats['concluidos'],
            'em_andamento' => $stats['em_andamento'],
            'aplicar_cliente' => $stats['aplicar_cliente']
        ]);
    } else {
        echo json_encode([
            'success' => true,
            'total' => 0,
            'concluidos' => 0,
            'em_andamento' => 0,
            'aplicar_cliente' => 0
        ]);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}