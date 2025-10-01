<?php
session_start();

// Verifica se o usuário está autenticado e é admin
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true || 
    !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

if (!isset($_GET['id'])) {
    header("Location: administracao_cliente.php");
    exit();
}

$id = $_GET['id'];

// Exclui o módulo
$query = "DELETE FROM modulos WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: administracao_cliente.php");
    exit();
} else {
    die("Erro ao excluir módulo: " . $conn->error);
}
?>