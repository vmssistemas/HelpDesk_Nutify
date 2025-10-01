<?php
ob_start();
require_once 'includes/header.php';

// Função para calcular as datas da semana (segunda a sexta)
function getWeekDates() {
    $today = new DateTime();
    $day = $today->format('w'); // 0=Domingo, 1=Segunda, etc.
    
    // Se for fim de semana, começa na próxima segunda
    $startDate = clone $today;
    if ($day == 0) { // Domingo
        $startDate->add(new DateInterval('P1D'));
    } elseif ($day == 6) { // Sábado
        $startDate->add(new DateInterval('P2D'));
    } elseif ($day > 1) { // Terça a sexta
        $startDate->sub(new DateInterval('P'.($day-1).'D'));
    }
    
    // Data fim é 4 dias depois (sexta)
    $endDate = clone $startDate;
    $endDate->add(new DateInterval('P4D'));
    
    return [
        'start' => $startDate->format('Y-m-d'),
        'end' => $endDate->format('Y-m-d')
    ];
}

// Função para obter o número da semana
function getWeekNumber($date) {
    $date = new DateTime($date);
    return $date->format('W');
}

// Inicializa array com dados padrão
$weekDates = getWeekDates();
$sprint = [
    'id' => null,
    'nome' => 'Sprint Semana ' . getWeekNumber($weekDates['start']),
    'data_inicio' => $weekDates['start'],
    'data_fim' => $weekDates['end'],
    'objetivo' => '',
    'ativa' => isset($_GET['planejar']) ? 2 : 1 // 1=Ativa, 2=Planejamento
];

// Verifica se está editando
if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $query = "SELECT * FROM chamados_sprints WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $sprint = $result->fetch_assoc();
    }
    $stmt->close();
}

// Processa o formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Recebe os dados
    $nome = trim($_POST['nome']);
    $data_inicio = $_POST['data_inicio'];
    $data_fim = $_POST['data_fim'];
    $objetivo = trim($_POST['objetivo']);
    
    // Define o status (mantém o atual para edição, usa o form para nova sprint)
    if (isset($sprint['id'])) {
        $ativa = $sprint['ativa']; // Mantém o status original ao editar
    } else {
        $ativa = isset($_POST['ativa']) ? (int)$_POST['ativa'] : 0;
    }
    
    $error = null;

    // Validações
    if (empty($nome)) {
        $error = "O nome da sprint é obrigatório";
    } elseif ($data_fim < $data_inicio) {
        $error = "A data de término não pode ser anterior à data de início";
    } else {
        try {
            if ($sprint['id']) {
                // Atualização - não altera o campo 'ativa'
                $query = "UPDATE chamados_sprints SET nome=?, data_inicio=?, data_fim=?, objetivo=? WHERE id=?";
                $stmt = $conn->prepare($query);
                $stmt->bind_param("ssssi", $nome, $data_inicio, $data_fim, $objetivo, $sprint['id']);
            } else {
                // Criação - lógica para sprints ativas/planejamento
                if ($ativa == 1) {
                    // Se for marcar como ativa, desativa outras sprints ativas
                    $conn->query("UPDATE chamados_sprints SET ativa = 0 WHERE ativa = 1");
                } elseif ($ativa == 2) {
                    // Se for planejamento, verifica se já existe uma em planejamento
                    $existing = $conn->query("SELECT id FROM chamados_sprints WHERE ativa = 2")->num_rows;
                    if ($existing > 0) {
                        $error = "Já existe uma sprint em planejamento. Finalize-a antes de criar outra.";
                    }
                }
                
                if (!$error) {
                    $query = "INSERT INTO chamados_sprints (nome, data_inicio, data_fim, objetivo, ativa) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->bind_param("ssssi", $nome, $data_inicio, $data_fim, $objetivo, $ativa);
                }
            }

            if (!$error && $stmt->execute()) {
                ob_end_clean();
                $_SESSION['success'] = "Sprint salva com sucesso!";
                header("Location: sprints.php");
                exit();
            } elseif ($error) {
                // Manter o erro
            } else {
                $error = "Erro ao salvar no banco de dados: " . $conn->error;
            }
        } catch (Exception $e) {
            $error = "Erro: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $sprint['id'] ? 'Editar' : 'Nova'; ?> Sprint</title>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Validação de datas
        document.getElementById('data_fim').addEventListener('change', function() {
            const inicio = new Date(document.getElementById('data_inicio').value);
            const fim = new Date(this.value);
            
            if (fim < inicio) {
                alert('Data de término não pode ser anterior ao início!');
                this.value = document.getElementById('data_inicio').value;
            }
        });
        
        // Se for uma nova sprint, calcula automaticamente o nome baseado na data
        if (!<?php echo $sprint['id'] ? 'true' : 'false'; ?>) {
            document.getElementById('data_inicio').addEventListener('change', function() {
                const startDate = new Date(this.value);
                const weekNumber = getWeekNumber(startDate);
                document.getElementById('nome').value = 'Sprint Semana ' + weekNumber;
                
                // Calcula a data de término (sexta-feira)
                const endDate = new Date(startDate);
                endDate.setDate(startDate.getDate() + 4);
                document.getElementById('data_fim').value = endDate.toISOString().split('T')[0];
            });
        }
        
        // Função para calcular o número da semana
        function getWeekNumber(date) {
            const firstDayOfYear = new Date(date.getFullYear(), 0, 1);
            const pastDaysOfYear = (date - firstDayOfYear) / 86400000;
            return Math.ceil((pastDaysOfYear + firstDayOfYear.getDay() + 1) / 7);
        }
    });
    </script>
