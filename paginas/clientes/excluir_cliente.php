<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

$id = $_GET['id'];

// Exclui o cliente pelo ID
$query = "DELETE FROM clientes WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: clientes.php");
    exit();
} else {
    echo "Erro ao excluir cliente.";
}
?>