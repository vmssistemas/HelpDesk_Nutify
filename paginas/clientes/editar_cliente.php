<?php
ob_start();
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

// Configuração para evitar erros no JSON
error_reporting(0);
ini_set('display_errors', 0);

$id = $_GET['id'];

// Busca o cliente pelo ID
$query = "SELECT * FROM clientes WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$cliente = $result->fetch_assoc();

// Busca todos os planos disponíveis
$query_planos = "SELECT * FROM planos ORDER BY nome";
$planos = $conn->query($query_planos)->fetch_all(MYSQLI_ASSOC);

// Busca todos os módulos disponíveis
$query_modulos = "SELECT * FROM modulos ORDER BY nome";
$modulos = $conn->query($query_modulos)->fetch_all(MYSQLI_ASSOC);

// Busca todos os grupos disponíveis
$query_grupos = "SELECT * FROM grupos ORDER BY nome";
$grupos = $conn->query($query_grupos)->fetch_all(MYSQLI_ASSOC);

// Busca todos os contatos disponíveis
$query_contatos = "SELECT * FROM contatos ORDER BY nome";
$contatos = $conn->query($query_contatos)->fetch_all(MYSQLI_ASSOC);

// Busca contatos vinculados ao cliente
$query_contatos_vinculados = "SELECT cc.contato_id, cc.tipo_relacao, c.nome, c.telefone 
                              FROM cliente_contato cc 
                              JOIN contatos c ON cc.contato_id = c.id 
                              WHERE cc.cliente_id = ?";
$stmt_contatos = $conn->prepare($query_contatos_vinculados);
$stmt_contatos->bind_param("i", $id);
$stmt_contatos->execute();
$result_contatos = $stmt_contatos->get_result();
$contatos_vinculados = $result_contatos->fetch_all(MYSQLI_ASSOC);

// Array com todas as UFs do Brasil
$ufs = [
    'AC'=>'Acre', 'AL'=>'Alagoas', 'AP'=>'Amapá', 'AM'=>'Amazonas',
    'BA'=>'Bahia', 'CE'=>'Ceará', 'DF'=>'Distrito Federal',
    'ES'=>'Espírito Santo', 'GO'=>'Goiás', 'MA'=>'Maranhão',
    'MT'=>'Mato Grosso', 'MS'=>'Mato Grosso do Sul', 'MG'=>'Minas Gerais',
    'PA'=>'Pará', 'PB'=>'Paraíba', 'PR'=>'Paraná', 'PE'=>'Pernambuco',
    'PI'=>'Piauí', 'RJ'=>'Rio de Janeiro', 'RN'=>'Rio Grande do Norte',
    'RS'=>'Rio Grande do Sul', 'RO'=>'Rondônia', 'RR'=>'Roraima',
    'SC'=>'Santa Catarina', 'SP'=>'São Paulo', 'SE'=>'Sergipe',
    'TO'=>'Tocantins'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dados = [
        'nome' => $_POST['nome'],
        'email' => $_POST['email'] ?? '',
        'telefone' => $_POST['telefone'],
        'cnpj' => $_POST['cnpj'],
        'contrato' => $_POST['contrato'],
        'uf' => $_POST['uf'],
        'plano' => $_POST['plano'],
         'data_inativacao' => !empty($_POST['data_inativacao']) ? $_POST['data_inativacao'] : null,
        'modulos' => implode(",", $_POST['modulos'] ?? []),
        'id_grupo' => !empty($_POST['grupo']) ? $_POST['grupo'] : null,
        'observacoes' => $_POST['observacoes'] ?? '',
        'observacoes_config' => $_POST['observacoes_config'] ?? '' // Adicionado
    ];

    // Verifica se o CNPJ já existe em outro cliente (apenas se o CNPJ não estiver vazio)
    if (!empty($dados['cnpj'])) {
        $stmt = $conn->prepare("SELECT id FROM clientes WHERE cnpj = ? AND id != ?");
        $stmt->bind_param("si", $dados['cnpj'], $id);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows > 0) {
            ob_end_clean();
            echo json_encode(['status'=>'error', 'message'=>'CNPJ já cadastrado em outro cliente']);
            exit();
        }
    }

    // Verifica se o Contrato já existe em outro cliente
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE contrato = ? AND id != ?");
    $stmt->bind_param("si", $dados['contrato'], $id);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['status'=>'error', 'message'=>'Número de contrato já cadastrado em outro cliente']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // Atualiza cliente - MODIFICAR ESTA QUERY
 $stmt = $conn->prepare("UPDATE clientes SET nome=?, email=?, telefone=?, cnpj=?, contrato=?, uf=?, plano=?, modulos=?, id_grupo=?, observacoes=?, observacoes_config=?, data_inativacao=? WHERE id=?");
$stmt->bind_param("ssssssssisssi", 
    $dados['nome'], 
    $dados['email'], 
    $dados['telefone'], 
    $dados['cnpj'], 
    $dados['contrato'], 
    $dados['uf'], 
    $dados['plano'], 
    $dados['modulos'], 
    $dados['id_grupo'], 
    $dados['observacoes'],
    $dados['observacoes_config'],
    $dados['data_inativacao'],
    $id
);
        $stmt->execute();

        // Remove vínculos antigos
        $stmt = $conn->prepare("DELETE FROM cliente_contato WHERE cliente_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        // Adiciona novos vínculos
        foreach ($_POST['contatos'] ?? [] as $contato_id => $tipo_relacao) {
            $stmt = $conn->prepare("INSERT INTO cliente_contato (cliente_id,contato_id,tipo_relacao) VALUES (?,?,?)");
            $stmt->bind_param("iis", $id, $contato_id, $tipo_relacao);
            $stmt->execute();
        }

        $conn->commit();
        ob_end_clean();
        echo json_encode(['status'=>'success', 'message'=>'Cliente atualizado com sucesso!']);
    } catch (Exception $e) {
        $conn->rollback();
        ob_end_clean();
        echo json_encode(['status'=>'error', 'message'=>'Erro: '.$e->getMessage()]);
    }
    exit();
}
ob_end_flush();
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Cliente</title>
    <link rel="icon" href="../../assets/img/icone_verde.ico">
   <link rel="stylesheet" href="../../assets/css/cliente.css?v=<?=filemtime('../../assets/css/cliente.css')?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
