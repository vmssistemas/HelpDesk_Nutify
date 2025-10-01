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


// Se existem filtros na sessão, usa eles
$back_url = 'index.php';
if (!empty($_SESSION['last_filters'])) {
    $back_url .= '?' . $_SESSION['last_filters'];
    
    // Limpa parâmetros desnecessários que podem estar na sessão
    $back_url = preg_replace('/(&|\?)page=\d+/', '', $back_url);
}
if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$chamado_id = (int)$_GET['id'];
$chamado = getChamadoById($chamado_id);

if (!$chamado) {
    header("Location: index.php");
    exit();
}

$comentarios = getComentariosChamado($chamado_id);
$anexos = getAnexosChamado($chamado_id);
$sprints = getSprintsAtivas();
$releases_list = getReleasesAtivas();

$chamado_id = (int)$_GET['id'];
$chamado = getChamadoById($chamado_id);

if (!$chamado) {
    header("Location: index.php");
    exit();
}

// Registrar que o usuário atual visualizou este chamado
registrarVisualizacaoChamado($chamado_id, $usuario_id);

// Obter lista de usuários que visualizaram este chamado
$visualizacoes = getUsuariosQueVisualizaram($chamado_id);

// Buscar clientes adicionais vinculados
$clientes_adicionais = [];
$query = "SELECT c.id, c.nome, c.contrato 
          FROM chamados_clientes cc
          JOIN clientes c ON cc.cliente_id = c.id
          WHERE cc.chamado_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $chamado_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $clientes_adicionais[] = $row;
}

// Processar adição de cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['adicionar_cliente'])) {
    $cliente_id = (int)$_POST['cliente_id'];
    
    if ($cliente_id > 0) {
        // Verificar se já não está vinculado
        $query = "SELECT id FROM chamados_clientes WHERE chamado_id = ? AND cliente_id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $chamado_id, $cliente_id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            $query = "INSERT INTO chamados_clientes (chamado_id, cliente_id) VALUES (?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("ii", $chamado_id, $cliente_id);
            
            if ($stmt->execute()) {
                header("Location: visualizar.php?id=$chamado_id");
                exit();
            }
        }
    }
}

// Processar remoção de cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remover_cliente'])) {
    $cliente_id = (int)$_POST['cliente_id'];
    
    $query = "DELETE FROM chamados_clientes WHERE chamado_id = ? AND cliente_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $chamado_id, $cliente_id);
    
    if ($stmt->execute()) {
        header("Location: visualizar.php?id=$chamado_id");
        exit();
    }
}
// Buscar marcadores vinculados
$marcadores_vinculados = getMarcadoresChamado($chamado_id);

// Processar adição de marcador
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Adicionar marcador existente
    if (isset($_POST['adicionar_marcador']) && !empty($_POST['marcador_id'])) {
        $marcador_id = (int)$_POST['marcador_id'];
        if ($marcador_id > 0 && adicionarMarcadorChamado($chamado_id, $marcador_id, $usuario_id)) {
            header("Location: visualizar.php?id=$chamado_id");
            exit();
        }
    }
    
    // Criar novo marcador e vincular
    if (isset($_POST['criar_marcador']) && !empty($_POST['novo_marcador'])) {
        $nome_marcador = trim($_POST['novo_marcador']);
        $cor_marcador = $_POST['cor_marcador'] ?? '#6c757d';
        
        $novo_marcador_id = criarMarcador($nome_marcador, $cor_marcador);
        if ($novo_marcador_id && adicionarMarcadorChamado($chamado_id, $novo_marcador_id, $usuario_id)) {
            header("Location: visualizar.php?id=$chamado_id");
            exit();
        }
    }
    
    // Remover marcador
    if (isset($_POST['remover_marcador']) && !empty($_POST['marcador_id'])) {
        $marcador_id = (int)$_POST['marcador_id'];
        if (removerMarcadorChamado($chamado_id, $marcador_id)) {
            header("Location: visualizar.php?id=$chamado_id");
            exit();
        }
    }
}

// Buscar lista de marcadores disponíveis
$marcadores_disponiveis = getMarcadoresDisponiveis();

// Processar comentário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['comentario'])) {
    $comentario = trim($_POST['comentario']);
    $tipo_comentario = $_POST['tipo_comentario'] ?? 'geral';
    
    if (!empty($comentario)) {
        $query = "INSERT INTO chamados_comentarios (chamado_id, usuario_id, comentario, tipo_comentario) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iiss", $chamado_id, $usuario_id, $comentario, $tipo_comentario);
        
        if ($stmt->execute()) {
            $comentario_id = $conn->insert_id;
            
            // Processar imagens do comentário se houver
            if (isset($_POST['imagens_info']) && !empty($_POST['imagens_info'])) {
                $imagens_info = json_decode($_POST['imagens_info'], true);
                if ($imagens_info && is_array($imagens_info)) {
                    salvarImagensComentario($comentario_id, $imagens_info);
                }
            }
            
            header("Location: visualizar.php?id=$chamado_id");
            exit();
        }
    }
}

// Modifique a parte do processamento de anexos:
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['anexo'])) {
    if ($_FILES['anexo']['error'] === UPLOAD_ERR_OK) {
        $nome_arquivo = $_FILES['anexo']['name'];
        $tamanho = $_FILES['anexo']['size'];
        $tmp_name = $_FILES['anexo']['tmp_name'];
        $mime_type = $_FILES['anexo']['type'];
        $folderName = "Chamado_" . $chamado_id; // Pasta no Azure
        
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
            
            if ($stmt->execute()) {
                header("Location: visualizar.php?id=$chamado_id");
                exit();
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Erro ao enviar arquivo para o Azure Blob Storage: " . $e->getMessage();
            header("Location: visualizar.php?id=$chamado_id");
            exit();
        }
    }
}


// Buscar checklist de conversão se for do tipo Conversão
$checklist_conversao = [];
if ($chamado['tipo_id'] == 6) { // ID do tipo Conversão
    $checklist_conversao = getChamadoConversaoChecklist($chamado_id);
    
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_conversao_checklist'])) {
    if (isset($_POST['checklist']) && is_array($_POST['checklist'])) {
        foreach ($_POST['checklist'] as $item_id => $data) {
            $concluido = isset($data['concluido']) ? 1 : 0;
            $observacao = trim($data['observacao'] ?? '');
            
            $query = "UPDATE chamados_conversao_checklist SET 
                      concluido = ?, observacao = ?
                      WHERE id = ? AND chamado_id = ?";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("isii", $concluido, $observacao, $item_id, $chamado_id);
            $stmt->execute();
        }
        
        $_SESSION['success'] = "Checklist de conversão atualizado com sucesso!";
        header("Location: visualizar.php?id=$chamado_id");
        exit();
    }
}
    
    // Se não houver itens no checklist, criar os padrões
    if (empty($checklist_conversao)) {
        $itens_padrao = [
            "Produto (Com estoque)",
            "Produto (Estoque zerado)",
            "Clientes",
            "Fornecedores",
            "Contas a pagar",
            "Contas a receber",
            "Programa de pontos"
        ];
        
        foreach ($itens_padrao as $item) {
            $query = "INSERT INTO chamados_conversao_checklist (chamado_id, item) VALUES (?, ?)";
            $stmt = $conn->prepare($query);
            $stmt->bind_param("is", $chamado_id, $item);
            $stmt->execute();
        }
        
        // Recarregar o checklist
        $checklist_conversao = getChamadoConversaoChecklist($chamado_id);
    }
}






// Buscar lista de clientes para o select
$query_clientes = "SELECT id, nome, contrato FROM clientes ORDER BY nome";
$result_clientes = $conn->query($query_clientes);
$clientes_disponiveis = $result_clientes->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chamado #<?= $chamado['id'] ?></title>
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
    
        .cliente-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px;
            margin-bottom: 5px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        .cliente-info {
            flex-grow: 1;
        }
        .cliente-actions {
            margin-left: 10px;
        }
        .clientes-card {
            margin-top: 20px;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
        }
        .clientes-card .card-header {
            background-color: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
        }
        
        /* Estilos para imagens inline dos comentários */
        .comment-image-inline {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin: 5px;
        }
        
        .comment-image-inline:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .comment-header .badge {
    font-size: 0.8rem;
    padding: 0.35em 0.65em;
}
        
        /* Modal de zoom com fundo preto */
        .image-zoom-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            z-index: 9999;
            display: flex;
            justify-content: center;
            align-items: center;
        }
        
        .zoom-overlay {
            position: relative;
            max-width: 90%;
            max-height: 90%;
        }
        
        .zoom-close {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 30px;
            cursor: pointer;
            z-index: 10000;
        }
        
        .zoom-close:hover {
            color: #ccc;
        }
        
        #zoomedImage {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        /* Responsividade para imagens */
        @media (max-width: 768px) {
            .comment-image {
                width: 120px !important;
                height: 120px !important;
            }
        }
        
        @media (max-width: 576px) {
            .comment-image {
                width: 100px !important;
                height: 100px !important;
            }
        }
    </style>
