<?php
require_once '../../config/db.php';
require_once '../includes/functions_treinamentos.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

// Pega o ID do usuário logado da sessão que foi definida no header.php
$usuario_id = $_SESSION['usuario_id'] ?? null;

// Verifica se temos um usuário autenticado
if (!$usuario_id) {
    // Tenta obter o ID do usuário de forma alternativa
    $email = $_SESSION['email'] ?? null;
    if ($email) {
        $query = "SELECT id FROM usuarios WHERE email = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            $usuario_id = $user['id'];
            $_SESSION['usuario_id'] = $usuario_id; // Armazena para próximas requisições
        }
    }
}

if (!$usuario_id) {
    echo json_encode(['success' => false, 'message' => 'Usuário não identificado']);
    exit();
}

$action = $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Ação inválida'];

switch ($action) {
    case 'update':
        $comment_id = (int)$_POST['comment_id'];
        $comentario = trim($_POST['comentario']);
        $treinamento_id = (int)$_POST['treinamento_id'];
        
        // Verifica se o comentário pertence ao usuário
        $stmt = $conn->prepare("SELECT usuario_id FROM treinamentos_comentarios WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response = ['success' => false, 'message' => 'Comentário não encontrado'];
            break;
        }
        
        $comment = $result->fetch_assoc();
        if ($comment['usuario_id'] != $usuario_id) {
            $response = ['success' => false, 'message' => 'Você só pode editar seus próprios comentários'];
            break;
        }
        
        // Atualiza o comentário
        $query = "UPDATE treinamentos_comentarios SET comentario = ?, data_atualizacao = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("si", $comentario, $comment_id);
        
        if ($stmt->execute()) {
            header("Location: ../visualizar.php?id=$treinamento_id");
            exit();
        } else {
            $response = ['success' => false, 'message' => 'Erro ao atualizar comentário'];
        }
        break;
        
    case 'delete':
        $comment_id = (int)$_POST['comment_id'];
        $treinamento_id = (int)$_POST['treinamento_id'];
        
        // Verifica se o comentário pertence ao usuário
        $stmt = $conn->prepare("SELECT usuario_id FROM treinamentos_comentarios WHERE id = ?");
        $stmt->bind_param("i", $comment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $response = ['success' => false, 'message' => 'Comentário não encontrado'];
            break;
        }
        
        $comment = $result->fetch_assoc();
        if ($comment['usuario_id'] != $usuario_id) {
            $response = ['success' => false, 'message' => 'Você só pode excluir seus próprios comentários'];
            break;
        }
        
        // Exclui o comentário
        $query = "DELETE FROM treinamentos_comentarios WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $comment_id);
        
        if ($stmt->execute()) {
            $response = ['success' => true];
            header("Location: ../visualizar.php?id=$treinamento_id");
            exit();
        } else {
            $response = ['success' => false, 'message' => 'Erro ao excluir comentário'];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Ação não reconhecida'];
}

echo json_encode($response);
?>