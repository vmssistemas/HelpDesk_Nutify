<?php
session_start();

// Verifica se o usuário está autenticado
if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

// Configuração para evitar avisos do Edge sobre cookies
header('Set-Cookie: SameSite=None; Secure');

require_once '../../config/db.php';

// Busca o ID do usuário logado
$email = $_SESSION['email'];
$query_usuario = "SELECT id, email FROM usuarios WHERE email = ?";
$stmt = $conn->prepare($query_usuario);
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->bind_result($usuario_id, $usuario_email);
$stmt->fetch();
$stmt->close();

// Busca todos os clientes, menus, submenus, tipos de erro, níveis de dificuldade e usuários
$query_clientes = "SELECT id, CONCAT(contrato, ' - ', nome) AS nome_completo, nome, contrato FROM clientes ORDER BY nome";
$result_clientes = $conn->query($query_clientes);
$clientes = $result_clientes->fetch_all(MYSQLI_ASSOC);

$query_menus = "SELECT * FROM menu_atendimento ORDER BY ordem";
$result_menus = $conn->query($query_menus);
$menus = $result_menus->fetch_all(MYSQLI_ASSOC);

$query_submenus = "SELECT * FROM submenu_atendimento ORDER BY menu_id, ordem";
$result_submenus = $conn->query($query_submenus);
$submenus = $result_submenus->fetch_all(MYSQLI_ASSOC);

$query_tipos_erro = "SELECT * FROM tipo_erro ORDER BY descricao";
$result_tipos_erro = $conn->query($query_tipos_erro);
$tipos_erro = $result_tipos_erro->fetch_all(MYSQLI_ASSOC);

$query_niveis = "SELECT * FROM niveis_dificuldade ORDER BY ordem";
$result_niveis = $conn->query($query_niveis);
$niveis = $result_niveis->fetch_all(MYSQLI_ASSOC);

$query_usuarios = "SELECT * FROM usuarios ORDER BY email";
$result_usuarios = $conn->query($query_usuarios);
$usuarios = $result_usuarios->fetch_all(MYSQLI_ASSOC);

$success_message = "";
$cliente_selecionado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $menu_id = $_POST['menu_id'];
    $submenu_id = $_POST['submenu_id'];
    $tipo_erro_id = $_POST['tipo_erro_id'];
    $nivel_dificuldade_id = $_POST['nivel_dificuldade_id'];
    $descricao = $_POST['descricao'];
    $usuario_id = $_POST['usuario_id'];
    $contato_id = !empty($_POST['contato_id']) ? $_POST['contato_id'] : null;

    // Definir timezone para Brasília (UTC-3)
    date_default_timezone_set('America/Sao_Paulo');
    $data_atendimento = date('Y-m-d H:i:s');
    
    $query = "INSERT INTO registros_atendimento (cliente_id, menu_id, submenu_id, tipo_erro_id, nivel_dificuldade_id, descricao, usuario_id, contato_id, data_atendimento) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiiisiis", $cliente_id, $menu_id, $submenu_id, $tipo_erro_id, $nivel_dificuldade_id, $descricao, $usuario_id, $contato_id, $data_atendimento);

    if ($stmt->execute()) {
        $success_message = "Atendimento registrado com sucesso!";
    } else {
        echo "Erro ao registrar atendimento.";
    }
}