</head>
<body>
    <div class="container mb-5">
        <div class="row">
            <div class="col-lg-8">
                <!-- Cabeçalho do Chamado -->
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Chamado #<?= $chamado['id'] ?></h5> 
                        
                        <div>
                        <a href="<?= $back_url ?>" class="btn btn-sm btn-outline-secondary me-2" title="Voltar para a lista com os filtros aplicados">
    <i class="material-icons me-1">arrow_back</i> Voltar
</a>
                            <a href="editar.php?id=<?= $chamado['id'] ?>" class="btn btn-sm btn-outline-primary me-2">
                                <i class="material-icons me-1">edit</i> Editar
                            </a>
                            <span class="badge" style="background-color: <?= $chamado['status_cor'] ?>">
                                <?= $chamado['status_nome'] ?>
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Detalhes do Chamado -->
                        <h4 class="mb-3">
                            <i class="material-icons type-icon"><?= $chamado['tipo_icone'] ?></i>
                            <?= htmlspecialchars($chamado['titulo']) ?>
                        </h4>

         
                      <!-- Informações do Chamado -->
<div class="d-flex flex-wrap gap-3 mb-4">
    <!-- Prioridade -->
    <div>
        <small class="text-muted">Prioridade</small>
        <div class="badge priority-badge" style="background-color: <?= $chamado['prioridade_cor'] ?>">
            <?= $chamado['prioridade_nome'] ?>
        </div>
    </div>
    
<!-- Cliente Principal -->
<?php if ($chamado['cliente_nome']): ?>
<div>
    <small class="text-muted">Cliente</small>
    <div><?= htmlspecialchars(trim(($chamado['cliente_contrato'] ? $chamado['cliente_contrato'] . ' - ' : '') . $chamado['cliente_nome'])) ?></div>
</div>
<?php endif; ?>
    
 <!-- Menu e Submenu agrupados -->
<?php if ($chamado['menu_nome'] || $chamado['submenu_nome']): ?>
<div>
    <small class="text-muted">Menu/Submenu</small>
    <div>
        <?= htmlspecialchars($chamado['menu_nome']) ?>
        <?= ($chamado['menu_nome'] && $chamado['submenu_nome']) ? ' > ' : '' ?>
        <?= htmlspecialchars($chamado['submenu_nome']) ?>
    </div>
</div>
<?php endif; ?>
    
    <!-- Criado por -->
    <div>
        <small class="text-muted">Criado por</small>
        <div><?= htmlspecialchars($chamado['usuario_nome']) ?></div>
    </div>
    
    <!-- Responsável -->
    <?php if ($chamado['responsavel_nome']): ?>
    <div>
        <small class="text-muted">Responsável</small>
        <div>
            <span class="avatar me-1"><?= substr($chamado['responsavel_nome'], 0, 1) ?></span>
            <?= htmlspecialchars($chamado['responsavel_nome']) ?>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Sprint -->
    <?php if (!empty($chamado['sprint_id'])): ?>
    <div>
        <small class="text-muted">Sprint</small>
        <div><?= htmlspecialchars($chamado['sprint_nome']) ?></div>
    </div>
    <?php endif; ?>

    
    <!-- Release -->
    <?php if (!empty($chamado['release_id'])): ?>
    <div>
        <small class="text-muted">Release</small>
        <div>
            <span class="badge" style="background-color: <?= $chamado['release_cor'] ?>">
                <?= htmlspecialchars($chamado['release_nome']) ?>
            </span>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Previsão de Liberação -->
    <?php if (!empty($chamado['previsao_liberacao'])): ?>
    <div>
        <small class="text-muted">Previsão de Liberação</small>
        <div>
            <i class="material-icons me-1" style="font-size: 16px; vertical-align: middle;">schedule</i>
            <?= date('d/m/Y', strtotime($chamado['previsao_liberacao'])) ?>
            <?php 
            $hoje = new DateTime();
            $previsao = new DateTime($chamado['previsao_liberacao']);
            $diff = $hoje->diff($previsao);
            
            if ($previsao < $hoje): ?>
                <span class="badge bg-danger ms-2">Atrasado</span>
            <?php elseif ($diff->days <= 7): ?>
                <span class="badge bg-danger ms-2">Esta semana</span>
            <?php elseif ($diff->days <= 15): ?>
                <span class="badge bg-warning ms-2">Próximos 15 dias</span>
            <?php else: ?>
                <span class="badge bg-success ms-2">Mais de 15 dias</span>
            <?php endif; ?>
            <button class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#previsaoModal" title="Editar previsão">
                <i class="material-icons" style="font-size: 14px;">edit</i>
            </button>
        </div>
    </div>
    <?php else: ?>
    <div>
        <small class="text-muted">Previsão de Liberação</small>
        <div>
            <span class="text-muted">Não definida</span>
            <button class="btn btn-sm btn-outline-primary ms-2" data-bs-toggle="modal" data-bs-target="#previsaoModal" title="Definir previsão">
                <i class="material-icons" style="font-size: 14px;">add</i>
            </button>
        </div>
    </div>
    <?php endif; ?>
    
                            <!-- Elemento para mostrar visualizações -->
        <span class="visualizacoes-indicator ms-2" 
              data-bs-toggle="tooltip" 
              data-bs-html="true" 
              title="<?= htmlspecialchars(formatarVisualizacoesTooltip($visualizacoes)) ?>">
            <i class="material-icons">visibility</i>
            <span class="visualizacoes-count"><?= count($visualizacoes) ?></span>
        </span>
</div>


                 
                        
<!-- Descrição -->
<div class="mb-4">
    <h6 class="border-bottom pb-2">Descrição</h6>
    <div class="p-3 bg-light rounded">
        <?= abrirLinksNovaGuia($chamado['descricao']) ?>
    </div>
</div>
 
                        
                        <!-- Histórico -->
                        <div class="mb-4">
                            <h6 class="border-bottom pb-2">Histórico</h6>
                            <div class="list-group">
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Criado em</small>
                                        <small><?= date('d/m/Y H:i', strtotime($chamado['data_criacao'])) ?></small>
                                    </div>
                                </div>
                                <?php if ($chamado['data_atualizacao']): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">Última atualização</small>
                                        <small><?= date('d/m/Y H:i', strtotime($chamado['data_atualizacao'])) ?></small>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>


 <?php if ($chamado['tipo_id'] == 6 && !empty($checklist_conversao)): ?>
<!-- Checklist de Conversão -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Checklist de Conversão</h5>
    </div>
    <div class="card-body p-3">
        <form method="POST" id="checklistConversaoForm">
            <?php foreach ($checklist_conversao as $item): ?>
            <div class="checklist-item mb-3 <?= $item['concluido'] ? 'bg-light-success' : '' ?>">
                <div class="d-flex align-items-center">
                    <div class="me-3">
                        <input type="checkbox" name="checklist[<?= $item['id'] ?>][concluido]" 
                               class="form-check-input checklist-item-toggle"
                               id="checklist_<?= $item['id'] ?>" 
                               value="1"
                               <?= $item['concluido'] ? 'checked' : '' ?>>
                    </div>
                    
                    <div class="d-flex flex-grow-1 align-items-center">
                        <label for="checklist_<?= $item['id'] ?>" class="form-check-label me-3 flex-shrink-0">
                            <strong><?= htmlspecialchars($item['item']) ?></strong>
                        </label>
                        
                        <input type="text" class="form-control form-control-sm" 
                               name="checklist[<?= $item['id'] ?>][observacao]"
                               value="<?= htmlspecialchars($item['observacao'] ?? '') ?>"
                               placeholder="Observações...">
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <div class="d-flex justify-content-end mt-3">
                <button type="submit" name="update_conversao_checklist" class="btn btn-primary">
                    <i class="material-icons me-1">save</i> Salvar Checklist
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Atualização visual do checklist
document.querySelectorAll('.checklist-item-toggle').forEach(checkbox => {
    checkbox.addEventListener('change', function() {
        const itemDiv = this.closest('.checklist-item');
        itemDiv.classList.toggle('bg-light-success', this.checked);
    });
});
</script>
<?php endif; ?>
                
           <!-- Comentários -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">Comentários</h5>
    </div>
    <div class="card-body">
      <?php foreach ($comentarios as $comentario): ?>
