<?php
require_once __DIR__ . '/includes/bootstrap.php';

if (usuario_logado()) {
    redirecionar('principal.php');
}

$erro  = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $email = mb_strtolower(post('email'));
    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';

    // 1) validação dos campos
    if ($email === '' || $senha === '') {
        $erro = 'Preencha o e-mail e a senha.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } else {
        // 2) validação das credenciais
        $st = db()->prepare('SELECT id_usuario, nome, senha_hash FROM usuario WHERE email = ?');
        $st->execute([$email]);
        $u = $st->fetch();

        if ($u && password_verify($senha, $u['senha_hash'])) {
            if (password_needs_rehash($u['senha_hash'], PASSWORD_DEFAULT)) {
                db()->prepare('UPDATE usuario SET senha_hash = ? WHERE id_usuario = ?')
                    ->execute([password_hash($senha, PASSWORD_DEFAULT), $u['id_usuario']]);
            }
            iniciar_sessao($u);
            redirecionar('principal.php');
        }
        // mensagem genérica: não revela se o e-mail existe
        $erro = 'E-mail ou senha incorretos.';
    }
    $_SESSION['falhas'] = ($_SESSION['falhas'] ?? 0) + 1;
}

$titulo = 'Entrar';
include __DIR__ . '/includes/header.php';
?>
<main class="auth">
    <section class="auth-brand">
        <div class="auth-brand-in">
            <span class="marca marca-grande"><span class="marca-icone" aria-hidden="true">⚙</span>Oficina<b>Pro</b></span>
            <h1>Agendamentos e clientes em um só lugar.</h1>
            <p>Controle revisões, veículos e serviços da oficina com segurança e sem perder horários.</p>
            <ul class="auth-lista">
                <li>Dados pessoais protegidos conforme a LGPD</li>
                <li>Sessão com tempo de expiração</li>
                <li>Agenda organizada por dia e horário</li>
            </ul>
        </div>
    </section>

    <section class="auth-form">
        <div class="auth-card">
            <h2>Entrar no sistema</h2>
            <p class="muted">Use seu e-mail e senha cadastrados.</p>

            <?php flash_exibir(); ?>
            <?php if ($erro !== ''): ?>
                <div class="alert alert-erro" role="alert"><span><?= e($erro) ?></span></div>
            <?php endif; ?>

            <form method="post" novalidate>
                <?= csrf_campo() ?>
                <div class="campo">
                    <label for="email">E-mail</label>
                    <input type="email" id="email" name="email" value="<?= e($email) ?>" autocomplete="username" required autofocus>
                </div>
                <div class="campo">
                    <label for="senha">Senha</label>
                    <div class="campo-senha">
                        <input type="password" id="senha" name="senha" autocomplete="current-password" required>
                        <button type="button" class="toggle-senha" aria-label="Mostrar senha">Mostrar</button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primario btn-bloco">Entrar</button>
            </form>

            <?php if ($erro !== ''): ?>
                <div class="caixa-ajuda">
                    <strong>Não conseguiu entrar?</strong>
                    <p>Informe seu e-mail ou telefone e receba um código de verificação para acessar.</p>
                    <a class="btn btn-secundario btn-bloco" href="verificar.php">Receber código de verificação</a>
                </div>
            <?php endif; ?>

            <p class="auth-rodape">Ainda não tem acesso? <a href="cadastro.php">Criar conta</a></p>
        </div>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
