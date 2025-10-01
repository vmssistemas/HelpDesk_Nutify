<?php
require_once 'includes/header.php';


// No início do arquivo, adicione:
require_once 'includes/azure_blob.php';

$blobService = new AzureBlobService(); // Agora usa as configurações do config.php


// Adicione no início do arquivo, após as includes
ini_set('upload_max_filesize', '500M');
ini_set('post_max_size', '500M');

// Função para formatar o tamanho do arquivo
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$chamado_id = $_GET['id'];
$chamado = getChamadoById($chamado_id);

if (!$chamado) {
    header("Location: index.php");
    exit();
}

$status_list = getChamadosStatus();
$tipos_list = getChamadosTipos();
$prioridades_list = getChamadosPrioridades();
$sprints_list = getSprintsAtivas();
$equipe_list = getUsuariosEquipe();
$releases_list = getReleasesAtivas();

// Buscar menus e submenus
$menus = getMenusAtendimento();
$submenus = getSubmenusAtendimento();

// Busca clientes - Modifique a query para incluir o contrato
$query_clientes = "SELECT id, nome, contrato FROM clientes ORDER BY nome";
$result_clientes = $conn->query($query_clientes);
$clientes = $result_clientes->fetch_all(MYSQLI_ASSOC);

// Busca anexos existentes
$anexos = getAnexosChamado($chamado_id);

// Modifique a parte do processamento de exclusão de anexo:
if (isset($_GET['excluir_anexo'])) {
    $anexo_id = (int)$_GET['excluir_anexo'];
    
// Busca informações do anexo
    $query = "SELECT blob_id FROM chamados_anexos WHERE id = ? AND chamado_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $anexo_id, $chamado_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $anexo = $result->fetch_assoc();
    
    if ($anexo && !empty($anexo['blob_id'])) {
        // Remove o arquivo do Azure Blob Storage
        $blobService->deleteFile($anexo['blob_id']);
        
        // Remove do banco de dados
        $query = "DELETE FROM chamados_anexos WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $anexo_id);
        if ($stmt->execute()) {
            $_SESSION['success'] = "Anexo excluído com sucesso!";
        } else {
            $_SESSION['error'] = "Erro ao excluir anexo!";
        }
    }
    
    header("Location: editar.php?id=$chamado_id");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = $_POST['titulo'];
    $descricao = $_POST['descricao'];
    $status_id = $_POST['status_id'];
    $tipo_id = $_POST['tipo_id'];
    $prioridade_id = $_POST['prioridade_id'];
    $cliente_id = !empty($_POST['cliente_id']) ? $_POST['cliente_id'] : null;
    $responsavel_id = !empty($_POST['responsavel_id']) ? $_POST['responsavel_id'] : null;
    $sprint_id = !empty($_POST['sprint_id']) ? $_POST['sprint_id'] : null;
    $release_id = !empty($_POST['release_id']) ? $_POST['release_id'] : null;
    $menu_id = !empty($_POST['menu_id']) ? $_POST['menu_id'] : null;
    $submenu_id = !empty($_POST['submenu_id']) ? $_POST['submenu_id'] : null;
    
    $query = "UPDATE chamados SET 
        titulo = ?, descricao = ?, status_id = ?, tipo_id = ?, prioridade_id = ?, 
        cliente_id = ?, responsavel_id = ?, sprint_id = ?, release_id = ?,
        menu_id = ?, submenu_id = ?
        WHERE id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ssiiiiiiiiii", $titulo, $descricao, $status_id, $tipo_id, $prioridade_id, 
        $cliente_id, $responsavel_id, $sprint_id, $release_id, $menu_id, $submenu_id, $chamado_id);
    