<div class="comment-card p-3 mb-3 position-relative" id="comment-<?= $comentario['id'] ?>">
    <?php if (
        $comentario['usuario_id'] == $usuario_id &&
        (
            !isset($_GET['edit_comment']) || $_GET['edit_comment'] != $comentario['id']
        )
    ): ?>

        <!-- Visualização normal do comentário -->
        <div class="comment-header d-flex justify-content-between mb-2">
            <div>
                <span class="avatar me-2"><?= substr($comentario['usuario_nome'], 0, 1) ?></span>
                <strong><?= htmlspecialchars($comentario['usuario_nome']) ?></strong>
                
                <!-- Badge do tipo de comentário -->
                <span class="badge ms-2" style="background-color: <?= getCorTipoComentario($comentario['tipo_comentario']) ?>">
                    <?= getNomeTipoComentario($comentario['tipo_comentario']) ?>
                </span>
            </div>
            <div>
                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($comentario['data_criacao'])) ?>
                <?php if ($comentario['data_atualizacao'] && $comentario['data_atualizacao'] != $comentario['data_criacao']): ?>
                    <span class="badge bg-secondary ms-2" title="Editado em <?= date('d/m/Y H:i', strtotime($comentario['data_atualizacao'])) ?>">
                        Editado
                    </span>
                <?php endif; ?>
                </small>
                <?php if ($comentario['usuario_id'] == $usuario_id): ?>
                    <div class="comment-actions dropdown d-inline-block ms-2">
                        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                            <i class="material-icons">more_vert</i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li>
                                <a class="dropdown-item" href="visualizar.php?id=<?= $chamado_id ?>&edit_comment=<?= $comentario['id'] ?>#comment-<?= $comentario['id'] ?>">
                                    <i class="material-icons me-1">edit</i> Editar
                                </a>
                            </li>
                            <li>
                                <form method="POST" action="api/comentarios.php" class="d-inline">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="comment_id" value="<?= $comentario['id'] ?>">
                                    <input type="hidden" name="chamado_id" value="<?= $chamado_id ?>">
                                    <button type="submit" class="dropdown-item text-danger" onclick="return confirm('Tem certeza que deseja excluir este comentário?')">
                                        <i class="material-icons me-1">delete</i> Excluir
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="comment-body">
            <?= abrirLinksNovaGuia($comentario['comentario']) ?>
        </div>

    <?php elseif ($comentario['usuario_id'] == $usuario_id && isset($_GET['edit_comment']) && $_GET['edit_comment'] == $comentario['id']): ?>
    <!-- Formulário de edição inline -->
    <form method="POST" action="api/comentarios.php" class="edit-comment-form" id="editCommentForm_<?= $comentario['id'] ?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="comment_id" value="<?= $comentario['id'] ?>">
        <input type="hidden" name="chamado_id" value="<?= $chamado_id ?>">
        
        <!-- Seletor de tipo para edição -->
        <div class="mb-3">
            <label class="form-label">Tipo do Comentário</label>
            <select class="form-select" name="tipo_comentario" required>
                <option value="geral" <?= $comentario['tipo_comentario'] == 'geral' ? 'selected' : '' ?>>Geral</option>
                <option value="analise_desenvolvimento" <?= $comentario['tipo_comentario'] == 'analise_desenvolvimento' ? 'selected' : '' ?>>Análise de Desenvolvimento</option>
                <option value="analise_teste" <?= $comentario['tipo_comentario'] == 'analise_teste' ? 'selected' : '' ?>>Análise de Teste</option>
                <option value="retorno_teste" <?= $comentario['tipo_comentario'] == 'retorno_teste' ? 'selected' : '' ?>>Retorno de Teste</option>
            </select>
        </div>
        
        <div class="mb-3">
            <textarea class="form-control d-none" name="comentario" id="comentario_edit_<?= $comentario['id'] ?>" required><?= htmlspecialchars($comentario['comentario']) ?></textarea>
            <div class="editor-edit-comment" id="editorEditComentario_<?= $comentario['id'] ?>"><?= $comentario['comentario'] ?></div>
        </div>
        
        <div id="uploadStatusEdit_<?= $comentario['id'] ?>" style="display: none;"></div>
        
        <div class="d-flex justify-content-end gap-2">
            <a href="visualizar.php?id=<?= $chamado_id ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" id="btnSalvarEdit_<?= $comentario['id'] ?>">
                <i class="material-icons me-1">save</i> Salvar
            </button>
        </div>
    </form>


    <?php else: ?>
        <!-- Visualização normal para outros usuários -->
        <div class="comment-header d-flex justify-content-between mb-2">
            <div>
                <span class="avatar me-2"><?= substr($comentario['usuario_nome'], 0, 1) ?></span>
                <strong><?= htmlspecialchars($comentario['usuario_nome']) ?></strong>
                
                <!-- Badge do tipo de comentário -->
                <span class="badge ms-2" style="background-color: <?= getCorTipoComentario($comentario['tipo_comentario']) ?>">
                    <?= getNomeTipoComentario($comentario['tipo_comentario']) ?>
                </span>
            </div>
            <div>
                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($comentario['data_criacao'])) ?>
                <?php if ($comentario['data_atualizacao'] && $comentario['data_atualizacao'] != $comentario['data_criacao']): ?>
                    <span class="badge bg-secondary ms-2" title="Editado em <?= date('d/m/Y H:i', strtotime($comentario['data_atualizacao'])) ?>">
                        Editado
                    </span>
                <?php endif; ?>
                </small>
            </div>
        </div>
        <div class="comment-body">
            <?= abrirLinksNovaGuia($comentario['comentario']) ?>
        </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>
        
<!-- Substitua o formulário de comentários atual por este: -->
<form method="POST" class="mt-4" id="formComentario">
    <div class="mb-3">
        <!-- Cabeçalho com tudo na mesma linha -->
        <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
            <div class="d-flex align-items-center">
                <label for="comentario" class="form-label mb-0 me-2">Adicionar Comentário</label>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                
                <select class="form-select form-select-sm" name="tipo_comentario" id="tipoComentario" style="width: auto; min-width: 220px;">
                      <option value="geral">Geral</option>
                    <option value="analise_desenvolvimento">Análise de Desenvolvimento</option>
                    <option value="analise_teste">Análise de Teste</option>
                    <option value="retorno_teste">Retorno de Teste</option>
                </select>
            </div>
        </div>
        
        <textarea class="form-control d-none" id="comentario" name="comentario" required></textarea>
        <div id="editorComentario"></div>
    </div>
    <div id="uploadStatus" style="display: none;"></div>
    <button type="submit" class="btn btn-primary">
        <i class="material-icons me-1">send</i> Enviar Comentário
    </button>
</form>


    </div>
</div>
            </div>
            
            <div class="col-lg-4">
          <!-- Anexos -->
