<?php
/**
 * Validação de login por código (requisito Id_validação).
 * Etapa 1: o usuário informa e-mail ou telefone cadastrado e recebe um código.
 * Etapa 2: digita o código recebido e, se válido, entra no sistema.
 */
require_once __DIR__ . '/includes/bootstrap.php';

if (usuario_logado()) {
    redirecionar('principal.php');
}

$pdo   = db();
$erro  = '';
$canal = 'email';
$destino = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    $acao = post('acao');

    if ($acao === 'cancelar') {
        unset($_SESSION['verif_usuario'], $_SESSION['verif_demo'], $_SESSION['verif_tentativas']);
        redirecionar('verificar.php');
    }

    if ($acao === 'enviar') {
        $canal   = post('canal') === 'telefone' ? 'telefone' : 'email';
        $destino = post('destino');

        if ($canal === 'email') {
            $destino = mb_strtolower($destino);
            $valido  = filter_var($destino, FILTER_VALIDATE_EMAIL) !== false;
            $campo   = 'email';
            $busca   = $destino;
        } else {
            $busca  = so_digitos($destino);
            $valido = in_array(strlen($busca), [10, 11], true);
            $campo  = 'telefone';
        }

        if (!$valido) {
            $erro = 'Informe um ' . ($canal === 'email' ? 'e-mail' : 'telefone') . ' válido.';
        } else {
            // $campo vem de lista fixa acima (nunca do usuário), seguro para o SQL
            $st = $pdo->prepare("SELECT id_usuario FROM usuario WHERE $campo = ?");
            $st->execute([$busca]);
            $idUsuario = $st->fetchColumn();

            if (!$idUsuario) {
                $erro = 'Não encontramos nenhum usuário com este ' . ($canal === 'email' ? 'e-mail' : 'telefone') . '.';
            } else {
                $codigo = (string) random_int(100000, 999999);
                $pdo->prepare('UPDATE codigo_verificacao SET utilizado = 1 WHERE id_usuario = ? AND utilizado = 0')
                    ->execute([$idUsuario]);
                $pdo->prepare(
                    'INSERT INTO codigo_verificacao (codigo, canal_envio, destino, data_expiracao, utilizado, id_usuario)
                     VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), 0, ?)'
                )->execute([$codigo, $canal, $busca, CODIGO_MINUTOS, $idUsuario]);

                $_SESSION['verif_usuario']    = (int) $idUsuario;
                $_SESSION['verif_tentativas'] = 0;
                if (MOSTRAR_CODIGO_NA_TELA) {
                    $_SESSION['verif_demo'] = $codigo;
                }
                // Produção: aqui entraria o envio real por e-mail (SMTP) ou SMS.
            }
        }
    }

    if ($acao === 'validar' && isset($_SESSION['verif_usuario'])) {
        $codigo = so_digitos(post('codigo'));
        $_SESSION['verif_tentativas'] = ($_SESSION['verif_tentativas'] ?? 0) + 1;

        if ($_SESSION['verif_tentativas'] > 5) {
            unset($_SESSION['verif_usuario'], $_SESSION['verif_demo'], $_SESSION['verif_tentativas']);
            flash('erro', 'Muitas tentativas incorretas. Solicite um novo código.');
            redirecionar('verificar.php');
        }

        $st = $pdo->prepare(
            'SELECT c.id_codigo, u.id_usuario, u.nome
               FROM codigo_verificacao c JOIN usuario u ON u.id_usuario = c.id_usuario
              WHERE c.id_usuario = ? AND c.codigo = ? AND c.utilizado = 0 AND c.data_expiracao > NOW()
              ORDER BY c.id_codigo DESC LIMIT 1'
        );
        $st->execute([$_SESSION['verif_usuario'], $codigo]);
        $ok = $st->fetch();

        if ($ok) {
            $pdo->prepare('UPDATE codigo_verificacao SET utilizado = 1 WHERE id_codigo = ?')->execute([$ok['id_codigo']]);
            unset($_SESSION['verif_demo'], $_SESSION['verif_tentativas']);
            iniciar_sessao($ok);
            flash('sucesso', 'Código validado. Bem-vindo(a), ' . $ok['nome'] . '!');
            redirecionar('principal.php');
        }
        $erro = 'Código inválido ou expirado.';
    }
}

$etapa  = isset($_SESSION['verif_usuario']) ? 2 : 1;
$titulo = 'Código de verificação';
include __DIR__ . '/includes/header.php';
?>
<main class="auth auth-simples">
    <section class="auth-form">
        <div class="auth-card">
            <span class="marca"><span class="marca-icone" aria-hidden="true">⚙</span>Oficina<b>Pro</b></span>
            <h2>Código de verificação</h2>
            <?php flash_exibir(); ?>
            <?php if ($erro !== ''): ?>
                <div class="alert alert-erro" role="alert"><span><?= e($erro) ?></span></div>
            <?php endif; ?>

            <?php if ($etapa === 1): ?>
                <p class="muted">Informe o e-mail ou o telefone cadastrado para receber um código de acesso.</p>
                <form method="post" novalidate>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="enviar">
                    <div class="campo">
                        <span class="rotulo">Enviar código por</span>
                        <div class="opcoes">
                            <label class="opcao"><input type="radio" name="canal" value="email" <?= $canal === 'email' ? 'checked' : '' ?>> E-mail</label>
                            <label class="opcao"><input type="radio" name="canal" value="telefone" <?= $canal === 'telefone' ? 'checked' : '' ?>> Telefone</label>
                        </div>
                    </div>
                    <div class="campo">
                        <label for="destino">E-mail ou telefone</label>
                        <input type="text" id="destino" name="destino" value="<?= e($destino) ?>" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primario btn-bloco">Enviar código</button>
                </form>
            <?php else: ?>
                <p class="muted">Digite o código de 6 dígitos. Ele é válido por <?= (int) CODIGO_MINUTOS ?> minutos.</p>
                <?php if (!empty($_SESSION['verif_demo'])): ?>
                    <div class="alert alert-info" role="status">
                        <span><strong>Modo demonstração:</strong> seu código é <strong class="codigo-demo"><?= e($_SESSION['verif_demo']) ?></strong>
                        (em produção ele seria enviado por e-mail ou SMS).</span>
                    </div>
                <?php endif; ?>
                <form method="post" novalidate>
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="validar">
                    <div class="campo">
                        <label for="codigo">Código</label>
                        <input type="text" id="codigo" name="codigo" class="input-codigo" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primario btn-bloco">Validar e entrar</button>
                </form>
                <form method="post" class="form-linha">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="acao" value="cancelar">
                    <button type="submit" class="btn btn-link">Usar outro e-mail ou telefone</button>
                </form>
            <?php endif; ?>

            <p class="auth-rodape"><a href="login.php">&larr; Voltar ao login</a></p>
        </div>
    </section>
</main>
<?php include __DIR__ . '/includes/footer.php'; ?>
