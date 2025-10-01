<?php
require_once '../../config/db.php';
require_once '../includes/functions.php';

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
    $tipo_comentario = $_POST['tipo_comentario'] ?? 'geral';
    $chamado_id = (int)$_POST['chamado_id'];
    
    // Verifica se o comentário pertence ao usuário
    $stmt = $conn->prepare("SELECT usuario_id FROM chamados_comentarios WHERE id = ?");
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
    
    // Inicia transação para garantir consistência
    $conn->begin_transaction();
    
    try {
        // Atualiza o comentário
     $query = "UPDATE chamados_comentarios SET comentario = ?, tipo_comentario = ?, data_atualizacao = NOW() WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssi", $comentario, $tipo_comentario, $comment_id);
        
        if (!$stmt->execute()) {
            throw new Exception('Erro ao atualizar comentário');
        }
        
        // Processa novas imagens se houver
        if (isset($_POST['imagens_info']) && !empty($_POST['imagens_info'])) {
            $imagens_info = json_decode($_POST['imagens_info'], true);
            if ($imagens_info && is_array($imagens_info)) {
                // Remove imagens antigas do comentário
                $query_delete = "DELETE FROM chamados_comentarios_imagens WHERE comentario_id = ?";
                $stmt_delete = $conn->prepare($query_delete);
                $stmt_delete->bind_param("i", $comment_id);
                $stmt_delete->execute();
                
                // Adiciona novas imagens
                if (!salvarImagensComentario($comment_id, $imagens_info)) {
                    throw new Exception('Erro ao salvar novas imagens');
                }
            }
        }
        
        // Confirma a transação
        $conn->commit();
        
        header("Location: ../visualizar.php?id=$chamado_id");
        exit();
        
    } catch (Exception $e) {
        // Desfaz a transação em caso de erro
        $conn->rollback();
        $response = ['success' => false, 'message' => $e->getMessage()];
    }
    break;
        
    case 'delete':
        $comment_id = (int)$_POST['comment_id'];
        $chamado_id = (int)$_POST['chamado_id'];
        
        // Verifica se o comentário pertence ao usuário
        $stmt = $conn->prepare("SELECT usuario_id FROM chamados_comentarios WHERE id = ?");
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
        $query = "DELETE FROM chamados_comentarios WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $comment_id);
        
        if ($stmt->execute()) {
            $response = ['success' => true];
            header("Location: ../visualizar.php?id=$chamado_id");
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