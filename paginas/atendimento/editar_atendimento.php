<?php
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';

$id = $_GET['id'];

// Busca o atendimento pelo ID
$query = "SELECT ra.*, c.nome as cliente_nome, c.contrato as cliente_contrato, cont.nome as contato_nome, cont.id as contato_id
          FROM registros_atendimento ra
          JOIN clientes c ON ra.cliente_id = c.id
          LEFT JOIN contatos cont ON ra.contato_id = cont.id
          WHERE ra.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$atendimento = $result->fetch_assoc();

// Busca todos os dados necessários
$query_clientes = "SELECT id, CONCAT(contrato, ' - ', nome) AS nome_completo, nome, contrato FROM clientes ORDER BY nome";
$clientes = $conn->query($query_clientes)->fetch_all(MYSQLI_ASSOC);

$query_menus = "SELECT * FROM menu_atendimento ORDER BY ordem";
$menus = $conn->query($query_menus)->fetch_all(MYSQLI_ASSOC);

$query_submenus = "SELECT * FROM submenu_atendimento ORDER BY menu_id, ordem";
$submenus = $conn->query($query_submenus)->fetch_all(MYSQLI_ASSOC);

$query_tipos_erro = "SELECT * FROM tipo_erro ORDER BY descricao";
$tipos_erro = $conn->query($query_tipos_erro)->fetch_all(MYSQLI_ASSOC);

$query_niveis = "SELECT * FROM niveis_dificuldade ORDER BY ordem";
$niveis = $conn->query($query_niveis)->fetch_all(MYSQLI_ASSOC);

// Busca o usuário logado
$email = $_SESSION['email'];
$query_usuario = "SELECT id, email FROM usuarios WHERE email = ?";
$stmt_usuario = $conn->prepare($query_usuario);
$stmt_usuario->bind_param("s", $email);
$stmt_usuario->execute();
$stmt_usuario->bind_result($usuario_id, $usuario_email);
$stmt_usuario->fetch();
$stmt_usuario->close();

// Busca dados do cliente (atual ou selecionado via GET)
$cliente_id = isset($_GET['cliente_id']) ? $_GET['cliente_id'] : $atendimento['cliente_id'];
$query_cliente = "SELECT c.*, p.nome as plano_nome, g.nome as grupo_nome 
                  FROM clientes c 
                  LEFT JOIN planos p ON c.plano = p.id 
                  LEFT JOIN grupos g ON c.id_grupo = g.id 
                  WHERE c.id = ?";
