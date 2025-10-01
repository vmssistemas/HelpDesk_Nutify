<?php
require_once 'includes/header.php';
require_once 'includes/azure_blob.php';

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$anexo_id = (int)$_GET['id'];
$anexo = getAnexoById($anexo_id);

if (!$anexo) {
    header("Location: index.php");
    exit();
}

// Verificação de permissão (implemente conforme sua necessidade)

// Se estiver no Azure Blob Storage
if (!empty($anexo['blob_url'])) {
    $ext = strtolower(pathinfo($anexo['nome_arquivo'], PATHINFO_EXTENSION));
    
    // Configuração específica para XML
    if ($ext === 'xml') {
        // Limpa qualquer buffer de saída
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Headers específicos para XML
        header('Content-Description: File Transfer');
        header('Content-Type: text/xml; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($anexo['nome_arquivo']) . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        
        // Obtém o conteúdo diretamente do Azure
        $content = file_get_contents($anexo['blob_url']);
        
        // Verifica se é um XML válido
        if ($content !== false) {
            // Força o encoding UTF-8 se necessário
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8');
            }
            
            // Adiciona header de tamanho
            header('Content-Length: ' . strlen($content));
            
            // Saída direta do conteúdo
            echo $content;
            exit;
        }
    } else {
        // Para outros tipos de arquivo, redireciona diretamente para o blob
        header("Location: " . $anexo['blob_url']);
        exit();
    }
}

// Se chegou aqui, houve algum erro
header("Location: visualizar.php?id=" . $anexo['chamado_id'] . "&error=download");
exit();
?>