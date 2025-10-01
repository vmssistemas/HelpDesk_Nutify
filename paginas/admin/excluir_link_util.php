<?php
session_start();

// Verifica se o usuário está autenticado e é admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || $_SESSION['is_admin'] != 1) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Verifica se o ID do link útil foi passado via GET
if (!isset($_GET['id'])) {
    header("Location: administracao.php");
    exit();
}

$id = intval($_GET['id']); // Converte o ID para inteiro

// Verifica se o link útil existe
$query = "SELECT id FROM links_uteis WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header("Location: administracao.php");
    exit();
}

// Exclui o link útil do banco de dados
$query = "DELETE FROM links_uteis WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

header("Location: administracao.php");
exit();
?>