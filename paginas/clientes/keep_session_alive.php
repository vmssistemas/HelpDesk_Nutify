<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Sessão expirada']);
    exit();
}

// Atualiza o timestamp da última atividade
$_SESSION['last_activity'] = time();

// Retorna sucesso
echo json_encode(['status' => 'success', 'message' => 'Sessão renovada']);
?>