<div class="card mb-4 collapsible-container" id="anexosContainer">
    <div class="card-header collapsible-header d-flex justify-content-between align-items-center">
        <div>
            <h5 class="mb-0">Anexos (<?= count($anexos) ?>)</h5>
            <?php if (!empty($anexos)): ?>
                <small class="text-muted">Clique nas imagens/vídeos para ampliar</small>
            <?php endif; ?>
        </div>
        <i class="material-icons collapsible-icon">keyboard_arrow_down</i>
    </div>
    <div class="card-body collapsible-content">
        <?php if (!empty($anexos)): ?>
            <div class="attachment-thumbnails">
                <?php foreach ($anexos as $anexo): 
                    $ext = strtolower(pathinfo($anexo['nome_arquivo'], PATHINFO_EXTENSION));
                    $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
                    $isVideo = in_array($ext, ['mp4', 'webm', 'ogg']);
                    $isPDF = $ext === 'pdf';
                ?>
                    <div class="attachment-preview">
                        <?php if ($isImage): ?>
                            <a href="<?= $anexo['caminho'] ?>" data-fancybox="gallery" data-caption="<?= htmlspecialchars($anexo['nome_arquivo']) ?>">
                                <img src="<?= $anexo['caminho'] ?>" alt="<?= htmlspecialchars($anexo['nome_arquivo']) ?>" class="img-thumbnail">
                            </a>
                        <?php elseif ($isVideo): ?>
                            <a href="<?= $anexo['caminho'] ?>" data-fancybox data-caption="<?= htmlspecialchars($anexo['nome_arquivo']) ?>">
                                <video class="js-plyr" playsinline controls style="width: 100px; height: 100px;">
                                    <source src="<?= $anexo['caminho'] ?>" type="video/<?= $ext ?>">
                                </video>
                                <div class="video-label"><?= strtoupper($ext) ?></div>
                            </a>
                        <?php elseif ($isPDF): ?>
                            <a href="<?= $anexo['caminho'] ?>" data-fancybox="gallery" data-type="iframe" data-caption="<?= htmlspecialchars($anexo['nome_arquivo']) ?>">
                                <div class="file-thumbnail pdf">
                                    <i class="material-icons">picture_as_pdf</i>
                                    <span>PDF</span>
                                </div>
                            </a>
                        <?php else: ?>
                            <a href="javascript:void(0);" onclick="downloadSilencioso(<?= $anexo['id'] ?>)" title="Baixar arquivo">
                                <div class="file-thumbnail">
                                    <i class="material-icons">insert_drive_file</i>
                                    <span><?= strtoupper($ext) ?></span>
                                </div>
                            </a>
                        <?php endif; ?>
                        <div class="attachment-info">
                            <small class="d-block text-truncate" style="max-width: 100px;" title="<?= htmlspecialchars($anexo['nome_arquivo']) ?>">
                                <?= htmlspecialchars($anexo['nome_arquivo']) ?>
                            </small>
                            <small class="text-muted"><?= round($anexo['tamanho'] / 1024, 2) ?> KB</small>
                            <small class="d-block"><?= date('d/m/Y H:i', strtotime($anexo['data_criacao'])) ?></small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center py-3">
                <i class="material-icons text-muted" style="font-size: 48px;">folder_open</i>
                <p class="text-muted">Nenhum anexo encontrado</p>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data" class="mt-4">
            <div class="mb-3">
                <label for="anexo" class="form-label">Adicionar Anexo</label>
                <input class="form-control" type="file" id="anexo" name="anexo" required>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="material-icons me-1">upload</i> Enviar
            </button>
        </form>
    </div>
</div>

 <!-- Ações Rápidas -->
<div class="card mb-4 collapsible-container" id="acoesContainer">
    <div class="card-header collapsible-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Ações Rápidas</h5>
        <div class="d-flex align-items-center">
            <div class="dropdown" onclick="event.stopPropagation()">
                <button class="btn btn-sm btn-outline-secondary dropdown-toggle me-1" type="button" id="statusDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                    Alterar Status
                </button>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="statusDropdown">
                    <?php 
                    $statuses = getChamadosStatus();
                    foreach ($statuses as $status): 
                        if ($status['id'] != $chamado['status_id']): // Não mostrar o status atual
                    ?>
                        <li>
                            <a class="dropdown-item change-status" 
                               href="api/chamados.php?action=mudar_status&id=<?= $chamado['id'] ?>&status_id=<?= $status['id'] ?>" 
                               data-status="<?= $status['id'] ?>">
                                <span class="badge me-2" style="background-color: <?= $status['cor'] ?>">&nbsp;&nbsp;</span>
                                <?= htmlspecialchars($status['nome']) ?>
                                <span class="status-loading spinner-border spinner-border-sm d-none ms-2" role="status"></span>
                            </a>
                        </li>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </ul>
            </div>
    
            <i class="material-icons collapsible-icon ms-2">keyboard_arrow_down</i>
        </div>
    </div>
    <div class="card-body collapsible-content">
        <div class="d-grid gap-2">
            <?php if ($chamado['status_id'] != 5): ?>
            <a href="api/chamados.php?action=mudar_status&id=<?= $chamado['id'] ?>&status_id=5" class="btn btn-success">
                <i class="material-icons me-1">check</i> Marcar como Concluído
            </a>
            <?php endif; ?>
            
            <?php if ($chamado['status_id'] != 6): ?>
            <a href="api/chamados.php?action=mudar_status&id=<?= $chamado['id'] ?>&status_id=6" class="btn btn-danger">
                <i class="material-icons me-1">cancel</i> Cancelar Chamado
            </a>
            <?php endif; ?>
            
            <?php if ($chamado['status_id'] != 5 && $chamado['status_id'] != 6 && empty($chamado['responsavel_id'])): ?>
            <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#responsavelModal">
                <i class="material-icons me-1">person_add</i> Selecionar Responsável
            </a>
            <?php endif; ?>
            
            <?php if (!$chamado['sprint_id'] && $chamado['status_id'] != 5 && $chamado['status_id'] != 6): ?>
            <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#sprintModal">
                <i class="material-icons me-1">date_range</i> Atribuir Sprint
            </a>
            <?php endif; ?>
            
            <?php if (!$chamado['release_id'] && $chamado['status_id'] != 5 && $chamado['status_id'] != 6): ?>
            <a href="#" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#releaseModal">
                <i class="material-icons me-1">rocket</i> Atribuir Release
            </a>
            <?php endif; ?>
        </div>
    </div>
</div>
                
           <!-- Container de Marcadores - MELHORADO -->
<div class="card mb-4 collapsible-container marcadores-container" id="marcadoresContainer">
    <div class="card-header collapsible-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Marcadores</h5>
        <i class="material-icons collapsible-icon">keyboard_arrow_down</i>
    </div>
    <div class="card-body collapsible-content">
        <!-- Marcadores vinculados -->
        <div class="marcadores-vinculados">
            <?php foreach ($marcadores_vinculados as $marcador): ?>
                <div class="marcador-item" style="color: <?= $marcador['cor'] ?>">
                    <div class="marcador-info">
                        <span class="marcador-cor" style="background-color: <?= $marcador['cor'] ?>"></span>
                        <?= htmlspecialchars($marcador['nome']) ?>
                    </div>
                    <div class="marcador-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="marcador_id" value="<?= $marcador['id'] ?>">
                            <button type="submit" name="remover_marcador" class="btn btn-sm btn-outline-danger">
                                <i class="material-icons">close</i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($marcadores_vinculados)): ?>
                <p class="text-muted">Nenhum marcador vinculado.</p>
            <?php endif; ?>
        </div>
        
   <!-- Formulário para adicionar marcador existente - MELHORADO COM BUSCA -->
<form method="POST" class="mt-3 form-marcador-existente">
    <div class="mb-3">
        <label class="form-label">Vincular marcador existente</label>
        <div class="custom-select">
            <input type="text" id="marcador_filter" placeholder="Digite para filtrar marcadores..." class="form-control form-control-sm">
            <div class="options" id="marcador_options">
                <div data-value="">Selecione um marcador</div>
                <?php foreach ($marcadores_disponiveis as $marcador): 
                    $ja_vinculado = false;
                    foreach ($marcadores_vinculados as $marcador_vinc) {
                        if ($marcador_vinc['id'] == $marcador['id']) {
                            $ja_vinculado = true;
                            break;
                        }
                    }
                    if (!$ja_vinculado):
                ?>
                    <div data-value="<?= $marcador['id'] ?>">
                        <span class="marcador-cor" style="background-color: <?= $marcador['cor'] ?>; display: inline-block; width: 12px; height: 12px; border-radius: 50%; margin-right: 5px;"></span>
                        <?= htmlspecialchars($marcador['nome']) ?>
                    </div>
                <?php endif; endforeach; ?>
            </div>
            <select id="marcador_id" name="marcador_id" class="form-select form-select-sm d-none" required>
                <option value="">Selecione um marcador</option>
                <?php foreach ($marcadores_disponiveis as $marcador): 
                    $ja_vinculado = false;
                    foreach ($marcadores_vinculados as $marcador_vinc) {
                        if ($marcador_vinc['id'] == $marcador['id']) {
                            $ja_vinculado = true;
                            break;
                        }
                    }
                    if (!$ja_vinculado):
                ?>
                    <option value="<?= $marcador['id'] ?>">
                        <?= htmlspecialchars($marcador['nome']) ?>
                    </option>
                <?php endif; endforeach; ?>
            </select>
        </div>
    </div>
    <button type="submit" name="adicionar_marcador" class="btn btn-primary btn-sm">
        <i class="material-icons">add_circle</i> Vincular
    </button>
</form>
        
        <!-- Formulário para criar novo marcador - MELHORADO -->
        <form method="POST" class="mt-3 form-novo-marcador">
            <div class="mb-3">
                <label class="form-label">Criar novo marcador</label>
                <div class="row g-2">
                    <div class="col-md-8">
                        <input type="text" class="form-control form-control-sm" name="novo_marcador" placeholder="Nome do marcador" required>
                    </div>
                    <div class="col-md-2">
                        <input type="color" class="form-control form-control-color form-control-sm" name="cor_marcador" value="#6c757d" title="Escolha a cor">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" name="criar_marcador" class="btn btn-success btn-sm w-100">
                            <i class="material-icons">add</i>
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
                
                        <!-- Clientes Vinculados -->
