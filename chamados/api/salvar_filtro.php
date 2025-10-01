<?php
require_once '../../config/db.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'];
$nome = trim($_POST['nome']);
$compartilhado = isset($_POST['compartilhado']) ? 1 : 0;
$padrao = isset($_POST['padrao']) ? 1 : 0;
$filtro_id = isset($_POST['filtro_id']) ? (int)$_POST['filtro_id'] : 0;

// Remove os campos do formulário que não são filtros
unset($_POST['nome']);
unset($_POST['compartilhado']);
unset($_POST['padrao']);
unset($_POST['filtro_id']);

// Serializa os parâmetros de filtro
$filtros = $_POST;

if (empty($nome)) {
    echo json_encode(['success' => false, 'message' => 'Nome do filtro é obrigatório']);
    exit();
}

try {
    $conn->begin_transaction();

    // Se for marcar como padrão, primeiro remove o padrão atual
    if ($padrao) {
        $query = "UPDATE usuario_filtros SET padrao = 0 WHERE usuario_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $usuario_id);
        $stmt->execute();
    }

    // Se tem ID, é uma atualização
    if ($filtro_id > 0) {
        // Verifica se o filtro pertence ao usuário
        $query = "SELECT usuario_id FROM usuario_filtros WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $filtro_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception('Filtro não encontrado');
        }
        
        $filtro = $result->fetch_assoc();
        if ($filtro['usuario_id'] != $usuario_id) {
            throw new Exception('Você não tem permissão para editar este filtro');
        }

        // Atualiza o filtro existente
        $query = "UPDATE usuario_filtros 
                 SET nome = ?, filtros = ?, compartilhado = ?, padrao = ?
                 WHERE id = ?";
        $stmt = $conn->prepare($query);
        $filtros_serializados = json_encode($filtros);
        $stmt->bind_param("ssiii", $nome, $filtros_serializados, $compartilhado, $padrao, $filtro_id);
    } else {
        // Cria um novo filtro
        $query = "INSERT INTO usuario_filtros (usuario_id, nome, filtros, compartilhado, padrao) 
                 VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $filtros_serializados = json_encode($filtros);
        $stmt->bind_param("issii", $usuario_id, $nome, $filtros_serializados, $compartilhado, $padrao);
    }

    if (!$stmt->execute()) {
        throw new Exception('Erro ao salvar filtro');
    }

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Filtro salvo com sucesso!']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}