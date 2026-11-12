<?php
require_once __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validar();
    encerrar_sessao();
    flash('sucesso', 'Você saiu do sistema com segurança.');
}
redirecionar('login.php');
