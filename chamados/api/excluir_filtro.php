<?php
require_once '../../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$filtro_id = (int)$_POST['filtro_id'];
$usuario_id = $_SESSION['usuario_id'];

// Verifica se o filtro pertence ao usuário antes de excluir
$query = "SELECT id FROM usuario_filtros WHERE id = ? AND usuario_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("ii", $filtro_id, $usuario_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Filtro não encontrado ou não pertence ao usuário']);
    exit();
}

// Exclui o filtro
$query = "DELETE FROM usuario_filtros WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $filtro_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Filtro excluído com sucesso']);
} else {
    echo json_encode(['success' => false, 'message' => 'Erro ao excluir filtro']);
}