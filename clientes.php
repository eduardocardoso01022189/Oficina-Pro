<?php
require_once __DIR__ . '/includes/bootstrap.php';
$usuario = exigir_login();
$pdo = db();

$erros = [];
$dados = ['nome' => '', 'cpf' => '', 'telefone' => '', 'email' => ''];

// ---- Cadastro de novo cliente ------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $dados = [
        'nome'     => post('nome'),
        'cpf'      => post('cpf'),
        'telefone' => post('telefone'),
        'email'    => mb_strtolower(post('email')),
    ];
    $cpf = so_digitos($dados['cpf']);
    $tel = so_digitos($dados['telefone']);
    $erros = validar_cliente($dados['nome'], $cpf, $tel, $dados['email']);

    if (!$erros) {
        try {
            $pdo->prepare(
                'INSERT INTO cliente (nome, cpf_criptografado, cpf_hash, telefone, email) VALUES (?, ?, ?, ?, ?)'
            )->execute([$dados['nome'], cpf_criptografar($cpf), cpf_hash($cpf), $tel, $dados['email']]);
            flash('sucesso', 'Cliente cadastrado com sucesso.');
            redirecionar('clientes.php');
        } catch (PDOException $ex) {
            if (!erro_duplicado($ex)) {
                throw $ex;
            }
            $erros[] = 'Já existe um cliente cadastrado com este CPF.';
        }
    }
}

// ---- Busca (termo enviado por GET) -------------------------------------------
$busca  = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$sql    = 'SELECT c.*, (SELECT COUNT(*) FROM veiculo v WHERE v.id_cliente = c.id_cliente) AS total_veiculos
             FROM cliente c';
$params = [];

if ($busca !== '') {
    $like  = '%' . addcslashes($busca, '%_\\') . '%';
    $conds = ['c.nome LIKE :nome', 'c.email LIKE :email'];
    $params[':nome']  = $like;
    $params[':email'] = $like;

    $digitos = so_digitos($busca);
    if ($digitos !== '') {
        $conds[] = 'c.telefone LIKE :tel';
        $params[':tel'] = '%' . $digitos . '%';
    }
    if (strlen($digitos) === 11) {            // CPF completo: compara pelo hash (o CPF está criptografado)
        $conds[] = 'c.cpf_hash = :hash';
        $params[':hash'] = cpf_hash($digitos);
    }
    $sql .= ' WHERE ' . implode(' OR ', $conds);
}
$sql .= ' ORDER BY c.nome';

$st = $pdo->prepare($sql);
$st->execute($params);
$clientes = $st->fetchAll();

$titulo = 'Clientes';
$pagina = 'clientes';
include __DIR__ . '/includes/header.php';
?>
<div class="pagina-topo">
    <div>
        <h1>Clientes</h1>
        <p class="muted">Cadastre, busque e edite os clientes da oficina.</p>
    </div>
</div>

<details class="card recolhivel" <?= $erros ? 'open' : '' ?>>
    <summary>＋ Novo cliente</summary>
    <?php erros_exibir($erros); ?>
    <form method="post" novalidate>
        <?= csrf_campo() ?>
        <?php include __DIR__ . '/includes/form_cliente.php'; ?>
        <div class="acoes"><button type="submit" class="btn btn-primario">Cadastrar cliente</button></div>
    </form>
</details>

<section class="card">
    <form method="get" class="busca" role="search">
        <input type="search" name="q" value="<?= e($busca) ?>" placeholder="Buscar por nome, CPF, telefone ou e-mail" aria-label="Termo de busca">
        <button type="submit" class="btn btn-primario">Buscar</button>
        <?php if ($busca !== ''): ?><a class="btn btn-secundario" href="clientes.php">Limpar</a><?php endif; ?>
    </form>

    <?php if ($busca !== ''): ?>
        <p class="resultado-busca"><?= count($clientes) ?> resultado(s) para “<strong><?= e($busca) ?></strong>”</p>
    <?php endif; ?>

    <div class="tabela-wrap">
        <table class="tabela">
            <thead>
                <tr><th>Nome</th><th>CPF</th><th>Telefone</th><th>E-mail</th><th>Veículos</th><th class="col-acoes">Ações</th></tr>
            </thead>
            <tbody>
            <?php if (!$clientes): ?>
                <tr><td colspan="6" class="vazio">Nenhum cliente encontrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($clientes as $c): ?>
                <tr>
                    <td><strong><?= e($c['nome']) ?></strong></td>
                    <td class="mono"><?= e(mascarar_cpf(cpf_descriptografar($c['cpf_criptografado']))) ?></td>
                    <td><?= e(formatar_telefone($c['telefone'])) ?></td>
                    <td><?= e($c['email']) ?></td>
                    <td><span class="badge badge-neutro"><?= (int) $c['total_veiculos'] ?></span></td>
                    <td class="col-acoes">
                        <a class="btn btn-secundario btn-pequeno" href="cliente_editar.php?id=<?= (int) $c['id_cliente'] ?>">Editar</a>
                        <a class="btn btn-secundario btn-pequeno" href="veiculos.php?cliente=<?= (int) $c['id_cliente'] ?>">＋ Veículo</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
