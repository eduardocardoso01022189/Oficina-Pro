<?php
require_once __DIR__ . '/includes/bootstrap.php';
$usuario = exigir_login();
$pdo = db();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$st = $pdo->prepare('SELECT * FROM cliente WHERE id_cliente = ?');
$st->execute([$id ?: 0]);
$cliente = $st->fetch();

if (!$cliente) {
    flash('erro', 'Cliente não encontrado.');
    redirecionar('clientes.php');
}

$erros = [];
$dados = [
    'nome'     => $cliente['nome'],
    'cpf'      => formatar_cpf(cpf_descriptografar($cliente['cpf_criptografado'])),
    'telefone' => formatar_telefone($cliente['telefone']),
    'email'    => $cliente['email'],
];

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
                'UPDATE cliente SET nome = ?, cpf_criptografado = ?, cpf_hash = ?, telefone = ?, email = ?
                  WHERE id_cliente = ?'
            )->execute([$dados['nome'], cpf_criptografar($cpf), cpf_hash($cpf), $tel, $dados['email'], $cliente['id_cliente']]);
            flash('sucesso', 'Dados do cliente atualizados com sucesso.');
            redirecionar('clientes.php');
        } catch (PDOException $ex) {
            if (!erro_duplicado($ex)) {
                throw $ex;
            }
            $erros[] = 'Já existe outro cliente cadastrado com este CPF.';
        }
    }
}

$titulo = 'Editar cliente';
$pagina = 'clientes';
include __DIR__ . '/includes/header.php';
?>
<div class="pagina-topo">
    <div>
        <h1>Editar cliente</h1>
        <p class="muted">Altere os dados e salve. O CPF é gravado criptografado.</p>
    </div>
    <a class="btn btn-secundario" href="clientes.php">&larr; Voltar</a>
</div>

<section class="card">
    <?php erros_exibir($erros); ?>
    <form method="post" novalidate>
        <?= csrf_campo() ?>
        <?php include __DIR__ . '/includes/form_cliente.php'; ?>
        <div class="acoes">
            <button type="submit" class="btn btn-primario">Salvar alterações</button>
            <a class="btn btn-secundario" href="clientes.php">Cancelar</a>
        </div>
    </form>
</section>
<?php include __DIR__ . '/includes/footer.php'; ?>