if ($stmt->execute()) {
    // Processar novos anexos
    if (!empty($_FILES['anexos']['name'][0])) {
        $total_files = count($_FILES['anexos']['name']);
        $folderName = "Chamado_" . $chamado_id; // Pasta no Azure
        
        for ($i = 0; $i < $total_files; $i++) {
            if ($_FILES['anexos']['error'][$i] === UPLOAD_ERR_OK) {
                $nome_arquivo = $_FILES['anexos']['name'][$i];
                $tamanho = $_FILES['anexos']['size'][$i];
                $tmp_name = $_FILES['anexos']['tmp_name'][$i];
                $mime_type = $_FILES['anexos']['type'][$i];
                
                try {
                    // Upload para o Azure Blob Storage
                    $fileInfo = $blobService->uploadFile($tmp_name, $nome_arquivo, $mime_type, $folderName);
                    
                    $query = "INSERT INTO chamados_anexos (
                        chamado_id, 
                        usuario_id, 
                        nome_arquivo, 
                        caminho, 
                        tamanho,
                        blob_id,
                        blob_url,
                        blob_path
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param(
                        "iississs", 
                        $chamado_id, 
                        $_SESSION['usuario_id'], // Usar o ID do usuário da sessão
                        $nome_arquivo, 
                        $fileInfo['direct_link'], 
                        $tamanho,
                        $fileInfo['file_id'],
                        $fileInfo['web_link'],
                        $fileInfo['folder_id']
                    );
                    $stmt->execute();
                } catch (Exception $e) {
                    error_log("Erro ao enviar arquivo para Azure: " . $e->getMessage());
                    $_SESSION['error'] = "Erro ao enviar arquivo: " . $e->getMessage();
                }
            }
        }
    }
    
    header("Location: visualizar.php?id=$chamado_id");
    exit();
} else {
    $error = "Erro ao atualizar chamado: " . $conn->error;
}
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Chamado #<?php echo $chamado['id']; ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        .file-list {
            margin-top: 10px;
        }
        .file-item {
            display: flex;
            align-items: center;
            margin-bottom: 5px;
            padding: 8px;
            background: #f8f9fa;
            border-radius: 4px;
            border: 1px solid #dee2e6;
        }
        .file-item .material-icons {
            margin-right: 8px;
            color: #6c757d;
        }
        .file-actions {
            display: flex;
            gap: 5px;
            margin-left: auto;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        #fileDropArea {
            border: 2px dashed #dee2e6;
            border-radius: 5px;
            padding: 25px;
            text-align: center;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }
        #fileDropArea:hover {
            border-color: #adb5bd;
            background-color: #f8f9fa;
        }
        #fileDropArea.highlight {
            border-color: #0d6efd;
            background-color: #f0f7ff;
        }
        .file-info {
            flex-grow: 1;
        }
        .file-name {
            display: block;
            margin-bottom: 3px;
            word-break: break-all;
        }
        .file-size {
            font-size: 0.8em;
            color: #6c757d;
        }
        .existing-file {
            background-color: #e8f4fd;
        }
        /* Estilos para os selects de menu */
        .menu-row {
            display: flex;
            gap: 10px;
        }
        .menu-row .form-group {
            flex: 1;
        }
        @media (max-width: 768px) {
            .menu-row {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container mb-5">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Editar Chamado #<?php echo $chamado['id']; ?></h5>
                <a href="visualizar.php?id=<?php echo $chamado['id']; ?>" class="btn btn-sm btn-outline-primary">
                    <i class="material-icons me-1">visibility</i> Visualizar
                </a>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="formChamado">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label for="titulo" class="form-label">Título *</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" value="<?php echo htmlspecialchars($chamado['titulo']); ?>" required>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="tipo_id" class="form-label">Tipo *</label>
                            <select id="tipo_id" name="tipo_id" class="form-select" required>
                                <?php foreach ($tipos_list as $tipo): ?>
                                    <option value="<?php echo $tipo['id']; ?>" <?php echo $tipo['id'] == $chamado['tipo_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                 <!-- Descrição -->
<div class="mb-4">
    <h6 class="border-bottom pb-2">Descrição</h6>
    <textarea id="descricao" name="descricao" style="display:none;"><?= htmlspecialchars($chamado['descricao']) ?></textarea>
    <div id="editor"><?= $chamado['descricao'] ?></div>
</div>
                        
                        <!-- Menu e Submenu -->
                        <div class="col-12">
                            <div class="menu-row">
                                <div class="form-group">
                                    <label for="menu_id" class="form-label">Menu</label>
                                    <select id="menu_id" name="menu_id" class="form-select" onchange="carregarSubmenus()">
                                        <option value="">Selecione um menu</option>
                                        <?php foreach ($menus as $menu): ?>
                                            <option value="<?php echo $menu['id']; ?>" <?php echo $menu['id'] == $chamado['menu_id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($menu['nome']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="submenu_id" class="form-label">Submenu</label>
                                    <select id="submenu_id" name="submenu_id" class="form-select">
                                        <option value="">Selecione um submenu</option>
                                        <?php 
                                        // Carrega submenus do menu selecionado
                                        if ($chamado['menu_id']) {
                                            $submenus_filtrados = array_filter($submenus, function($submenu) use ($chamado) {
                                                return $submenu['menu_id'] == $chamado['menu_id'];
                                            });
                                            
                                            foreach ($submenus_filtrados as $submenu) {
                                                echo '<option value="' . $submenu['id'] . '" ' . ($submenu['id'] == $chamado['submenu_id'] ? 'selected' : '') . '>' . htmlspecialchars($submenu['nome']) . '</option>';
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                       <!-- Seção de Anexos -->
<div class="col-12">
    <label class="form-label">Anexos</label>
    
    <div id="fileDropArea">
        <i class="material-icons" style="font-size: 48px; color: #6c757d;">cloud_upload</i>
        <p class="mb-1">Arraste arquivos para aqui ou clique para selecionar</p>
    
    </div>
    
    <input class="form-control d-none" type="file" id="anexos" name="anexos[]" multiple>
    
    <div class="file-list" id="fileList">
        <?php if (empty($anexos)): ?>
            <div class="text-muted">Nenhum arquivo selecionado</div>
        <?php else: ?>
            <?php foreach ($anexos as $anexo): ?>
                <div class="file-item existing-file">
                    <i class="material-icons">insert_drive_file</i>
                    <div class="file-info">
                        <span class="file-name"><?php echo htmlspecialchars($anexo['nome_arquivo']); ?></span>
                        <span class="file-size"><?php echo formatFileSize($anexo['tamanho']); ?></span>
                    </div>
                    <div class="file-actions">
                        <a href="download.php?id=<?php echo $anexo['id']; ?>" class="btn btn-sm btn-outline-primary" title="Download">
                            <i class="material-icons">download</i>
                        </a>
                        <a href="editar.php?id=<?php echo $chamado_id; ?>&excluir_anexo=<?php echo $anexo['id']; ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Tem certeza que deseja excluir este anexo?')" title="Excluir">
                            <i class="material-icons">delete</i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
                        
                        <div class="col-md-3">
                            <label for="status_id" class="form-label">Status *</label>
                            <select id="status_id" name="status_id" class="form-select" required>
                                <?php foreach ($status_list as $status): ?>
                                    <option value="<?php echo $status['id']; ?>" <?php echo $status['id'] == $chamado['status_id'] ? 'selected' : ''; ?> style="color: <?php echo $status['cor']; ?>">
                                        <?php echo htmlspecialchars($status['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="prioridade_id" class="form-label">Prioridade *</label>
                            <select id="prioridade_id" name="prioridade_id" class="form-select" required>
                                <?php foreach ($prioridades_list as $prioridade): ?>
                                    <option value="<?php echo $prioridade['id']; ?>" <?php echo $prioridade['id'] == $chamado['prioridade_id'] ? 'selected' : ''; ?> style="color: <?php echo $prioridade['cor']; ?>">
                                        <?php echo htmlspecialchars($prioridade['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="cliente_id" class="form-label">Cliente</label>
                            <select id="cliente_id" name="cliente_id" class="form-select">
                                <option value="">Nenhum</option>
                                <?php foreach ($clientes as $cliente): ?>
                                    <option value="<?php echo $cliente['id']; ?>" <?php echo $cliente['id'] == $chamado['cliente_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-3">
                            <label for="responsavel_id" class="form-label">Responsável</label>
                            <select id="responsavel_id" name="responsavel_id" class="form-select">
                                <option value="">Ninguém</option>
                                <?php foreach ($equipe_list as $membro): ?>
                                    <option value="<?php echo $membro['id']; ?>" <?php echo $membro['id'] == $chamado['responsavel_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($membro['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="sprint_id" class="form-label">Sprint</label>
                            <select id="sprint_id" name="sprint_id" class="form-select">
                                <option value="">Nenhuma</option>
                                <?php foreach ($sprints_list as $sprint): ?>
                                    <option value="<?php echo $sprint['id']; ?>" <?php echo $sprint['id'] == $chamado['sprint_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sprint['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label for="release_id" class="form-label">Release</label>
                            <select id="release_id" name="release_id" class="form-select">
                                <option value="">Nenhuma</option>
                                <?php foreach ($releases_list as $release): ?>
                                    <option value="<?php echo $release['id']; ?>" <?php echo $release['id'] == $chamado['release_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($release['nome']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="material-icons me-1">save</i> Salvar Alterações
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Array para armazenar os arquivos selecionados
        let selectedFiles = [];
        // Na parte do JavaScript, modificar a constante MAX_SIZE
const MAX_SIZE = 500 * 1024 * 1024; // 500MB
        
        // Dados dos submenus
        const submenusData = <?php echo json_encode($submenus); ?>;
        
        // Função para carregar submenus
        function carregarSubmenus() {
            const menuId = document.getElementById('menu_id').value;
            const submenuSelect = document.getElementById('submenu_id');

            // Limpa e adiciona a opção padrão
            submenuSelect.innerHTML = '<option value="">Selecione um submenu</option>';

            if (menuId) {
                // Filtra submenus pelo menu selecionado
                const submenusFiltrados = submenusData.filter(submenu => submenu.menu_id == menuId);

                // Adiciona as opções
                submenusFiltrados.forEach(submenu => {
                    const option = document.createElement('option');
                    option.value = submenu.id;
                    option.textContent = submenu.nome;
                    submenuSelect.appendChild(option);
                });
            }
        }

        // Elementos DOM
        const fileInput = document.getElementById('anexos');
        const fileDropArea = document.getElementById('fileDropArea');
        const fileList = document.getElementById('fileList');
        const form = document.getElementById('formChamado');
        
        // Evento de clique na área de drop
        fileDropArea.addEventListener('click', () => fileInput.click());
        
        // Evento de mudança no input de arquivo
        fileInput.addEventListener('change', function() {
            handleFiles(this.files);
        });
        
        // Eventos para drag and drop
        ['dragover', 'dragenter'].forEach(event => {
            fileDropArea.addEventListener(event, (e) => {
                e.preventDefault();
                fileDropArea.classList.add('highlight');
            });
        });
        
        ['dragleave', 'dragend'].forEach(event => {
            fileDropArea.addEventListener(event, () => {
                fileDropArea.classList.remove('highlight');
            });
        });
        
        fileDropArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileDropArea.classList.remove('highlight');
            handleFiles(e.dataTransfer.files);
        });
        
        // Função para processar os arquivos
        function handleFiles(files) {
            for (let file of files) {
                // Verificar tamanho
                if (file.size > MAX_SIZE) {
                    alert(`Arquivo "${file.name}" excede o limite de 50MB`);
                    continue;
                }
                
                // Verificar se já foi adicionado
                const exists = selectedFiles.some(f => 
                    f.name === file.name && f.size === file.size
                );
                
                if (!exists) {
                    selectedFiles.push(file);
                }
            }
            updateFileList();
            updateFileInput();
        }
        
  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const units = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k)); // Corrigido aqui
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + units[i];
}
        
        // Atualiza a visualização da lista de arquivos
        function updateFileList() {
            // Manter os anexos existentes
            const existingFiles = document.querySelectorAll('.existing-file');
            const newFilesContainer = document.createElement('div');
            
            // Adicionar novos arquivos selecionados
            if (selectedFiles.length > 0) {
                selectedFiles.forEach((file, index) => {
                    const fileItem = document.createElement('div');
                    fileItem.className = 'file-item';
                    
                    fileItem.innerHTML = `
                        <i class="material-icons">insert_drive_file</i>
                        <div class="file-info">
                            <span class="file-name">${file.name}</span>
                            <span class="file-size">${formatFileSize(file.size)}</span>
                        </div>
                        <i class="material-icons remove-file" onclick="removeFile(${index})">delete</i>
                    `;
                    
                    newFilesContainer.appendChild(fileItem);
                });
            }
            
            // Limpar e reconstruir a lista (mantendo os existentes primeiro)
            fileList.innerHTML = '';
            existingFiles.forEach(file => fileList.appendChild(file.cloneNode(true)));
            fileList.appendChild(newFilesContainer);
            
            // Mostrar mensagem se não houver arquivos
            if (fileList.children.length === 0) {
                fileList.innerHTML = '<div class="text-muted">Nenhum arquivo selecionado</div>';
            }
        }
        
        // Função para remover um arquivo
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
            updateFileInput();
        }
        
        // Atualiza o input de arquivo com os arquivos selecionados
        function updateFileInput() {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
        }
    </script>
<script>
    // Inicializa o CKEditor 5 - Decoupled Document Editor
    DecoupledEditor
        .create(document.querySelector('#editor'), {
            toolbar: [
                'heading', '|',
                'bold', 'italic', 'underline', 'strikethrough', '|',
                'fontSize', 'fontColor', 'fontBackgroundColor', '|',
                'alignment', '|',
                'bulletedList', 'numberedList', '|',
                'outdent', 'indent', '|',
                'link', 'insertTable', 'blockQuote', '|',
                'undo', 'redo'
            ],
            fontSize: {
                options: [
                    9, 10, 11, 12, 13, 14, 15, 16, 18, 20, 22, 24, 26, 28, 30, 32, 34, 36
                ]
            },
            fontColor: {
                colors: [
                    {
                        color: 'hsl(0, 0%, 0%)',
                        label: 'Black'
                    },
                    {
                        color: 'hsl(0, 0%, 30%)',
                        label: 'Dim grey'
                    },
                    {
                        color: 'hsl(0, 0%, 60%)',
                        label: 'Grey'
                    },
                    {
                        color: 'hsl(0, 0%, 90%)',
                        label: 'Light grey'
                    },
                    {
                        color: 'hsl(0, 0%, 100%)',
                        label: 'White',
                        hasBorder: true
                    },
                    {
                        color: 'hsl(0, 75%, 60%)',
                        label: 'Red'
                    },
                    {
                        color: 'hsl(30, 75%, 60%)',
                        label: 'Orange'
                    },
                    {
                        color: 'hsl(60, 75%, 60%)',
                        label: 'Yellow'
                    },
                    {
                        color: 'hsl(90, 75%, 60%)',
                        label: 'Light green'
                    },
                    {
                        color: 'hsl(120, 75%, 60%)',
                        label: 'Green'
                    },
                    {
                        color: 'hsl(150, 75%, 60%)',
                        label: 'Aquamarine'
                    },
                    {
                        color: 'hsl(180, 75%, 60%)',
                        label: 'Turquoise'
                    },
                    {
                        color: 'hsl(210, 75%, 60%)',
                        label: 'Light blue'
                    },
                    {
                        color: 'hsl(240, 75%, 60%)',
                        label: 'Blue'
                    },
                    {
                        color: 'hsl(270, 75%, 60%)',
                        label: 'Purple'
                    }
                ]
            },
            fontBackgroundColor: {
                colors: [
                    {
                        color: 'hsl(0, 75%, 60%)',
                        label: 'Red'
                    },
                    {
                        color: 'hsl(30, 75%, 60%)',
                        label: 'Orange'
                    },
                    {
                        color: 'hsl(60, 75%, 60%)',
                        label: 'Yellow'
                    },
                    {
                        color: 'hsl(90, 75%, 60%)',
                        label: 'Light green'
                    },
                    {
                        color: 'hsl(120, 75%, 60%)',
                        label: 'Green'
                    },
                    {
                        color: 'hsl(150, 75%, 60%)',
                        label: 'Aquamarine'
                    },
                    {
                        color: 'hsl(180, 75%, 60%)',
                        label: 'Turquoise'
                    },
                    {
                        color: 'hsl(210, 75%, 60%)',
                        label: 'Light blue'
                    },
                    {
                        color: 'hsl(240, 75%, 60%)',
                        label: 'Blue'
                    },
                    {
                        color: 'hsl(270, 75%, 60%)',
                        label: 'Purple'
                    }
                ]
            },
            table: {
                contentToolbar: [
                    'tableColumn',
                    'tableRow',
                    'mergeTableCells'
                ]
            }
        })
        .then(editor => {
            // Adiciona a toolbar ao DOM
            const toolbarContainer = document.createElement('div');
            toolbarContainer.appendChild(editor.ui.view.toolbar.element);
            document.querySelector('#editor').parentNode.insertBefore(toolbarContainer, document.querySelector('#editor'));

            // Sincroniza o conteúdo com o textarea hidden
            editor.model.document.on('change:data', () => {
                document.querySelector('#descricao').value = editor.getData();
            });

            // Adiciona estilo à toolbar
            toolbarContainer.style.border = '1px solid #c4c4c4';
            toolbarContainer.style.borderBottom = 'none';
            toolbarContainer.style.borderRadius = '4px 4px 0 0';
            
            // Adiciona estilo ao editor e força altura mínima
            const editorElement = document.querySelector('#editor');
            editorElement.style.border = '1px solid #c4c4c4';
            editorElement.style.borderTop = 'none';
            editorElement.style.borderRadius = '0 0 4px 4px';
            editorElement.style.minHeight = '200px';
            editorElement.style.height = '200px';
            
            // Adiciona CSS personalizado para manter altura
            const style = document.createElement('style');
            style.textContent = `
                .ck-editor__editable {
                    min-height: 200px !important;
                    height: 200px !important;
                }
                .ck-editor__editable:focus {
                    min-height: 200px !important;
                    height: auto !important;
                    min-height: 200px !important;
                }
            `;
            document.head.appendChild(style);
        })
        .catch(error => {
            console.error('Erro ao inicializar o CKEditor:', error);
        });
</script>
<script>
// Mostra o loading quando o formulário for enviado
document.getElementById('formChamado').addEventListener('submit', function() {
    showLoading();
});
</script>
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>