<?php
require_once '../../config/db.php';
require_once '../includes/functions_instalacoes.php';

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
    $instalacao_id = (int)$_POST['instalacao_id'];
    $item_id = (int)$_POST['item_id'];
    $concluido = (int)$_POST['concluido'];
    
    $query = "UPDATE instalacoes_checklist 
              SET concluido = ?
              WHERE instalacao_id = ? AND item_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $concluido, $instalacao_id, $item_id);
    
    if ($stmt->execute()) {
        $response = ['success' => true];
    } else {
        $response = ['success' => false, 'message' => 'Erro ao atualizar item'];
    }
    break;
        
    case 'concluir':
        $id = (int)$_GET['id'];
        $status_concluido = 3;
        
        $query = "UPDATE instalacoes SET status_id = ?, data_conclusao = NOW() WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $status_concluido, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Instalação marcada como concluída!";
            header("Location: ../visualizar.php?id=$id");
            exit();
        } else {
            $response = ['success' => false, 'message' => 'Erro ao concluir instalação'];
        }
        break;
        
    case 'cancelar':
        $id = (int)$_GET['id'];
        $status_cancelado = 4;
        
        $query = "UPDATE instalacoes SET status_id = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $status_cancelado, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Instalação cancelada!";
            header("Location: ../visualizar.php?id=$id");
            exit();
        } else {
            $response = ['success' => false, 'message' => 'Erro ao cancelar instalação'];
        }
        break;
        
    default:
        $response = ['success' => false, 'message' => 'Ação não reconhecida'];
        case 'excluir':
    $id = (int)$_GET['id'];
    
    try {
        $conn->begin_transaction();
        
        // Excluir comentários primeiro
        $conn->query("DELETE FROM instalacoes_comentarios WHERE instalacao_id = $id");
        
        // Excluir checklist
        $conn->query("DELETE FROM instalacoes_checklist WHERE instalacao_id = $id");
        
        // Excluir a instalação
        $conn->query("DELETE FROM instalacoes WHERE id = $id");
        
        $conn->commit();
        
        $_SESSION['success'] = "Instalação excluída com sucesso!";
        header("Location: ../index.php");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Erro ao excluir instalação: " . $e->getMessage();
        header("Location: ../visualizar.php?id=$id");
        exit();
    }
    break;
    // instalacoes.php - Adicionar ao switch


    case 'preview_checklist':
    $tipo_id = (int)$_GET['tipo_id'];
    $response = ['success' => false, 'itens' => []];
    
    try {
        if ($tipo_id == 1 && isset($_GET['plano_id'])) { // Plano
            $plano_id = (int)$_GET['plano_id'];
            $query = "SELECT item, descricao, horas_padrao 
                      FROM instalacoes_itens_plano 
                      WHERE plano_id = ? AND ativo = 1 
                      ORDER BY ordem";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("i", $plano_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $response['itens'] = $result->fetch_all(MYSQLI_ASSOC);
            $response['success'] = true;
        } 
        elseif ($tipo_id == 2 && isset($_GET['origem']) && isset($_GET['destino'])) { // Upgrade
            $origem = (int)$_GET['origem'];
            $destino = (int)$_GET['destino'];
            $query = "SELECT item, descricao, horas_padrao 
                      FROM instalacoes_itens_upgrade 
                      WHERE origem_plano_id = ? AND destino_plano_id = ? AND ativo = 1 
                      ORDER BY ordem";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $origem, $destino);
            $stmt->execute();
            $result = $stmt->get_result();
            $response['itens'] = $result->fetch_all(MYSQLI_ASSOC);
            $response['success'] = true;
        } 
        elseif ($tipo_id == 4 && isset($_GET['modulo'])) { // Módulo
            $modulo = $_GET['modulo'];
            $query = "SELECT item, descricao, horas_padrao 
                      FROM instalacoes_itens_modulo 
                      WHERE modulo = ? AND ativo = 1 
                      ORDER BY ordem";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("s", $modulo);
            $stmt->execute();
            $result = $stmt->get_result();
            $response['itens'] = $result->fetch_all(MYSQLI_ASSOC);
            $response['success'] = true;
        }
    } catch (Exception $e) {
        $response['message'] = "Erro: " . $e->getMessage();
    }
    
    echo json_encode($response);
    break;

case 'get_agendamento':
    $id = (int)$_GET['id'];
    $agendamento = getAgendamentoById($id);
    
    if ($agendamento) {
        $response = [
            'success' => true,
            'agendamento' => [
                'id' => $agendamento['id'],
                'data_agendada' => $agendamento['data_agendada'],
                'horas' => $agendamento['horas'],
                'observacao' => $agendamento['observacao'],
                'status' => $agendamento['status']
            ]
        ];
    } else {
        $response = ['success' => false, 'message' => 'Agendamento não encontrado'];
    }
    break;
}

echo json_encode($response);
?>