</head>
<body>
<header>
    <div class="client-tabs-container">
       <h1>Editando Cliente: <?=htmlspecialchars($cliente['contrato'])?> - <?=htmlspecialchars($cliente['nome'])?></h1>
<nav class="client-tabs">
    <a href="clientes.php" class="back-link"><i class="fas fa-arrow-left"></i> Voltar</a>
    <a href="#" class="client-tab active" data-tab="dados"><i class="fas fa-user"></i> Dados Cadastrais</a>
    <a href="#" class="client-tab" data-tab="chamados"><i class="fas fa-ticket-alt"></i> Chamados</a>
    <a href="#" class="client-tab" data-tab="atendimentos"><i class="fas fa-headset"></i> Atendimentos</a>
       <a href="#" class="client-tab" data-tab="treinamentos"><i class="fas fa-graduation-cap"></i> Treinamentos</a>
    <a href="#" class="client-tab" data-tab="instalacoes"><i class="fas fa-cogs"></i> Instalações</a>
</nav>
    </div>
</header>
  <div id="mensagem"></div>

<main class="cadastro-cliente">
    <!-- Aba de Dados Cadastrais -->
    <div id="dados-tab" class="tab-content-container active">
        <form id="form-editar" method="POST">
              <form id="form-editar" method="POST">
        <div class="form-container">
            <!-- Coluna Esquerda - Campos Cadastrais -->
            <div class="form-main-fields">
                <h2 class="section-title">Dados Cadastrais</h2>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="contrato">Nº Contrato</label>
                        <input type="text" id="contrato" name="contrato" value="<?=htmlspecialchars($cliente['contrato'])?>" maxlength="10" required
                               placeholder="000.000.00" title="Formato: 000.000.00">
                    </div>
                    <div class="form-group">
                        <label for="nome">Nome</label>
                        <input type="text" id="nome" name="nome" value="<?=htmlspecialchars($cliente['nome'])?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="cnpj">CNPJ</label>
                        <input type="text" id="cnpj" name="cnpj" value="<?=htmlspecialchars($cliente['cnpj'])?>" maxlength="18">
                    </div>
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" value="<?=htmlspecialchars($cliente['email'])?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="telefone" id="label-telefone" style="cursor: pointer; color: #007bff; text-decoration: underline;" title="Clique para abrir o PlugChat">Telefone</label>
                        <input type="text" id="telefone" name="telefone" value="<?=htmlspecialchars($cliente['telefone'])?>" maxlength="15" required>
                    </div>
                    <div class="form-group">
                        <label for="uf">UF</label>
                        <select id="uf" name="uf" required>
                            <option value="">Selecione</option>
                            <?php foreach($ufs as $sigla=>$nome): ?>
                            <option value="<?=$sigla?>" <?=$cliente['uf']==$sigla?'selected':''?>><?="$sigla - $nome"?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="grupo">Grupo</label>
                        <select id="grupo" name="grupo">
                            <option value="">Selecione</option>
                            <?php foreach($grupos as $grupo): ?>
                            <option value="<?=$grupo['id']?>" <?=$cliente['id_grupo']==$grupo['id']?'selected':''?>><?=htmlspecialchars($grupo['nome'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="plano">Plano</label>
                        <select id="plano" name="plano" required>
                            <option value="">Selecione</option>
                            <?php foreach($planos as $plano): ?>
                            <option value="<?=$plano['id']?>" <?=$cliente['plano']==$plano['id']?'selected':''?>><?=htmlspecialchars($plano['nome'])?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
    <div class="form-group">
        <label for="data_inativacao">Data de Inativação</label>
        <input type="date" id="data_inativacao" name="data_inativacao" 
               value="<?= !empty($cliente['data_inativacao']) ? htmlspecialchars($cliente['data_inativacao']) : '' ?>">
    </div>
</div>

                <div class="modulos-container">
                    <fieldset>
                        <legend>Módulos</legend>
                        <div class="checkbox-group">
                            <?php 
                            $modulosCliente = explode(",", $cliente['modulos']);
                            foreach($modulos as $modulo): ?>
                            <label>
                                <input type="checkbox" name="modulos[]" value="<?=$modulo['id']?>" <?=in_array($modulo['id'], $modulosCliente)?'checked':''?>>
                                <?=htmlspecialchars($modulo['nome'])?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </fieldset>
                </div>
            </div>

            <!-- Coluna Direita - Abas de Observações -->
            <div class="form-secondary-fields">
                <div class="tabs-container">
                    <div class="tabs-header">
                        <button type="button" class="tab-button active" data-tab="contatos">Contatos</button>
                        <button type="button" class="tab-button" data-tab="observacoes">Observações</button>
                        <button type="button" class="tab-button" data-tab="configuracoes">Configurações</button>
                    </div>
                    
                    <div class="tab-content active" id="contatos-tab">
                        <div class="contatos-container">
                            <div class="contatos-header">
                                <h3>Contatos Vinculados</h3>
                                <button type="button" onclick="abrirModalContatos()">
                                    <i class="fas fa-plus"></i> Adicionar
                                </button>
                            </div>
                            <div class="contatos-list" id="contatos-vinculados">
                                <?php if(empty($contatos_vinculados)): ?>
                                    <p>Nenhum contato vinculado</p>
                                <?php else: ?>
                                    <?php foreach($contatos_vinculados as $contato): ?>
                                    <div class="contato-item">
                                        <div class="contato-info">
                                            <strong><?=htmlspecialchars($contato['nome'])?></strong> - 
                                            <span class="contato-telefone" style="cursor: pointer; color: #007bff; text-decoration: underline;" 
                                                  title="Clique para abrir o PlugChat" 
                                                  onclick="abrirPlugChatContato('<?=htmlspecialchars($contato['telefone'])?>')">
                                                <?=htmlspecialchars($contato['telefone'])?>
                                            </span>
                                            <span class="contato-tipo">(<?=htmlspecialchars($contato['tipo_relacao'])?>)</span>
                                        </div>
                                        <div>
                                            <button type="button" onclick="removerContato(<?=$contato['contato_id']?>)" style="color:red">
                                                <i class="fas fa-times"></i>
                                            </button>
                                            <input type="hidden" name="contatos[<?=$contato['contato_id']?>]" value="<?=htmlspecialchars($contato['tipo_relacao'])?>">
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="observacoes-tab">
                        <div class="form-group">
                            <textarea id="observacoes" name="observacoes" style="display:none;"><?=htmlspecialchars($cliente['observacoes'])?></textarea>
                            <div id="editor-observacoes"></div>
                        </div>
                    </div>
                    
                    <div class="tab-content" id="configuracoes-tab">
                        <div class="form-group">
                            <textarea id="observacoes_config" name="observacoes_config" style="display:none;"><?=htmlspecialchars($cliente['observacoes_config'] ?? '')?></textarea>
                            <div id="editor-observacoes-config"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="button-container">
            <button type="submit">Atualizar Cliente</button>
        </div>
    </form>
        </form>
    </div>
    
    <!-- Aba de Chamados -->
    <div id="chamados-tab" class="tab-content-container">
        <div class="chamados-container">
            <div class="chamados-header">
                <h3><a href="../../chamados/index.php" target="_blank" style="color: inherit;  cursor: pointer;" title="Clique para acessar a página de Chamados">Chamados do Cliente</a></h3>
                <div class="chamados-stats">
                    <span class="stat-item"><i class="fas fa-list"></i> Total: <span id="total-chamados">0</span></span>
                    <span class="stat-item"><i class="fas fa-spinner"></i> Em Aberto: <span id="abertos-chamados">0</span></span>
                    <span class="stat-item"><i class="fas fa-check-circle"></i> Resolvidos: <span id="resolvidos-chamados">0</span></span>
                </div>
            </div>
            
            <div class="chamados-list-container">
                <table id="lista-chamados">
                    <thead>
                        <tr>
                            <th>Nº</th>
                            <th>Título</th>
                            <th>Status</th>
                            <th>Prioridade</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody id="chamados-list">
                        <!-- Os chamados serão carregados via AJAX -->
                    </tbody>
                </table>
            </div>
            
            <div class="chamados-pagination">
                <button id="prev-page" disabled><i class="fas fa-chevron-left"></i></button>
                <span id="page-info">Página 1</span>
                <button id="next-page" disabled><i class="fas fa-chevron-right"></i></button>
            </div>
        </div>
        
    </div>
   <!-- Aba de Atendimentos -->
<div id="atendimentos-tab" class="tab-content-container">
    <div class="atendimentos-container">
        <div class="atendimentos-header">
            <h3><a href="../atendimento/atendimento.php" target="_blank" style="color: inherit; cursor: pointer;" title="Clique para acessar a página de Atendimentos">Atendimentos do Cliente</a></h3>
            <div class="atendimentos-stats">
                <span class="stat-item"><i class="fas fa-list"></i> Total: <span id="total-atendimentos">0</span></span>
                <span class="stat-item"><i class="fas fa-clock"></i> Últimos 30 dias: <span id="recentes-atendimentos">0</span></span>
            </div>
        </div>
        
        <div class="atendimentos-list-container">
            <table id="lista-atendimentos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Data</th>
                        <th>Menu</th>
                        <th>Submenu</th>
                        <th>Tipo</th>
                        <th>Dificuldade</th>
                        <th>Descrição</th>
                        <th>Usuário</th>
                    </tr>
                </thead>
                <tbody id="atendimentos-list">
                    <!-- Os atendimentos serão carregados via AJAX -->
                </tbody>
            </table>
        </div>
        
        <div class="atendimentos-pagination">
            <button id="prev-page-atendimentos" disabled><i class="fas fa-chevron-left"></i></button>
            <span id="page-info-atendimentos">Página 1</span>
            <button id="next-page-atendimentos" disabled><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</div>

<!-- Aba de Treinamentos -->
<div id="treinamentos-tab" class="tab-content-container">
    <div class="treinamentos-container">
        <div class="treinamentos-header">
            <h3><a href="../../treinamentos/index.php" target="_blank" style="color: inherit;  cursor: pointer;" title="Clique para acessar a página de Treinamentos">Treinamentos do Cliente</a></h3>
            <div class="treinamentos-stats">
                <span class="stat-item"><i class="fas fa-list"></i> Total: <span id="total-treinamentos">0</span></span>
                <span class="stat-item"><i class="fas fa-calendar-check"></i> Concluídos: <span id="concluidos-treinamentos">0</span></span>
                <span class="stat-item"><i class="fas fa-calendar-alt"></i> Agendados: <span id="agendados-treinamentos">0</span></span>
            </div>
        </div>
        
        <div class="treinamentos-list-container">
            <table id="lista-treinamentos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Plano</th>
                        <th>Data</th>
                        <th>Responsável</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="treinamentos-list">
                    <!-- Os treinamentos serão carregados via AJAX -->
                </tbody>
            </table>
        </div>
        
        <div class="treinamentos-pagination">
            <button id="prev-page-treinamentos" disabled><i class="fas fa-chevron-left"></i></button>
            <span id="page-info-treinamentos">Página 1</span>
            <button id="next-page-treinamentos" disabled><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</div>
    <!-- Aba de Instalações -->
<div id="instalacoes-tab" class="tab-content-container">
    <div class="instalacoes-container">
        <div class="instalacoes-header">
            <h3><a href="../../instalacoes/index.php" target="_blank" style="color: inherit;  cursor: pointer;" title="Clique para acessar a página de Instalações">Instalações do Cliente</a></h3>
            <div class="instalacoes-stats">
                <span class="stat-item"><i class="fas fa-list"></i> Total: <span id="total-instalacoes">0</span></span>
                <span class="stat-item"><i class="fas fa-calendar-check"></i> Concluídas: <span id="concluidas-instalacoes">0</span></span>
                <span class="stat-item"><i class="fas fa-calendar-alt"></i> Agendadas: <span id="agendadas-instalacoes">0</span></span>
            </div>
        </div>
        
        <div class="instalacoes-list-container">
            <table id="lista-instalacoes">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Tipo</th>
                        <th>Plano</th>
                        <th>Data</th>
                        <th>Responsável</th>
                        <th>Status</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody id="instalacoes-list">
                    <!-- As instalações serão carregadas via AJAX -->
                </tbody>
            </table>
        </div>
        
        <div class="instalacoes-pagination">
            <button id="prev-page-instalacoes" disabled><i class="fas fa-chevron-left"></i></button>
            <span id="page-info-instalacoes">Página 1</span>
            <button id="next-page-instalacoes" disabled><i class="fas fa-chevron-right"></i></button>
        </div>
    </div>
</div>
</main>



    <!-- Modal Contatos -->
    <div id="modal-contatos" class="modal">
        <div class="modal-content">
            <span class="close" onclick="fecharModalContatos()">&times;</span>
            <h2>Selecionar Contatos</h2>
            <div style="margin-bottom:10px">
                <input type="text" id="filtro-contato" placeholder="Filtrar contatos..." style="width:100%;padding:6px">
            </div>
            <div style="max-height:50vh;overflow-y:auto">
                <table id="lista-contatos" style="width:100%">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Telefone</th>
                            <th>Tipo</th>
                            <th>Ação</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($contatos as $contato): 
                            $tel = preg_replace('/[^0-9]/','',$contato['telefone']);
                            $tel_formatado = strlen($tel)===11 ? 
                                preg_replace('/(\d{2})(\d{5})(\d{4})/','($1) $2-$3',$tel) :
                                (strlen($tel)===10 ? preg_replace('/(\d{2})(\d{4})(\d{4})/','($1) $2-$3',$tel) : $contato['telefone']);
                        ?>
                        <tr data-id="<?=$contato['id']?>">
                            <td><?=htmlspecialchars($contato['nome'])?></td>
                            <td><?=$tel_formatado?></td>
                            <td>
                                <select name="tipo_relacao[<?=$contato['id']?>]" style="padding:4px">
                                    <option value="dono">Dono</option>
                                    <option value="gerente">Gerente</option>
                                    <option value="funcionario">Funcionário</option>
                                    <option value="outros">Outros</option>
                                </select>
                            </td>
                            <td>
                                <button type="button" onclick="adicionarContato(<?=$contato['id']?>,'<?=htmlspecialchars(addslashes($contato['nome']))?>','<?=htmlspecialchars(addslashes($contato['telefone']))?>')">
                                    Adicionar
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    // Variáveis globais
    const contatosSelecionados = {};
    
    // Inicializar contatos já vinculados
    <?php foreach($contatos_vinculados as $contato): ?>
        contatosSelecionados[<?=$contato['contato_id']?>] = {
            tipo: '<?=$contato['tipo_relacao']?>',
            nome: '<?=htmlspecialchars(addslashes($contato['nome']))?>',
            telefone: '<?=htmlspecialchars(addslashes($contato['telefone']))?>'
        };
    <?php endforeach; ?>
    
    // Formatação de campos
    function formatarCNPJ(cnpj) {
        return cnpj.replace(/\D/g,'')
            .replace(/^(\d{2})(\d)/,'$1.$2')
            .replace(/^(\d{2})\.(\d{3})(\d)/,'$1.$2.$3')
            .replace(/\.(\d{3})(\d)/,'.$1/$2')
            .replace(/(\d{4})(\d)/,'$1-$2');
    }

    function formatarContrato(contrato) {
        // Remove tudo que não é dígito
        contrato = contrato.replace(/\D/g,'');
        
        // Aplica a máscara 000.000.00
        if (contrato.length > 6) {
            contrato = contrato.substring(0, 6) + '.' + contrato.substring(6);
        }
        if (contrato.length > 3) {
            contrato = contrato.substring(0, 3) + '.' + contrato.substring(3);
        }
        
        return contrato;
    }

    function formatarTelefone(tel) {
        tel = tel.replace(/\D/g,'');
        return tel.length===11 ? tel.replace(/(\d{2})(\d{5})(\d{4})/,'($1) $2-$3') :
               tel.length===10 ? tel.replace(/(\d{2})(\d{4})(\d{4})/,'($1) $2-$3') : tel;
    }

    // Validação UF
    document.getElementById('uf').addEventListener('change', function(e) {
        if(e.target.value.length!==2 && e.target.value!=='') {
            alert('UF deve ter 2 caracteres');
            e.target.value = '';
        }
    });

    // Validação Contrato
    function validarContrato(contrato) {
        // Verifica o formato 000.000.00
        const regex = /^\d{3}\.\d{3}\.\d{2}$/;
        return regex.test(contrato);
    }

    // Aplicar máscaras
    document.getElementById('cnpj').addEventListener('input', function(e) {
        e.target.value = formatarCNPJ(e.target.value);
    });

    document.getElementById('contrato').addEventListener('input', function(e) {
        e.target.value = formatarContrato(e.target.value);
        
        // Validação em tempo real
        if (!validarContrato(e.target.value) && e.target.value.length >= 10) {
            alert('O número do contrato deve estar no formato 000.000.00');
            e.target.value = '';
        }
    });

    document.getElementById('telefone').addEventListener('input', function(e) {
        e.target.value = formatarTelefone(e.target.value);
    });

    // Modal de contatos
    function abrirModalContatos() {
        document.getElementById('modal-contatos').style.display = 'block';
    }

    function fecharModalContatos() {
        document.getElementById('modal-contatos').style.display = 'none';
    }

    // Filtro de contatos
    document.getElementById('filtro-contato').addEventListener('input', function(e) {
        const filtro = e.target.value.toLowerCase();
        document.querySelectorAll('#lista-contatos tbody tr').forEach(tr => {
            const nome = tr.cells[0].textContent.toLowerCase();
            const tel = tr.cells[1].textContent.toLowerCase();
            tr.style.display = (nome.includes(filtro) || tel.includes(filtro)) ? '' : 'none';
        });
    });

    // Gerenciamento de contatos
    function adicionarContato(id, nome, telefone) {
        const tipo = document.querySelector(`select[name="tipo_relacao[${id}]"]`).value;
        contatosSelecionados[id] = {
            tipo: tipo,
            nome: nome,
            telefone: formatarTelefone(telefone.replace(/\D/g,''))
        };
        atualizarContatosVinculados();
        fecharModalContatos();
    }

    function removerContato(id) {
        delete contatosSelecionados[id];
        atualizarContatosVinculados();
    }

    function atualizarContatosVinculados() {
        const container = document.getElementById('contatos-vinculados');
        if(Object.keys(contatosSelecionados).length === 0) {
            container.innerHTML = '<p>Nenhum contato vinculado</p>';
            return;
        }
        
        let html = '';
        for(const [id, contato] of Object.entries(contatosSelecionados)) {
            html += `
            <div class="contato-item">
                <div class="contato-info">
                    <strong>${contato.nome}</strong> - 
                    <span class="contato-telefone" style="cursor: pointer; color: #007bff; text-decoration: underline;" 
                          title="Clique para abrir o PlugChat" 
                          onclick="abrirPlugChatContato('${contato.telefone}')">
                        ${contato.telefone}
                    </span>
                    <span class="contato-tipo">(${contato.tipo})</span>
                </div>
                <div>
                    <button type="button" onclick="removerContato(${id})" style="color:red">
                        <i class="fas fa-times"></i>
                    </button>
                    <input type="hidden" name="contatos[${id}]" value="${contato.tipo}">
                </div>
            </div>`;
        }
        container.innerHTML = html;
    }
// Controle das abas
document.querySelectorAll('.tab-button').forEach(button => {
    button.addEventListener('click', function() {
        // Remove classe active de todos os botões e conteúdos
        document.querySelectorAll('.tab-button').forEach(btn => btn.classList.remove('active'));
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        
        // Adiciona classe active ao botão clicado
        this.classList.add('active');
        
        // Mostra o conteúdo correspondente
        const tabId = this.getAttribute('data-tab') + '-tab';
        document.getElementById(tabId).classList.add('active');
        
        // Ajusta a altura dos editores quando a aba é aberta
        if (tabId === 'observacoes-tab' || tabId === 'configuracoes-tab') {
            setTimeout(() => {
                const editor = document.querySelector(`#${tabId} .ck-editor__editable`);
                if (editor) {
                    editor.style.minHeight = '300px';
                }
            }, 100);
        }
    });
});

    // CKEditor
    ClassicEditor.create(document.querySelector('#editor-observacoes'), {
        toolbar: ['bold','italic','link','bulletedList','numberedList','|','undo','redo'],
        wordWrap: true
    }).then(editor => {
        editor.setData(document.querySelector('#observacoes').value);
        editor.model.document.on('change:data', () => {
            document.querySelector('#observacoes').value = editor.getData();
        });
    }).catch(console.error);

    // CKEditor para observações de configurações
ClassicEditor.create(document.querySelector('#editor-observacoes-config'), {
    toolbar: ['bold','italic','link','bulletedList','numberedList','|','undo','redo'],
    wordWrap: true
}).then(editor => {
    editor.setData(document.querySelector('#observacoes_config').value);
    editor.model.document.on('change:data', () => {
        document.querySelector('#observacoes_config').value = editor.getData();
    });
}).catch(console.error);

    // Envio do formulário
    document.getElementById('form-editar').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validação do contrato antes de enviar
        const contrato = document.getElementById('contrato').value;
        if (!validarContrato(contrato)) {
            alert('O número do contrato deve estar no formato 000.000.00');
            return;
        }

        const formData = new FormData(this);
        
        fetch('editar_cliente.php?id=<?=$id?>', {
            method: 'POST',
            body: formData
        })
        .then(res => res.json())
        .then(data => {
            const msg = document.getElementById('mensagem');
            msg.style.display = 'block';
            msg.textContent = data.message;
            msg.style.backgroundColor = data.status==='success' ? '#d4edda' : '#f8d7da';
            msg.style.color = data.status==='success' ? '#155724' : '#721c24';
            
            if(data.status==='success') {
                setTimeout(() => window.location.href='clientes.php', 1000);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Ocorreu um erro ao enviar o formulário');
        });
    });

    // Tecla ESC para voltar
    document.addEventListener('keydown', e => {
        if(e.keyCode===27) window.history.back();
    });
    // Controle das abas
// Controle das abas
document.querySelectorAll('.client-tab').forEach(tab => {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        
        // Remove a classe active de todas as abas e conteúdos
        document.querySelectorAll('.client-tab').forEach(t => t.classList.remove('active'));
        document.querySelectorAll('.tab-content-container').forEach(c => c.classList.remove('active'));
        
        // Adiciona a classe active à aba clicada
        this.classList.add('active');
        
        // Mostra o conteúdo correspondente
        const tabId = this.getAttribute('data-tab') + '-tab';
        document.getElementById(tabId).classList.add('active');
        
        // Carrega os dados conforme a aba selecionada
        if (this.getAttribute('data-tab') === 'chamados') {
            carregarChamadosCliente();
        } else if (this.getAttribute('data-tab') === 'instalacoes') {
            carregarInstalacoesCliente();
        } else if (this.getAttribute('data-tab') === 'treinamentos') {
            carregarTreinamentosCliente();
        } else if (this.getAttribute('data-tab') === 'atendimentos') {
            carregarAtendimentosCliente();
        }
    });
});

