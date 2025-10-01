<?php
session_start();

// Simular dados de teste
$_SESSION['csrf_token'] = 'test_token';
$_SESSION['authenticated'] = true;
$_SESSION['email'] = 'test@example.com';

// Incluir configuração do banco
require_once '../config/db.php';

// Função para testar as restrições de admin
function testarRestricoes() {
    echo "<h2>Teste das Restrições de Admin para Datas Retroativas</h2>";
    
    // Teste 1: Verificar função isDataRetroativa
    echo "<h3>1. Testando função isDataRetroativa:</h3>";
    
    // Data de ontem (retroativa)
    $dataOntem = date('Y-m-d H:i:s', strtotime('-1 day'));
    echo "Data de ontem ($dataOntem): ";
    
    // Simular a função isDataRetroativa
    $inicioHoje = date('Y-m-d 00:00:00');
    $isRetroativa = $dataOntem < $inicioHoje;
    echo $isRetroativa ? "✓ Retroativa (correto)" : "✗ Não retroativa (erro)";
    echo "<br>";
    
    // Data de hoje (não retroativa)
    $dataHoje = date('Y-m-d H:i:s');
    echo "Data de hoje ($dataHoje): ";
    $isRetroativa = $dataHoje < $inicioHoje;
    echo !$isRetroativa ? "✓ Não retroativa (correto)" : "✗ Retroativa (erro)";
    echo "<br><br>";
    
    // Teste 2: Verificar se as funções foram implementadas
    echo "<h3>2. Verificando implementações no agenda_ajax.php:</h3>";
    
    $agendaFile = file_get_contents('agenda_ajax.php');
    
    // Verificar função isDataRetroativa
    if (strpos($agendaFile, 'function isDataRetroativa') !== false) {
        echo "✓ Função isDataRetroativa implementada<br>";
    } else {
        echo "✗ Função isDataRetroativa não encontrada<br>";
    }
    
    // Verificar restrições em createEvent
    if (strpos($agendaFile, 'isDataRetroativa($inicio)') !== false && 
        strpos($agendaFile, 'isAdmin($conn, $user_id)') !== false) {
        echo "✓ Restrição de admin implementada em createEvent<br>";
    } else {
        echo "✗ Restrição de admin não encontrada em createEvent<br>";
    }
    
    // Verificar restrições em updateEvent
    if (strpos($agendaFile, 'Apenas administradores podem editar agendamentos em datas retroativas') !== false) {
        echo "✓ Restrição de admin implementada em updateEvent<br>";
    } else {
        echo "✗ Restrição de admin não encontrada em updateEvent<br>";
    }
    
    // Verificar restrições em deleteEvent
    if (strpos($agendaFile, 'Apenas administradores podem excluir agendamentos em datas retroativas') !== false) {
        echo "✓ Restrição de admin implementada em deleteEvent<br>";
    } else {
        echo "✗ Restrição de admin não encontrada em deleteEvent<br>";
    }
    
    // Verificar restrições em setStatus
    if (strpos($agendaFile, 'Apenas administradores podem alterar o status de agendamentos em datas retroativas') !== false) {
        echo "✓ Restrição de admin implementada em setStatus<br>";
    } else {
        echo "✗ Restrição de admin não encontrada em setStatus<br>";
    }
    
    echo "<br><h3>3. Resumo das Implementações:</h3>";
    echo "<ul>";
    echo "<li><strong>Função isDataRetroativa:</strong> Verifica se uma data é anterior ao início do dia atual</li>";
    echo "<li><strong>createEvent:</strong> Apenas admins podem criar eventos em datas retroativas</li>";
    echo "<li><strong>updateEvent:</strong> Apenas admins podem editar eventos em datas retroativas</li>";
    echo "<li><strong>deleteEvent:</strong> Apenas admins podem excluir eventos em datas retroativas</li>";
    echo "<li><strong>setStatus:</strong> Apenas admins podem alterar status de eventos em datas retroativas</li>";
    echo "<li><strong>toggleConcluido:</strong> Apenas admins podem alterar status de eventos em datas retroativas</li>";
    echo "</ul>";
    
    echo "<br><h3>4. Como Testar:</h3>";
    echo "<ol>";
    echo "<li>Faça login com um usuário não-admin (admin = 0)</li>";
    echo "<li>Tente criar um evento com data de ontem - deve ser bloqueado</li>";
    echo "<li>Tente editar um evento existente de ontem - deve ser bloqueado</li>";
    echo "<li>Tente excluir um evento de ontem - deve ser bloqueado</li>";
    echo "<li>Tente alterar o status (botão direito) de um evento de ontem - deve ser bloqueado</li>";
    echo "<li>Faça login com um usuário admin (admin = 1)</li>";
    echo "<li>Repita os testes acima - deve funcionar normalmente</li>";
    echo "</ol>";
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teste de Restrições de Admin</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #333; }
        h3 { color: #666; }
        .success { color: green; }
        .error { color: red; }
    </style>
</head>
<body>
    <?php testarRestricoes(); ?>
    
    <br><br>
    <a href="principal.php">← Voltar para a Agenda</a>
</body>
</html>