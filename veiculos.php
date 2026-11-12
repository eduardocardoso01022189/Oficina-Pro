<?php
require_once __DIR__ . '/includes/bootstrap.php';
$usuario = exigir_login();
$pdo = db();

$clientes = $pdo->query('SELECT id_cliente, nome FROM cliente ORDER BY nome')->fetchAll();
$idsClientes = array_map('intval', array_column($clientes, 'id_cliente'));

$erros = [];
$preSelecionado = filter_input(INPUT_GET, 'cliente', FILTER_VALIDATE_INT) ?: 0;
$dados = ['placa' => '', 'marca' => '', 'modelo' => '', 'ano' => '', 'cor' => '', 'id_cliente' => (string) $preSelecionado];

// ---- Cadastro de veículo (obrigatoriamente associado a um cliente) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $dados = [
        'placa'      => strtoupper(preg_replace('/[^A-Za-z0-9]/', '', post('placa'))),
        'marca'      => post('marca'),
        'modelo'     => post('modelo'),
        'ano'        => post('ano'),
        'cor'        => post('cor'),
        'id_cliente' => post('id_cliente'),
    ];
    $anoMax = (int) date('Y') + 1;

    if (!in_array((int) $dados['id_cliente'], $idsClientes, true)) {
        $erros[] = 'Selecione o cliente proprietário do veículo.';
    }
    if (!preg_match('/^[A-Z]{3}[0-9][A-Z0-9][0-9]{2}$/', $dados['placa'])) {
        $erros[] = 'Placa inválida. Use o formato ABC1D23 ou ABC1234.';
    }
    foreach (['marca' => 'a marca', 'modelo' => 'o modelo', 'cor' => 'a cor'] as $campo => $rotulo) {
        if ($dados[$campo] === '' || mb_strlen($dados[$campo]) > 50) {
            $erros[] = "Informe $rotulo do veículo.";
        }
    }
    if (!ctype_digit($dados['ano']) || (int) $dados['ano'] < 1950 || (int) $dados['ano'] > $anoMax) {
        $erros[] = "Ano inválido (entre 1950 e $anoMax).";
    }

    if (!$erros) {
        try {
            $pdo->prepare('INSERT INTO veiculo (placa, marca, modelo, ano, cor, id_cliente) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$dados['placa'], $dados['marca'], $dados['modelo'], (int) $dados['ano'], $dados['cor'], (int) $dados['id_cliente']]);
            flash('sucesso', 'Veículo cadastrado e associado ao cliente.');
            redirecionar('veiculos.php');
        } catch (PDOException $ex) {
            if (!erro_duplicado($ex)) {
                throw $ex;
            }
            $erros[] = 'Já existe um veículo cadastrado com esta placa.';
        }
    }
}

$veiculos = $pdo->query(
    'SELECT v.*, c.nome AS cliente FROM veiculo v JOIN cliente c ON c.id_cliente = v.id_cliente
      ORDER BY c.nome, v.modelo'
)->fetchAll();

$titulo = 'Veículos';
$pagina = 'veiculos';
include __DIR__ . '/includes/header.php';
?>
<div class="pagina-topo">
    <div>
        <h1>Veículos</h1>
        <p class="muted">Todo veículo cadastrado precisa estar associado a um cliente.</p>
    </div>
</div>

<details class="card recolhivel" <?= ($erros || $preSelecionado) ? 'open' : '' ?>>
    <summary>＋ Novo veículo</summary>
    <?php erros_exibir($erros); ?>
    <?php if (!$clientes): ?>
        <p class="vazio">Cadastre um cliente antes de adicionar veículos. <a href="clientes.php">Ir para clientes</a></p>
    <?php else: ?>
    <form method="post" novalidate>
        <?= csrf_campo() ?>
        <div class="campo">
            <label for="id_cliente">Cliente (proprietário)</label>
            <select id="id_cliente" name="id_cliente" required>
                <option value="">Selecione um cliente…</option>
                <?php foreach ($clientes as $c): ?>
                    <option value="<?= (int) $c['id_cliente'] ?>" <?= (string) $c['id_cliente'] === $dados['id_cliente'] ? 'selected' : '' ?>><?= e($c['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="grade-5">
            <div class="campo">
                <label for="placa">Placa</label>
                <input type="text" id="placa" name="placa" value="<?= valor($dados, 'placa') ?>" data-mask="placa" maxlength="8" placeholder="ABC1D23" required>
            </div>
            <div class="campo">
                <label for="marca">Marca</label>
                <input type="text" id="marca" name="marca" value="<?= valor($dados, 'marca') ?>" maxlength="50" required>
            </div>
            <div class="campo">
                <label for="modelo">Modelo</label>
                <input type="text" id="modelo" name="modelo" value="<?= valor($dados, 'modelo') ?>" maxlength="50" required>
            </div>
            <div class="campo">
                <label for="ano">Ano</label>
                <input type="number" id="ano" name="ano" value="<?= valor($dados, 'ano') ?>" min="1950" max="<?= (int) date('Y') + 1 ?>" required>
            </div>
            <div class="campo">
                <label for="cor">Cor</label>
                <input type="text" id="cor" name="cor" value="<?= valor($dados, 'cor') ?>" maxlength="30" required>
            </div>
        </div>
        <div class="acoes"><button type="submit" class="btn btn-primario">Cadastrar veículo</button></div>
    </form>
    <?php endif; ?>
</details>

<section class="card">
    <div class="tabela-wrap">
        <table class="tabela">
            <thead><tr><th>Placa</th><th>Marca / Modelo</th><th>Ano</th><th>Cor</th><th>Proprietário</th></tr></thead>
            <tbody>
            <?php if (!$veiculos): ?>
                <tr><td colspan="5" class="vazio">Nenhum veículo cadastrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($veiculos as $v): ?>
                <tr>
                    <td><span class="placa"><?= e(formatar_placa($v['placa'])) ?></span></td>
                    <td><strong><?= e($v['marca']) ?></strong> <?= e($v['modelo']) ?></td>
                    <td><?= (int) $v['ano'] ?></td>
                    <td><?= e($v['cor']) ?></td>
                    <td><?= e($v['cliente']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
