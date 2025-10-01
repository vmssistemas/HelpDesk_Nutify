<?php
require_once 'includes/header_atendimento.php';

// Filtros
$filtro_cliente = isset($_GET['cliente']) ? $_GET['cliente'] : '';
$filtro_usuario = isset($_GET['usuario']) ? $_GET['usuario'] : '';
$filtro_data_inicio = isset($_GET['data_inicio']) ? $_GET['data_inicio'] : date('Y-m-d');
$filtro_data_fim = isset($_GET['data_fim']) ? $_GET['data_fim'] : date('Y-m-d');
$filtro_dificuldade = isset($_GET['dificuldade']) ? $_GET['dificuldade'] : '';

// Parâmetros de ordenação
$ordenar_por = isset($_GET['ordenar_por']) ? $_GET['ordenar_por'] : 'ra.data_atendimento';
$direcao = isset($_GET['direcao']) ? $_GET['direcao'] : 'DESC';

// Consulta base
$query = "SELECT ra.*, CONCAT(c.contrato, ' - ', c.nome) AS cliente_nome_completo, 
                 c.nome AS cliente_nome, c.contrato AS cliente_contrato,
                 ma.nome AS menu_nome, sma.nome AS submenu_nome, 
                 te.descricao AS tipo_erro_descricao, u.nome AS usuario_nome, 
                 nd.nome AS nivel_dificuldade_nome, nd.cor AS nivel_dificuldade_cor,
                 IFNULL(CONCAT(ct.nome, ' (', cc.tipo_relacao, ')'), 'Não informado') AS contato_nome
          FROM registros_atendimento ra
          JOIN clientes c ON ra.cliente_id = c.id
          JOIN menu_atendimento ma ON ra.menu_id = ma.id
          JOIN submenu_atendimento sma ON ra.submenu_id = sma.id
          JOIN tipo_erro te ON ra.tipo_erro_id = te.id
          JOIN usuarios u ON ra.usuario_id = u.id
          JOIN niveis_dificuldade nd ON ra.nivel_dificuldade_id = nd.id
          LEFT JOIN contatos ct ON ra.contato_id = ct.id
          LEFT JOIN cliente_contato cc ON ct.id = cc.contato_id AND cc.cliente_id = c.id
          WHERE 1=1";

// Aplica filtros
if (!empty($filtro_cliente)) {
    $query .= " AND c.id = $filtro_cliente";
}
if (!empty($filtro_usuario)) {
    $query .= " AND u.id = $filtro_usuario";
}
if (!empty($filtro_data_inicio) && !empty($filtro_data_fim)) {
    $query .= " AND DATE(ra.data_atendimento) BETWEEN '$filtro_data_inicio' AND '$filtro_data_fim'";
}
if (!empty($filtro_dificuldade)) {
    $query .= " AND nd.id = $filtro_dificuldade";
}

// Ordenação
$query .= " ORDER BY $ordenar_por $direcao";

$result_atendimentos = $conn->query($query);
$atendimentos = $result_atendimentos->fetch_all(MYSQLI_ASSOC);

// Contador de atendimentos
$total_atendimentos = count($atendimentos);

// Busca todos os usuários para o filtro
$query_usuarios = "SELECT id, nome, email FROM usuarios ORDER BY nome";
$result_usuarios = $conn->query($query_usuarios);
$usuarios_filtro = $result_usuarios->fetch_all(MYSQLI_ASSOC);

// Busca todos os clientes para o filtro
$query_clientes = "SELECT id, CONCAT(contrato, ' - ', nome) AS nome_completo, nome, contrato FROM clientes ORDER BY nome";
$result_clientes = $conn->query($query_clientes);
$clientes_filtro = $result_clientes->fetch_all(MYSQLI_ASSOC);

// Busca todos os níveis de dificuldade para o filtro
$query_dificuldades = "SELECT * FROM niveis_dificuldade ORDER BY ordem";
$result_dificuldades = $conn->query($query_dificuldades);
$dificuldades_filtro = $result_dificuldades->fetch_all(MYSQLI_ASSOC);
?>

