<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

$conteudo_id = $_GET['id'];

// Exclui o conteúdo
$query = "DELETE FROM conteudos WHERE id = $conteudo_id";
if ($conn->query($query)) {
    header("Location: administracao.php");
    exit();
} else {
    echo "Erro ao excluir conteúdo: " . $conn->error;
}
?>