// Variáveis para paginação
let currentPage = 1;
const itemsPerPage = 10;

// Função para carregar os chamados do cliente
function carregarChamadosCliente() {
    const clienteId = <?= $id ?>;
    
    fetch(`./api/get_chamados_cliente.php?cliente_id=${clienteId}&page=${currentPage}&per_page=${itemsPerPage}`)

        .then(response => response.json())
        .then(data => {
            const chamadosList = document.getElementById('chamados-list');
            chamadosList.innerHTML = '';
            
            // Atualiza estatísticas
            document.getElementById('total-chamados').textContent = data.total;
            document.getElementById('abertos-chamados').textContent = data.abertos;
            document.getElementById('resolvidos-chamados').textContent = data.resolvidos;
            
            // Preenche a tabela com os chamados
            data.chamados.forEach(chamado => {
                const row = document.createElement('tr');
                
                // Formata a data
                const dataCriacao = new Date(chamado.data_criacao);
                const dataFormatada = dataCriacao.toLocaleDateString('pt-BR');
                
                // Determina a cor do status
                let statusClass = '';
                if (chamado.status_id === 5) { // Concluído
                    statusClass = 'bg-success';
                } else if ([3, 4, 7, 8, 9].includes(chamado.status_id)) { // Em andamento
                    statusClass = 'bg-warning text-dark';
                } else if ([11].includes(chamado.status_id)) { // Aplicar no cliente
                    statusClass = 'bg-danger';
                } else { // Outros
                    statusClass = 'bg-primary';
                }
                
                // Determina a cor da prioridade
                let priorityClass = '';
                if (chamado.prioridade_id === 1) { // Baixa
                    priorityClass = 'bg-info';
                } else if (chamado.prioridade_id === 2) { // Média
                    priorityClass = 'bg-warning text-dark';
                } else if (chamado.prioridade_id === 3) { // Alta
                    priorityClass = 'bg-danger';
                } else if (chamado.prioridade_id === 4) { // Urgente
                    priorityClass = 'bg-dark';
                }
                
                row.innerHTML = `
                    <td>#${chamado.id}</td>
                    <td><a href="../../chamados/visualizar.php?id=${chamado.id}">${chamado.titulo}</a></td>
                    <td><span class="badge ${statusClass} status-badge">${chamado.status_nome}</span></td>
                    <td><span class="badge ${priorityClass} priority-badge">${chamado.prioridade_nome}</span></td>
                    <td>${dataFormatada}</td>
                    <td>
                        <a href="../../chamados/visualizar.php?id=${chamado.id}" class="btn-action" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="../../chamados/editar.php?id=${chamado.id}" class="btn-action" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                    </td>
                `;
                
                chamadosList.appendChild(row);
            });
            
            // Atualiza controles de paginação
            document.getElementById('page-info').textContent = `Página ${currentPage} de ${Math.ceil(data.total / itemsPerPage)}`;
            document.getElementById('prev-page').disabled = currentPage === 1;
            document.getElementById('next-page').disabled = currentPage === Math.ceil(data.total / itemsPerPage);
        })
        .catch(error => {
            console.error('Erro ao carregar chamados:', error);
            document.getElementById('chamados-list').innerHTML = `
                <tr>
                    <td colspan="6" class="text-center text-muted">Erro ao carregar chamados</td>
                </tr>
            `;
        });
}

