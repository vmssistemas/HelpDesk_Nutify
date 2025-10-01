<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['error' => 'Não autenticado']);
    exit();
}

require_once '../../config/db.php';

header('Content-Type: application/json');

// Verifica se foi enviado o ID do cliente
if (!isset($_GET['cliente_id']) || empty($_GET['cliente_id'])) {
    echo json_encode(['error' => 'ID do cliente não informado']);
    exit();
}

$cliente_id = (int)$_GET['cliente_id'];

try {
    // Consulta para verificar se o cliente tem chamados com status "aplicar no cliente" (id 11)
    $query = "SELECT c.id, c.titulo
              FROM chamados c 
              WHERE (c.cliente_id = ? OR EXISTS (
                  SELECT 1 FROM chamados_clientes cc 
                  WHERE cc.chamado_id = c.id AND cc.cliente_id = ?
              )) 
              AND c.status_id = 11
              ORDER BY c.id DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $cliente_id, $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $chamados = $result->fetch_all(MYSQLI_ASSOC);
    
    $tem_pendencias = count($chamados) > 0;
    $total_pendencias = count($chamados);
    
    // Criar lista de chamados com informações estruturadas
    $chamados_estruturados = [];
    $chamados_texto = [];
    
    foreach ($chamados as $chamado) {
        $chamados_estruturados[] = [
            'id' => $chamado['id'],
            'titulo' => $chamado['titulo']
        ];
        $chamados_texto[] = "#{$chamado['id']} - {$chamado['titulo']}";
    }
    
    $response = [
        'tem_pendencias' => $tem_pendencias,
        'total_pendencias' => $total_pendencias,
        'chamados_pendentes' => implode('; ', $chamados_texto),
        'chamados_lista' => $chamados_estruturados,
        'mensagem' => $tem_pendencias ? 
            "⚠️ ATENÇÃO: Este cliente possui {$total_pendencias} chamado(s) com status 'Aplicar no Cliente' que precisa(m) ser atualizado(s)." : 
            'Cliente sem pendências de atualização.'
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erro interno do servidor: ' . $e->getMessage()]);
}

$conn->close();
?>