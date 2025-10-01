<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated'])) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    
    try {
        // Verifica se o contato está vinculado a algum cliente
        $query_verifica = "SELECT COUNT(*) as total FROM cliente_contato WHERE contato_id = ?";
        $stmt_verifica = $conn->prepare($query_verifica);
        $stmt_verifica->bind_param("i", $id);
        $stmt_verifica->execute();
        $result = $stmt_verifica->get_result();
        $row = $result->fetch_assoc();
        
        if ($row['total'] > 0) {
            $_SESSION['mensagem'] = [
                'tipo' => 'erro',
                'texto' => 'Este contato está vinculado a um ou mais clientes e não pode ser excluído.'
            ];
            header("Location: contatos.php");
            exit();
        }
        
        // Exclui o contato
        $query_excluir = "DELETE FROM contatos WHERE id = ?";
        $stmt_excluir = $conn->prepare($query_excluir);
        $stmt_excluir->bind_param("i", $id);
        
        if ($stmt_excluir->execute()) {
            $_SESSION['mensagem'] = [
                'tipo' => 'sucesso',
                'texto' => 'Contato excluído com sucesso!'
            ];
        } else {
            $_SESSION['mensagem'] = [
                'tipo' => 'erro',
                'texto' => 'Erro ao excluir contato: ' . $conn->error
            ];
        }
    } catch (Exception $e) {
        $_SESSION['mensagem'] = [
            'tipo' => 'erro',
            'texto' => 'Erro: ' . $e->getMessage()
        ];
    }
    
    header("Location: contatos.php");
    exit();
} else {
    $_SESSION['mensagem'] = [
        'tipo' => 'erro',
        'texto' => 'ID não fornecido'
    ];
    header("Location: contatos.php");
    exit();
}
?>