-- =====================================================================
--  Banco de dados: oficina_db  |  Sistema de agendamento de revisões
--  SGBD: MySQL / MariaDB (XAMPP)  |  Importar pelo phpMyAdmin (aba Importar)
--  Senhas dos usuários de teste:  Admin@123 | Maria@123 | Joao@123
-- =====================================================================
DROP DATABASE IF EXISTS oficina_db;
CREATE DATABASE oficina_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE oficina_db;

-- ---------------------------------------------------------------------
--  TABELAS
-- ---------------------------------------------------------------------
CREATE TABLE usuario (
    id_usuario  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome        VARCHAR(100) NOT NULL,
    email       VARCHAR(120) NOT NULL,
    telefone    VARCHAR(11)  NOT NULL,              -- somente dígitos
    senha_hash  VARCHAR(255) NOT NULL,              -- bcrypt (password_hash)
    perfil      ENUM('administrador','atendente') NOT NULL DEFAULT 'atendente',
    PRIMARY KEY (id_usuario),
    UNIQUE KEY uq_usuario_email (email)
) ENGINE=InnoDB;

CREATE TABLE cliente (
    id_cliente        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome              VARCHAR(100) NOT NULL,
    cpf_criptografado VARCHAR(255) NOT NULL,        -- AES-256-GCM (LGPD)
    cpf_hash          CHAR(64)     NOT NULL,        -- HMAC-SHA256: evita duplicidade e permite busca
    telefone          VARCHAR(11)  NOT NULL,
    email             VARCHAR(120) NOT NULL,
    PRIMARY KEY (id_cliente),
    UNIQUE KEY uq_cliente_cpf_hash (cpf_hash)
) ENGINE=InnoDB;

