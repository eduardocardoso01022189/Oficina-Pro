<?php
require_once __DIR__ . '/includes/bootstrap.php';
$usuario = exigir_login();
$pdo = db();

$totais = [
    'clientes'     => (int) $pdo->query('SELECT COUNT(*) FROM cliente')->fetchColumn(),
    'veiculos'     => (int) $pdo->query('SELECT COUNT(*) FROM veiculo')->fetchColumn(),
    'servicos'     => (int) $pdo->query('SELECT COUNT(*) FROM servico')->fetchColumn(),
    'agendamentos' => (int) $pdo->query(
        "SELECT COUNT(*) FROM agendamento WHERE status = 'agendado' AND data_agendamento >= CURDATE()"
    )->fetchColumn(),
];

$proximos = $pdo->query(
    "SELECT a.id_agendamento, a.data_agendamento, a.hora_agendamento, v.placa, v.modelo, c.nome AS cliente
       FROM agendamento a
       JOIN veiculo v ON v.id_veiculo = a.id_veiculo
       JOIN cliente c ON c.id_cliente = v.id_cliente
      WHERE a.status = 'agendado' AND a.data_agendamento >= CURDATE()
      ORDER BY a.data_agendamento, a.hora_agendamento
      LIMIT 5"
)->fetchAll();

$titulo = 'Início';
$pagina = 'inicio';
include __DIR__ . '/includes/header.php';
?>
<section class="boas-vindas">
    <div>
        <h1>Olá, <?= e(explode(' ', $usuario['nome'])[0]) ?>! 👋</h1>
        <p class="muted">Você está logado como <strong><?= e($usuario['nome']) ?></strong> (<?= e($usuario['perfil']) ?>). Escolha um recurso para começar.</p>
    </div>
</section>

<section class="cards-resumo">
    <a class="card-resumo" href="clientes.php"><span class="icone">👥</span><strong><?= $totais['clientes'] ?></strong><span>Clientes</span></a>
    <a class="card-resumo" href="veiculos.php"><span class="icone">🚗</span><strong><?= $totais['veiculos'] ?></strong><span>Veículos</span></a>
    <a class="card-resumo" href="servicos.php"><span class="icone">🔧</span><strong><?= $totais['servicos'] ?></strong><span>Serviços</span></a>
    <a class="card-resumo destaque" href="agendamentos.php"><span class="icone">📅</span><strong><?= $totais['agendamentos'] ?></strong><span>Agendamentos futuros</span></a>
</section>

<section class="card">
    <div class="card-topo">
        <h2>Próximos agendamentos</h2>
        <a class="btn btn-secundario btn-pequeno" href="agendamentos.php">Ver todos</a>
    </div>
    <?php if (!$proximos): ?>
        <p class="vazio">Nenhum agendamento futuro.</p>
    <?php else: ?>
        <ul class="lista-proximos">
            <?php foreach ($proximos as $p): ?>
                <li>
                    <span class="data-bloco"><strong><?= e(date('d', strtotime($p['data_agendamento']))) ?></strong><small><?= e(date('m/Y', strtotime($p['data_agendamento']))) ?></small></span>
                    <span class="info">
                        <strong><?= e($p['cliente']) ?></strong>
                        <small><?= e($p['modelo']) ?> · <?= e(formatar_placa($p['placa'])) ?></small>
                    </span>
                    <span class="hora"><?= e(formatar_hora($p['hora_agendamento'])) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