// Event listeners para paginação
document.getElementById('prev-page').addEventListener('click', () => {
    if (currentPage > 1) {
        currentPage--;
        carregarChamadosCliente();
    }
});

document.getElementById('next-page').addEventListener('click', () => {
    currentPage++;
    carregarChamadosCliente();
});
// Variáveis para paginação de atendimentos
let currentPageAtendimentos = 1;
const itemsPerPageAtendimentos = 10;

// Função para carregar os atendimentos do cliente
function carregarAtendimentosCliente() {
    const clienteId = <?= $id ?>;
    
    fetch(`./api/get_atendimentos_cliente.php?cliente_id=${clienteId}&page=${currentPageAtendimentos}&per_page=${itemsPerPageAtendimentos}`)
        .then(response => response.json())
        .then(data => {
            const atendimentosList = document.getElementById('atendimentos-list');
            atendimentosList.innerHTML = '';
            
            // Atualiza estatísticas
            document.getElementById('total-atendimentos').textContent = data.total;
            document.getElementById('recentes-atendimentos').textContent = data.recentes;
            
            // Preenche a tabela com os atendimentos
            data.atendimentos.forEach(atendimento => {
                const row = document.createElement('tr');
                
                // Formata a data
                const dataAtendimento = new Date(atendimento.data_atendimento);
                const dataFormatada = dataAtendimento.toLocaleDateString('pt-BR') + ' ' + dataAtendimento.toLocaleTimeString('pt-BR');
                
                row.innerHTML = `
                    <td>${atendimento.id}</td>
                    <td>${dataFormatada}</td>
                    <td>${atendimento.menu_nome}</td>
                    <td>${atendimento.submenu_nome}</td>
                    <td>${atendimento.tipo_erro_descricao}</td>
                    <td>
                        <span class="nivel-dificuldade" style="background-color: ${atendimento.nivel_dificuldade_cor}">
                            ${atendimento.nivel_dificuldade_nome}
                        </span>
                    </td>
                    <td class="descricao-atendimento" title="${atendimento.descricao.replace(/"/g, '&quot;')}">
                        ${atendimento.descricao.substring(0, 50)}${atendimento.descricao.length > 50 ? '...' : ''}
                    </td>
                    <td>${atendimento.usuario_email}</td>
                `;
                
                atendimentosList.appendChild(row);
            });
            
            // Atualiza controles de paginação
            document.getElementById('page-info-atendimentos').textContent = `Página ${currentPageAtendimentos} de ${Math.ceil(data.total / itemsPerPageAtendimentos)}`;
            document.getElementById('prev-page-atendimentos').disabled = currentPageAtendimentos === 1;
            document.getElementById('next-page-atendimentos').disabled = currentPageAtendimentos === Math.ceil(data.total / itemsPerPageAtendimentos);
        })
        .catch(error => {
            console.error('Erro ao carregar atendimentos:', error);
            document.getElementById('atendimentos-list').innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted">Erro ao carregar atendimentos</td>
                </tr>
            `;
        });
}

// Event listeners para paginação de atendimentos
document.getElementById('prev-page-atendimentos').addEventListener('click', () => {
    if (currentPageAtendimentos > 1) {
        currentPageAtendimentos--;
        carregarAtendimentosCliente();
    }
});

document.getElementById('next-page-atendimentos').addEventListener('click', () => {
    currentPageAtendimentos++;
    carregarAtendimentosCliente();
});


// Variáveis para paginação de treinamentos
let currentPageTreinamentos = 1;
const itemsPerPageTreinamentos = 10;

// Função para carregar os treinamentos do cliente
function carregarTreinamentosCliente() {
    const clienteId = <?= $id ?>;
    
    fetch(`./api/get_treinamentos_cliente.php?cliente_id=${clienteId}&page=${currentPageTreinamentos}&per_page=${itemsPerPageTreinamentos}`)
        .then(response => response.json())
        .then(data => {
            const treinamentosList = document.getElementById('treinamentos-list');
            treinamentosList.innerHTML = '';
            
            // Atualiza estatísticas
            document.getElementById('total-treinamentos').textContent = data.total;
            document.getElementById('concluidos-treinamentos').textContent = data.concluidos;
            document.getElementById('agendados-treinamentos').textContent = data.agendados;
            
            // Preenche a tabela com os treinamentos
            data.treinamentos.forEach(treinamento => {
                const row = document.createElement('tr');
                
                // Formata a data
                const dataTreinamento = treinamento.data_treinamento ? 
                    new Date(treinamento.data_treinamento).toLocaleDateString('pt-BR') + ' ' + 
                    new Date(treinamento.data_treinamento).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'}) : '-';
                
                row.innerHTML = `
                    <td>#${treinamento.id}</td>
                    <td>
                        <a href="../../treinamentos/visualizar.php?id=${treinamento.id}" style="color: #0d6efd; text-decoration: underline;">
                            ${treinamento.titulo}
                        </a>
                    </td>
                    <td>${treinamento.tipo_nome || '-'}</td>
                    <td>${treinamento.plano_nome || '-'}</td>
                    <td>${dataTreinamento}</td>
                    <td>${treinamento.responsavel_nome || '-'}</td>
                    <td>
                        <span class="badge status-badge" style="background-color: ${treinamento.status_cor || '#6c757d'}">
                            ${treinamento.status_nome || '-'}
                        </span>
                    </td>
                    <td>
                        <a href="../../treinamentos/visualizar.php?id=${treinamento.id}" class="btn-action" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="../../treinamentos/editar.php?id=${treinamento.id}" class="btn-action" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                    </td>
                `;
                
                treinamentosList.appendChild(row);
            });
            
            // Atualiza controles de paginação
            document.getElementById('page-info-treinamentos').textContent = `Página ${currentPageTreinamentos} de ${Math.ceil(data.total / itemsPerPageTreinamentos)}`;
            document.getElementById('prev-page-treinamentos').disabled = currentPageTreinamentos === 1;
            document.getElementById('next-page-treinamentos').disabled = currentPageTreinamentos === Math.ceil(data.total / itemsPerPageTreinamentos);
        })
        .catch(error => {
            console.error('Erro ao carregar treinamentos:', error);
            document.getElementById('treinamentos-list').innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted">Erro ao carregar treinamentos</td>
                </tr>
            `;
        });
}

