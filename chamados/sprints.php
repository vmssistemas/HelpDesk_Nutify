<?php
require_once 'includes/header.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Finalizar sprint
if (isset($_GET['finalizar'])) {
    $sprint_id = (int)$_GET['finalizar'];
    
    try {
        $conn->begin_transaction();
        
        // 1. Finalizar a sprint
        $conn->query("UPDATE chamados_sprints SET ativa = 0 WHERE id = $sprint_id");
        
        // 2. Verificar se há uma sprint em planejamento para ativar automaticamente
        $sprint_planejamento = $conn->query("SELECT id FROM chamados_sprints WHERE ativa = 2 LIMIT 1")->fetch_row();
        
        if ($sprint_planejamento) {
            $nova_sprint_id = $sprint_planejamento[0];
            
            // 3. Buscar chamados não concluídos da sprint que está sendo finalizada
            $chamados_ativos = $conn->query("
                SELECT id FROM chamados 
                WHERE sprint_id = $sprint_id
                AND status_id NOT IN (5, 6, 11, 12)
            ")->fetch_all(MYSQLI_ASSOC);
            
            // 4. Ativar a sprint em planejamento
            $conn->query("UPDATE chamados_sprints SET ativa = 1 WHERE id = $nova_sprint_id");
            
            // 5. Se houver chamados ativos, redirecionar para página de transferência
            if (!empty($chamados_ativos)) {
                $_SESSION['transferir_chamados'] = [
                    'sprint_antiga' => $sprint_id,
                    'sprint_nova' => $nova_sprint_id,
                    'chamados' => array_column($chamados_ativos, 'id')
                ];
                $conn->commit();
                header("Location: transferir_chamados.php");
                exit();
            }
        }
        
        $conn->commit();
        header("Location: sprints.php?success=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Erro ao finalizar sprint: " . $e->getMessage();
        header("Location: sprints.php");
        exit();
    }
}

// Ativar sprint em planejamento
if (isset($_GET['ativar'])) {
    $sprint_id = (int)$_GET['ativar'];
    
    try {
        $conn->begin_transaction();
        
        // 1. Finalizar a sprint ativa atual (se houver)
        $conn->query("UPDATE chamados_sprints SET ativa = 0 WHERE ativa = 1");
        
        // 2. Buscar chamados da sprint ativa que não estão concluídos/cancelados
        $chamados_ativos = $conn->query("
            SELECT id FROM chamados 
            WHERE sprint_id IN (SELECT id FROM chamados_sprints WHERE ativa = 1)
            AND status_id NOT IN (5, 6, 11, 12)
        ")->fetch_all(MYSQLI_ASSOC);
        
        // 3. Ativar a nova sprint
        $conn->query("UPDATE chamados_sprints SET ativa = 1 WHERE id = $sprint_id");
        
        // 4. Se houver chamados ativos, redirecionar para página de transferência
        if (!empty($chamados_ativos)) {
            $_SESSION['transferir_chamados'] = [
                'sprint_antiga' => $conn->query("SELECT id FROM chamados_sprints WHERE ativa = 0 ORDER BY data_fim DESC LIMIT 1")->fetch_row()[0],
                'sprint_nova' => $sprint_id,
                'chamados' => array_column($chamados_ativos, 'id')
            ];
            $conn->commit();
            header("Location: transferir_chamados.php");
            exit();
        }
        
        $conn->commit();
        header("Location: sprints.php?success=1");
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Erro ao ativar sprint: " . $e->getMessage();
        header("Location: sprints.php");
        exit();
    }
}

// Cancelar transferência de chamados
if (isset($_GET['cancelar_transferencia'])) {
    unset($_SESSION['transferir_chamados']);
    header("Location: sprints.php");
    exit();
}

// Busca todas as sprints
$sprints = $conn->query("
    SELECT s.*, 
           COUNT(c.id) as total_chamados,
           SUM(CASE WHEN c.status_id IN (5, 6, 11, 12) THEN 1 ELSE 0 END) as concluidos,
           SUM(COALESCE(c.pontos_historia, 0)) as total_pontos,
           SUM(CASE WHEN c.status_id IN (5, 6, 11, 12) THEN COALESCE(c.pontos_historia, 0) ELSE 0 END) as pontos_concluidos
    FROM chamados_sprints s
    LEFT JOIN chamados c ON s.id = c.sprint_id
    GROUP BY s.id
    ORDER BY 
        CASE 
            WHEN s.ativa = 1 THEN 0 
            WHEN s.ativa = 2 THEN 1 
            ELSE 2 
        END,
        s.data_inicio DESC
")->fetch_all(MYSQLI_ASSOC);

// Busca estatísticas por usuário para a sprint ativa
$pontos_por_usuario = [];
$sprint_ativa = array_filter($sprints, fn($s) => $s['ativa'] == 1);
$sprint_planejamento = array_filter($sprints, fn($s) => $s['ativa'] == 2);

if ($sprint_ativa) {
    $sprint = current($sprint_ativa);
    $sprint_id = $sprint['id'];
    
    $pontos_por_usuario = $conn->query("
        SELECT 
            u.id as usuario_id,
            u.nome as usuario_nome,
            SUM(COALESCE(c.pontos_historia, 0)) as total_pontos,
            SUM(CASE WHEN c.status_id IN (5, 6, 11, 12) THEN COALESCE(c.pontos_historia, 0) ELSE 0 END) as pontos_concluidos,
            COUNT(c.id) as total_chamados,
            SUM(CASE WHEN c.status_id IN (5, 6, 11, 12) THEN 1 ELSE 0 END) as chamados_concluidos
        FROM chamados c
        JOIN usuarios u ON c.responsavel_id = u.id
        WHERE c.sprint_id = $sprint_id
        GROUP BY u.id, u.nome
        ORDER BY pontos_concluidos DESC
    ")->fetch_all(MYSQLI_ASSOC);
}
?>

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Sprints</h5>
                <div>
                    <a href="sprint_form.php" class="btn btn-sm btn-primary me-2">
                        <i class="material-icons me-1">add</i> Nova Sprint
                    </a>
                    <?php if ($sprint_ativa && !$sprint_planejamento): ?>
                        <a href="sprint_form.php?planejar=1" class="btn btn-sm btn-outline-primary">
                            <i class="material-icons me-1">date_range</i> Planejar Próxima
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert alert-success">Operação realizada com sucesso!</div>
                <?php endif; ?>
                
                <table class="table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Período</th>
                            <th>Progresso</th>
                            <th>Pontos</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sprints as $sprint): ?>
                        <tr>
                            <td><?= htmlspecialchars($sprint['nome']) ?></td>
                            <td>
                                <?= date('d/m/Y', strtotime($sprint['data_inicio'])) ?> - 
                                <?= date('d/m/Y', strtotime($sprint['data_fim'])) ?>
                            </td>
                            <td>
                                <?php if ($sprint['total_chamados'] > 0): ?>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar" 
                                         role="progressbar" 
                                         style="width: <?= ($sprint['concluidos']/$sprint['total_chamados'])*100 ?>%"
                                         aria-valuenow="<?= ($sprint['concluidos']/$sprint['total_chamados'])*100 ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <small>
                                    <?= $sprint['concluidos'] ?>/<?= $sprint['total_chamados'] ?> concluídos
                                    (<?= round(($sprint['concluidos']/$sprint['total_chamados'])*100, 0) ?>%)
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sprint['total_pontos'] > 0): ?>
                                <div class="progress" style="height: 20px;">
                                    <div class="progress-bar bg-success" 
                                         role="progressbar" 
                                         style="width: <?= ($sprint['pontos_concluidos']/$sprint['total_pontos'])*100 ?>%"
                                         aria-valuenow="<?= ($sprint['pontos_concluidos']/$sprint['total_pontos'])*100 ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                <small>
                                    <?= $sprint['pontos_concluidos'] ?>/<?= $sprint['total_pontos'] ?> pontos
                                    (<?= round(($sprint['pontos_concluidos']/$sprint['total_pontos'])*100, 0) ?>%)
                                </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sprint['ativa'] == 1): ?>
                                    <span class="badge bg-success">Ativa</span>
                                <?php elseif ($sprint['ativa'] == 2): ?>
                                    <span class="badge bg-info">Planejamento</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Finalizada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($sprint['ativa'] == 1): ?>
                                <a href="sprints.php?finalizar=<?= $sprint['id'] ?>" 
                                   class="btn btn-sm btn-outline-danger me-1"
                                   onclick="return confirm('Finalizar esta sprint?')">
                                    <i class="material-icons">done_all</i> Finalizar
                                </a>
                                <?php elseif ($sprint['ativa'] == 2 && !$sprint_ativa): ?>
                                <a href="sprints.php?ativar=<?= $sprint['id'] ?>" 
                                   class="btn btn-sm btn-outline-success me-1"
                                   onclick="return confirm('Ativar esta sprint? A sprint atual será finalizada.')">
                                    <i class="material-icons">play_arrow</i> Ativar
                                </a>
                                <?php endif; ?>
                                <a href="sprint_form.php?id=<?= $sprint['id'] ?>" 
                                   class="btn btn-sm btn-outline-primary">
                                    <i class="material-icons">edit</i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Estatísticas</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <h6>Sprint Atual</h6>
                    <?php if ($sprint_ativa): 
                        $sprint = current($sprint_ativa);
                        $dias_totais = (strtotime($sprint['data_fim']) - strtotime($sprint['data_inicio'])) / (60*60*24);
                        $dias_passados = (time() - strtotime($sprint['data_inicio'])) / (60*60*24);
                        $dias_passados = max(0, min(round($dias_passados), $dias_totais));
                        $dias_restantes = max(0, round((strtotime($sprint['data_fim']) - time()) / (60*60*24)));
                        
                        // Calcula a velocidade média (pontos/dia)
                        $velocidade = $sprint['pontos_concluidos'] > 0 ? 
                            round($sprint['pontos_concluidos'] / max(1, $dias_passados), 1) : 0;
                    ?>
                        <p><strong><?= htmlspecialchars($sprint['nome']) ?></strong></p>
                        
                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar bg-success" 
                                 role="progressbar"
                                 style="width: <?= $sprint['total_pontos'] > 0 ? 
                                     round(($sprint['pontos_concluidos']/$sprint['total_pontos'])*100, 0) : 0 ?>%"
                                 aria-valuenow="<?= $sprint['total_pontos'] > 0 ? 
                                     round(($sprint['pontos_concluidos']/$sprint['total_pontos'])*100, 0) : 0 ?>"
                                 aria-valuemin="0"
                                 aria-valuemax="100">
                            </div>
                        </div>
                        
                        <p class="mb-1">
                            <i class="material-icons" style="font-size:14px;">done</i>
                            <strong><?= $sprint['pontos_concluidos'] ?? 0 ?></strong> de 
                            <strong><?= $sprint['total_pontos'] ?? 0 ?></strong> pontos concluídos
                            (<?= $sprint['total_pontos'] > 0 ? 
                                round(($sprint['pontos_concluidos']/$sprint['total_pontos'])*100, 0) : 0 ?>%)
                        </p>
                        
                        <p class="mb-1">
                            <i class="material-icons" style="font-size:14px;">calendar_today</i>
                            Dia <?= $dias_passados ?> de <?= $dias_totais ?> (<?= $dias_restantes ?> dias restantes)
                        </p>
                        
                        <p class="mb-1">
                            <i class="material-icons" style="font-size:14px;">speed</i>
                            Velocidade: <?= $velocidade ?> pts/dia
                        </p>
                        
                        <?php if ($velocidade > 0 && $dias_restantes > 0): ?>
                        <p class="mb-1">
                            <i class="material-icons" style="font-size:14px;">trending_up</i>
                            Previsão: <?= ceil(($sprint['total_pontos'] - $sprint['pontos_concluidos']) / max(1, $velocidade)) ?> dias
                        </p>
                        <?php endif; ?>
                        
                        <?php if (!empty($pontos_por_usuario)): ?>
                        <div class="mt-3">
                            <h6>Pontos por Usuário</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Usuário</th>
                                            <th>Pontos</th>
                                            <th>Progresso</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pontos_por_usuario as $usuario): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($usuario['usuario_nome']) ?></td>
                                            <td>
                                                <?= $usuario['pontos_concluidos'] ?>/<?= $usuario['total_pontos'] ?>
                                            </td>
                                            <td>
                                                <?php if ($usuario['total_pontos'] > 0): ?>
                                                <div class="progress" style="height: 15px;">
                                                    <div class="progress-bar" 
                                                         role="progressbar"
                                                         style="width: <?= ($usuario['pontos_concluidos']/$usuario['total_pontos'])*100 ?>%"
                                                         aria-valuenow="<?= ($usuario['pontos_concluidos']/$usuario['total_pontos'])*100 ?>"
                                                         aria-valuemin="0"
                                                         aria-valuemax="100">
                                                    </div>
                                                </div>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                    <?php else: ?>
                        <p>Nenhuma sprint ativa no momento</p>
                    <?php endif; ?>
                </div>
                
                <?php if ($sprint_planejamento): ?>
                <div class="mt-4">
                    <h6>Próxima Sprint (Planejamento)</h6>
                    <?php $sprint = current($sprint_planejamento); ?>
                    <p><strong><?= htmlspecialchars($sprint['nome']) ?></strong></p>
                    <p class="mb-1">
                        <i class="material-icons" style="font-size:14px;">date_range</i>
                        <?= date('d/m/Y', strtotime($sprint['data_inicio'])) ?> - <?= date('d/m/Y', strtotime($sprint['data_fim'])) ?>
                    </p>
                    <p class="mb-1">
                        <i class="material-icons" style="font-size:14px;">description</i>
                        <?= $sprint['objetivo'] ? htmlspecialchars($sprint['objetivo']) : 'Nenhum objetivo definido' ?>
                    </p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>