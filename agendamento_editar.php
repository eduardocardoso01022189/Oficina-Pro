<?php
require_once __DIR__ . '/includes/bootstrap.php';
$usuario = exigir_login();
$pdo = db();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$st = $pdo->prepare(
    'SELECT a.*, v.placa, v.marca, v.modelo, c.nome AS cliente
       FROM agendamento a
       JOIN veiculo v ON v.id_veiculo = a.id_veiculo
       JOIN cliente c ON c.id_cliente = v.id_cliente
      WHERE a.id_agendamento = ?'
);
$st->execute([$id ?: 0]);
$ag = $st->fetch();

if (!$ag) {
    flash('erro', 'Agendamento não encontrado.');
    redirecionar('agendamentos.php');
}

$erros = [];
$dados = [
    'data'   => $ag['data_agendamento'],
    'hora'   => formatar_hora($ag['hora_agendamento']),
    'status' => $ag['status'],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $dados = ['data' => post('data'), 'hora' => post('hora'), 'status' => post('status')];

    if (!data_valida($dados['data'])) {
        $erros[] = 'Informe uma data válida.';
    } elseif ($dados['data'] < date('Y-m-d') && $dados['data'] !== $ag['data_agendamento']) {
        $erros[] = 'A nova data não pode estar no passado.';
    }
    if (!hora_valida($dados['hora']) || $dados['hora'] < '08:00' || $dados['hora'] > '18:00') {
        $erros[] = 'Informe um horário entre 08:00 e 18:00.';
    }
    if (!isset(STATUS_AGENDAMENTO[$dados['status']])) {
        $erros[] = 'Status inválido.';
    }
    if (!$erros && $dados['status'] !== 'cancelado'
        && horario_ocupado($pdo, $dados['data'], $dados['hora'], (int) $ag['id_agendamento'])) {
        $erros[] = 'Já existe outro agendamento neste dia e horário.';
    }

    if (!$erros) {
        $pdo->prepare(
            'UPDATE agendamento SET data_agendamento = ?, hora_agendamento = ?, status = ? WHERE id_agendamento = ?'
        )->execute([$dados['data'], $dados['hora'] . ':00', $dados['status'], $ag['id_agendamento']]);
        flash('sucesso', 'Agendamento atualizado com sucesso.');
        redirecionar('agendamentos.php');
    }
}

$titulo = 'Alterar agendamento';
$pagina = 'agendamentos';
include __DIR__ . '/includes/header.php';
?>
<div class="pagina-topo">
    <div>
        <h1>Alterar agendamento</h1>
        <p class="muted"><?= e($ag['cliente']) ?> · <?= e($ag['marca']) ?> <?= e($ag['modelo']) ?> (<?= e(formatar_placa($ag['placa'])) ?>)</p>
    </div>
    <a class="btn btn-secundario" href="agendamentos.php">&larr; Voltar</a>
</div>

<section class="card">
    <p class="atual">Data atual: <strong><?= e(formatar_data($ag['data_agendamento'])) ?></strong> às <strong><?= e(formatar_hora($ag['hora_agendamento'])) ?></strong> <?= badge_status($ag['status']) ?></p>
    <?php erros_exibir($erros); ?>
    <form method="post" novalidate>
        <?= csrf_campo() ?>
        <div class="grade-3">
            <div class="campo">
                <label for="data">Nova data</label>
                <input type="date" id="data" name="data" value="<?= valor($dados, 'data') ?>" required>
            </div>
            <div class="campo">
                <label for="hora">Horário</label>
                <input type="time" id="hora" name="hora" value="<?= valor($dados, 'hora') ?>" min="08:00" max="18:00" step="900" required>
            </div>
            <div class="campo">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach (STATUS_AGENDAMENTO as $chave => $rotulo): ?>
                        <option value="<?= e($chave) ?>" <?= $dados['status'] === $chave ? 'selected' : '' ?>><?= e($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="acoes">
            <button type="submit" class="btn btn-primario">Salvar alteração</button>
            <a class="btn btn-secundario" href="agendamentos.php">Cancelar</a>
        </div>
    </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
