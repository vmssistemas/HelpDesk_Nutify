<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

$id = $_GET['id'];

// Exclui o atendimento pelo ID
$query = "DELETE FROM registros_atendimento WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    header("Location: atendimento.php");
    exit();
} else {
    echo "Erro ao excluir atendimento.";
}
?>