<?php if (!empty($clientes_adicionais) || $chamado['cliente_id']): ?>
<div class="card mb-4 collapsible-container" id="clientesContainer">
    <div class="card-header collapsible-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Clientes Vinculados</h5>
        <i class="material-icons collapsible-icon">keyboard_arrow_down</i>
    </div>
    <div class="card-body collapsible-content">
        <div class="clientes-adicionais">
            <?php foreach ($clientes_adicionais as $cliente): ?>
                <div class="cliente-item">
                  <div class="cliente-info">
    <?= htmlspecialchars(trim(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome'])) ?>
</div>
                    <div class="cliente-actions">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="cliente_id" value="<?= $cliente['id'] ?>">
                            <button type="submit" name="remover_cliente" class="btn btn-sm btn-outline-danger">
                               <i class="material-icons">close</i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if (empty($clientes_adicionais)): ?>
                <p class="text-muted">Nenhum cliente adicional vinculado.</p>
            <?php endif; ?>
        </div>
        
<!-- Formulário para adicionar novo cliente - MELHORADO -->
<form method="POST" class="mt-3 form-adicionar-cliente">
    <div class="mb-2">
        <label class="form-label">Adicionar Cliente</label>
        <div class="custom-select">
            <input type="text" id="cliente_filter" placeholder="Digite para filtrar clientes..." class="form-control form-control-sm">
            <div class="options" id="cliente_options">
                <div data-value="">Selecione um cliente</div>
                <?php foreach ($clientes_disponiveis as $cliente): 
                    // Pular o cliente principal se já estiver definido
                    if ($chamado['cliente_id'] && $cliente['id'] == $chamado['cliente_id']) continue;
                    
                    // Verificar se o cliente já está adicionado
                    $ja_adicionado = false;
                    foreach ($clientes_adicionais as $cliente_add) {
                        if ($cliente_add['id'] == $cliente['id']) {
                            $ja_adicionado = true;
                            break;
                        }
                    }
                    if ($ja_adicionado) continue;
                ?>
                <div data-value="<?= $cliente['id'] ?>">
    <?= htmlspecialchars(trim(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome'])) ?>
</div>
                <?php endforeach; ?>
            </div>
            <select id="cliente_id" name="cliente_id" class="form-select form-select-sm d-none" required>
                <option value="">Selecione um cliente</option>
                <?php foreach ($clientes_disponiveis as $cliente): 
                    if ($chamado['cliente_id'] && $cliente['id'] == $chamado['cliente_id']) continue;
                    $ja_adicionado = false;
                    foreach ($clientes_adicionais as $cliente_add) {
                        if ($cliente_add['id'] == $cliente['id']) {
                            $ja_adicionado = true;
                            break;
                        }
                    }
                    if ($ja_adicionado) continue;
                ?>
                    <option value="<?= $cliente['id'] ?>" <?= $filtro_cliente == $cliente['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(($cliente['contrato'] ? $cliente['contrato'] . ' - ' : '') . $cliente['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <button type="submit" name="adicionar_cliente" class="btn btn-primary btn-sm">
        <i class="material-icons" style="font-size: 16px;">add_circle</i> Adicionar
    </button>
</form>
    </div>
</div>
<?php endif; ?>

    <!-- Modal para atribuir sprint -->
    <div class="modal fade" id="sprintModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Atribuir Sprint</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="api/chamados.php" method="POST">
                    <input type="hidden" name="action" value="atribuir_sprint">
                    <input type="hidden" name="chamado_id" value="<?= $chamado['id'] ?>">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Selecione a Sprint</label>
                            <select name="sprint_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($sprints as $sprint): ?>
                                    <option value="<?= $sprint['id'] ?>" <?= ($sprint['id'] == $chamado['sprint_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sprint['nome']) ?> 
                                        (<?= date('d/m/Y', strtotime($sprint['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($sprint['data_fim'])) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal para atribuir release -->
    <div class="modal fade" id="releaseModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Atribuir Release</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="api/chamados.php" method="POST">
                    <input type="hidden" name="action" value="atribuir_release">
                    <input type="hidden" name="chamado_id" value="<?= $chamado['id'] ?>">
                    
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Selecione a Release</label>
                            <select name="release_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($releases_list as $release): ?>
                                    <option value="<?= $release['id'] ?>">
                                        <?= htmlspecialchars($release['nome']) ?> 
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <!-- Modal para selecionar responsável -->
<div class="modal fade" id="responsavelModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Selecionar Responsável</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="api/chamados.php" method="POST">
                <input type="hidden" name="action" value="definir_responsavel">
                <input type="hidden" name="chamado_id" value="<?= $chamado['id'] ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Selecione o Responsável</label>
                        <select name="responsavel_id" class="form-select" required>
                            <option value="">Selecione...</option>
                            <?php 
                            $equipe = getUsuariosEquipe();
                            foreach ($equipe as $membro): 
                            ?>
                                <option value="<?= $membro['id'] ?>" <?= ($membro['id'] == $chamado['responsavel_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($membro['nome']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para definir previsão de liberação -->
<div class="modal fade" id="previsaoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Definir Previsão de Liberação</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="api/chamados.php" method="POST" id="previsaoForm">
                <input type="hidden" name="action" value="definir_previsao_liberacao">
                <input type="hidden" name="chamado_id" value="<?= $chamado['id'] ?>">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Data de Previsão de Liberação</label>
                        <div class="input-group">
                            <input type="date" name="previsao_liberacao" id="previsaoInput" class="form-control" 
                                   value="<?= $chamado['previsao_liberacao'] ?? '' ?>">
                            <button type="button" class="btn btn-outline-danger" id="limparPrevisao" title="Limpar data">
                                <i class="material-icons" style="font-size: 18px;">clear</i>
                            </button>
                        </div>
                        <div class="form-text">Data prometida ao cliente para liberação do chamado. Deixe vazio para remover a previsão.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Modal para criar nova sprint -->
<div class="modal fade" id="createSprintModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Nova Sprint</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="api/chamados.php" method="POST" id="createSprintForm">
                <input type="hidden" name="action" value="create_sprint">
                <input type="hidden" name="chamado_id" value="<?= $chamado['id'] ?>">
                <input type="hidden" name="vincular" value="1" id="vincularSprint">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome da Sprint *</label>
                        <input type="text" class="form-control" name="nome" required>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Data de Início *</label>
                            <input type="date" class="form-control" name="data_inicio" id="sprintDataInicio" required>
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Data de Término *</label>
                            <input type="date" class="form-control" name="data_fim" id="sprintDataFim" required>
                        </div>
                    </div>
                    
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="vincular_check" id="vincularSprintCheck" checked>
                        <label class="form-check-label" for="vincularSprintCheck">
                            Vincular esta sprint ao chamado
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Sprint</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal para criar nova release -->
<div class="modal fade" id="createReleaseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Nova Release</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="api/chamados.php" method="POST" id="createReleaseForm">
                <input type="hidden" name="action" value="create_release">
                <input type="hidden" name="chamado_id" value="<?= $chamado['id'] ?>">
                <input type="hidden" name="vincular" value="1" id="vincularRelease">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Nome da Release *</label>
                        <input type="text" class="form-control" name="nome" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Descrição</label>
                        <textarea class="form-control" name="descricao" rows="3"></textarea>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Data Planejada</label>
                            <input type="date" class="form-control" name="data_planejada">
                        </div>
                        
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="planejado">Planejado</option>
                                <option value="em_desenvolvimento">Em Desenvolvimento</option>
                                <option value="teste">Teste</option>
                                <option value="lançado">Lançado</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mt-3">
                        <label class="form-label">Cor</label>
                        <input type="color" class="form-control form-control-color" name="cor" value="#6c757d">
                    </div>
                    
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="vincular_check" id="vincularReleaseCheck" checked>
                        <label class="form-check-label" for="vincularReleaseCheck">
                            Vincular esta release ao chamado
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Criar Release</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal de zoom com fundo preto -->
<div id="imageZoomModal" class="image-zoom-modal" style="display: none;">
    <div class="zoom-overlay">
        <button class="zoom-close" onclick="closeImageZoom()">&times;</button>
        <img id="zoomedImage" src="" alt="Imagem ampliada">
    </div>
</div>

    <?php require_once 'includes/footer.php'; ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
$(document).ready(function() {
    // Validação simples para evitar envio vazio
    $('form').on('submit', function(e) {
        const formType = $(this).find('button[type="submit"]').attr('name');
        
        if (formType === 'adicionar_marcador' && !$('[name="marcador_id"]').val()) {
            e.preventDefault();
            alert('Selecione um marcador para vincular!');
        }
        
        if (formType === 'criar_marcador' && !$('[name="novo_marcador"]').val()) {
            e.preventDefault();
            alert('Digite um nome para o novo marcador!');
        }
    });
});
</script>
<script>
// Inicializa o CKEditor para edição inline de comentários
document.addEventListener('DOMContentLoaded', function() {
    let uploadedImages = [];
    
    // Editor para novo comentário
    DecoupledEditor
        .create(document.querySelector('#editorComentario'), {
            toolbar: [
                'bold', 'italic', 'underline', 'strikethrough', 'link', 'bulletedList', 'numberedList', '|',
                'imageUpload', 'imageInsert', '|',
                'undo', 'redo'
            ],
            wordWrap: true,
            simpleUpload: {
                uploadUrl: 'api/upload_imagem_comentario.php',
                withCredentials: true,
                headers: {
                    'X-CSRF-TOKEN': 'CSRF-Token',
                    'Authorization': 'Bearer <JSON Web Token>'
                }
            },
            image: {
                toolbar: [
                    'imageTextAlternative',
                    'imageStyle:inline',
                    'imageStyle:block',
                    'imageStyle:side'
                ]
            }
        })
        .then(editor => {
            // Adicionar a toolbar ao DOM
            const toolbarContainer = document.createElement('div');
            toolbarContainer.appendChild(editor.ui.view.toolbar.element);
            document.querySelector('#editorComentario').parentNode.insertBefore(toolbarContainer, document.querySelector('#editorComentario'));
            
            // Configurar upload de imagem
            editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
                return new MyUploadAdapter(loader);
            };
            
            editor.model.document.on('change:data', () => {
                document.querySelector('#comentario').value = editor.getData();
            });
            
            // Interceptar o envio do formulário para incluir informações das imagens
            const form = document.querySelector('form[method="post"]');
            if (form) {
                form.addEventListener('submit', function(e) {
                    if (uploadedImages.length > 0) {
                        const imagensInput = document.createElement('input');
                        imagensInput.type = 'hidden';
                        imagensInput.name = 'imagens_info';
                        imagensInput.value = JSON.stringify(uploadedImages);
                        form.appendChild(imagensInput);
                    }
                });
            }
        })
        .catch(error => {
            console.error(error);
        });
    
    // Variável para controlar uploads em andamento
    let uploadsInProgress = 0;
    
    // Função para atualizar o estado do botão de envio
    function updateSubmitButton() {
        const submitButton = document.querySelector('button[type="submit"]');
        const uploadStatus = document.getElementById('uploadStatus');
        
        if (uploadsInProgress > 0) {
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.innerHTML = '<i class="material-icons me-1">hourglass_empty</i> Aguarde o upload...';
            }
            if (uploadStatus) {
                uploadStatus.innerHTML = `<div class="alert alert-info"><i class="material-icons me-1">cloud_upload</i> Fazendo upload de ${uploadsInProgress} imagem(ns)...</div>`;
                uploadStatus.style.display = 'block';
            }
        } else {
            if (submitButton) {
                submitButton.disabled = false;
                submitButton.innerHTML = '<i class="material-icons me-1">send</i> Enviar Comentário';
            }
            if (uploadStatus) {
                uploadStatus.innerHTML = '<div class="alert alert-success"><i class="material-icons me-1">check_circle</i> Todas as imagens foram carregadas com sucesso!</div>';
                setTimeout(() => {
                    uploadStatus.style.display = 'none';
                }, 3000);
            }
        }
    }
    
    // Classe personalizada para upload de imagens
    class MyUploadAdapter {
        constructor(loader) {
            this.loader = loader;
        }
        
        upload() {
            return this.loader.file
                .then(file => new Promise((resolve, reject) => {
                    // Incrementa contador de uploads
                    uploadsInProgress++;
                    updateSubmitButton();
                    
                    const data = new FormData();
                    data.append('upload', file);
                    data.append('chamado_id', <?php echo $chamado_id; ?>);
                    
                    fetch('api/upload_imagem_comentario.php', {
                        method: 'POST',
                        body: data
                    })
                    .then(response => response.json())
                    .then(result => {
                        // Decrementa contador de uploads
                        uploadsInProgress--;
                        updateSubmitButton();
                        
                        if (result.success) {
                            uploadedImages.push(result.file_info);
                            resolve({
                                default: result.url
                            });
                        } else {
                            reject(result.message);
                        }
                    })
                    .catch(error => {
                        // Decrementa contador de uploads em caso de erro
                        uploadsInProgress--;
                        updateSubmitButton();
                        reject('Erro no upload: ' + error);
                    });
                }));
        }
        
        abort() {
            // Decrementa contador se upload for abortado
            if (uploadsInProgress > 0) {
                uploadsInProgress--;
                updateSubmitButton();
            }
        }
    }
    
    // Editor para edição de comentário existente
    const editEditorElement = document.querySelector('.editor-edit-comment');
    if (editEditorElement) {
        let uploadedImagesEdit = [];
        let uploadsInProgressEdit = 0;
        
        // Função para atualizar o estado do botão de salvar na edição
        function updateSaveButtonEdit(commentId) {
            const saveButton = document.querySelector(`#btnSalvarEdit_${commentId}`);
            const uploadStatus = document.querySelector(`#uploadStatusEdit_${commentId}`);
            
            if (uploadsInProgressEdit > 0) {
                if (saveButton) {
                    saveButton.disabled = true;
                    saveButton.innerHTML = '<i class="material-icons me-1">hourglass_empty</i> Aguarde o upload...';
                }
                if (uploadStatus) {
                    uploadStatus.innerHTML = `<div class="alert alert-info"><i class="material-icons me-1">cloud_upload</i> Fazendo upload de ${uploadsInProgressEdit} imagem(ns)...</div>`;
                    uploadStatus.style.display = 'block';
                }
            } else {
                if (saveButton) {
                    saveButton.disabled = false;
                    saveButton.innerHTML = '<i class="material-icons me-1">save</i> Salvar';
                }
                if (uploadStatus) {
                    uploadStatus.innerHTML = '<div class="alert alert-success"><i class="material-icons me-1">check_circle</i> Todas as imagens foram carregadas com sucesso!</div>';
                    setTimeout(() => {
                        uploadStatus.style.display = 'none';
                    }, 3000);
                }
            }
        }
        
        // Classe personalizada para upload de imagens na edição
        class MyUploadAdapterEdit {
            constructor(loader) {
                this.loader = loader;
            }
            
            upload() {
                uploadsInProgressEdit++;
                const commentId = editEditorElement.id.split('_')[1];
                updateSaveButtonEdit(commentId);
                
                return this.loader.file.then(file => new Promise((resolve, reject) => {
                    const formData = new FormData();
                    formData.append('upload', file);
                    formData.append('chamado_id', <?= $chamado_id ?>);
                    
                    fetch('api/upload_imagem_comentario.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        uploadsInProgressEdit--;
                        updateSaveButtonEdit(commentId);
                        
                        if (data.success) {
                            uploadedImagesEdit.push(data.file_info);
                            resolve({
                                default: data.url
                            });
                        } else {
                            reject(data.message || 'Erro no upload');
                        }
                    })
                    .catch(error => {
                        uploadsInProgressEdit--;
                        updateSaveButtonEdit(commentId);
                        reject('Erro no upload: ' + error);
                    });
                }));
            }
            
            abort() {
                if (uploadsInProgressEdit > 0) {
                    uploadsInProgressEdit--;
                    const commentId = editEditorElement.id.split('_')[1];
                    updateSaveButtonEdit(commentId);
                }
            }
        }
        
        DecoupledEditor
            .create(editEditorElement, {
                toolbar: [
                    'bold', 'italic', 'underline', 'strikethrough', 'link', 'bulletedList', 'numberedList', '|',
                    'imageUpload', 'imageInsert', '|',
                    'undo', 'redo'
                ],
                wordWrap: true,
                simpleUpload: {
                    uploadUrl: 'api/upload_imagem_comentario.php',
                    withCredentials: true,
                    headers: {
                        'X-CSRF-TOKEN': 'CSRF-Token',
                        'Authorization': 'Bearer <JSON Web Token>'
                    }
                },
                image: {
                    toolbar: [
                        'imageTextAlternative',
                        'imageStyle:inline',
                        'imageStyle:block',
                        'imageStyle:side'
                    ]
                }
            })
            .then(editor => {
                // Adicionar a toolbar ao DOM para o editor de edição
                const toolbarContainer = document.createElement('div');
                toolbarContainer.appendChild(editor.ui.view.toolbar.element);
                editEditorElement.parentNode.insertBefore(toolbarContainer, editEditorElement);
                
                // Configurar upload de imagem
                editor.plugins.get('FileRepository').createUploadAdapter = (loader) => {
                    return new MyUploadAdapterEdit(loader);
                };
                
                editor.model.document.on('change:data', () => {
                    const textarea = editEditorElement.closest('form').querySelector('textarea');
                    textarea.value = editor.getData();
                });
                
                // Interceptar o envio do formulário para incluir informações das imagens
                const form = editEditorElement.closest('form');
                if (form) {
                    form.addEventListener('submit', function(e) {
                        if (uploadedImagesEdit.length > 0) {
                            const imagensInput = document.createElement('input');
                            imagensInput.type = 'hidden';
                            imagensInput.name = 'imagens_info';
                            imagensInput.value = JSON.stringify(uploadedImagesEdit);
                            form.appendChild(imagensInput);
                        }
                    });
                }
            })
            .catch(error => {
                console.error(error);
            });
    }
    
    // Rola para o comentário sendo editado
    if (window.location.hash && window.location.hash.startsWith('#comment-')) {
        const commentElement = document.querySelector(window.location.hash);
        if (commentElement) {
            setTimeout(() => {
                commentElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }, 100);
        }
    }
});
</script>
<script>
$(document).ready(function() {
    // Controle do checkbox de vincular sprint
    $('#vincularSprintCheck').change(function() {
        $('#vincularSprint').val(this.checked ? '1' : '0');
    });
    
    // Controle do checkbox de vincular release
    $('#vincularReleaseCheck').change(function() {
        $('#vincularRelease').val(this.checked ? '1' : '0');
    });
    
    // Formulário de criação de sprint
    $('#createSprintForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    location.reload(); // Recarrega a página para atualizar as informações
                } else {
                    alert('Erro: ' + (response.message || 'Falha ao criar sprint'));
                }
            },
            error: function() {
                alert('Erro ao enviar requisição');
            }
        });
    });
    
    // Formulário de criação de release
    $('#createReleaseForm').submit(function(e) {
        e.preventDefault();
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    location.reload(); // Recarrega a página para atualizar as informações
                } else {
                    alert('Erro: ' + (response.message || 'Falha ao criar release'));
                }
            },
            error: function() {
                alert('Erro ao enviar requisição');
            }
        });
    });
});
</script>
<script>
// No final do arquivo, adicione este script
document.addEventListener('DOMContentLoaded', function() {
    // Calcula as datas da semana (segunda a sexta)
    function getWeekDates() {
        const today = new Date();
        const day = today.getDay(); // 0=Domingo, 1=Segunda, etc.
        
        // Se for fim de semana, começa na próxima segunda
        let startDate = new Date(today);
        if (day === 0) { // Domingo
            startDate.setDate(today.getDate() + 1);
        } else if (day === 6) { // Sábado
            startDate.setDate(today.getDate() + 2);
        } else if (day > 1) { // Terça a sexta
            startDate.setDate(today.getDate() - (day - 1));
        }
        
        // Data fim é 4 dias depois (sexta)
        let endDate = new Date(startDate);
        endDate.setDate(startDate.getDate() + 4);
        
        return { start: startDate, end: endDate };
    }
    
    // Preenche as datas quando o modal é aberto
    $('#createSprintModal').on('show.bs.modal', function() {
        const dates = getWeekDates();
        const formatDate = (date) => date.toISOString().split('T')[0];
        
        document.getElementById('sprintDataInicio').value = formatDate(dates.start);
        document.getElementById('sprintDataFim').value = formatDate(dates.end);
        
        // Define o nome padrão como "Sprint Semana X"
        const weekNumber = getWeekNumber(dates.start);
        document.querySelector('#createSprintModal input[name="nome"]').value = `Sprint Semana ${weekNumber}`;
    });
    
    // Função para obter o número da semana
    function getWeekNumber(date) {
        const firstDayOfYear = new Date(date.getFullYear(), 0, 1);
        const pastDaysOfYear = (date - firstDayOfYear) / 86400000;
        return Math.ceil((pastDaysOfYear + firstDayOfYear.getDay() + 1) / 7);
    }
    
    // Controle adicional para uploads de imagens na edição
    document.addEventListener('DOMContentLoaded', function() {
        // Validação de tipos de arquivo para uploads
        const allowedImageTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        const maxFileSize = 500 * 1024 * 1024; // 500MB
        
        // Função para validar arquivo antes do upload
        function validateImageFile(file) {
            if (!allowedImageTypes.includes(file.type)) {
                alert('Tipo de arquivo não permitido. Apenas imagens são aceitas (JPEG, PNG, GIF, WebP).');
                return false;
            }
            
            if (file.size > maxFileSize) {
                alert('Arquivo muito grande. O tamanho máximo é 500MB.');
                return false;
            }
            
            return true;
        }
        
        // Intercepta uploads no editor de edição para validação
        document.addEventListener('change', function(e) {
            if (e.target && e.target.type === 'file' && e.target.closest('.editor-edit-comment')) {
                const files = e.target.files;
                for (let i = 0; i < files.length; i++) {
                    if (!validateImageFile(files[i])) {
                        e.target.value = ''; // Limpa o input
                        return false;
                    }
                }
            }
        });
        
        // Adiciona feedback visual durante uploads na edição
        const editForms = document.querySelectorAll('.edit-comment-form');
        editForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = form.querySelector('button[type="submit"]');
                const uploadStatus = form.querySelector('[id^="uploadStatusEdit_"]');
                
                // Verifica se há uploads em andamento
                if (window.uploadsInProgressEdit && window.uploadsInProgressEdit > 0) {
                    e.preventDefault();
                    alert('Aguarde o upload das imagens terminar antes de salvar.');
                    return false;
                }
                
                // Adiciona indicador de salvamento
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="material-icons me-1">hourglass_empty</i> Salvando...';
                }
            });
        });
        
        // Função para mostrar preview de imagens durante upload
        function showImagePreview(file, container) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.createElement('div');
                preview.className = 'image-preview-edit';
                preview.innerHTML = `
                    <img src="${e.target.result}" style="max-width: 100px; max-height: 100px; margin: 5px; border-radius: 4px;">
                    <div class="text-center"><small>Carregando...</small></div>
                `;
                container.appendChild(preview);
            };
            reader.readAsDataURL(file);
        }
        
        // Adiciona suporte para drag and drop no editor de edição
        const editEditors = document.querySelectorAll('.editor-edit-comment');
        editEditors.forEach(editor => {
            editor.addEventListener('dragover', function(e) {
                e.preventDefault();
                editor.style.border = '2px dashed #007bff';
            });
            
            editor.addEventListener('dragleave', function(e) {
                e.preventDefault();
                editor.style.border = '';
            });
            
            editor.addEventListener('drop', function(e) {
                e.preventDefault();
                editor.style.border = '';
                
                const files = e.dataTransfer.files;
                for (let i = 0; i < files.length; i++) {
                    if (files[i].type.startsWith('image/')) {
                        if (validateImageFile(files[i])) {
                            // O CKEditor irá processar o arquivo automaticamente
                            console.log('Arquivo de imagem válido:', files[i].name);
                        }
                    }
                }
            });
        });
    });
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tratamento da mudança de status com feedback visual instantâneo
    document.querySelectorAll('.change-status').forEach(item => {
        item.addEventListener('click', function(e) {
            e.preventDefault();
            
            const link = this;
            const loadingSpinner = link.querySelector('.status-loading');
            const dropdownMenu = link.closest('.dropdown-menu');
            
            // Feedback IMEDIATO
            loadingSpinner.classList.remove('d-none');
            link.classList.add('disabled');
            showLoading(); // Mostra o loading global IMEDIATAMENTE
            
            // Esconde o dropdown após o clique
            const dropdownInstance = bootstrap.Dropdown.getInstance(document.getElementById('statusDropdown'));
            dropdownInstance.hide();
            
            // Faz a requisição
            fetch(link.href)
                .then(response => {
                    if (response.ok) {
                        // Recarrega a página após o sucesso
                        setTimeout(() => {
                            window.location.reload();
                        }, 300); // Tempo mínimo para garantir que o loading seja visível
                    } else {
                        throw new Error('Erro ao alterar status');
                    }
                })
                .catch(error => {
                    console.error('Erro:', error);
                    hideLoading(); // Esconde o loading em caso de erro
                    loadingSpinner.classList.add('d-none');
                    link.classList.remove('disabled');
                    
                    // Mostra mensagem de erro mais amigável
                    const toast = new bootstrap.Toast(document.getElementById('errorToast'));
                    document.getElementById('toastMessage').textContent = 'Erro ao alterar status. Tente novamente.';
                    toast.show();
                });
        });
    });
    
    // As funções showLoading() e hideLoading() estão definidas no footer.php
});

