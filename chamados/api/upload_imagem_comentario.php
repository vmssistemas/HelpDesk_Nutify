<?php
require_once '../../config/db.php';
require_once '../includes/functions.php';
require_once '../includes/azure_blob.php';

header('Content-Type: application/json');

session_start();

if (!isset($_SESSION['authenticated'])) {
    echo json_encode(['success' => false, 'message' => 'Não autenticado']);
    exit();
}

$usuario_id = $_SESSION['usuario_id'] ?? null;

if (!$usuario_id) {
    echo json_encode(['success' => false, 'message' => 'Usuário não identificado']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit();
}

if (!isset($_FILES['upload']) || $_FILES['upload']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Erro no upload do arquivo']);
    exit();
}

$chamado_id = (int)($_POST['chamado_id'] ?? 0);
if ($chamado_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID do chamado inválido']);
    exit();
}

// Verificar se o chamado existe
$stmt = $conn->prepare("SELECT id FROM chamados WHERE id = ?");
$stmt->bind_param("i", $chamado_id);
$stmt->execute();
if ($stmt->get_result()->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Chamado não encontrado']);
    exit();
}

$file = $_FILES['upload'];
$nome_arquivo = $file['name'];
$tamanho = $file['size'];
$tmp_name = $file['tmp_name'];
$mime_type = $file['type'];

// Validar tipo de arquivo (apenas imagens)
$tipos_permitidos = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime_type, $tipos_permitidos)) {
    echo json_encode(['success' => false, 'message' => 'Tipo de arquivo não permitido. Apenas imagens são aceitas.']);
    exit();
}

// Validar tamanho (máximo 500MB)
if ($tamanho > 500 * 1024 * 1024) {
    echo json_encode(['success' => false, 'message' => 'Arquivo muito grande. Máximo 500MB.']);
    exit();
}

try {
    $blobService = new AzureBlobService();
    
    // Pasta específica para comentários dentro da pasta do chamado
    $folderName = "Chamado_" . $chamado_id . "/comentarios";
    
    // Upload para o Azure Blob Storage
    $fileInfo = $blobService->uploadFile($tmp_name, $nome_arquivo, $mime_type, $folderName);
    
    // Retornar URL da imagem para o CKEditor
    echo json_encode([
        'success' => true,
        'url' => $fileInfo['direct_link'],
        'file_info' => [
            'nome_arquivo' => $nome_arquivo,
            'caminho_azure' => $fileInfo['direct_link'],
            'blob_id' => $fileInfo['file_id'],
            'blob_url' => $fileInfo['web_link'],
            'blob_path' => $fileInfo['folder_id'],
            'tamanho' => $tamanho,
            'mime_type' => $mime_type
        ]
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Erro ao enviar arquivo para o Azure: ' . $e->getMessage()
    ]);
}
?>