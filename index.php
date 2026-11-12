<?php
require_once __DIR__ . '/includes/bootstrap.php';
redirecionar(usuario_logado() ? 'principal.php' : 'login.php');
