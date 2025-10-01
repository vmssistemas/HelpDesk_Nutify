<?php
require_once '../../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

$release_id = (int)$_GET['id'];

// Busca os dados da release
$release = getReleaseById($release_id);
if (!$release) {
    echo json_encode(['success' => false, 'message' => 'Release não encontrada']);
    exit();
}

// Busca os chamados vinculados a essa release
$query = "SELECT c.id, c.titulo, t.nome as tipo_nome 
          FROM chamados c
          JOIN chamados_tipos t ON c.tipo_id = t.id
          WHERE c.release_id = ?
          ORDER BY t.nome, c.titulo";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $release_id);
$stmt->execute();
$result = $stmt->get_result();
$chamados = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode([
    'success' => true,
    'release' => $release,
    'chamados' => $chamados
]);