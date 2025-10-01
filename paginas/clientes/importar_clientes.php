<?php
ob_start();
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../../login/login.php");
    exit();
}

require_once '../../config/db.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Busca dados para referência
$query_planos = "SELECT * FROM planos ORDER BY nome";
$planos = $conn->query($query_planos)->fetch_all(MYSQLI_ASSOC);

$query_grupos = "SELECT * FROM grupos ORDER BY nome";
$grupos = $conn->query($query_grupos)->fetch_all(MYSQLI_ASSOC);

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

// Processar upload do arquivo - ETAPA 1
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['arquivo']) && !isset($_POST['confirmar_import'])) {
    header('Content-Type: application/json');
    
    $arquivo = $_FILES['arquivo'];
    
    // Validar extensão do arquivo
    $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
    if (!in_array(strtolower($extensao), ['xlsx', 'xls', 'csv'])) {
        echo json_encode(['status' => 'error', 'message' => 'Formato de arquivo inválido. Use XLSX, XLS ou CSV.']);
        exit();
    }
    
    try {
        // Carregar o arquivo Excel
        $spreadsheet = IOFactory::load($arquivo['tmp_name']);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();
        
        // Remover cabeçalho se existir
        $cabecalho = array_shift($rows);
        
        // Validar estrutura mínima do arquivo
        $colunasEsperadas = ['nome', 'cnpj', 'contrato', 'telefone', 'uf', 'plano'];
        foreach ($colunasEsperadas as $coluna) {
            if (!in_array(strtolower($coluna), array_map('strtolower', $cabecalho))) {
                echo json_encode(['status' => 'error', 'message' => 'O arquivo não possui a coluna obrigatória: ' . $coluna]);
                exit();
            }
        }
        
        // Mapear índices das colunas
        $colunas = array_flip(array_map('strtolower', $cabecalho));
        
        // Processar cada linha para pré-visualização
        $dadosPreview = [];
        $errosPreview = [];
        
        foreach ($rows as $linha => $dados) {
            $linhaNum = $linha + 2; // Considerando que a linha 1 é o cabeçalho
            $cliente = [
                'linha' => $linhaNum,
                'nome' => trim($dados[$colunas['nome']] ?? ''),
                'cnpj' => trim($dados[$colunas['cnpj']] ?? ''),
                'contrato' => trim($dados[$colunas['contrato']] ?? ''),
                'telefone' => trim($dados[$colunas['telefone']] ?? ''),
                'uf' => trim($dados[$colunas['uf']] ?? ''),
                'plano' => trim($dados[$colunas['plano']] ?? ''),
                'email' => trim($dados[$colunas['email'] ?? ''] ?? ''),
                'grupo' => trim($dados[$colunas['grupo'] ?? ''] ?? ''),
                'observacoes' => trim($dados[$colunas['observacoes'] ?? ''] ?? ''),
                'status' => 'pendente',
                'erro' => ''
            ];
            
            // Validações básicas para pré-visualização
            foreach (['nome', 'cnpj', 'contrato', 'telefone', 'uf', 'plano'] as $campo) {
                if (empty($cliente[$campo])) {
                    $cliente['status'] = 'erro';
                    $cliente['erro'] = "Campo $campo é obrigatório";
                    break;
                }
            }
            
            // Verificar CNPJ duplicado (apenas na pré-visualização)
            if ($cliente['status'] !== 'erro') {
                $cnpjFormatado = preg_replace('/[^0-9]/', '', $cliente['cnpj']);
                $stmt = $conn->prepare("SELECT id FROM clientes WHERE cnpj = ?");
                $stmt->bind_param("s", $cnpjFormatado);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $cliente['status'] = 'erro';
                    $cliente['erro'] = "CNPJ já cadastrado";
                }
            }
            
            // Verificar Contrato duplicado (apenas na pré-visualização)
            if ($cliente['status'] !== 'erro') {
                $stmt = $conn->prepare("SELECT id FROM clientes WHERE contrato = ?");
                $stmt->bind_param("s", $cliente['contrato']);
                $stmt->execute();
                
                if ($stmt->get_result()->num_rows > 0) {
                    $cliente['status'] = 'erro';
                    $cliente['erro'] = "Número de contrato já cadastrado";
                }
            }
            
            $dadosPreview[] = $cliente;
            
            if ($cliente['status'] === 'erro') {
                $errosPreview[] = "Linha $linhaNum: " . $cliente['erro'];
            }
        }
        
        // Salvar dados na sessão para confirmação
        $_SESSION['dados_importacao'] = $dadosPreview;
        $_SESSION['erros_preview'] = $errosPreview;
        
        echo json_encode([
            'status' => 'preview',
            'html' => gerarHtmlPreview($dadosPreview, $errosPreview)
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao processar arquivo: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Confirmar importação - ETAPA 2
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirmar_import'])) {
    header('Content-Type: application/json');
    
    if (!isset($_SESSION['dados_importacao'])) {
        echo json_encode(['status' => 'error', 'message' => 'Dados de importação não encontrados']);
        exit();
    }
    
    $dadosImportacao = $_SESSION['dados_importacao'];
    $clientesProcessados = [];
    $errosImportacao = [];
    
    $conn->begin_transaction();
    
    try {
        foreach ($dadosImportacao as $cliente) {
            if ($cliente['status'] === 'erro') {
                $errosImportacao[] = "Linha {$cliente['linha']} ({$cliente['nome']}): " . $cliente['erro'];
                continue;
            }
            
            try {
                // Formatar CNPJ
                $cnpjFormatado = preg_replace('/[^0-9]/', '', $cliente['cnpj']);
                $cnpjFormatado = substr($cnpjFormatado, 0, 2) . '.' . 
                                substr($cnpjFormatado, 2, 3) . '.' . 
                                substr($cnpjFormatado, 5, 3) . '/' . 
                                substr($cnpjFormatado, 8, 4) . '-' . 
                                substr($cnpjFormatado, 12, 2);
                
                // Formatar telefone
                $telefoneFormatado = preg_replace('/[^0-9]/', '', $cliente['telefone']);
                if (strlen($telefoneFormatado) === 11) {
                    $telefoneFormatado = '(' . substr($telefoneFormatado, 0, 2) . ') ' . 
                                         substr($telefoneFormatado, 2, 5) . '-' . 
                                         substr($telefoneFormatado, 7);
                } elseif (strlen($telefoneFormatado) === 10) {
                    $telefoneFormatado = '(' . substr($telefoneFormatado, 0, 2) . ') ' . 
                                         substr($telefoneFormatado, 2, 4) . '-' . 
                                         substr($telefoneFormatado, 6);
                }
                
                // Obter ID do plano
                $planoId = null;
                foreach ($planos as $plano) {
                    if (strcasecmp($plano['nome'], $cliente['plano']) === 0 || $plano['id'] == $cliente['plano']) {
                        $planoId = $plano['id'];
                        break;
                    }
                }
                
                if (!$planoId) {
                    throw new Exception("Plano '{$cliente['plano']}' não encontrado");
                }
                
                // Obter ID do grupo (se informado)
                $grupoId = null;
                if (!empty($cliente['grupo'])) {
                    foreach ($grupos as $grupo) {
                        if (strcasecmp($grupo['nome'], $cliente['grupo']) === 0 || $grupo['id'] == $cliente['grupo']) {
                            $grupoId = $grupo['id'];
                            break;
                        }
                    }
                    
                    if (!$grupoId && !empty($cliente['grupo'])) {
                        throw new Exception("Grupo '{$cliente['grupo']}' não encontrado");
                    }
                }
                
                // Inserir cliente
                $stmt = $conn->prepare("INSERT INTO clientes (nome, email, telefone, cnpj, contrato, uf, plano, id_grupo, observacoes) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssssiss", 
                    $cliente['nome'],
                    $cliente['email'],
                    $telefoneFormatado,
                    $cnpjFormatado,
                    $cliente['contrato'],
                    $cliente['uf'],
                    $planoId,
                    $grupoId,
                    $cliente['observacoes']
                );
                $stmt->execute();
                
                $clientesProcessados[] = $cliente['nome'];
                
            } catch (Exception $e) {
                $errosImportacao[] = "Linha {$cliente['linha']} ({$cliente['nome']}): " . $e->getMessage();
            }
        }
        
        if (count($errosImportacao)) {
            $conn->rollback();
            echo json_encode([
                'status' => 'partial',
                'html' => gerarHtmlResultado($clientesProcessados, $errosImportacao)
            ]);
        } else {
            $conn->commit();
            echo json_encode([
                'status' => 'success',
                'html' => gerarHtmlResultado($clientesProcessados, [])
            ]);
        }
        
        unset($_SESSION['dados_importacao']);
        unset($_SESSION['erros_preview']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro na importação: ' . $e->getMessage()
        ]);
    }
    exit();
}

// Função para gerar HTML da pré-visualização
function gerarHtmlPreview($dados, $erros) {
    ob_start();
    ?>
    <div class="preview-container">
        <h2>Pré-visualização da Importação</h2>
        
        <?php if (count($erros)): ?>
        <div class="alert alert-warning">
            <h3>Atenção!</h3>
            <p>Foram encontrados <?= count($erros) ?> problema(s) no arquivo:</p>
            <ul>
                <?php foreach($erros as $erro): ?>
                <li><?= htmlspecialchars($erro) ?></li>
                <?php endforeach; ?>
            </ul>
            <p>Os registros com erro não serão importados.</p>
        </div>
        <?php endif; ?>
        
        <div class="table-responsive">
            <table class="preview-table">
                <thead>
                    <tr>
                        <th>Linha</th>
                        <th>Nome</th>
                        <th>CNPJ</th>
                        <th>Contrato</th>
                        <th>Telefone</th>
                        <th>UF</th>
                        <th>Plano</th>
                        <th>Grupo</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($dados as $cliente): ?>
                    <tr class="<?= $cliente['status'] === 'erro' ? 'erro' : 'ok' ?>">
                        <td><?= $cliente['linha'] ?></td>
                        <td><?= htmlspecialchars($cliente['nome']) ?></td>
                        <td><?= htmlspecialchars($cliente['cnpj']) ?></td>
                        <td><?= htmlspecialchars($cliente['contrato']) ?></td>
                        <td><?= htmlspecialchars($cliente['telefone']) ?></td>
                        <td><?= htmlspecialchars($cliente['uf']) ?></td>
                        <td><?= htmlspecialchars($cliente['plano']) ?></td>
                        <td><?= htmlspecialchars($cliente['grupo']) ?></td>
                        <td>
                            <?php if($cliente['status'] === 'erro'): ?>
                            <span class="status-erro">Erro</span>
                            <?php else: ?>
                            <span class="status-ok">OK</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="preview-actions">
            <button type="button" onclick="voltarParaUpload()" class="btn-secondary">
                <i class="fas fa-arrow-left"></i> Corrigir Arquivo
            </button>
            <button type="button" onclick="confirmarImportacao()" class="btn-primary">
                <i class="fas fa-check"></i> Confirmar Importação
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Função para gerar HTML do resultado
function gerarHtmlResultado($processados, $erros) {
    ob_start();
    ?>
    <div class="result-container">
        <h2>Resultado da Importação</h2>
        
        <?php if(count($processados)): ?>
        <div class="alert alert-success">
            <h3><i class="fas fa-check-circle"></i> Sucesso!</h3>
            <p><?= count($processados) ?> cliente(s) importado(s) com sucesso:</p>
            <ul>
                <?php foreach($processados as $cliente): ?>
                <li><?= htmlspecialchars($cliente) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <?php if(count($erros)): ?>
        <div class="alert alert-danger">
            <h3><i class="fas fa-exclamation-triangle"></i> Erros encontrados</h3>
            <p><?= count($erros) ?> cliente(s) não foram importados:</p>
            <ul>
                <?php foreach($erros as $erro): ?>
                <li><?= htmlspecialchars($erro) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="result-actions">
            <button type="button" onclick="window.location.href='clientes.php'" class="btn-secondary">
                <i class="fas fa-list"></i> Ver Todos os Clientes
            </button>
            <button type="button" onclick="voltarParaImportacao()" class="btn-primary">
                <i class="fas fa-file-import"></i> Importar Novo Arquivo
            </button>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

ob_end_flush();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importar Clientes</title>
    <link rel="icon" href="../assets/img/icone_verde.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background-color: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        header {
            background-color: #023324;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        header h1 {
            font-size: 1.5rem;
        }
        
        nav a {
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        nav a:hover {
            text-decoration: underline;
        }
        
        #mensagem {
            padding: 10px 20px;
            margin: 10px 20px;
            border-radius: 4px;
            display: none;
        }
        
        .container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        
        .upload-container, .preview-container, .result-container {
            padding: 20px;
        }
        
        .instructions {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        
        .instructions ol {
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 8px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input[type="file"] {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .button-container {
            margin-top: 20px;
            text-align: center;
        }
        
        .btn-primary {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-primary:hover {
            background-color: #218838;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        
        .btn-secondary:hover {
            background-color: #5a6268;
        }
        
        .alert {
            padding: 15px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }
        
        .alert-warning {
            background-color: #fff3cd;
            color: #856404;
            border-left: 4px solid #ffc107;
        }
        
        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }
        
        .table-responsive {
            overflow-x: auto;
            margin-bottom: 20px;
        }
        
        .preview-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .preview-table th, .preview-table td {
            padding: 10px;
            border: 1px solid #ddd;
            text-align: left;
        }
        
        .preview-table th {
            background-color: #f8f9fa;
        }
        
        .preview-table tr.erro {
            background-color: #f8d7da;
        }
        
        .preview-table tr.ok {
            background-color: #d4edda;
        }
        
        .status-ok {
            color: #28a745;
            font-weight: bold;
        }
        
        .status-erro {
            color: #dc3545;
            font-weight: bold;
        }
        
        .preview-actions, .result-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        
        #upload-section, #preview-section, #result-section {
            display: none;
        }
        
        #upload-section.active, #preview-section.active, #result-section.active {
            display: block;
        }
    </style>
</head>
<body>
    <header>
        <h1>Importar Clientes</h1>
        <nav>
            <a href="clientes.php"><i class="fas fa-arrow-left"></i> Voltar</a>
        </nav>
    </header>

    <div id="mensagem"></div>

    <main class="container">
        <!-- Seção de Upload -->
        <section id="upload-section" class="active">
            <div class="upload-container">
                <h2>Importar de Arquivo Excel</h2>
                
                <div class="instructions">
                    <h3>Instruções:</h3>
                    <ol>
                        <li>O arquivo deve estar nos formatos XLSX, XLS ou CSV</li>
                        <li>As colunas obrigatórias são: <strong>nome, cnpj, contrato, telefone, uf, plano</strong></li>
                        <li>Colunas opcionais: email, grupo, observacoes</li>
                        <li><a href="modelo_importacao_clientes.xlsx" download>Baixe o modelo de arquivo aqui</a></li>
                    </ol>
                </div>
                
                <form id="form-import" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="arquivo">Selecione o arquivo:</label>
                        <input type="file" id="arquivo" name="arquivo" accept=".xlsx,.xls,.csv" required>
                    </div>
                    
                    <div class="button-container">
                        <button type="submit" class="btn-primary">
                            <i class="fas fa-upload"></i> Enviar Arquivo
                        </button>
                    </div>
                </form>
            </div>
        </section>
        
        <!-- Seção de Pré-visualização -->
        <section id="preview-section">
            <div id="preview-content"></div>
        </section>
        
        <!-- Seção de Resultado -->
        <section id="result-section">
            <div id="result-content"></div>
        </section>
    </main>

    <script>
    // Variáveis globais
    const uploadSection = document.getElementById('upload-section');
    const previewSection = document.getElementById('preview-section');
    const resultSection = document.getElementById('result-section');
    const previewContent = document.getElementById('preview-content');
    const resultContent = document.getElementById('result-content');
    const mensagem = document.getElementById('mensagem');
    
    // Função para mostrar seção
    function mostrarSecao(secao) {
        uploadSection.classList.remove('active');
        previewSection.classList.remove('active');
        resultSection.classList.remove('active');
        
        secao.classList.add('active');
    }
    
    // Voltar para upload
    function voltarParaUpload() {
        mostrarSecao(uploadSection);
    }
    
    // Confirmar importação
    function confirmarImportacao() {
        const formData = new FormData();
        formData.append('confirmar_import', '1');
        
        fetch('importar_clientes.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na requisição');
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'success' || data.status === 'partial') {
                resultContent.innerHTML = data.html;
                mostrarSecao(resultSection);
            } else {
                mostrarMensagemErro(data.message || 'Erro desconhecido');
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarMensagemErro('Ocorreu um erro ao confirmar a importação');
        });
    }
    
    // Voltar para importação
    function voltarParaImportacao() {
        mostrarSecao(uploadSection);
    }
    
    // Mostrar mensagem de erro
    function mostrarMensagemErro(mensagem) {
        mensagem.style.display = 'block';
        mensagem.style.backgroundColor = '#f8d7da';
        mensagem.style.color = '#721c24';
        mensagem.textContent = mensagem;
    }
    
    // Envio do formulário inicial
    document.getElementById('form-import').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        mensagem.style.display = 'none';
        
        fetch('importar_clientes.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Erro na requisição');
            }
            return response.json();
        })
        .then(data => {
            if (data.status === 'preview') {
                previewContent.innerHTML = data.html;
                mostrarSecao(previewSection);
            } else if (data.status === 'error') {
                mostrarMensagemErro(data.message);
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            mostrarMensagemErro('Ocorreu um erro ao enviar o formulário');
        });
    });
    
    // Tecla ESC para voltar
    document.addEventListener('keydown', e => {
        if(e.key === 'Escape') window.history.back();
    });
    </script>
</body>
</html>