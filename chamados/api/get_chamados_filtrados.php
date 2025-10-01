<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$release_id = isset($_GET['release']) ? (int)$_GET['release'] : 0;

if (!$release_id) {
    echo json_encode([]);
    exit;
}

// Montar query baseada nos mesmos filtros do index.php
$query = "SELECT c.id, c.titulo, c.tipo_id 
          FROM chamados c
          WHERE c.release_id = ?
          ORDER BY c.tipo_id, c.id";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $release_id);
$stmt->execute();
$result = $stmt->get_result();

$chamados = $result->fetch_all(MYSQLI_ASSOC);

echo json_encode($chamados);