// Busca dados do cliente se um ID foi passado via GET
if (isset($_GET['cliente_id'])) {
    $cliente_id = $_GET['cliente_id'];
    $query_cliente = "SELECT c.*, p.nome as plano_nome, g.nome as grupo_nome 
                      FROM clientes c 
                      LEFT JOIN planos p ON c.plano = p.id 
                      LEFT JOIN grupos g ON c.id_grupo = g.id 
                      WHERE c.id = ?";
    $stmt = $conn->prepare($query_cliente);
    $stmt->bind_param("i", $cliente_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $cliente_selecionado = $result->fetch_assoc();
    $stmt->close();
    
    // Busca contatos vinculados ao cliente
    if ($cliente_selecionado) {
        $query_contatos = "SELECT c.id, c.nome, cc.tipo_relacao 
                           FROM contatos c
                           JOIN cliente_contato cc ON c.id = cc.contato_id
                           WHERE cc.cliente_id = ?
                           ORDER BY c.nome";
        $stmt = $conn->prepare($query_contatos);
        $stmt->bind_param("i", $cliente_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $cliente_selecionado['contatos'] = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
    
    // Se o cliente pertence a um grupo, busca os outros clientes do mesmo grupo
    if ($cliente_selecionado && $cliente_selecionado['id_grupo']) {
        $query_clientes_grupo = "SELECT id, CONCAT(contrato, ' - ', nome) AS nome_completo, nome, contrato FROM clientes 
                                WHERE id_grupo = ? AND id != ? 
                                ORDER BY nome";
        $stmt = $conn->prepare($query_clientes_grupo);
        $stmt->bind_param("ii", $cliente_selecionado['id_grupo'], $cliente_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $clientes_grupo = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        $cliente_selecionado['clientes_grupo'] = $clientes_grupo;
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Incluir Atendimento Rápido</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: white;
            color: #333;
        }

        main {
            padding: 20px;
            max-width: 1000px;
            margin: -20px auto;
            background-color: white;
            border-radius: 8px;
        }

        .container {
            display: flex;
            gap: 20px;
        }

        .form-atendimento {
            flex: 1;
        }

        .dados-cliente {
            flex: 0 0 300px;
            background-color: #f5f5f5;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #023324;
            margin-left: 20px;
        }

        .dados-cliente h3 {
            margin-top: 0;
            color: #023324;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }

        .dado-cliente {
            margin-bottom: 10px;
        }

        .dado-cliente label {
            font-weight: bold;
            display: block;
            color: #666;
            font-size: 0.9em;
        }

        .dado-cliente div {
            padding: 5px;
            background-color: white;
            border-radius: 4px;
            border: 1px solid #eee;
            word-wrap: break-word;
        }

        .form-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .form-container label {
            font-weight: bold;
            color: #023324;
        }

        .form-container select,
        .form-container textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }

        .form-container textarea {
            resize: vertical;
        }

        .form-container button {
            background-color: #023324;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.3s ease;
        }

        .form-container button:hover {
            background-color: #7FB449;
        }

        .menu-row {
            display: flex;
            gap: 10px;
        }

        .menu-row .form-group {
            flex: 1;
        }

        .niveis-dificuldade {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }

        .nivel-option {
            flex: 1;
            text-align: center;
            padding: 8px;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: bold;
        }

        .nivel-option:hover {
            opacity: 0.9;
        }

        .nivel-option.selected {
            box-shadow: 0 0 0 2px #023324;
        }

        .usuario-field {
            background-color: #f5f5f5;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #ddd;
        }

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
            top: 100%;
            left: 0;
            right: 0;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            background-color: white;
            z-index: 1000;
            display: none;
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

        .field-error {
            color: red;
            font-size: 12px;
            margin-top: 5px;
            display: none;
        }

        .success-message {
            background-color: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 20px;
            text-align: center;
        }

        .alerta-cliente-pendencias {
            background-color: #fff3cd;
            border: 1px solid #ffeaa7;
            border-left: 4px solid #f39c12;
            color: #856404;
            padding: 12px 15px;
            border-radius: 4px;
            margin: 10px 0;
            font-size: 14px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            animation: slideDown 0.3s ease-out;
        }

        .alerta-cliente-pendencias .alert-content {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .alerta-cliente-pendencias strong {
            color: #d68910;
            font-weight: 600;
        }

        .alerta-cliente-pendencias small {
            color: #6c757d;
            font-size: 12px;
            line-height: 1.4;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .ck-editor__editable {
            min-height: 150px;
            max-height: 300px;
            overflow-y: auto;
            word-wrap: break-word;
            white-space: pre-wrap;
        }

        .dado-cliente ul {
            margin: 5px 0 0 0;
            padding-left: 20px;
            max-height: 150px;
            overflow-y: auto;
            background-color: #f8f8f8;
            border-radius: 4px;
            padding: 8px;
        }

        .dado-cliente li {
            margin-bottom: 3px;
            font-size: 0.9em;
            padding: 3px 5px;
            border-bottom: 1px solid #eee;
        }

        .dado-cliente li:last-child {
            border-bottom: none;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
            }
            
            .dados-cliente {
                flex: 1;
                margin-left: 0;
                margin-top: 20px;
            }
            
            .menu-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .niveis-dificuldade {
                flex-wrap: wrap;
            }
            
            .nivel-option {
                flex: 0 0 calc(50% - 5px);
            }
        }

        /* ========================================
           ESTILOS PARA TEMA ESCURO
           ======================================== */
        
        /* Estilos aplicados quando o tema escuro está ativo */
        body.dark-theme {
            background-color: #1a1a1a !important;
            color: #e0e0e0 !important;
        }

        body.dark-theme main {
            background-color: #1a1a1a !important;
            color: #e0e0e0 !important;
        }

        body.dark-theme .dados-cliente {
            background-color: #2d2d2d !important;
            border-left-color: #7FB449 !important;
            color: #e0e0e0 !important;
        }

        body.dark-theme .dados-cliente h3 {
            color: #7FB449 !important;
            border-bottom-color: #444 !important;
        }

        body.dark-theme .dado-cliente label {
            color: #b0b0b0 !important;
        }

        body.dark-theme .dado-cliente div {
            background-color: #333 !important;
            border-color: #555 !important;
            color: #e0e0e0 !important;
        }

        body.dark-theme .form-container label {
            color: #7FB449 !important;
        }

        body.dark-theme .form-container select,
        body.dark-theme .form-container textarea,
        body.dark-theme .custom-select input {
            background-color: #333 !important;
            border-color: #555 !important;
            color: #e0e0e0 !important;
        }

        body.dark-theme .form-container select:focus,
        body.dark-theme .form-container textarea:focus,
        body.dark-theme .custom-select input:focus {
            border-color: #7FB449 !important;
            outline: none !important;
            box-shadow: 0 0 0 2px rgba(127, 180, 73, 0.2) !important;
        }

        body.dark-theme .form-container button {
            background-color: #7FB449 !important;
            color: #1a1a1a !important;
        }

        body.dark-theme .form-container button:hover {
            background-color: #6fa03a !important;
        }

        body.dark-theme .usuario-field {
            background-color: #333 !important;
            border-color: #555 !important;
            color: #e0e0e0 !important;
        }

        body.dark-theme .custom-select .options {
            background-color: #333 !important;
            border-color: #555 !important;
        }

        body.dark-theme .custom-select .options div {
            color: #e0e0e0 !important;
        }

        body.dark-theme .custom-select .options div:hover {
            background-color: #444 !important;
        }

        body.dark-theme .custom-select .options div.selected {
            background-color: #7FB449 !important;
            color: #1a1a1a !important;
        }

        body.dark-theme .nivel-option.selected {
            box-shadow: 0 0 0 2px #7FB449 !important;
        }

        body.dark-theme .success-message {
            background-color: #2d4a2d !important;
            color: #7FB449 !important;
            border: 1px solid #7FB449 !important;
        }

        body.dark-theme .alerta-cliente-pendencias {
            background-color: #3d3520 !important;
            border-color: #f39c12 !important;
            color: #ffc107 !important;
        }

        body.dark-theme .alerta-cliente-pendencias strong {
            color: #ffc107 !important;
        }

        body.dark-theme .dado-cliente ul {
            background-color: #2a2a2a !important;
            color: #e0e0e0 !important;
        }

        body.dark-theme .dado-cliente li {
            border-bottom-color: #444 !important;
        }

        /* CKEditor tema escuro */
        body.dark-theme .ck-editor__editable {
            background-color: #333 !important;
            color: #e0e0e0 !important;
            border-color: #555 !important;
        }

        body.dark-theme .ck-toolbar {
            background-color: #2d2d2d !important;
            border-color: #555 !important;
        }

        body.dark-theme .ck-button {
            color: #e0e0e0 !important;
        }

        body.dark-theme .ck-button:hover {
            background-color: #444 !important;
        }
    </style>
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
</head>
<body>
    <main>
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <div class="container">
            <div class="form-atendimento">
                <form method="POST" class="form-container" onsubmit="return validarFormulario()">
                    <label for="cliente_id" id="cliente-label" style="cursor: pointer; color: #7FB449; text-decoration: underline;">Cliente:</label>
                    <div class="custom-select">
                        <input type="text" id="cliente_filter" placeholder="Digite para filtrar..." value="<?php 
                            if (isset($cliente_selecionado)) {
                                echo htmlspecialchars($cliente_selecionado['contrato'] . ' - ' . $cliente_selecionado['nome']);
                            }
                        ?>">
                        <div class="options" id="cliente_options">
                            <?php foreach ($clientes as $cliente): ?>
                                <div data-value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_completo']); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <select id="cliente_id" name="cliente_id" required style="display: none;">
                            <option value="">Selecione um cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo (isset($cliente_selecionado) && $cliente_selecionado['id'] == $cliente['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-error" id="cliente_error">Por favor, selecione um cliente.</div>
                    </div>

                    <!-- Alerta para cliente com pendências -->
                    <div id="alerta_cliente_pendencias" class="alerta-cliente-pendencias" style="display: none;"></div>

                    <label for="contato_id">Contato na Empresa:</label>
                    <select id="contato_id" name="contato_id">
                        <option value="">Selecione com quem falou</option>
                        <?php if (isset($cliente_selecionado['contatos']) && !empty($cliente_selecionado['contatos'])): ?>
                            <?php foreach ($cliente_selecionado['contatos'] as $contato): ?>
                                <option value="<?php echo $contato['id']; ?>">
                                    <?php echo htmlspecialchars($contato['nome'] . ' (' . $contato['tipo_relacao'] . ')'); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <div class="menu-row">
                        <div class="form-group">
                            <label for="menu_id">Menu:</label>
                            <select id="menu_id" name="menu_id" required onchange="carregarSubmenus()">
                                <option value="">Selecione um menu</option>
                                <?php foreach ($menus as $menu): ?>
                                    <option value="<?php echo $menu['id']; ?>"><?php echo htmlspecialchars($menu['nome']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="submenu_id">Submenu:</label>
                            <select id="submenu_id" name="submenu_id" required>
                                <option value="">Selecione um submenu</option>
                            </select>
                        </div>
                    </div>

                    <div class="menu-row">
                        <div class="form-group">
                            <label for="tipo_erro_id">Tipo de Erro:</label>
                            <select id="tipo_erro_id" name="tipo_erro_id" required>
                                <option value="">Selecione um tipo de erro</option>
                                <?php foreach ($tipos_erro as $tipo): ?>
                                    <option value="<?php echo $tipo['id']; ?>"><?php echo htmlspecialchars($tipo['descricao']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nível de Dificuldade:</label>
                            <input type="hidden" id="nivel_dificuldade_id" name="nivel_dificuldade_id" required>
                            <div class="niveis-dificuldade">
                                <?php foreach ($niveis as $nivel): ?>
                                    <div class="nivel-option" 
                                         style="background-color: <?php echo $nivel['cor']; ?>; color: white;"
                                         data-value="<?php echo $nivel['id']; ?>"
                                         onclick="selecionarNivel(this)">
                                        <?php echo htmlspecialchars($nivel['nome']); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <label>Usuário que Atendeu:</label>
                    <div class="usuario-field">
                        <?php echo htmlspecialchars($usuario_email); ?>
                        <input type="hidden" id="usuario_id" name="usuario_id" value="<?php echo $usuario_id; ?>">
                    </div>

                    <label for="descricao">Descrição:</label>
                    <textarea id="descricao" name="descricao" style="display:none;"></textarea>
                    <div id="editor"></div>

                    <button type="submit">Registrar Atendimento</button>
                </form>
            </div>

            <div class="dados-cliente">
                <h3>Dados do Cliente</h3>
                <?php if ($cliente_selecionado): ?>
                    <div class="dado-cliente">
                        <label>Plano:</label>
                        <div><?php echo htmlspecialchars($cliente_selecionado['plano_nome'] ?? 'Não informado'); ?></div>
                    </div>
                    <div class="dado-cliente">
                        <label>CNPJ:</label>
                        <div><?php echo htmlspecialchars($cliente_selecionado['cnpj'] ?? 'Não informado'); ?></div>
                    </div>
                    <div class="dado-cliente">
                        <label>UF:</label>
                        <div><?php echo htmlspecialchars($cliente_selecionado['uf'] ?? 'Não informado'); ?></div>
                    </div>
                    <div class="dado-cliente">
                        <label>Módulos:</label>
                        <div>
                            <?php 
                            if (!empty($cliente_selecionado['modulos'])) {
                                $modulos_ids = explode(',', $cliente_selecionado['modulos']);
                                $query_modulos = "SELECT nome FROM modulos WHERE id IN (" . implode(',', array_fill(0, count($modulos_ids), '?')) . ")";
                                $stmt = $conn->prepare($query_modulos);
                                $stmt->bind_param(str_repeat('i', count($modulos_ids)), ...$modulos_ids);
                                $stmt->execute();
                                $result = $stmt->get_result();
                                $modulos_nomes = [];
                                while ($row = $result->fetch_assoc()) {
                                    $modulos_nomes[] = $row['nome'];
                                }
                                echo htmlspecialchars(implode(', ', $modulos_nomes));
                            } else {
                                echo 'Não informado';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="dado-cliente">
                        <label>Grupo:</label>
                        <div>
                            <?php 
                            echo htmlspecialchars($cliente_selecionado['grupo_nome'] ?? 'Não pertence a nenhum grupo');
                            
                            if (!empty($cliente_selecionado['clientes_grupo'])) {
                                echo '<ul>';
                                foreach ($cliente_selecionado['clientes_grupo'] as $cliente_grupo) {
                                    echo '<li>' . htmlspecialchars($cliente_grupo['nome_completo']) . '</li>';
                                }
                                echo '</ul>';
                            }
                            ?>
                        </div>
                    </div>
                    <div class="dado-cliente">
                        <label>Observações:</label>
                    <div><?php 
    if (!empty($cliente_selecionado['observacoes'])) {
        // Permite apenas tags seguras de formatação
        $allowed_tags = '<p><br><strong><b><em><i><u><ul><ol><li>';
        echo strip_tags($cliente_selecionado['observacoes'], $allowed_tags);
    } else {
        echo 'Não informado';
    }
?></div>
                <?php else: ?>
                    <p>Selecione um cliente para visualizar seus dados.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script>
        // Inicializa o CKEditor
        ClassicEditor
            .create(document.querySelector('#editor'), {
                toolbar: [
                    'bold', 'italic', 'link', 'bulletedList', 'numberedList', '|',
                    'undo', 'redo'
                ],
                wordWrap: true
            })
            .then(editor => {
                editor.model.document.on('change:data', () => {
                    document.querySelector('#descricao').value = editor.getData();
                });
                
                editor.editing.view.change(writer => {
                    writer.setStyle('word-break', 'break-word', editor.editing.view.document.getRoot());
                });
            })
            .catch(error => {
                console.error(error);
            });

        // Dados dos submenus
        const submenus = <?php echo json_encode($submenus); ?>;

        function carregarSubmenus() {
            const menuId = document.getElementById('menu_id').value;
            const submenuSelect = document.getElementById('submenu_id');

            submenuSelect.innerHTML = '<option value="">Selecione um submenu</option>';

            if (menuId) {
                const submenusFiltrados = submenus.filter(submenu => submenu.menu_id == menuId);

                submenusFiltrados.forEach(submenu => {
                    const option = document.createElement('option');
                    option.value = submenu.id;
                    option.textContent = submenu.nome;
                    submenuSelect.appendChild(option);
                });
            }
        }

        function selecionarNivel(elemento) {
            document.querySelectorAll('.nivel-option').forEach(el => {
                el.classList.remove('selected');
            });
            
            elemento.classList.add('selected');
            document.getElementById('nivel_dificuldade_id').value = elemento.getAttribute('data-value');
        }

        function setupFilter(inputId, optionsId, selectId) {
            const input = document.getElementById(inputId);
            const options = document.getElementById(optionsId);
            const select = document.getElementById(selectId);

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
                    carregarDadosCliente(select.value);
                }
            });
        }

        function carregarDadosCliente(clienteId) {
            if (!clienteId) {
                // Limpa o alerta se não há cliente selecionado
                const alertaElement = document.getElementById('alerta_cliente_pendencias');
                if (alertaElement) {
                    alertaElement.style.display = 'none';
                }
                return;
            }
            
            fetch(window.location.pathname + '?cliente_id=' + clienteId)
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const novoConteudo = doc.querySelector('.dados-cliente');
                    if (novoConteudo) {
                        document.querySelector('.dados-cliente').innerHTML = novoConteudo.innerHTML;
                    }
                    
                    // Verifica se o cliente tem pendências
                    verificarPendenciasCliente(clienteId);
                    
                    // Atualiza o select de contatos
                    const contatosSelect = doc.getElementById('contato_id');
                    if (contatosSelect) {
                        document.getElementById('contato_id').innerHTML = contatosSelect.innerHTML;
                    }
                })
                .catch(error => console.error('Erro ao carregar dados do cliente:', error));
        }
        
        function verificarPendenciasCliente(clienteId) {
            fetch('verificar_cliente_pendencias.php?cliente_id=' + clienteId)
                .then(response => response.json())
                .then(data => {
                    const alertaElement = document.getElementById('alerta_cliente_pendencias');
                    if (data.tem_pendencias) {
                        // Criar links clicáveis para os chamados
                        let chamadosLinks = '';
                        if (data.chamados_lista && data.chamados_lista.length > 0) {
                            const links = data.chamados_lista.map(chamado => 
                                `<a href="../../chamados/visualizar.php?id=${chamado.id}" target="_blank" style="color: #0d6efd; text-decoration: underline; margin-right: 10px;">#${chamado.id} - ${chamado.titulo}</a>`
                            ).join('<br>');
                            chamadosLinks = `<div style="margin-top: 8px; font-size: 12px;">${links}</div>`;
                        }
                        
                        alertaElement.innerHTML = `
                            <div class="alert-content">
                                <strong>${data.mensagem}</strong>
                                ${chamadosLinks}
                            </div>
                        `;
                        alertaElement.style.display = 'block';
                    } else {
                        alertaElement.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Erro ao verificar pendências do cliente:', error);
                    const alertaElement = document.getElementById('alerta_cliente_pendencias');
                    alertaElement.style.display = 'none';
                });
        }

        function validarFormulario() {
            let valido = true;

            const clienteSelect = document.getElementById('cliente_id');
            const clienteError = document.getElementById('cliente_error');
            if (clienteSelect.value === "") {
                clienteError.style.display = 'block';
                valido = false;
            } else {
                clienteError.style.display = 'none';
            }

            const nivelDificuldade = document.getElementById('nivel_dificuldade_id');
            if (!nivelDificuldade.value) {
                alert('Por favor, selecione um nível de dificuldade');
                valido = false;
            }

            return valido;
        }

        // Configura o filtro para o campo de cliente
        document.addEventListener('DOMContentLoaded', () => {
            setupFilter('cliente_filter', 'cliente_options', 'cliente_id');
            
            // Carrega submenus se já houver um menu selecionado
            const menuSelect = document.getElementById('menu_id');
            if (menuSelect.value) {
                carregarSubmenus();
            }

            // Detecta e aplica o tema escuro do parent window
            function detectarTemaEscuro() {
                try {
                    // Verifica se o parent window tem a classe dark-theme
                    if (window.parent && window.parent.document && window.parent.document.body) {
                        const parentBody = window.parent.document.body;
                        if (parentBody.classList.contains('dark-theme')) {
                            document.body.classList.add('dark-theme');
                        } else {
                            document.body.classList.remove('dark-theme');
                        }
                    }
                } catch (e) {
                    // Se não conseguir acessar o parent (cross-origin), usa prefers-color-scheme
                    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
                        document.body.classList.add('dark-theme');
                    }
                }
            }

            // Aplica o tema na inicialização
            detectarTemaEscuro();

            // Monitora mudanças no tema do parent window
            if (window.parent && window.parent.document) {
                try {
                    const observer = new MutationObserver(() => {
                        detectarTemaEscuro();
                    });
                    
                    observer.observe(window.parent.document.body, {
                        attributes: true,
                        attributeFilter: ['class']
                    });
                } catch (e) {
                    // Fallback: verifica periodicamente
                    setInterval(detectarTemaEscuro, 1000);
                }
            }

            // Função para lidar com o clique no título "Cliente"
            function handleClienteClick() {
                const clienteSelect = document.getElementById('cliente_id');
                const clienteValue = clienteSelect.value;
                
                if (clienteValue && clienteValue !== '') {
                    // Se há um cliente selecionado, abrir editar_cliente em nova aba
                    const url = `../clientes/editar_cliente.php?id=${clienteValue}`;
                    window.open(url, '_blank');
                } else {
                    // Se não há cliente selecionado, abrir clientes.php em nova aba
                    window.open('../clientes/clientes.php', '_blank');
                }
            }

            // Adiciona o event listener ao label Cliente
            const clienteLabel = document.getElementById('cliente-label');
            if (clienteLabel) {
                clienteLabel.addEventListener('click', handleClienteClick);
            }
        });
    </script>
</body>
</html>