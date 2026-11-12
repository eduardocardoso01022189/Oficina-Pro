<?php
require_once __DIR__ . '/includes/bootstrap.php';
$usuario = exigir_login();
$pdo = db();

$veiculos = $pdo->query(
    'SELECT v.id_veiculo, v.placa, v.marca, v.modelo, c.nome AS cliente
       FROM veiculo v JOIN cliente c ON c.id_cliente = v.id_cliente ORDER BY c.nome, v.modelo'
)->fetchAll();
$servicos = $pdo->query('SELECT id_servico, nome, valor, duracao_minutos FROM servico ORDER BY nome')->fetchAll();

$erros = [];
$dados = ['id_veiculo' => '', 'data' => '', 'hora' => '', 'observacao' => '', 'servicos' => []];

// ---- Novo agendamento: veículo + data/hora + pelo menos um serviço ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $selecionados = isset($_POST['servicos']) && is_array($_POST['servicos'])
        ? array_values(array_unique(array_map('intval', $_POST['servicos']))) : [];
    $dados = [
        'id_veiculo' => post('id_veiculo'),
        'data'       => post('data'),
        'hora'       => post('hora'),
        'observacao' => post('observacao'),
        'servicos'   => $selecionados,
    ];

    $idsVeiculos = array_map('intval', array_column($veiculos, 'id_veiculo'));
    $idsServicos = array_map('intval', array_column($servicos, 'id_servico'));

    if (!in_array((int) $dados['id_veiculo'], $idsVeiculos, true)) {
        $erros[] = 'Selecione o veículo.';
    }
    if (!data_valida($dados['data']) || $dados['data'] < date('Y-m-d')) {
        $erros[] = 'Informe uma data válida (hoje ou futura).';
    }
    if (!hora_valida($dados['hora']) || $dados['hora'] < '08:00' || $dados['hora'] > '18:00') {
        $erros[] = 'Informe um horário entre 08:00 e 18:00.';
    }
    if (!$selecionados || array_diff($selecionados, $idsServicos)) {
        $erros[] = 'Selecione ao menos um serviço para o veículo.';
    }
    if (mb_strlen($dados['observacao']) > 255) {
        $erros[] = 'A observação deve ter no máximo 255 caracteres.';
    }
    if (!$erros && horario_ocupado($pdo, $dados['data'], $dados['hora'])) {
        $erros[] = 'Já existe um agendamento neste dia e horário. Escolha outro horário.';
    }

    if (!$erros) {
        $pdo->beginTransaction();
        try {
            $pdo->prepare(
                "INSERT INTO agendamento (data_agendamento, hora_agendamento, status, observacao, id_veiculo, id_usuario)
                 VALUES (?, ?, 'agendado', ?, ?, ?)"
            )->execute([
                $dados['data'], $dados['hora'] . ':00',
                $dados['observacao'] !== '' ? $dados['observacao'] : null,
                (int) $dados['id_veiculo'], $usuario['id_usuario'],
            ]);
            $idAgendamento = (int) $pdo->lastInsertId();

            // vincula cada serviço ao agendamento guardando o valor praticado
            $ins = $pdo->prepare(
                'INSERT INTO agendamento_servico (valor_aplicado, id_agendamento, id_servico)
                 VALUES ((SELECT valor FROM servico WHERE id_servico = ?), ?, ?)'
            );
            foreach ($selecionados as $idServico) {
                $ins->execute([$idServico, $idAgendamento, $idServico]);
            }
            $pdo->commit();
            flash('sucesso', 'Agendamento cadastrado com sucesso.');
            redirecionar('agendamentos.php');
        } catch (Throwable $ex) {
            $pdo->rollBack();
            throw $ex;
        }
    }
}

// ---- Listagem ordenada por dia, com todos os dados de cliente e veículo -----
$agendamentos = $pdo->query(
    "SELECT a.id_agendamento, a.data_agendamento, a.hora_agendamento, a.status, a.observacao,
            c.nome AS cliente_nome, c.cpf_criptografado, c.telefone AS cliente_telefone, c.email AS cliente_email,
            v.placa, v.marca, v.modelo, v.ano, v.cor,
            (SELECT GROUP_CONCAT(s.nome ORDER BY s.nome SEPARATOR ', ')
               FROM agendamento_servico x JOIN servico s ON s.id_servico = x.id_servico
              WHERE x.id_agendamento = a.id_agendamento) AS servicos,
            (SELECT SUM(x.valor_aplicado) FROM agendamento_servico x
              WHERE x.id_agendamento = a.id_agendamento) AS total
       FROM agendamento a
       JOIN veiculo v ON v.id_veiculo = a.id_veiculo
       JOIN cliente c ON c.id_cliente = v.id_cliente
      ORDER BY a.data_agendamento ASC, a.hora_agendamento ASC"
)->fetchAll();

