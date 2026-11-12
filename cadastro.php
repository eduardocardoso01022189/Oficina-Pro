<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (usuario_logado()) {
    redirecionar('principal.php');
}

$erros = [];
$dados = ['nome' => '', 'email' => '', 'telefone' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $dados = [
        'nome'     => post('nome'),
        'email'    => mb_strtolower(post('email')),
        'telefone' => post('telefone'),
    ];
    $tel       = so_digitos($dados['telefone']);
    $senha     = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
    $confirmar = is_string($_POST['confirmar'] ?? null) ? $_POST['confirmar'] : '';

    if (mb_strlen($dados['nome']) < 3 || mb_strlen($dados['nome']) > 100) {
        $erros[] = 'Informe seu nome completo.';
    }
    if (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($dados['email']) > 120) {
        $erros[] = 'Informe um e-mail válido.';
    }
    if (!in_array(strlen($tel), [10, 11], true)) {
        $erros[] = 'Informe um telefone válido com DDD.';
    }
    if (strlen($senha) < 8 || !preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
        $erros[] = 'A senha deve ter no mínimo 8 caracteres, com letras e números.';
    }
    if ($senha !== $confirmar) {
        $erros[] = 'A confirmação da senha não confere.';
    }

    if (!$erros) {
        try {
            db()->prepare('INSERT INTO usuario (nome, email, telefone, senha_hash) VALUES (?, ?, ?, ?)')
                ->execute([$dados['nome'], $dados['email'], $tel, password_hash($senha, PASSWORD_DEFAULT)]);
            flash('sucesso', 'Conta criada com sucesso! Faça login para continuar.');
            redirecionar('login.php');
        } catch (PDOException $ex) {
            if (!erro_duplicado($ex)) {
                throw $ex;
            }
            $erros[] = 'Já existe uma conta com este e-mail.';
        }
    }
}

$titulo = 'Criar conta';
include __DIR__ . '/includes/header.php';
?>
<main class="auth auth-simples">
    <section class="auth-form">
        <div class="auth-card auth-card-largo">
            <span class="marca"><span class="marca-icone" aria-hidden="true">⚙</span>Oficina<b>Pro</b></span>
            <h2>Criar conta de acesso</h2>
            <p class="muted">Cada pessoa que usa o sistema precisa de um login próprio.</p>
            <?php erros_exibir($erros); ?>

            <form method="post" novalidate>
                <?= csrf_campo() ?>
                <div class="campo">
                    <label for="nome">Nome completo</label>
                    <input type="text" id="nome" name="nome" value="<?= valor($dados, 'nome') ?>" maxlength="100" required>
                </div>
                <div class="grade-2">
                    <div class="campo">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" value="<?= valor($dados, 'email') ?>" maxlength="120" required>
                    </div>
                    <div class="campo">
                        <label for="telefone">Telefone</label>
                        <input type="tel" id="telefone" name="telefone" value="<?= valor($dados, 'telefone') ?>" data-mask="telefone" placeholder="(00) 00000-0000" required>
                    </div>
                </div>
                <div class="grade-2">
                    <div class="campo">
                        <label for="senha">Senha</label>
                        <input type="password" id="senha" name="senha" autocomplete="new-password" minlength="8" required>
                        <small class="dica">Mínimo de 8 caracteres, com letras e números.</small>
                    </div>
                    <div class="campo">
                        <label for="confirmar">Confirmar senha</label>
                        <input type="password" id="confirmar" name="confirmar" autocomplete="new-password" required>
                    </div>
                </div>
                <button type="submit" class="btn btn-primario btn-bloco">Criar conta</button>
            </form>
            <p class="auth-rodape">Já tem conta? <a href="login.php">Entrar</a></p>
        </div>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