// Event listeners para paginação de treinamentos
document.getElementById('prev-page-treinamentos').addEventListener('click', () => {
    if (currentPageTreinamentos > 1) {
        currentPageTreinamentos--;
        carregarTreinamentosCliente();
    }
});

document.getElementById('next-page-treinamentos').addEventListener('click', () => {
    currentPageTreinamentos++;
    carregarTreinamentosCliente();
});
// Variáveis para paginação de instalações
let currentPageInstalacoes = 1;
const itemsPerPageInstalacoes = 10;

// Função para carregar as instalações do cliente
function carregarInstalacoesCliente() {
    const clienteId = <?= $id ?>;
    
    fetch(`./api/get_instalacoes_cliente.php?cliente_id=${clienteId}&page=${currentPageInstalacoes}&per_page=${itemsPerPageInstalacoes}`)
        .then(response => response.json())
        .then(data => {
            const instalacoesList = document.getElementById('instalacoes-list');
            instalacoesList.innerHTML = '';
            
            // Atualiza estatísticas
            document.getElementById('total-instalacoes').textContent = data.total;
            document.getElementById('concluidas-instalacoes').textContent = data.concluidas;
            document.getElementById('agendadas-instalacoes').textContent = data.agendadas;
            
            // Preenche a tabela com as instalações
            data.instalacoes.forEach(instalacao => {
                const row = document.createElement('tr');
                
                // Formata a data
                const dataInstalacao = instalacao.data_instalacao ? 
                    new Date(instalacao.data_instalacao).toLocaleDateString('pt-BR') + ' ' + 
                    new Date(instalacao.data_instalacao).toLocaleTimeString('pt-BR', {hour: '2-digit', minute:'2-digit'}) : '-';
                
                // Tipos de instalação
                const tipos = {
                    1: 'Implantação',
                    2: 'Upgrade',
                    3: 'Adicional',
                    4: 'Módulo',
                    5: 'Cancelamento',
                    6: 'Troca CNPJ'
                };
                
                row.innerHTML = `
                    <td>#${instalacao.id}</td>
                    <td>
                        <a href="../../instalacoes/visualizar.php?id=${instalacao.id}" style="color: #0d6efd; text-decoration: underline;">
                            ${instalacao.titulo}
                        </a>
                    </td>
                    <td>${tipos[instalacao.tipo_id] || '-'}</td>
                    <td>${instalacao.plano_nome || '-'}</td>
                    <td>${dataInstalacao}</td>
                    <td>${instalacao.responsavel_nome || '-'}</td>
                    <td>
                        <span class="badge status-badge" style="background-color: ${instalacao.status_cor || '#6c757d'}">
                            ${instalacao.status_nome || '-'}
                        </span>
                    </td>
                    <td>
                        <a href="../../instalacoes/visualizar.php?id=${instalacao.id}" class="btn-action" title="Visualizar">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="../../instalacoes/editar.php?id=${instalacao.id}" class="btn-action" title="Editar">
                            <i class="fas fa-edit"></i>
                        </a>
                    </td>
                `;
                
                instalacoesList.appendChild(row);
            });
            
            // Atualiza controles de paginação
            document.getElementById('page-info-instalacoes').textContent = `Página ${currentPageInstalacoes} de ${Math.ceil(data.total / itemsPerPageInstalacoes)}`;
            document.getElementById('prev-page-instalacoes').disabled = currentPageInstalacoes === 1;
            document.getElementById('next-page-instalacoes').disabled = currentPageInstalacoes === Math.ceil(data.total / itemsPerPageInstalacoes);
        })
        .catch(error => {
            console.error('Erro ao carregar instalações:', error);
            document.getElementById('instalacoes-list').innerHTML = `
                <tr>
                    <td colspan="8" class="text-center text-muted">Erro ao carregar instalações</td>
                </tr>
            `;
        });
}