// Controle do modal de zoom
function openImageZoom(imageSrc) {
    document.getElementById('zoomedImage').src = imageSrc;
    document.getElementById('imageZoomModal').style.display = 'flex';
    document.body.style.overflow = 'hidden'; // Previne scroll da página
}

function closeImageZoom() {
    document.getElementById('imageZoomModal').style.display = 'none';
    document.body.style.overflow = 'auto'; // Restaura scroll da página
}

// Event listeners para imagens inline
document.addEventListener('click', function(e) {
    // Para imagens inline no texto dos comentários
    if (e.target.classList.contains('comment-image-inline')) {
        const imageSrc = e.target.getAttribute('data-image-src') || e.target.src;
        if (imageSrc) {
            openImageZoom(imageSrc);
        }
    }
});

// Fechar modal ao clicar fora da imagem
document.addEventListener('click', function(e) {
    if (e.target.id === 'imageZoomModal') {
        closeImageZoom();
    }
});

// Fechar modal com tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageZoom();
    }
});

</script>
<script>
// Mostra o loading quando qualquer formulário for enviado
document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
        showLoading();
    });
});
</script>

<script>
function downloadSilencioso(anexoId) {
    // Cria um link temporário invisível
    const link = document.createElement('a');
    link.style.display = 'none';
    link.href = `download.php?id=${anexoId}`;
    document.body.appendChild(link);
    
    // Dispara o clique
    link.click();
    
    // Remove o link após um pequeno delay
    setTimeout(() => {
        document.body.removeChild(link);
    }, 100);
}
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
</script>
<script>
// Função para o filtro de clientes (igual ao do index.php)
document.addEventListener('DOMContentLoaded', function() {
    const clienteFilter = document.getElementById('cliente_filter');
    const clienteOptions = document.getElementById('cliente_options');
    const clienteSelect = document.getElementById('cliente_id');
    
    if (clienteFilter && clienteOptions) {
        clienteFilter.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const options = clienteOptions.querySelectorAll('div[data-value]');
            
            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                if (text.includes(filter)) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
            
            clienteOptions.style.display = 'block';
        });
        
        // Mostrar opções ao focar
        clienteFilter.addEventListener('focus', function() {
            clienteOptions.style.display = 'block';
        });
        
        // Esconder opções ao clicar fora
        document.addEventListener('click', function(e) {
            if (!clienteFilter.contains(e.target) && !clienteOptions.contains(e.target)) {
                clienteOptions.style.display = 'none';
            }
        });
        
        // Selecionar opção ao clicar
        const options = clienteOptions.querySelectorAll('div[data-value]');
        options.forEach(option => {
            option.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                const text = this.textContent;
                
                clienteSelect.value = value;
                clienteFilter.value = text;
                clienteOptions.style.display = 'none';
            });
        });
    }
});

