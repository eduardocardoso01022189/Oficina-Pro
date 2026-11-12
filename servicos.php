<?php
require_once __DIR__ . '/includes/bootstrap.php';
$usuario = exigir_login();
$pdo = db();

$erros = [];
$dados = ['nome' => '', 'descricao' => '', 'valor' => '', 'duracao' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $dados = [
        'nome'      => post('nome'),
        'descricao' => post('descricao'),
        'valor'     => post('valor'),
        'duracao'   => post('duracao'),
    ];
    $valorNum = moeda_para_float($dados['valor']);

    if (mb_strlen($dados['nome']) < 3 || mb_strlen($dados['nome']) > 80) {
        $erros[] = 'Informe o nome do serviço (3 a 80 caracteres).';
    }
    if ($dados['descricao'] === '' || mb_strlen($dados['descricao']) > 255) {
        $erros[] = 'Informe a descrição do serviço (até 255 caracteres).';
    }
    if ($valorNum <= 0 || $valorNum > 99999999.99) {
        $erros[] = 'Informe um valor maior que zero.';
    }
    if (!ctype_digit($dados['duracao']) || (int) $dados['duracao'] < 5 || (int) $dados['duracao'] > 1440) {
        $erros[] = 'Informe a duração em minutos (entre 5 e 1440).';
    }

    if (!$erros) {
        try {
            $pdo->prepare('INSERT INTO servico (nome, descricao, valor, duracao_minutos) VALUES (?, ?, ?, ?)')
                ->execute([$dados['nome'], $dados['descricao'], number_format($valorNum, 2, '.', ''), (int) $dados['duracao']]);
            flash('sucesso', 'Serviço cadastrado com sucesso.');
            redirecionar('servicos.php');
        } catch (PDOException $ex) {
            if (!erro_duplicado($ex)) {
                throw $ex;
            }
            $erros[] = 'Já existe um serviço com este nome.';
        }
    }
}

$servicos = $pdo->query(
    'SELECT s.*, (SELECT COUNT(*) FROM agendamento_servico x WHERE x.id_servico = s.id_servico) AS total_agendamentos
       FROM servico s ORDER BY s.nome'
)->fetchAll();

$titulo = 'Serviços';
$pagina = 'servicos';
include __DIR__ . '/includes/header.php';
?>
<div class="pagina-topo">
    <div>
        <h1>Serviços</h1>
        <p class="muted">Catálogo de serviços oferecidos. Eles são vinculados aos veículos ao criar um agendamento.</p>
    </div>
</div>

<details class="card recolhivel" <?= $erros ? 'open' : '' ?>>
    <summary>＋ Novo serviço</summary>
    <?php erros_exibir($erros); ?>
    <form method="post" novalidate>
        <?= csrf_campo() ?>
        <div class="grade-3">
            <div class="campo">
                <label for="nome">Nome do serviço</label>
                <input type="text" id="nome" name="nome" value="<?= valor($dados, 'nome') ?>" maxlength="80" required>
            </div>
            <div class="campo">
                <label for="valor">Valor (R$)</label>
                <input type="text" id="valor" name="valor" value="<?= valor($dados, 'valor') ?>" inputmode="decimal" placeholder="0,00" required>
            </div>
            <div class="campo">
                <label for="duracao">Duração (minutos)</label>
                <input type="number" id="duracao" name="duracao" value="<?= valor($dados, 'duracao') ?>" min="5" max="1440" required>
            </div>
        </div>
        <div class="campo">
            <label for="descricao">Descrição</label>
            <input type="text" id="descricao" name="descricao" value="<?= valor($dados, 'descricao') ?>" maxlength="255" required>
        </div>
        <div class="acoes"><button type="submit" class="btn btn-primario">Cadastrar serviço</button></div>
    </form>
</details>

<section class="card">
    <div class="tabela-wrap">
        <table class="tabela">
            <thead><tr><th>Serviço</th><th>Descrição</th><th>Valor</th><th>Duração</th><th>Agendamentos</th></tr></thead>
            <tbody>
            <?php if (!$servicos): ?>
                <tr><td colspan="5" class="vazio">Nenhum serviço cadastrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($servicos as $s): ?>
                <tr>
                    <td><strong><?= e($s['nome']) ?></strong></td>
                    <td><?= e($s['descricao']) ?></td>
                    <td><?= e(formatar_moeda($s['valor'])) ?></td>
                    <td><?= (int) $s['duracao_minutos'] ?> min</td>
                    <td><span class="badge badge-neutro"><?= (int) $s['total_agendamentos'] ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