// Event listeners para paginação de instalações
document.getElementById('prev-page-instalacoes').addEventListener('click', () => {
    if (currentPageInstalacoes > 1) {
        currentPageInstalacoes--;
        carregarInstalacoesCliente();
    }
});

document.getElementById('next-page-instalacoes').addEventListener('click', () => {
    currentPageInstalacoes++;
    carregarInstalacoesCliente();
});
document.getElementById('data_inativacao').addEventListener('focus', function() {
    if (!this.value) {
        const today = new Date().toISOString().split('T')[0];
        this.value = today;
    }
});

// Variável global para controlar a guia do PlugChat
let plugChatTab = null;
let sessionKeepAliveInterval = null;

// Função para manter a sessão ativa
function keepSessionAlive() {
    fetch('keep_session_alive.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'error') {
            console.warn('Sessão expirada, redirecionando para login...');
            window.location.href = '../../login/login.php';
        }
    })
    .catch(error => {
        console.error('Erro ao manter sessão ativa:', error);
    });
}

// Inicia o keep-alive da sessão a cada 5 minutos (300000ms)
function startSessionKeepAlive() {
    if (sessionKeepAliveInterval) {
        clearInterval(sessionKeepAliveInterval);
    }
    sessionKeepAliveInterval = setInterval(keepSessionAlive, 300000); // 5 minutos
}

