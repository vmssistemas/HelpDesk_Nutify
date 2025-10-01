<?php
require_once '../../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$response = ['success' => false, 'message' => 'Ação inválida'];

switch ($action) {
    case 'update_status':
        $id = (int)$_POST['id'];
        $status_id = (int)$_POST['status_id'];
        
        $query = "UPDATE chamados SET status_id = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $status_id, $id);
        
        if ($stmt->execute()) {
            if ($status_id == 5) {
                $conn->query("UPDATE chamados SET data_encerramento = NOW() WHERE id = $id");
            }
            $response = ['success' => true];
        } else {
            $response = ['success' => false, 'message' => 'Erro ao atualizar status'];
        }
        break;
case 'create_sprint':
    $nome = trim($_POST['nome']);
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $chamado_id = (int)$_POST['chamado_id'];
    $vincular = (int)$_POST['vincular'];
    
    try {
        $conn->begin_transaction();
        
        // Primeiro encerra todas as sprints ativas
        $conn->query("UPDATE chamados_sprints SET ativa = 0 WHERE ativa = 1");
        
        // Depois cria a nova sprint ativa
        $query = "INSERT INTO chamados_sprints (nome, data_inicio, data_fim, ativa) VALUES (?, ?, ?, 1)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $nome, $data_inicio, $data_fim);
        
        if ($stmt->execute()) {
            $sprint_id = $conn->insert_id;
            
            // Vincular ao chamado se solicitado
            if ($vincular) {
                $query = "UPDATE chamados SET sprint_id = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ii", $sprint_id, $chamado_id);
                $stmt->execute();
            }
            
            $conn->commit();
            $_SESSION['success'] = "Sprint criada com sucesso!" . ($vincular ? " E vinculada ao chamado." : "");
            $response = ['success' => true];
        } else {
            $conn->rollback();
            $response = ['success' => false, 'message' => 'Erro ao criar sprint'];
        }
    } catch (Exception $e) {
        $conn->rollback();
        $response = ['success' => false, 'message' => 'Erro: ' . $e->getMessage()];
    }
    break;
case 'create_release':
    $nome = trim($_POST['nome']);
    $descricao = trim($_POST['descricao']);
    $data_planejada = $_POST['data_planejada'] ?: null;
    $status = $_POST['status'];
    $cor = $_POST['cor'];
    $chamado_id = (int)$_POST['chamado_id'];
    $vincular = (int)$_POST['vincular'];
    
    // Criar a release
    $query = "INSERT INTO chamados_releases (nome, descricao, data_planejada, status, cor) VALUES (?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssss", $nome, $descricao, $data_planejada, $status, $cor);
    
    if ($stmt->execute()) {
        $release_id = $conn->insert_id;
        
        // Vincular ao chamado se solicitado
        if ($vincular) {
            $query = "UPDATE chamados SET release_id = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $release_id, $chamado_id);
            $stmt->execute();
        }
        
        $_SESSION['success'] = "Release criada com sucesso!" . ($vincular ? " E vinculada ao chamado." : "");
        $response = ['success' => true];
    } else {
        $response = ['success' => false, 'message' => 'Erro ao criar release'];
    }
    break;
        
    case 'mudar_status':
        $id = (int)$_GET['id'];
        $status_id = (int)$_GET['status_id'];
        
        $query = "UPDATE chamados SET status_id = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $status_id, $id);
        
        if ($stmt->execute()) {
            if ($status_id == 5) {
                $conn->query("UPDATE chamados SET data_encerramento = NOW() WHERE id = $id");
            }
            $_SESSION['success'] = "Status atualizado com sucesso!";
            header("Location: ../visualizar.php?id=$id");
            exit();
        } else {
            $response = ['success' => false, 'message' => 'Erro ao atualizar status'];
        }
        break;
        
    case 'atribuir_release':
        $chamado_id = (int)$_POST['chamado_id'];
        $release_id = !empty($_POST['release_id']) ? (int)$_POST['release_id'] : null;
        
        $query = "UPDATE chamados SET release_id = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $release_id, $chamado_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Release atribuída com sucesso!";
            $response = ['success' => true];
            header("Location: ../visualizar.php?id=$chamado_id");
            exit();
        } else {
            $response = ['success' => false, 'message' => 'Erro ao atribuir release'];
        }
        break;
    
        case 'definir_responsavel':
            $chamado_id = (int)$_POST['chamado_id'];
            $responsavel_id = !empty($_POST['responsavel_id']) ? (int)$_POST['responsavel_id'] : null;
            
            $query = "UPDATE chamados SET responsavel_id = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $responsavel_id, $chamado_id);
            
            if ($stmt->execute()) {
                $_SESSION['success'] = "Responsável definido com sucesso!";
                header("Location: ../visualizar.php?id=$chamado_id");
                exit();
            } else {
                $response = ['success' => false, 'message' => 'Erro ao definir responsável'];
            }
            break;

        case 'definir_previsao_liberacao':
            $chamado_id = (int)$_POST['chamado_id'];
            $previsao_liberacao = !empty($_POST['previsao_liberacao']) ? $_POST['previsao_liberacao'] : null;
            
            $query = "UPDATE chamados SET previsao_liberacao = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("si", $previsao_liberacao, $chamado_id);
            
            if ($stmt->execute()) {
                if ($previsao_liberacao) {
                    $_SESSION['success'] = "Previsão de liberação definida com sucesso!";
                } else {
                    $_SESSION['success'] = "Previsão de liberação removida com sucesso!";
                }
                header("Location: ../visualizar.php?id=$chamado_id");
                exit();
            } else {
                $response = ['success' => false, 'message' => 'Erro ao definir previsão de liberação'];
            }
            break;


            case 'update_pontos':
    $id = (int)$_POST['id'];
    $pontos = !empty($_POST['pontos']) ? (int)$_POST['pontos'] : null;
    
    $query = "UPDATE chamados SET pontos_historia = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $pontos, $id);
    
    if ($stmt->execute()) {
        $response = ['success' => true];
    } else {
        $response = ['success' => false, 'message' => 'Erro ao atualizar pontos'];
    }
    break;
        
    case 'registrar_tempo':
        $id = (int)$_POST['id'];
        $horas = (float)$_POST['horas'];
        
        $query = "UPDATE chamados SET tempo_gasto = tempo_gasto + ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("di", $horas, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Tempo registrado com sucesso!";
            header("Location: ../visualizar.php?id=$id");
            exit();
        } else {
            $response = ['success' => false, 'message' => 'Erro ao registrar tempo'];
        }
        break;
        