$hoje   = date('Y-m-d');
$titulo = 'Agendamentos';
$pagina = 'agendamentos';
include __DIR__ . '/includes/header.php';
?>
<div class="pagina-topo">
    <div>
        <h1>Agendamentos de revisão</h1>
        <p class="muted">Listagem ordenada por dia e horário, com os dados do cliente e do veículo.</p>
    </div>
</div>

<details class="card recolhivel" <?= $erros ? 'open' : '' ?>>
    <summary>＋ Novo agendamento</summary>
    <?php erros_exibir($erros); ?>
    <?php if (!$veiculos || !$servicos): ?>
        <p class="vazio">É preciso ter ao menos um <a href="veiculos.php">veículo</a> e um <a href="servicos.php">serviço</a> cadastrados.</p>
    <?php else: ?>
    <form method="post" novalidate>
        <?= csrf_campo() ?>
        <div class="grade-3">
            <div class="campo">
                <label for="id_veiculo">Veículo</label>
                <select id="id_veiculo" name="id_veiculo" required>
                    <option value="">Selecione…</option>
                    <?php foreach ($veiculos as $v): ?>
                        <option value="<?= (int) $v['id_veiculo'] ?>" <?= (string) $v['id_veiculo'] === $dados['id_veiculo'] ? 'selected' : '' ?>>
                            <?= e(formatar_placa($v['placa'])) ?> — <?= e($v['modelo']) ?> (<?= e($v['cliente']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="campo">
                <label for="data">Data</label>
                <input type="date" id="data" name="data" value="<?= valor($dados, 'data') ?>" min="<?= e($hoje) ?>" required>
            </div>
            <div class="campo">
                <label for="hora">Horário</label>
                <input type="time" id="hora" name="hora" value="<?= valor($dados, 'hora') ?>" min="08:00" max="18:00" step="900" required>
            </div>
        </div>

        <div class="campo">
            <span class="rotulo">Serviços (selecione ao menos um)</span>
            <div class="checks">
                <?php foreach ($servicos as $s): ?>
                    <label class="check">
                        <input type="checkbox" name="servicos[]" value="<?= (int) $s['id_servico'] ?>" <?= in_array((int) $s['id_servico'], $dados['servicos'], true) ? 'checked' : '' ?>>
                        <span><strong><?= e($s['nome']) ?></strong><small><?= e(formatar_moeda($s['valor'])) ?> · <?= (int) $s['duracao_minutos'] ?> min</small></span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="campo">
            <label for="observacao">Observação (opcional)</label>
            <input type="text" id="observacao" name="observacao" value="<?= valor($dados, 'observacao') ?>" maxlength="255">
        </div>
        <div class="acoes"><button type="submit" class="btn btn-primario">Agendar revisão</button></div>
    </form>
    <?php endif; ?>
</details>

<section class="card">
    <div class="tabela-wrap">
        <table class="tabela tabela-agenda">
            <thead>
                <tr><th>Data / Hora</th><th>Cliente</th><th>Veículo</th><th>Serviços</th><th>Status</th><th class="col-acoes">Ações</th></tr>
            </thead>
            <tbody>
            <?php if (!$agendamentos): ?>
                <tr><td colspan="6" class="vazio">Nenhum agendamento cadastrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($agendamentos as $a): ?>
                <?php $classe = $a['data_agendamento'] < $hoje ? 'passado' : ($a['data_agendamento'] === $hoje ? 'hoje' : ''); ?>
                <tr class="<?= e($classe) ?>">
                    <td>
                        <strong><?= e(formatar_data($a['data_agendamento'])) ?></strong>
                        <?php if ($classe === 'hoje'): ?><span class="tag-hoje">Hoje</span><?php endif; ?>
                        <small class="bloco"><?= e(formatar_hora($a['hora_agendamento'])) ?></small>
                    </td>
                    <td>
                        <strong><?= e($a['cliente_nome']) ?></strong>
                        <small class="bloco">CPF <?= e(mascarar_cpf(cpf_descriptografar($a['cpf_criptografado']))) ?></small>
                        <small class="bloco"><?= e(formatar_telefone($a['cliente_telefone'])) ?></small>
                        <small class="bloco"><?= e($a['cliente_email']) ?></small>
                    </td>
                    <td>
                        <span class="placa"><?= e(formatar_placa($a['placa'])) ?></span>
                        <small class="bloco"><?= e($a['marca']) ?> <?= e($a['modelo']) ?> · <?= (int) $a['ano'] ?></small>
                        <small class="bloco"><?= e($a['cor']) ?></small>
                    </td>
                    <td>
                        <?= e((string) $a['servicos']) ?>
                        <small class="bloco"><?= e(formatar_moeda($a['total'] ?? 0)) ?></small>
                        <?php if (!empty($a['observacao'])): ?><small class="bloco obs">“<?= e($a['observacao']) ?>”</small><?php endif; ?>
                    </td>
                    <td><?= badge_status($a['status']) ?></td>
                    <td class="col-acoes">
                        <a class="btn btn-secundario btn-pequeno" href="agendamento_editar.php?id=<?= (int) $a['id_agendamento'] ?>">Alterar data</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
