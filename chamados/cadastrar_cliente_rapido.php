<?php
ob_start();
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header("Location: ../login/login.php");
    exit();
}

require_once '../config/db.php';

// Configuração para evitar erros no JSON
error_reporting(0);
ini_set('display_errors', 0);

// Busca dados do banco
$query_planos = "SELECT * FROM planos ORDER BY nome";
$planos = $conn->query($query_planos)->fetch_all(MYSQLI_ASSOC);

$query_modulos = "SELECT * FROM modulos ORDER BY nome";
$modulos = $conn->query($query_modulos)->fetch_all(MYSQLI_ASSOC);

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
    // Valida campos obrigatórios
    $required = ['nome', 'cnpj', 'contrato', 'telefone', 'uf', 'plano'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            ob_end_clean();
            echo json_encode(['status'=>'error', 'message'=>"O campo $field é obrigatório"]);
            exit();
        }
    }

    $dados = [
        'nome' => $_POST['nome'],
        'email' => $_POST['email'] ?? '',
        'telefone' => $_POST['telefone'],
        'cnpj' => $_POST['cnpj'],
        'contrato' => $_POST['contrato'],
        'uf' => $_POST['uf'],
        'plano' => $_POST['plano'],
        'modulos' => implode(",", $_POST['modulos'] ?? [])
    ];

    // Verifica CNPJ duplicado
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE cnpj = ?");
    $stmt->bind_param("s", $dados['cnpj']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['status'=>'error', 'message'=>'CNPJ já cadastrado']);
        exit();
    }

    // Verifica Contrato duplicado
    $stmt = $conn->prepare("SELECT id FROM clientes WHERE contrato = ?");
    $stmt->bind_param("s", $dados['contrato']);
    $stmt->execute();
    
    if ($stmt->get_result()->num_rows > 0) {
        ob_end_clean();
        echo json_encode(['status'=>'error', 'message'=>'Número de contrato já cadastrado']);
        exit();
    }

      $conn->begin_transaction();
    try {
        // Insere cliente
        $stmt = $conn->prepare("INSERT INTO clientes (nome,email,telefone,cnpj,contrato,uf,plano,modulos) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->bind_param("ssssssss", $dados['nome'],$dados['email'],$dados['telefone'],$dados['cnpj'],$dados['contrato'],$dados['uf'],$dados['plano'],$dados['modulos']);
        $stmt->execute();
        $cliente_id = $conn->insert_id;

        $conn->commit();
        ob_end_clean();
        echo json_encode([
            'status' => 'success',
            'message' => 'Cliente cadastrado com sucesso!',
            'cliente_id' => $cliente_id,
            'cliente_nome' => $dados['nome'],
            'cliente_contrato' => $dados['contrato'],
            'cliente_plano' => $dados['plano'] // Adicione esta linha
        ]);
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
    <title>Cadastrar Cliente Rápido</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
   <style>
    body {
        font-family: Arial, sans-serif;
        padding: 20px;
        background-color: #f8f9fa;
    }
    
    .form-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .form-group {
        margin-bottom: 15px;
    }
    
    label {
        display: block;
        margin-bottom: 5px;
        font-weight: bold;
        font-size: 0.85em;
        color: #495057;
    }
    
    input, select {
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ced4da;
        border-radius: 4px;
        font-size: 0.9em;
        box-sizing: border-box;
        background-color: #fff;
        transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
    }
    
    input:focus, select:focus {
        border-color: #86b7fe;
        outline: 0;
        box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }
    
    .form-row {
        grid-column: span 2;
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 15px;
    }
    
    .modulos-container {
        grid-column: span 2;
        margin-top: 10px;
    }
    
    .modulos-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
        gap: 10px;
        margin-top: 5px;
    }
    
    .modulo-item {
        display: flex;
        align-items: center;
        background: #f8f9fa;
        padding: 8px;
        border-radius: 4px;
        border: 1px solid #dee2e6;
    }
    
    .modulo-item input {
        width: auto;
        margin-right: 8px;
    }
    
    .modulo-item label {
        font-weight: normal;
        margin-bottom: 0;
        color: #212529;
    }
    
    .button-container {
        grid-column: span 2;
        text-align: right;
        margin-top: 15px;
    }
    
    button {
        padding: 8px 16px;
        background-color: #8cc053;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.9em;
        transition: background-color 0.15s ease-in-out;
    }
    
    button:hover {
        background-color: #8cc053;
        transform: translateY(-1px);
    }
    
    #mensagem {
        padding: 12px;
        margin-bottom: 20px;
        border-radius: 4px;
        display: none;
        font-size: 0.9em;
        grid-column: span 2;
    }
    
    .full-width {
        grid-column: span 2;
    }
    .modulos-container {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 15px;
    margin-top: 10px;
}