// Para o keep-alive da sessão
function stopSessionKeepAlive() {
    if (sessionKeepAliveInterval) {
        clearInterval(sessionKeepAliveInterval);
        sessionKeepAliveInterval = null;
    }
}

// Função para verificar se existe uma guia do PlugChat aberta
function verificarPlugChatAberto() {
    // Tenta recuperar a referência da guia do sessionStorage
    const plugChatTabId = sessionStorage.getItem('plugChatTabId');
    if (plugChatTabId && plugChatTab && !plugChatTab.closed) {
        return true;
    }
    return false;
}

// Função para abrir PlugChat sempre reutilizando a mesma guia
function abrirPlugChat(telefone) {
    if (!telefone) {
        alert('Telefone não informado.');
        return;
    }
    
    // Mantém a sessão ativa antes de abrir o PlugChat
    keepSessionAlive();
    
    // Remove caracteres especiais do telefone, mantendo apenas números
    const telefoneNumeros = telefone.replace(/\D/g, '');
    
    if (telefoneNumeros.length < 10) {
        alert('Número de telefone inválido.');
        return;
    }
    
    // Adiciona o código do país (55) se não estiver presente
    let telefoneFormatado = telefoneNumeros;
    if (!telefoneFormatado.startsWith('55')) {
        telefoneFormatado = '55' + telefoneFormatado;
    }
    
    // URL do PlugChat com o telefone
    const plugChatUrl = `https://www.plugchat.com.br/chat/fb33abc3-2894-4df7-95e2-59f6a17febbf?phone=${telefoneFormatado}`;
    
    // Sempre tenta reutilizar a guia existente usando um nome específico
    // O nome 'plugchat_nutify' garante que sempre use a mesma guia
    plugChatTab = window.open(plugChatUrl, 'plugchat_nutify');
    
    if (plugChatTab) {
        // Salva um identificador no sessionStorage para controle
        sessionStorage.setItem('plugChatTabId', 'plugchat_nutify');
        plugChatTab.focus();
    } else {
        alert('Por favor, permita pop-ups para este site para abrir o PlugChat.');
    }
}