$stmt_cliente = $conn->prepare($query_cliente);
$stmt_cliente->bind_param("i", $cliente_id);
$stmt_cliente->execute();
$result_cliente = $stmt_cliente->get_result();
$cliente_selecionado = $result_cliente->fetch_assoc();
$stmt_cliente->close();

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id = $_POST['cliente_id'];
    $menu_id = $_POST['menu_id'];
    $submenu_id = $_POST['submenu_id'];
    $tipo_erro_id = $_POST['tipo_erro_id'];
    $nivel_dificuldade_id = $_POST['nivel_dificuldade_id'];
    $descricao = $_POST['descricao'];
    $usuario_id = $_POST['usuario_id'];
    $contato_id = !empty($_POST['contato_id']) ? $_POST['contato_id'] : null;

    $query = "UPDATE registros_atendimento SET 
              cliente_id = ?, 
              menu_id = ?, 
              submenu_id = ?, 
              tipo_erro_id = ?, 
              nivel_dificuldade_id = ?, 
              descricao = ?, 
              usuario_id = ?,
              contato_id = ?
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iiiiissii", $cliente_id, $menu_id, $submenu_id, $tipo_erro_id, $nivel_dificuldade_id, $descricao, $usuario_id, $contato_id, $id);

    if ($stmt->execute()) {
        header("Location: atendimento.php");
        exit();
    } else {
        echo "Erro ao atualizar atendimento.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Atendimento</title>
    <link rel="icon" href="../assets/img/icone_verde.ico" type="image/png">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
            color: #333;
        }

        header {
            background-color: #023324;
            color: white;
            padding: 20px;
            text-align: center;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        header h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 500;
        }

        header nav {
            margin-top: 10px;
        }

        header nav a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        header nav a:hover {
            font-weight: bold;
            transform: scale(1.05);
        }

        main {
            padding: 20px;
            max-width: 1000px;
            margin: 20px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
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
            background-color: #022a1e;
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
    </style>
    <script src="https://cdn.ckeditor.com/ckeditor5/41.1.0/classic/ckeditor.js"></script>
</head>
<body>
    <header>
        <h1>Editar Atendimento</h1>
        <nav>
            <a href="atendimento.php">Voltar</a>
        </nav>
    </header>

    <main>
        <div class="container">
            <div class="form-atendimento">
                <form method="POST" class="form-container" onsubmit="return validarFormulario()">
                    <label for="cliente_id">Cliente:</label>
                    <div class="custom-select">
                        <input type="text" id="cliente_filter" placeholder="Digite para filtrar..." value="<?php echo htmlspecialchars($atendimento['cliente_contrato'] . ' - ' . $atendimento['cliente_nome']); ?>">
                        <div class="options" id="cliente_options">
                            <?php foreach ($clientes as $cliente): ?>
                                <div data-value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_completo']); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <select id="cliente_id" name="cliente_id" required style="display: none;">
                            <option value="">Selecione um cliente</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo ($cliente['id'] == $atendimento['cliente_id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="field-error" id="cliente_error">Por favor, selecione um cliente.</div>
                    </div>

                    <label for="contato_id">Contato na Empresa:</label>
                    <select id="contato_id" name="contato_id">
                        <option value="">Selecione com quem falou</option>
                        <?php if (isset($cliente_selecionado['contatos']) && !empty($cliente_selecionado['contatos'])): ?>
                            <?php foreach ($cliente_selecionado['contatos'] as $contato): ?>
                                <option value="<?php echo $contato['id']; ?>" <?php echo ($contato['id'] == $atendimento['contato_id']) ? 'selected' : ''; ?>>
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
                                    <option value="<?php echo $menu['id']; ?>" <?php echo ($menu['id'] == $atendimento['menu_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($menu['nome']); ?>
                                    </option>
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
                                    <option value="<?php echo $tipo['id']; ?>" <?php echo ($tipo['id'] == $atendimento['tipo_erro_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tipo['descricao']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Nível de Dificuldade:</label>
                            <input type="hidden" id="nivel_dificuldade_id" name="nivel_dificuldade_id" required value="<?php echo $atendimento['nivel_dificuldade_id']; ?>">
                            <div class="niveis-dificuldade">
                                <?php foreach ($niveis as $nivel): ?>
                                    <div class="nivel-option <?php echo ($nivel['id'] == $atendimento['nivel_dificuldade_id']) ? 'selected' : ''; ?>" 
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
                    <textarea id="descricao" name="descricao" style="display:none;"><?php echo htmlspecialchars($atendimento['descricao']); ?></textarea>
                    <div id="editor"><?php echo $atendimento['descricao']; ?></div>

                    <button type="submit">Salvar Alterações</button>
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
                        <div><?php echo !empty($cliente_selecionado['observacoes']) ? nl2br(htmlspecialchars($cliente_selecionado['observacoes'])) : 'Não informado'; ?></div>
                    </div>
                <?php else: ?>
                    <p>Dados do cliente não disponíveis.</p>
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

                // Seleciona o submenu atual
                const submenuAtual = <?php echo $atendimento['submenu_id'] ?? 'null'; ?>;
                if (submenuAtual) {
                    submenuSelect.value = submenuAtual;
                }
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
                    div.style.display = text.indexOf(filter) > -1 ? '' : 'none';
                }
                options.style.display = 'block';
            });

            input.addEventListener('focus', function() {
                options.style.display = 'block';
            });

            input.addEventListener('blur', function() {
                setTimeout(() => { options.style.display = 'none'; }, 200);
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
            if (!clienteId) return;
            
            // Mantém o ID do atendimento na URL
            const url = new URL(window.location.href);
            url.searchParams.set('cliente_id', clienteId);
            url.searchParams.set('id', <?php echo $id; ?>);
            
            fetch(url.toString())
                .then(response => response.text())
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    const novoConteudo = doc.querySelector('.dados-cliente');
                    if (novoConteudo) {
                        document.querySelector('.dados-cliente').innerHTML = novoConteudo.innerHTML;
                    }
                    
                    // Atualiza o select de contatos
                    const contatosSelect = doc.getElementById('contato_id');
                    if (contatosSelect) {
                        document.getElementById('contato_id').innerHTML = contatosSelect.innerHTML;
                    }
                })
                .catch(error => console.error('Erro ao carregar dados do cliente:', error));
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

        // Configurações iniciais
        document.addEventListener('DOMContentLoaded', () => {
            setupFilter('cliente_filter', 'cliente_options', 'cliente_id');
            
            // Seleciona o nível de dificuldade atual
            const nivelAtual = <?php echo $atendimento['nivel_dificuldade_id'] ?? 'null'; ?>;
            if (nivelAtual) {
                const nivelElement = document.querySelector(`.nivel-option[data-value="${nivelAtual}"]`);
                if (nivelElement) {
                    nivelElement.classList.add('selected');
                }
            }
            
            // Carrega submenus se já houver um menu selecionado
            if (document.getElementById('menu_id').value) {
                carregarSubmenus();
            }
        });
    </script>
</body>
</html>