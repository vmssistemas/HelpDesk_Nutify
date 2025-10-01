<?php
require_once '../../config/db.php';
require_once '../includes/functions_treinamentos.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Ação inválida'];

switch ($action) {
case 'update_checklist_item':
    $treinamento_id = (int)$_POST['treinamento_id'];
    $item_id = (int)$_POST['item_id'];
    $concluido = (int)$_POST['concluido'];
    
    $query = "UPDATE treinamentos_checklist 
              SET concluido = ?
              WHERE treinamento_id = ? AND item_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $concluido, $treinamento_id, $item_id);
    
    if ($stmt->execute()) {
        $response = ['success' => true];
    } else {
        $response = ['success' => false, 'message' => 'Erro ao atualizar item'];
    }
    break;
        
    case 'concluir':
        $id = (int)$_GET['id'];
        $status_concluido = 3;
        
        $query = "UPDATE treinamentos SET status_id = ?, data_conclusao = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $status_concluido, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Treinamento marcado como concluído!";
            header("Location: ../visualizar.php?id=$id");
            exit();
        } else {
            $response = ['success' => false, 'message' => 'Erro ao concluir treinamento'];
        }
        break;
        
    case 'cancelar':
        $id = (int)$_GET['id'];
        $status_cancelado = 4;
        
        $query = "UPDATE treinamentos SET status_id = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $status_cancelado, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Treinamento cancelado!";
            header("Location: ../visualizar.php?id=$id");
            exit();
        } else {
            $response = ['success' => false, 'message' => 'Erro ao cancelar treinamento'];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Ação não reconhecida'];
        case 'excluir':
    $id = (int)$_GET['id'];
    
    try {
        $conn->begin_transaction();
        
        // Excluir comentários primeiro
        $conn->query("DELETE FROM treinamentos_comentarios WHERE treinamento_id = $id");
        
        // Excluir checklist
        $conn->query("DELETE FROM treinamentos_checklist WHERE treinamento_id = $id");
        
        // Excluir o treinamento
        $conn->query("DELETE FROM treinamentos WHERE id = $id");
        
        $conn->commit();
        
        $_SESSION['success'] = "Treinamento excluído com sucesso!";
        header("Location: ../index.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Erro ao excluir treinamento: " . $e->getMessage();
        header("Location: ../visualizar.php?id=$id");
        exit();
    }
    break;
    // treinamentos.php - Adicionar ao switch

case 'get_agendamento':
    $id = (int)$_GET['id'];
    $agendamento = getAgendamentoById($id);
    
    if ($agendamento) {
        // Formatar horas para HH:MM
        $horas = $agendamento['horas'];
        $horas_formatadas = substr($horas, 0, 5); // Extrai HH:MM do formato HH:MM:SS
        
        $response = [
            'success' => true,
            'agendamento' => [
                'id' => $agendamento['id'],
                'data_agendada' => $agendamento['data_agendada'],
                'horas' => $horas_formatadas,
                'observacao' => $agendamento['observacao'],
                'status' => $agendamento['status'],
                'usuario_id' => $agendamento['usuario_id'],
                'motivo_cancelamento' => $agendamento['motivo_cancelamento']
            ]
        ];
    } else {
        $response = ['success' => false, 'message' => 'Agendamento não encontrado'];
    }
    break;
}

echo json_encode($response);
?>