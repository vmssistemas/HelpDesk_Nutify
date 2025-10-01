<?php
require_once '../../config/db.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

try {
    // Busca a release mais atual com status 'lançado'
    $query = "SELECT nome, data_lancamento 
              FROM chamados_releases 
              WHERE status = 'lançado' 
              ORDER BY data_lancamento DESC, id DESC 
              LIMIT 1";
    
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        $release = $result->fetch_assoc();
        
        // Gera o link de download baseado no nome da release
        $download_link = "https://updateversao.blob.core.windows.net/updateversao/NUTIFY_PDV_" . $release['nome'] . ".exe";
        
        echo json_encode([
            'success' => true,
            'release' => [
                'nome' => $release['nome'],
                'data_lancamento' => $release['data_lancamento'],
                'download_link' => $download_link
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Nenhuma release lançada encontrada'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Erro ao buscar release: ' . $e->getMessage()
    ]);
}
?>