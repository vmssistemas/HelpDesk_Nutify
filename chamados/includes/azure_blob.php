<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/config.php'; // Adicione esta linha

use MicrosoftAzure\Storage\Blob\BlobRestProxy;
use MicrosoftAzure\Storage\Common\Exceptions\ServiceException;

class AzureBlobService {
    private $blobClient;
    private $containerName;

    public function __construct($connectionString = null, $containerName = null) {
        // Usa as constantes definidas no config.php se nenhum parâmetro for fornecido
        $this->blobClient = BlobRestProxy::createBlobService($connectionString ?: AZURE_CONNECTION_STRING);
        $this->containerName = $containerName ?: AZURE_CONTAINER_NAME;
        
        // Verifica se o container existe, se não, cria
        $this->ensureContainerExists();
    }

    private function ensureContainerExists() {
        try {
            $this->blobClient->getContainerProperties($this->containerName);
        } catch (ServiceException $e) {
            if ($e->getCode() == 404) {
                // Container não existe, vamos criar
                $this->blobClient->createContainer($this->containerName);
                
                // Define permissões públicas apenas para blobs (não para o container)
                $this->blobClient->setContainerACL(
                    $this->containerName,
                    'blob', // Apenas os blobs terão acesso público
                    []
                );
            } else {
                throw $e;
            }
        }
    }

    /**
     * Faz upload de um arquivo para o Azure Blob Storage
     * 
     * @param string $filePath Caminho local do arquivo
     * @param string $fileName Nome do arquivo no Blob
     * @param string $mimeType Tipo MIME do arquivo
     * @param string|null $folderName Nome da pasta (opcional)
     * @return array Informações do arquivo enviado
     * @throws Exception Em caso de erro no upload
     */
public function uploadFile($filePath, $fileName, $mimeType, $folderName = null) {
    try {
        // Sanitiza o nome do arquivo
        $safeFileName = $this->sanitizeFileName($fileName);
        
        // Define o blob name (incluindo folder se especificado)
        $blobName = $folderName ? "$folderName/$safeFileName" : $safeFileName;
        
        // Cria as opções para o upload
        $options = new \MicrosoftAzure\Storage\Blob\Models\CreateBlockBlobOptions();
        $options->setContentType($mimeType);
        
        // Faz o upload do arquivo
        $content = fopen($filePath, "r");
        $this->blobClient->createBlockBlob(
            $this->containerName,
            $blobName,
            $content,
            $options
        );
        
        // Obtém a URL do blob
        $blobUrl = $this->blobClient->getBlobUrl($this->containerName, $blobName);
        
        return [
            'file_id' => $blobName, // Usamos o blob name como ID
            'web_link' => $blobUrl, // URL direta para o arquivo
            'direct_link' => $blobUrl, // Mesma URL para download direto
            'folder_id' => $folderName ? $folderName : ''
        ];
    } catch (Exception $e) {
        error_log("Erro no upload para Azure Blob: " . $e->getMessage());
        throw new Exception("Falha ao enviar arquivo para o Azure Blob Storage: " . $e->getMessage());
    }
}

    /**
     * Exclui um arquivo do Azure Blob Storage
     * 
     * @param string $blobName Nome do blob (incluindo path)
     * @return bool True se excluído com sucesso
     */
    public function deleteFile($blobName) {
        try {
            $this->blobClient->deleteBlob($this->containerName, $blobName);
            return true;
        } catch (Exception $e) {
            error_log("Erro ao excluir arquivo do Azure Blob: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Lista arquivos em uma pasta específica
     * 
     * @param string $folderPath Caminho da pasta
     * @return array Lista de arquivos
     */
    public function listFilesInFolder($folderPath) {
        try {
            $blobs = $this->blobClient->listBlobs($this->containerName, [
                'prefix' => $folderPath . '/'
            ]);
            
            $files = [];
            foreach ($blobs->getBlobs() as $blob) {
                $files[] = [
                    'name' => $blob->getName(),
                    'url' => $blob->getUrl(),
                    'properties' => $blob->getProperties()
                ];
            }
            
            return $files;
        } catch (Exception $e) {
            error_log("Erro ao listar arquivos da pasta: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Verifica se um arquivo existe no Blob Storage
     * 
     * @param string $blobName Nome do blob (incluindo path)
     * @return bool True se o arquivo existe
     */
    public function fileExists($blobName) {
        try {
            $this->blobClient->getBlobProperties($this->containerName, $blobName);
            return true;
        } catch (ServiceException $e) {
            if ($e->getCode() == 404) {
                return false;
            }
            throw $e;
        }
    }

    /**
     * Obtém a URL de um blob
     * 
     * @param string $blobName Nome do blob (incluindo path)
     * @return string URL do blob
     */
    public function getBlobUrl($blobName) {
        return $this->blobClient->getBlobUrl($this->containerName, $blobName);
    }
    
    /**
     * Sanitiza o nome do arquivo para evitar problemas
     */
    private function sanitizeFileName($filename) {
        // Remove caracteres especiais
        $filename = preg_replace('/[^a-zA-Z0-9\.\-_]/', '_', $filename);
        // Remove múltiplos underscores consecutivos
        $filename = preg_replace('/_+/', '_', $filename);
        // Limita o tamanho do nome do arquivo
        if (strlen($filename) > 255) {
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $name = substr($filename, 0, 255 - strlen($ext) - 1);
            $filename = $name . '.' . $ext;
        }
        return $filename;
    }
}