case 'atribuir_sprint':
    $chamado_id = (int)$_POST['chamado_id'];
    $sprint_id = !empty($_POST['sprint_id']) ? (int)$_POST['sprint_id'] : null;
    
    // Removida a atualização automática do status
    $query = "UPDATE chamados SET sprint_id = ? WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $sprint_id, $chamado_id);
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Sprint atribuída com sucesso!";
        $response = ['success' => true];
        header("Location: ../visualizar.php?id=$chamado_id");
        exit();
    } else {
        $response = ['success' => false, 'message' => 'Erro ao atribuir sprint'];
    }
    break;
        
  case 'bulk_update':
    $selectedCards = explode(',', $_POST['selected_cards']);
    
    try {
        $conn->begin_transaction();
        
        foreach ($selectedCards as $cardId) {
            $id = (int)$cardId;
            
            // Monta a query dinamicamente baseada nos campos enviados
            $updates = [];
            $params = [];
            $types = '';
            
            if (!empty($_POST['new_status'])) {
                $updates[] = 'status_id = ?';
                $params[] = (int)$_POST['new_status'];
                $types .= 'i';
                
                if ($_POST['new_status'] == 5) {
                    $updates[] = 'data_encerramento = NOW()';
                }
            }
            
            if (isset($_POST['sprint_id']) && $_POST['sprint_id'] !== '') {
                if ($_POST['sprint_id'] === 'remove') {
                    $updates[] = 'sprint_id = NULL';
                } else {
                    $updates[] = 'sprint_id = ?';
                    $params[] = (int)$_POST['sprint_id'];
                    $types .= 'i';
                }
            }
            
            if (isset($_POST['release_id']) && $_POST['release_id'] !== '') {
                if ($_POST['release_id'] === 'remove') {
                    $updates[] = 'release_id = NULL';
                } else {
                    $updates[] = 'release_id = ?';
                    $params[] = (int)$_POST['release_id'];
                    $types .= 'i';
                }
            }
            
            if (isset($_POST['responsavel_id']) && $_POST['responsavel_id'] !== '') {
                if ($_POST['responsavel_id'] === 'remove') {
                    $updates[] = 'responsavel_id = NULL';
                } else {
                    $updates[] = 'responsavel_id = ?';
                    $params[] = (int)$_POST['responsavel_id'];
                    $types .= 'i';
                }
            }
            
            if (!empty($_POST['prioridade_id'])) {
                $updates[] = 'prioridade_id = ?';
                $params[] = (int)$_POST['prioridade_id'];
                $types .= 'i';
            }
            
            if (!empty($_POST['pontos_historia']) || $_POST['pontos_historia'] === '0') {
                $updates[] = 'pontos_historia = ?';
                $params[] = $_POST['pontos_historia'] ? (int)$_POST['pontos_historia'] : null;
                $types .= 'i';
            }
            
            if (!empty($updates)) {
                $query = "UPDATE chamados SET " . implode(', ', $updates) . " WHERE id = ?";
                $params[] = $id;
                $types .= 'i';
                
                $stmt = $conn->prepare($query);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();
                $stmt->close();
            }
        }
        
        $conn->commit();
        $response = ['success' => true, 'message' => 'Chamados atualizados com sucesso'];
    } catch (Exception $e) {
        $conn->rollback();
        $response = ['success' => false, 'message' => 'Erro ao atualizar chamados: ' . $e->getMessage()];
    }
    break;
    
    default:
        $response = ['success' => false, 'message' => 'Ação não reconhecida'];
}

echo json_encode($response);
?>