</script>
<script>
// Função para o filtro de marcadores com melhor posicionamento
document.addEventListener('DOMContentLoaded', function() {
    const marcadorFilter = document.getElementById('marcador_filter');
    const marcadorOptions = document.getElementById('marcador_options');
    const marcadorSelect = document.getElementById('marcador_id');
    
    if (marcadorFilter && marcadorOptions) {
        // Função para ajustar posicionamento em telas pequenas
        function adjustOptionsPosition() {
            if (window.innerWidth <= 768) {
                marcadorOptions.style.position = 'fixed';
                marcadorOptions.style.top = '50%';
                marcadorOptions.style.left = '50%';
                marcadorOptions.style.transform = 'translate(-50%, -50%)';
                marcadorOptions.style.width = '90%';
                marcadorOptions.style.maxWidth = '300px';
                marcadorOptions.style.maxHeight = '60vh';
                marcadorOptions.style.zIndex = '9999';
            } else {
                marcadorOptions.style.position = 'absolute';
                marcadorOptions.style.top = '';
                marcadorOptions.style.left = '';
                marcadorOptions.style.transform = '';
                marcadorOptions.style.width = '';
                marcadorOptions.style.maxWidth = '';
                marcadorOptions.style.maxHeight = '200px';
                marcadorOptions.style.zIndex = '1000';
            }
        }
        
        // Ajustar inicialmente
        adjustOptionsPosition();
        
        // Reajustar quando a janela for redimensionada
        window.addEventListener('resize', adjustOptionsPosition);
        
        marcadorFilter.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const options = marcadorOptions.querySelectorAll('div[data-value]');
            let hasResults = false;
            
            options.forEach(option => {
                const text = option.textContent.toLowerCase();
                if (text.includes(filter)) {
                    option.style.display = 'flex';
                    hasResults = true;
                } else {
                    option.style.display = 'none';
                }
            });
            
            // Mostrar mensagem se não houver resultados
            let noResults = marcadorOptions.querySelector('.no-results');
            if (!hasResults) {
                if (!noResults) {
                    noResults = document.createElement('div');
                    noResults.className = 'no-results';
                    noResults.textContent = 'Nenhum marcador encontrado';
                    marcadorOptions.appendChild(noResults);
                }
                noResults.style.display = 'block';
            } else if (noResults) {
                noResults.style.display = 'none';
            }
            
            marcadorOptions.style.display = 'block';
        });
        
        // Mostrar opções ao focar
        marcadorFilter.addEventListener('focus', function() {
            adjustOptionsPosition();
            marcadorOptions.style.display = 'block';
            // Focar no primeiro item
            const firstOption = marcadorOptions.querySelector('div[data-value]:not([style*="display: none"])');
            if (firstOption) {
                firstOption.scrollIntoView({ block: 'nearest' });
            }
        });
        
        // Esconder opções ao clicar fora
        document.addEventListener('click', function(e) {
            if (!marcadorFilter.contains(e.target) && !marcadorOptions.contains(e.target)) {
                marcadorOptions.style.display = 'none';
            }
        });
        
        // Navegação com teclado
        marcadorFilter.addEventListener('keydown', function(e) {
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                const visibleOptions = Array.from(marcadorOptions.querySelectorAll('div[data-value]:not([style*="display: none"])'));
                
                if (visibleOptions.length > 0) {
                    let currentIndex = -1;
                    
                    // Encontrar opção atualmente focada
                    visibleOptions.forEach((option, index) => {
                        if (option.classList.contains('focused')) {
                            currentIndex = index;
                        }
                    });
                    
                    if (e.key === 'ArrowDown') {
                        currentIndex = (currentIndex + 1) % visibleOptions.length;
                    } else if (e.key === 'ArrowUp') {
                        currentIndex = (currentIndex - 1 + visibleOptions.length) % visibleOptions.length;
                    }
                    
                    // Remover foco anterior e aplicar novo
                    visibleOptions.forEach(option => option.classList.remove('focused'));
                    visibleOptions[currentIndex].classList.add('focused');
                    visibleOptions[currentIndex].scrollIntoView({ block: 'nearest' });
                }
            } else if (e.key === 'Enter') {
                e.preventDefault();
                const focusedOption = marcadorOptions.querySelector('div.focused');
                if (focusedOption) {
                    focusedOption.click();
                }
            }
        });
        
        // Selecionar opção ao clicar
        const options = marcadorOptions.querySelectorAll('div[data-value]');
        options.forEach(option => {
            option.addEventListener('click', function() {
                const value = this.getAttribute('data-value');
                const text = this.textContent;
                
                marcadorSelect.value = value;
                marcadorFilter.value = text.trim();
                marcadorOptions.style.display = 'none';
                
                // Remover classe focused
                options.forEach(opt => opt.classList.remove('focused'));
            });
            
            // Efeito hover
            option.addEventListener('mouseenter', function() {
                options.forEach(opt => opt.classList.remove('focused'));
                this.classList.add('focused');
            });
        });
    }
});

// JavaScript para o botão limpar previsão de liberação
document.addEventListener('DOMContentLoaded', function() {
    const limparBtn = document.getElementById('limparPrevisao');
    const previsaoInput = document.getElementById('previsaoInput');
    
    if (limparBtn && previsaoInput) {
        limparBtn.addEventListener('click', function() {
            previsaoInput.value = '';
        });
    }
});

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
</body>
</html>