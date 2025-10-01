<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'];
    $nome = $_POST['nome'];
    $telefone = $_POST['telefone'];
    $email = $_POST['email'] ?? null;
    $observacoes = $_POST['observacoes'] ?? null;

    // Remove formatação do telefone
    $telefone = preg_replace('/[^0-9]/', '', $telefone);

    // Validação do telefone (deve ter 11 dígitos)
    if (strlen($telefone) !== 11) {
        echo json_encode(['status' => 'error', 'message' => 'Telefone deve conter 11 dígitos.']);
        exit();
    }

    // Verifica se o telefone já existe em outro contato
    $query_verifica = "SELECT id FROM contatos WHERE telefone = ? AND id != ?";
    $stmt_verifica = $conn->prepare($query_verifica);
    $stmt_verifica->bind_param("si", $telefone, $id);
    $stmt_verifica->execute();
    $stmt_verifica->store_result();

    if ($stmt_verifica->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Já existe outro contato cadastrado com este telefone.']);
        exit();
    }

    // Atualiza o contato
    $query_atualizar = "UPDATE contatos SET nome = ?, telefone = ?, email = ?, observacoes = ? WHERE id = ?";
    $stmt_atualizar = $conn->prepare($query_atualizar);
    $stmt_atualizar->bind_param("ssssi", $nome, $telefone, $email, $observacoes, $id);

    if ($stmt_atualizar->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Contato atualizado com sucesso!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Erro ao atualizar contato: ' . $stmt_atualizar->error]);
    }
}
?>