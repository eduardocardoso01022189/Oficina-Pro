# OficinaPro — Sistema de agendamento de revisões

Projeto da avaliação prática SAEP: cadastro e gestão de clientes, veículos, serviços e agendamentos de revisão, com autenticação com expiração e CPF criptografado (LGPD).

## Requisitos de infraestrutura
| Item | Valor |
|---|---|
| SGBD | MySQL (MariaDB do XAMPP) — confira a versão no phpMyAdmin |
| Linguagem | PHP 7.4+ (com extensão `openssl` e `pdo_mysql`, ativas por padrão no XAMPP), HTML5, CSS3 e JavaScript |
| Ambiente | VS Code + XAMPP (Apache + MySQL) |

## Como executar
1. Copie a pasta `oficina` para `C:\xampp\htdocs\`.
2. No XAMPP, inicie **Apache** e **MySQL**.
3. Abra `http://localhost/phpmyadmin` → aba **Importar** → escolha `database/oficina.sql` → **Importar**.
4. Acesse `http://localhost/oficina/`.

Se o MySQL tiver senha ou outra porta, ajuste `config/config.php`.

## Usuários de teste
| E-mail | Senha |
|---|---|
| admin@oficina.com | Admin@123 |
| maria@oficina.com | Maria@123 |
| joao@oficina.com | Joao@123 |

## Requisitos atendidos
| Requisito | Onde |
|---|---|
| Pelo menos 3 registros por tabela (tipos, PKs e FKs) | `database/oficina.sql` |
| Login com validação de falha | `login.php` |
| Cadastro de usuário (Id_login) | `cadastro.php` |
| Código de verificação por e-mail/telefone após falha (Id_validação) | `verificar.php` (tabela `codigo_verificacao`) |
| Login com tempo de expiração | `includes/auth.php` (tabela `sessao_login`, 30 min) |
| CPF criptografado no banco | `includes/crypto.php` (AES-256-GCM) |
| Principal: usuário logado, acesso aos recursos e sair | `principal.php` + menu |
| Clientes: busca, listagem filtrada e edição | `clientes.php`, `cliente_editar.php` |
| Agendamentos ordenados por dia, com dados do cliente e do veículo | `agendamentos.php` |
| Atualizar a data de um agendamento | `agendamento_editar.php` |
| Veículo obrigatoriamente associado a um cliente | `veiculos.php` (FK `id_cliente` NOT NULL) |
| Cadastrar serviço / vincular serviço ao veículo (Id_serviço, Id_veiculo_serviço) | `servicos.php` e `agendamentos.php` (exige ao menos um serviço) |

## Estrutura
```
oficina/
├── config/config.php        # banco, tempos de expiração, segredo de criptografia
├── database/oficina.sql     # tabelas + dados de exemplo
├── includes/                # bootstrap, db (PDO), auth, crypto, funções, layout
├── assets/css/style.css
├── assets/js/app.js         # máscaras, menu, cronômetro da sessão
└── *.php                    # páginas (login, principal, clientes, veículos, ...)
```

## Segurança aplicada
- **SQL Injection:** todas as consultas usam PDO com *prepared statements*.
- **XSS:** toda saída passa por `e()` (`htmlspecialchars`).
- **CSRF:** token em todos os formulários `POST`.
- **Senhas:** `password_hash` / `password_verify` (bcrypt).
- **CPF:** AES-256-GCM no banco + `cpf_hash` (HMAC) para impedir duplicidade e permitir busca; exibido mascarado (`***.456.789-**`) nas listagens.
- **Sessão:** token com expiração validado no banco a cada requisição; `session_regenerate_id` no login.

## Observações
- **Código de verificação:** sem servidor de e-mail/SMS, o código aparece na tela (modo demonstração, `MOSTRAR_CODIGO_NA_TELA` em `config.php`). Em produção, desative e integre um serviço de envio.
- **Regra de horários:** não é possível ter dois agendamentos ativos no mesmo dia e horário (evita perda de horários).