// Funcionalidade para abrir PlugChat ao clicar no label do telefone
document.getElementById('label-telefone').addEventListener('click', function() {
    const telefoneInput = document.getElementById('telefone');
    const telefone = telefoneInput.value.trim();
    
    if (!telefone) {
        alert('Por favor, insira um número de telefone primeiro.');
        telefoneInput.focus();
        return;
    }
    
    abrirPlugChat(telefone);
});

// Função para abrir PlugChat para contatos
function abrirPlugChatContato(telefone) {
    abrirPlugChat(telefone);
}

// Inicia o sistema de keep-alive quando a página carrega
document.addEventListener('DOMContentLoaded', function() {
    startSessionKeepAlive();
    
    // Mantém a sessão ativa em atividades do usuário
    const userActivityEvents = ['click', 'keypress', 'scroll', 'mousemove'];
    let lastActivityTime = Date.now();
    
    userActivityEvents.forEach(event => {
        document.addEventListener(event, function() {
            const now = Date.now();
            // Só chama keep-alive se passou mais de 1 minuto desde a última atividade
            if (now - lastActivityTime > 60000) {
                keepSessionAlive();
                lastActivityTime = now;
            }
        }, { passive: true });
    });
});

// Para o keep-alive quando a página é fechada
window.addEventListener('beforeunload', function() {
    stopSessionKeepAlive();
});
    </script>
</body>
</html>