</head>
<body>
    <div class="container mb-5">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><?php echo $sprint['id'] ? 'Editar' : 'Nova'; ?> Sprint</h5>
            </div>
            <div class="card-body">
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" id="formSprint">
                    <?php if ($sprint['id']): ?>
                        <input type="hidden" name="id" value="<?= $sprint['id'] ?>">
                        <div class="alert alert-info mb-3">
                            Status atual: 
                            <?php if ($sprint['ativa'] == 1): ?>
                                <span class="badge bg-success">Ativa</span>
                            <?php elseif ($sprint['ativa'] == 2): ?>
                                <span class="badge bg-info">Planejamento</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Finalizada</span>
                            <?php endif; ?>
                            <small class="d-block mt-1">O status só pode ser alterado através das ações específicas na página de sprints.</small>
                        </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label for="nome" class="form-label">Nome da Sprint *</label>
                        <input type="text" class="form-control" id="nome" name="nome" value="<?php echo htmlspecialchars($sprint['nome']); ?>" required>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label for="data_inicio" class="form-label">Data Início *</label>
                            <input type="date" class="form-control" id="data_inicio" name="data_inicio" value="<?php echo htmlspecialchars($sprint['data_inicio']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="data_fim" class="form-label">Data Término *</label>
                            <input type="date" class="form-control" id="data_fim" name="data_fim" value="<?php echo htmlspecialchars($sprint['data_fim']); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="objetivo" class="form-label">Objetivo</label>
                        <textarea class="form-control" id="objetivo" name="objetivo" rows="3"><?php echo htmlspecialchars($sprint['objetivo']); ?></textarea>
                    </div>

                    <?php if (!$sprint['id']): ?>
                    <div class="mb-3">
                        <label class="form-label">Status Inicial</label>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ativa" id="ativa1" value="1" <?= $sprint['ativa'] == 1 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativa1">
                                Ativar como sprint atual (encerrará a sprint ativa atual)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="ativa" id="ativa2" value="2" <?= $sprint['ativa'] == 2 ? 'checked' : '' ?>>
                            <label class="form-check-label" for="ativa2">
                                Criar em modo de planejamento (para organizar a próxima sprint)
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between">
                        <button type="submit" class="btn btn-primary">
                            <i class="material-icons me-1">save</i> Salvar
                        </button>
                        <a href="sprints.php" class="btn btn-outline-secondary">
                            <i class="material-icons me-1">cancel</i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
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
<?php require_once 'includes/footer.php'; ?>