.modulos-container label {
    font-size: 1em;
    margin-bottom: 10px;
    color: #212529;
}

.modulo-item {
    transition: all 0.2s;
}

.modulo-item:hover {
    background: #e9ecef;
    border-color: #ced4da;
}

.form-check-input {
    width: 1.2em;
    height: 1.2em;
    margin-top: 0.1em;
}

.form-check-label {
    margin-left: 0.5em;
    cursor: pointer;
}
</style>
</head>
<body>
    <div id="mensagem"></div>

    <form id="form-cadastro" method="POST">
    <div class="form-container">
        <div class="form-group full-width">
            <label for="nome">Nome *</label>
            <input type="text" id="nome" name="nome" required>
        </div>
        
        <div class="form-group">
            <label for="cnpj">CNPJ *</label>
            <input type="text" id="cnpj" name="cnpj" maxlength="18" required>
        </div>
        
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email">
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="contrato">Nº Contrato *</label>
                <input type="text" id="contrato" name="contrato" maxlength="10" required placeholder="000.000.00">
            </div>
            
            <div class="form-group">
                <label for="plano">Plano *</label>
                <select id="plano" name="plano" required>
                    <option value="">Selecione</option>
                    <?php foreach($planos as $plano): ?>
                    <option value="<?=$plano['id']?>"><?=htmlspecialchars($plano['nome'])?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="uf">UF *</label>
                <select id="uf" name="uf" required>
                    <option value="">Selecione</option>
                    <?php foreach($ufs as $sigla=>$nome): ?>
                    <option value="<?=$sigla?>"><?="$sigla - $nome"?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="telefone">Telefone *</label>
            <input type="text" id="telefone" name="telefone" maxlength="15" required>
        </div>
        
    <div class="modulos-container">
    <label>Módulos</label>
    <div class="modulos-grid">
        <?php foreach($modulos as $modulo): ?>
        <div class="modulo-item">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="modulos[]" value="<?=$modulo['id']?>" id="modulo-<?=$modulo['id']?>">
                <label class="form-check-label" for="modulo-<?=$modulo['id']?>">
                    <?=htmlspecialchars($modulo['nome'])?>
                </label>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
        
        <div class="button-container">
            <button type="submit">Cadastrar Cliente</button>
        </div>
    </div>
</form>

    <script>
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

    // Envio do formulário
    document.getElementById('form-cadastro').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Validação do contrato antes de enviar
        const contrato = document.getElementById('contrato').value;
        if (!validarContrato(contrato)) {
            alert('O número do contrato deve estar no formato 000.000.00');
            return;
        }

        const formData = new FormData(this);
        
     fetch('cadastrar_cliente_rapido.php', {
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
            // Envia os dados do novo cliente para a página principal
            window.parent.postMessage({
                type: 'clienteCadastrado',
                cliente: {
                    id: data.cliente_id,
                    nome: data.cliente_nome,
                    contrato: data.cliente_contrato,
                    plano: data.cliente_plano // Adicione esta linha
                }
            }, '*');
                // Limpa o formulário para novo cadastro
                document.getElementById('form-cadastro').reset();
                
                // Fecha o modal após 1 segundo (se estiver em um modal)
                if (window.parent.document.querySelector('.modal')) {
                    setTimeout(() => {
                        const modal = window.parent.bootstrap.Modal.getInstance(window.parent.document.querySelector('.modal'));
                        if (modal) modal.hide();
                    }, 1000);
                }
            }
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Ocorreu um erro ao enviar o formulário');
        });
    });
    </script>
</body>
</html>