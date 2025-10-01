<?php
require_once 'includes/header.php';

// Configurações de upload para 500MB
ini_set('upload_max_filesize', '500M');
ini_set('post_max_size', '500M');

// Verifica se é uma requisição para manter a sessão
if (isset($_GET['manter_sessao']) && $_GET['manter_sessao'] == 1) {
    // Apenas renova a sessão e retorna resposta simples
    echo 'OK';
    exit();
}

// No início do arquivo, adicione:
require_once 'includes/azure_blob.php';

$blobService = new AzureBlobService(); // Agora usa as configurações do config.php



$status_list = getChamadosStatus();
$tipos_list = getChamadosTipos();
$prioridades_list = getChamadosPrioridades();
$equipe_list = getUsuariosEquipe();

// Buscar menus e submenus
$menus = getMenusAtendimento();
$submenus = getSubmenusAtendimento();

// Busca clientes - Modifique a query para incluir o contrato
$query_clientes = "SELECT id, nome, contrato FROM clientes ORDER BY nome";
$result_clientes = $conn->query($query_clientes);
$clientes = $result_clientes->fetch_all(MYSQLI_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo = trim($_POST['titulo']);
    $descricao = trim($_POST['descricao'] ?? ''); // Agora pode ser vazio
    $tipo_id = (int)$_POST['tipo_id'];
    $prioridade_id = (int)$_POST['prioridade_id'];
    $cliente_id = !empty($_POST['cliente_id']) ? (int)$_POST['cliente_id'] : null;
    $responsavel_id = !empty($_POST['responsavel_id']) ? (int)$_POST['responsavel_id'] : null;
    $menu_id = !empty($_POST['menu_id']) ? (int)$_POST['menu_id'] : null;
    $submenu_id = !empty($_POST['submenu_id']) ? (int)$_POST['submenu_id'] : null;
    
    $query = "INSERT INTO chamados (
                titulo, descricao, status_id, tipo_id, prioridade_id, 
                cliente_id, usuario_id, responsavel_id, menu_id, submenu_id
              ) VALUES (?, ?, 1, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param(
        "ssiiiiiii",
        $titulo,
        $descricao,
        $tipo_id,
        $prioridade_id,
        $cliente_id,
        $usuario_id,
        $responsavel_id,
        $menu_id,
        $submenu_id
    );

 // Modifique a parte do processamento de anexos:
if ($stmt->execute()) {
    $chamado_id = $stmt->insert_id;
    
    // Processar múltiplos anexos
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
                        $usuario_id, 
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
    
    $_SESSION['success'] = "Chamado criado com sucesso!";
    header("Location: visualizar.php?id=$chamado_id");
    exit();
} else {
        $error = "Erro ao criar chamado: " . $conn->error;
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Novo Chamado</title>
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
        .remove-file {
            margin-left: auto;
            cursor: pointer;
            color: #dc3545;
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
        /* Estilo para o link do cliente */
label[for="cliente_id"] span.text-primary {
    transition: color 0.2s;
}
label[for="cliente_id"] span.text-primary:hover {
    color: #0b5ed7 !important;
    text-decoration: underline;
}
/* Ajuste para alinhar o select de clientes */
.cliente-select-container .form-select {
    margin-top: 1px; /* Ajuste este valor conforme necessário */
}

/* Ou se preferir ajustar todo o container */
.cliente-select-container {
    padding-top: 2px; /* Isso empurra todo o conteúdo para baixo */
}
/* Estilos para o select de cliente personalizado */
.custom-select {
    position: relative;
    width: 100%;
}

.custom-select input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
}

.custom-select .options {
    position: absolute;
    bottom: 100%;  /* Isso faz a lista abrir para cima */
    left: 0;
    right: 0;
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid #ddd;
    border-radius: 4px;
    background-color: white;
    z-index: 1000;
    display: none;
    box-shadow: 0 -2px 5px rgba(0,0,0,0.1); /* Sombra na parte inferior */
}

.custom-select .options div {
    padding: 8px;
    cursor: pointer;
    transition: background-color 0.3s ease;
    font-size: 14px;
}

.custom-select .options div:hover {
    background-color: #f1f1f1;
}

.custom-select .options div.selected {
    background-color: #023324;
    color: white;
}
    </style>
</head>
<body>
    <div class="container mb-5">

<!-- Modal de Cadastro Rápido de Cliente -->
<div class="modal fade" id="cadastroClienteModal" tabindex="-1" aria-labelledby="cadastroClienteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header text-white" style="background-color: #023324;">
                <h5 class="modal-title" id="cadastroClienteModalLabel">Cadastrar Cliente Rápido</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <iframe id="iframeCadastroCliente" src="cadastrar_cliente_rapido.php" style="width:100%; height:600px; border:none;"></iframe>
            </div>
        </div>
    </div>
</div>
        <div class="card">
            <div class="card-header text-white" style="background-color: #023324;">
                <h5 class="mb-0"><i class="material-icons me-2">add</i> Criar Novo Chamado</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>
                
                <form method="POST" enctype="multipart/form-data" id="formChamado">
                    <div class="row g-3">
                        <!-- Título -->
                        <div class="col-md-8">
                            <label for="titulo" class="form-label">Título *</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" required maxlength="100">
                        </div>
                        
                        <!-- Tipo -->
                        <div class="col-md-4">
                            <label for="tipo_id" class="form-label">Tipo *</label>
                            <select id="tipo_id" name="tipo_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($tipos_list as $tipo): ?>
                                    <option value="<?= $tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                  <!-- Descrição -->
<div class="col-12">
    <label for="descricao" class="form-label">Descrição *</label>
    <textarea class="form-control d-none" id="descricao" name="descricao"></textarea>
    <div id="editor"></div>
</div>
                        
                        <!-- Menu e Submenu -->
                        <div class="col-12">
                            <div class="menu-row">
                                <div class="form-group">
                                    <label for="menu_id" class="form-label">Menu</label>
                                    <select id="menu_id" name="menu_id" class="form-select" onchange="carregarSubmenus()">
                                        <option value="">Selecione um menu</option>
                                        <?php foreach ($menus as $menu): ?>
                                            <option value="<?= $menu['id'] ?>"><?= htmlspecialchars($menu['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label for="submenu_id" class="form-label">Submenu</label>
                                    <select id="submenu_id" name="submenu_id" class="form-select">
                                        <option value="">Selecione um submenu</option>
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
                                <div class="text-muted">Nenhum arquivo selecionado</div>
                            </div>
                        </div>
                        
                        <!-- Status -->
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <div class="form-control bg-light d-flex align-items-center">
                                <i class="material-icons me-2">view_column</i> Backlog
                            </div>
                            <input type="hidden" name="status_id" value="1">
                            <small class="text-muted">Status inicial do chamado</small>
                        </div>
                        
                        <!-- Prioridade -->
                        <div class="col-md-3">
                            <label for="prioridade_id" class="form-label">Prioridade *</label>
                            <select id="prioridade_id" name="prioridade_id" class="form-select" required>
                                <?php foreach ($prioridades_list as $prioridade): ?>
                                    <option value="<?= $prioridade['id'] ?>" <?= $prioridade['id'] == 2 ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($prioridade['nome']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
<!-- Cliente -->
<div class="col-md-3 cliente-select-container">
    <div class="d-flex align-items-center">
        <label for="cliente_id" class="form-label me-2" style="cursor: pointer;">
            <span class="text-primary" data-bs-toggle="modal" data-bs-target="#cadastroClienteModal">
                Cliente <i class="material-icons" style="font-size: 16px; vertical-align: middle;">add</i>
            </span>
        </label>
        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-bs-toggle="modal" data-bs-target="#cadastroClienteModal" title="Cadastrar novo cliente" style="display: none;">
            <i class="material-icons">add</i>
        </button>
    </div>
    
    <div class="custom-select">
        <input type="text" id="cliente_filter" placeholder="Digite para filtrar..." class="form-control">
        <div class="options" id="cliente_options">
            <div data-value="">Nenhum</div>
            <?php foreach ($clientes as $cliente): ?>
       <div data-value="<?= $cliente['id'] ?>">
    <?= htmlspecialchars(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome']) ?>
</div>

            <?php endforeach; ?>
        </div>
        <select id="cliente_id" name="cliente_id" class="form-select d-none">
            <option value="">Nenhum</option>
            <?php foreach ($clientes as $cliente): ?>
                <option value="<?= $cliente['id'] ?>">
                    <?= htmlspecialchars(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>
                        
                        <!-- Responsável -->
                        <div class="col-md-3">
                            <label for="responsavel_id" class="form-label">Responsável</label>
                            <select id="responsavel_id" name="responsavel_id" class="form-select">
                                <option value="">Ninguém</option>
                                <?php foreach ($equipe_list as $membro): ?>
                                    <option value="<?= $membro['id'] ?>"><?= htmlspecialchars($membro['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Botões -->
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="material-icons me-1">save</i> Criar Chamado
                            </button>
                            <a href="index.php" class="btn btn-outline-secondary">
                                <i class="material-icons me-1">cancel</i> Cancelar
                            </a>
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
        
        // Elementos DOM
        const fileInput = document.getElementById('anexos');
        const fileDropArea = document.getElementById('fileDropArea');
        const fileList = document.getElementById('fileList');
        const form = document.getElementById('formChamado');
        
        // Dados dos submenus
        const submenusData = <?php echo json_encode($submenus); ?>;
        
        // Função para carregar submenus
        function carregarSubmenus() {
            const menuId = document.getElementById('menu_id').value;
            const submenuSelect = document.getElementById('submenu_id');

            submenuSelect.innerHTML = '<option value="">Selecione um submenu</option>';

            if (menuId) {
                const submenusFiltrados = submenusData.filter(submenu => submenu.menu_id == menuId);

                submenusFiltrados.forEach(submenu => {
                    const option = document.createElement('option');
                    option.value = submenu.id;
                    option.textContent = submenu.nome;
                    submenuSelect.appendChild(option);
                });
            }
        }

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
        
        // Função para remover um arquivo
        function removeFile(index) {
            selectedFiles.splice(index, 1);
            updateFileList();
            updateFileInput();
        }
        
        // Atualiza a visualização da lista
        function updateFileList() {
            fileList.innerHTML = '';
            
            if (selectedFiles.length === 0) {
                fileList.innerHTML = '<div class="text-muted">Nenhum arquivo selecionado</div>';
                return;
            }
            
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
                
                fileList.appendChild(fileItem);
            });
        }
        
        // Atualiza o input de arquivo
        function updateFileInput() {
            const dataTransfer = new DataTransfer();
            selectedFiles.forEach(file => dataTransfer.items.add(file));
            fileInput.files = dataTransfer.files;
        }
        
        // Formata o tamanho do arquivo
        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const units = ['Bytes', 'KB', 'MB', 'GB'];
            const k = 1024;
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + units[i];
        }
// Manipula o cadastro de cliente rápido
window.addEventListener('message', function(e) {
    if (e.data.type === 'clienteCadastrado') {
        const cliente = e.data.cliente;
        
        // Atualiza o select original (hidden)
        const selectCliente = document.getElementById('cliente_id');
        const option = document.createElement('option');
        option.value = cliente.id;
        option.textContent = (cliente.contrato ? cliente.contrato + ' - ' : '') + cliente.nome;
        selectCliente.appendChild(option);
        selectCliente.value = cliente.id;
        
        // Atualiza o input de busca e options
        const inputCliente = document.getElementById('cliente_filter');
        const optionsContainer = document.getElementById('cliente_options');
        
        // Cria novo option na lista dinâmica
        const newOption = document.createElement('div');
        newOption.dataset.value = cliente.id;
        newOption.textContent = (cliente.contrato ? cliente.contrato + ' - ' : '') + cliente.nome;
        optionsContainer.appendChild(newOption);
        
        // Seta o valor no input
        inputCliente.value = newOption.textContent;
        
        // Fecha o modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('cadastroClienteModal'));
        modal.hide();
        
        showToast('Cliente cadastrado com sucesso e selecionado!', 'success');
    }
});

// Reseta o iframe quando o modal é fechado
document.getElementById('cadastroClienteModal').addEventListener('hidden.bs.modal', function() {
    document.getElementById('iframeCadastroCliente').src = 'cadastrar_cliente_rapido.php';
});
// Configura o filtro para o campo de cliente
function setupClienteFilter() {
    const input = document.getElementById('cliente_filter');
    const options = document.getElementById('cliente_options');
    const select = document.getElementById('cliente_id');

    input.addEventListener('input', function() {
        const filter = input.value.toUpperCase();
        const divs = options.getElementsByTagName('div');

        for (let i = 0; i < divs.length; i++) {
            const div = divs[i];
            const text = div.textContent.toUpperCase();
            if (text.indexOf(filter) > -1) {
                div.style.display = '';
            } else {
                div.style.display = 'none';
            }
        }

        options.style.display = 'block';
    });

    input.addEventListener('focus', function() {
        options.style.display = 'block';
    });

    input.addEventListener('blur', function() {
        setTimeout(() => {
            options.style.display = 'none';
        }, 200);
    });

    options.addEventListener('click', function(e) {
        if (e.target.tagName === 'DIV') {
            input.value = e.target.textContent;
            select.value = e.target.getAttribute('data-value');
            options.style.display = 'none';
        }
    });

    // Sincroniza o valor inicial se já houver um selecionado
    if (select.value) {
        const selectedOption = select.options[select.selectedIndex];
        input.value = selectedOption.textContent;
    }
}
function showToast(message, type = 'success') {
    const toastContainer = document.createElement('div');
    toastContainer.className = `toast align-items-center text-white bg-${type} border-0 position-fixed bottom-0 end-0 m-3`;
    toastContainer.style.zIndex = '1100';
    toastContainer.setAttribute('role', 'alert');
    toastContainer.setAttribute('aria-live', 'assertive');
    toastContainer.setAttribute('aria-atomic', 'true');
    
    toastContainer.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    document.body.appendChild(toastContainer);
    const toast = new bootstrap.Toast(toastContainer);
    toast.show();
    
    setTimeout(() => {
        toastContainer.remove();
    }, 5000);
}
// Inicializa quando o DOM estiver carregado
document.addEventListener('DOMContentLoaded', setupClienteFilter);
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
// Sistema simples para manter a sessão ativa
// Renova a cada 5 minutos (300 segundos)
setInterval(function() {
    // Faz uma requisição invisível para manter a sessão
    fetch('?manter_sessao=1', {
        cache: 'no-store',
        headers: {
            'Cache-Control': 'no-cache'
        }
    }).then(() => {
        console.log('Sessão renovada automaticamente');
    }).catch(() => {
        // Não faz nada em caso de erro
    });
}, 300000); // 5 minutos = 300000 milissegundos

// Exibir mensagens de sessão como notificações flutuantes
<?php if (isset($_SESSION['success'])): ?>
    showToast('<?php echo addslashes($_SESSION['success']); ?>', 'success');
    <?php unset($_SESSION['success']); ?>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    showToast('<?php echo addslashes($_SESSION['error']); ?>', 'danger');
    <?php unset($_SESSION['error']); ?>
<?php endif; ?>
</script>
    <?php require_once 'includes/footer.php'; ?>
</body>
</html>