<style>
        /* Estilo para o nível de dificuldade */
        .nivel-dificuldade {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            color: white;
            font-weight: bold;
            text-align: center;
            min-width: 80px;
        }
        
        /* Estilos específicos para a página de atendimento */
        main {
            padding: 20px;
            max-width: 100%;
            margin: 20px auto;
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            margin-left: 20px;
            margin-right: 20px;
        }
        
        /* Filtros */
        .filtros {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .filtros h2 {
            margin-top: 0;
            font-size: 18px;
            color: #023324;
        }
        
        .filtros-form {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        
        .filtro-group {
            flex: 1;
            min-width: 200px;
            margin: 4px;
        }
        
        .filtro-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #023324;
        }
        
        .filtro-group input,
        .filtro-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .filtro-group button {
            background-color: #023324;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        
        .filtro-group button:hover {
            background-color: #034d3a;
        }
        
        .limpar-filtros {
            color: #8CC053;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }
        
        .limpar-filtros:hover {
            color: #023324;
        }
        
        /* Contador de Atendimentos */
        .contador-atendimentos {
            color: #023324;
            font-size: 15px;
            text-align: right;
            margin-bottom: 20px;
            padding: 5px 10px;
            border-radius: 4px;
            background-color: transparent;
        }
        
        /* Tabelas */
        table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-top: 10px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            background-color: white;
        }
        
        table th,
        table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e0e0e0;
        }
        
        table th {
            background-color: #023324;
            color: white;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 13px;
            letter-spacing: 0.5px;
        }
        
        table tr {
            transition: background-color 0.3s ease;
        }
        
        table tr:nth-child(even) {
            background-color: #f8f8f8;
        }
        
        table tr:hover {
            background-color: #f1f1f1;
        }
        
        table td {
            color: #333;
            font-size: 14px;
        }
        
        table td a {
            color: #8CC053;
            text-decoration: none;
            margin-right: 10px;
            transition: color 0.3s ease;
        }
        
        table td a:hover {
            color: #023324;
        }
        
        /* Estilo para o filtro de dificuldade */
        .filtro-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        /* Estilo para ordenação */
        th a {
            color: inherit;
            text-decoration: none;
            display: block;
        }
        
        th a.asc:after {
            content: " ▲";
        }
        
        th a.desc:after {
            content: " ▼";
        }
        
        /* Estilo para o filtro de cliente */
        .custom-select {
            position: relative;
        }
        
        .custom-select input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        
        .custom-select .options {
            display: none;
            position: absolute;
            background: white;
            border: 1px solid #ddd;
            width: 100%;
            max-height: 200px;
            overflow-y: auto;
            z-index: 1000;
        }
        
        .custom-select .options div {
            padding: 8px;
            cursor: pointer;
        }
        
        .custom-select .options div:hover {
            background-color: #f0f0f0;
        }
        
        /* Estilo para a coluna de contato */
        table th:nth-child(3),
        table td:nth-child(3) {
            max-width: 200px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>

    <main>
        <!-- Filtros -->
        <section class="filtros">
            <h2>Filtros</h2>
            <form method="GET" action="atendimento.php" class="filtros-form">
                <!-- Filtro de Cliente com Busca Inteligente -->
                <div class="filtro-group">
                    <label for="cliente">Cliente:</label>
                    <div class="custom-select">
                        <input type="text" id="cliente_filter" placeholder="Digite para filtrar..." value="<?php
                            $cliente_nome = '';
                            if (!empty($filtro_cliente)) {
                                foreach ($clientes_filtro as $cliente) {
                                    if ($cliente['id'] == $filtro_cliente) {
                                        $cliente_nome = $cliente['nome_completo'];
                                        break;
                                    }
                                }
                            }
                            echo htmlspecialchars($cliente_nome);
                        ?>">
                        <div class="options" id="cliente_options">
                            <?php foreach ($clientes_filtro as $cliente): ?>
                                <div data-value="<?php echo $cliente['id']; ?>"><?php echo htmlspecialchars($cliente['nome_completo']); ?></div>
                            <?php endforeach; ?>
                        </div>
                        <select id="cliente" name="cliente" style="display: none;">
                            <option value="">Todos</option>
                            <?php foreach ($clientes_filtro as $cliente): ?>
                                <option value="<?php echo $cliente['id']; ?>" <?php echo ($filtro_cliente == $cliente['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cliente['nome_completo']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="filtro-group">
                    <label for="usuario">Usuário:</label>
                    <select id="usuario" name="usuario">
                        <option value="">Todos</option>
                        <?php foreach ($usuarios_filtro as $usuario): ?>
                            <option value="<?php echo $usuario['id']; ?>" <?php echo ($filtro_usuario == $usuario['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($usuario['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Novo Filtro de Dificuldade -->
                <div class="filtro-group">
                    <label for="dificuldade">Nível de Dificuldade:</label>
                    <select id="dificuldade" name="dificuldade">
                        <option value="">Todos</option>
                        <?php foreach ($dificuldades_filtro as $dificuldade): ?>
                            <option value="<?php echo $dificuldade['id']; ?>" <?php echo ($filtro_dificuldade == $dificuldade['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dificuldade['nome']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="filtro-group">
                    <label for="data_inicio">Data Início:</label>
                    <input type="date" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($filtro_data_inicio); ?>">
                </div>

                <div class="filtro-group">
                    <label for="data_fim">Data Fim:</label>
                    <input type="date" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($filtro_data_fim); ?>">
                </div>

                <div class="filtro-group">
                    <button type="submit">Filtrar</button>
                    <a href="atendimento.php" class="limpar-filtros">Limpar Filtros</a>
                </div>
                
                <!-- Campos ocultos para manter a ordenação -->
                <input type="hidden" name="ordenar_por" value="<?php echo htmlspecialchars($ordenar_por); ?>">
                <input type="hidden" name="direcao" value="<?php echo htmlspecialchars($direcao); ?>">
            </form>
        </section>

        <!-- Contador de Atendimentos -->
        <div class="contador-atendimentos">
            <strong>Total de Atendimentos:</strong> <?php echo $total_atendimentos; ?>
        </div>

        <!-- Linha separadora -->
        <hr class="hr">

        <!-- Seção de Registros de Atendimento -->
        <section>
            <h2>Registros de Atendimento</h2>
            <table>
                <thead>
                    <tr>
                        <th>
                            <a href="?ordenar_por=ra.id&direcao=<?php echo ($ordenar_por == 'ra.id') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&usuario=<?php echo urlencode($filtro_usuario); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>&dificuldade=<?php echo urlencode($filtro_dificuldade); ?>"
                               class="<?php echo ($ordenar_por == 'ra.id') ? ($direcao == 'ASC' ? 'asc' : 'desc') : ''; ?>">
                                ID
                            </a>
                        </th>
                        <th>
                            <a href="?ordenar_por=c.nome&direcao=<?php echo ($ordenar_por == 'c.nome') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&usuario=<?php echo urlencode($filtro_usuario); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>&dificuldade=<?php echo urlencode($filtro_dificuldade); ?>"
                               class="<?php echo ($ordenar_por == 'c.nome') ? ($direcao == 'ASC' ? 'asc' : 'desc') : ''; ?>">
                                Cliente
                            </a>
                        </th>
                        <th>Contato</th>
                        <th>
                            <a href="?ordenar_por=ma.nome&direcao=<?php echo ($ordenar_por == 'ma.nome') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&usuario=<?php echo urlencode($filtro_usuario); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>&dificuldade=<?php echo urlencode($filtro_dificuldade); ?>"
                               class="<?php echo ($ordenar_por == 'ma.nome') ? ($direcao == 'ASC' ? 'asc' : 'desc') : ''; ?>">
                                Menu
                            </a>
                        </th>
                        <th>
                            <a href="?ordenar_por=sma.nome&direcao=<?php echo ($ordenar_por == 'sma.nome') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&usuario=<?php echo urlencode($filtro_usuario); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>&dificuldade=<?php echo urlencode($filtro_dificuldade); ?>"
                               class="<?php echo ($ordenar_por == 'sma.nome') ? ($direcao == 'ASC' ? 'asc' : 'desc') : ''; ?>">
                                Submenu
                            </a>
                        </th>
                        <th>
                            <a href="?ordenar_por=te.descricao&direcao=<?php echo ($ordenar_por == 'te.descricao') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&usuario=<?php echo urlencode($filtro_usuario); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>&dificuldade=<?php echo urlencode($filtro_dificuldade); ?>"
                               class="<?php echo ($ordenar_por == 'te.descricao') ? ($direcao == 'ASC' ? 'asc' : 'desc') : ''; ?>">
                                Tipo
                            </a>
                        </th>
                        <th>
                            <a href="?ordenar_por=nd.nome&direcao=<?php echo ($ordenar_por == 'nd.nome') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&usuario=<?php echo urlencode($filtro_usuario); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>&dificuldade=<?php echo urlencode($filtro_dificuldade); ?>"
                               class="<?php echo ($ordenar_por == 'nd.nome') ? ($direcao == 'ASC' ? 'asc' : 'desc') : ''; ?>">
                                Dificuldade
                            </a>
                        </th>
                        <th>Descrição</th>
                        <th>
                            <a href="?ordenar_por=u.email&direcao=<?php echo ($ordenar_por == 'u.email') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'ASC'; ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&usuario=<?php echo urlencode($filtro_usuario); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>&dificuldade=<?php echo urlencode($filtro_dificuldade); ?>"
                               class="<?php echo ($ordenar_por == 'u.email') ? ($direcao == 'ASC' ? 'asc' : 'desc') : ''; ?>">
                                Usuário
                            </a>
                        </th>
                        <th>
                            <a href="?ordenar_por=ra.data_atendimento&direcao=<?php echo ($ordenar_por == 'ra.data_atendimento') ? ($direcao == 'ASC' ? 'DESC' : 'ASC') : 'DESC'; ?>&cliente=<?php echo urlencode($filtro_cliente); ?>&usuario=<?php echo urlencode($filtro_usuario); ?>&data_inicio=<?php echo urlencode($filtro_data_inicio); ?>&data_fim=<?php echo urlencode($filtro_data_fim); ?>&dificuldade=<?php echo urlencode($filtro_dificuldade); ?>"
                               class="<?php echo ($ordenar_por == 'ra.data_atendimento') ? ($direcao == 'ASC' ? 'asc' : 'desc') : 'desc'; ?>">
                                Data
                            </a>
                        </th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($atendimentos as $atendimento): ?>
                        <tr>
                            <td><?php echo $atendimento['id']; ?></td>
                            <td><?php echo htmlspecialchars($atendimento['cliente_nome_completo']); ?></td>
                            <td><?php echo htmlspecialchars($atendimento['contato_nome']); ?></td>
                            <td><?php echo htmlspecialchars($atendimento['menu_nome']); ?></td>
                            <td><?php echo htmlspecialchars($atendimento['submenu_nome']); ?></td>
                            <td><?php echo htmlspecialchars($atendimento['tipo_erro_descricao']); ?></td>
                            <td>
                                <span class="nivel-dificuldade" style="background-color: <?php echo $atendimento['nivel_dificuldade_cor']; ?>">
                                    <?php echo htmlspecialchars($atendimento['nivel_dificuldade_nome']); ?>
                                </span>
                            </td>
                            <td class="descricao" data-descricao="<?php echo htmlspecialchars($atendimento['descricao']); ?>">
                                <?php echo htmlspecialchars(substr($atendimento['descricao'], 0, 50)); ?>...
                            </td>
                            <td><?php echo htmlspecialchars($atendimento['usuario_nome']); ?></td>
                            <td><?php echo date('d/m/Y H:i:s', strtotime($atendimento['data_atendimento'])); ?></td>
                            <td>
                                <a href="editar_atendimento.php?id=<?php echo $atendimento['id']; ?>">Editar</a>
                                <a href="excluir_atendimento.php?id=<?php echo $atendimento['id']; ?>" onclick="return confirm('Tem certeza?')">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </main>

    <script>
    // Função para remover tags HTML e exibir o conteúdo formatado
    function formatarDescricao() {
        const descricoes = document.querySelectorAll('.descricao');
        descricoes.forEach(descricao => {
            const conteudo = descricao.getAttribute('data-descricao');
            const textoFormatado = removerTagsHTML(conteudo);

            if (textoFormatado.length > 50) {
                descricao.textContent = textoFormatado.substring(0, 50) + '...';
            } else {
                descricao.textContent = textoFormatado;
            }

            descricao.setAttribute('title', textoFormatado);
        });
    }

    function removerTagsHTML(texto) {
        return texto.replace(/<[^>]*>/g, '');
    }

    // Função para configurar o filtro de cliente
    function setupFilter(inputId, optionsId, selectId) {
        const input = document.getElementById(inputId);
        const options = document.getElementById(optionsId);
        const select = document.getElementById(selectId);

        input.addEventListener('input', function () {
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

        input.addEventListener('focus', function () {
            options.style.display = 'block';
        });

        input.addEventListener('blur', function () {
            setTimeout(() => {
                options.style.display = 'none';
            }, 200);
        });

        options.addEventListener('click', function (e) {
            if (e.target.tagName === 'DIV') {
                input.value = e.target.textContent;
                select.value = e.target.getAttribute('data-value');
                options.style.display = 'none';
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        formatarDescricao();
        setupFilter('cliente_filter', 'cliente_options', 'cliente');
    });

    // Voltar com ESC
    document.addEventListener('keydown', function(event) {
        if (event.keyCode === 27) {
            window.history.back();
        }
    });
    </script>
</div>
</body>
</html>