CREATE TABLE veiculo (
    id_veiculo  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    placa       VARCHAR(7)   NOT NULL,
    marca       VARCHAR(50)  NOT NULL,
    modelo      VARCHAR(50)  NOT NULL,
    ano         SMALLINT UNSIGNED NOT NULL,
    cor         VARCHAR(30)  NOT NULL,
    id_cliente  INT UNSIGNED NOT NULL,              -- todo veículo pertence a um cliente
    PRIMARY KEY (id_veiculo),
    UNIQUE KEY uq_veiculo_placa (placa),
    CONSTRAINT fk_veiculo_cliente FOREIGN KEY (id_cliente)
        REFERENCES cliente (id_cliente) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE servico (
    id_servico       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nome             VARCHAR(80)  NOT NULL,
    descricao        VARCHAR(255) NOT NULL,
    valor            DECIMAL(10,2) NOT NULL,
    duracao_minutos  SMALLINT UNSIGNED NOT NULL,
    PRIMARY KEY (id_servico),
    UNIQUE KEY uq_servico_nome (nome)
) ENGINE=InnoDB;

CREATE TABLE agendamento (
    id_agendamento    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    data_agendamento  DATE NOT NULL,
    hora_agendamento  TIME NOT NULL,
    status            ENUM('agendado','concluido','cancelado') NOT NULL DEFAULT 'agendado',
    observacao        VARCHAR(255) NULL,
    id_veiculo        INT UNSIGNED NOT NULL,
    id_usuario        INT UNSIGNED NOT NULL,        -- atendente que registrou
    PRIMARY KEY (id_agendamento),
    KEY idx_agendamento_data (data_agendamento, hora_agendamento),
    CONSTRAINT fk_agend_veiculo FOREIGN KEY (id_veiculo)
        REFERENCES veiculo (id_veiculo) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_agend_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- Tabela associativa: um agendamento tem vários serviços (N:N)
CREATE TABLE agendamento_servico (
    id_agend_servico  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    valor_aplicado    DECIMAL(10,2) NOT NULL,
    id_agendamento    INT UNSIGNED NOT NULL,
    id_servico        INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_agend_servico),
    UNIQUE KEY uq_agend_servico (id_agendamento, id_servico),
    CONSTRAINT fk_as_agendamento FOREIGN KEY (id_agendamento)
        REFERENCES agendamento (id_agendamento) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_as_servico FOREIGN KEY (id_servico)
        REFERENCES servico (id_servico) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE sessao_login (
    id_sessao       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    token           CHAR(64) NOT NULL,
    data_criacao    DATETIME NOT NULL,
    data_expiracao  DATETIME NOT NULL,
    id_usuario      INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_sessao),
    UNIQUE KEY uq_sessao_token (token),
    CONSTRAINT fk_sessao_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE codigo_verificacao (
    id_codigo       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo          CHAR(6) NOT NULL,
    canal_envio     ENUM('email','telefone') NOT NULL,
    destino         VARCHAR(120) NOT NULL,
    data_expiracao  DATETIME NOT NULL,
    utilizado       TINYINT(1) NOT NULL DEFAULT 0,
    id_usuario      INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_codigo),
    CONSTRAINT fk_codigo_usuario FOREIGN KEY (id_usuario)
        REFERENCES usuario (id_usuario) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
--  DADOS DE EXEMPLO (3+ registros por tabela)
-- ---------------------------------------------------------------------
INSERT INTO usuario (nome, email, telefone, senha_hash, perfil) VALUES
('Carlos Mendes','admin@oficina.com','41999990001','$2y$12$hkmh4r9SW2O6a2oU2AUNOO/Jut4tL5DnQXtNNFlMw9SybxyApYGGy','administrador'),
('Maria Souza','maria@oficina.com','41999990002','$2y$12$IAkYgM0juLPqnG1sCpB2oeFll.K3Jdf10Q5KsjM2RxQUufEne27Yu','atendente'),
('João Pereira','joao@oficina.com','41999990003','$2y$12$kCQbNtl16KrOYh3.T5C2KOlmFRt5cFNWUcVw7koKbW6e3S3iJKLRC','atendente');

INSERT INTO cliente (nome, cpf_criptografado, cpf_hash, telefone, email) VALUES
('Ana Beatriz Lima','BWomauERll8B4Bptp8q4sx7Oar/8qH6zq1t4YPX9X3sQwVd4d0MB','b8759f0b4f67475f7122c2eb9e242f38186002cac13f20c6773cd9b1bf41d7da','41988880001','ana.lima@email.com'),
('Roberto Carlos Silva','z32y8crFIwds1jPkdaP7dayifpRmHcPh9ldN0L/5Nm2Tp/yT2tOp','4c7695f4f40be77cd679053d08d102b45026b9d1d11a17bdda15a5f974ebe2bb','41988880002','roberto.silva@email.com'),
('Fernanda Oliveira','8Mbr2iBbkbJz7emKZWcOH61EghwH0o+XztuPhJZk1cMYbN8UIQJ4','ff6e5b27c09ea525de814d9859c519f7b33f5bcf65df0b9e8902400df5b69b6a','41988880003','fernanda.oliveira@email.com');

INSERT INTO veiculo (placa, marca, modelo, ano, cor, id_cliente) VALUES
('ABC1D23', 'Fiat',       'Argo',  2020, 'Prata',  1),
('QWE4R56', 'Volkswagen', 'Gol',   2018, 'Branco', 2),
('JKL7M89', 'Chevrolet',  'Onix',  2022, 'Preto',  3),
('RTY2U34', 'Honda',      'Civic', 2019, 'Cinza',  1);

INSERT INTO servico (nome, descricao, valor, duracao_minutos) VALUES
('Revisão completa',           'Inspeção geral: motor, suspensão, elétrica e fluidos', 350.00, 120),
('Troca de óleo e filtro',     'Óleo sintético e filtro de óleo novos',                  180.00,  45),
('Alinhamento e balanceamento','Alinhamento 3D e balanceamento das quatro rodas',        120.00,  60),
('Revisão de freios',          'Pastilhas, discos e fluido de freio',                    220.00,  90);

INSERT INTO agendamento (data_agendamento, hora_agendamento, status, observacao, id_veiculo, id_usuario) VALUES
(DATE_ADD(CURDATE(), INTERVAL 2 DAY),  '09:00:00', 'agendado',  'Cliente relata barulho na suspensão', 1, 1),
(DATE_ADD(CURDATE(), INTERVAL 5 DAY),  '14:00:00', 'agendado',  NULL,                                   2, 2),
(DATE_ADD(CURDATE(), INTERVAL 1 DAY),  '10:30:00', 'agendado',  'Revisão dos 30.000 km',                3, 1),
(DATE_SUB(CURDATE(), INTERVAL 7 DAY),  '08:30:00', 'concluido', NULL,                                   4, 3);

INSERT INTO agendamento_servico (valor_aplicado, id_agendamento, id_servico) VALUES
(350.00, 1, 1),
(180.00, 1, 2),
(180.00, 2, 2),
(120.00, 3, 3),
(220.00, 4, 4);

-- sessões e códigos de exemplo (já expirados / utilizados)
INSERT INTO sessao_login (token, data_criacao, data_expiracao, id_usuario) VALUES
('e8bc163c82eee18733288c7d4ac636db3a6deb013ef2d37b68322be20edc45cc', DATE_SUB(NOW(), INTERVAL 3 HOUR), DATE_SUB(NOW(), INTERVAL 150 MINUTE), 1),
('ad328846aa18b32a335816374511cac1063c704b8c57999e51da9f908290a7a4', DATE_SUB(NOW(), INTERVAL 2 HOUR), DATE_SUB(NOW(), INTERVAL 90 MINUTE),  2),
('41242b9fae56fad4e6e77dfe33cb18d1c3fc583f988cf25ef9f2d9be0d440bbb', DATE_SUB(NOW(), INTERVAL 1 DAY),  DATE_SUB(NOW(), INTERVAL 1410 MINUTE), 3);

INSERT INTO codigo_verificacao (codigo, canal_envio, destino, data_expiracao, utilizado, id_usuario) VALUES
('482913', 'email',    'maria@oficina.com', DATE_SUB(NOW(), INTERVAL 1 DAY), 1, 2),
('735204', 'telefone', '41999990003',       DATE_SUB(NOW(), INTERVAL 2 DAY), 1, 3),
('119867', 'email',    'admin@oficina.com', DATE_SUB(NOW(), INTERVAL 3 DAY), 0, 1);
