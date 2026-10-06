-- Frota · PF / Frota Pública RO — modelo relacional 1.0.2
-- Instalação nova: MySQL 8.0.16+ / MariaDB 10.11+. Importar em banco vazio.
-- Não contém DROP, senha padrão nem dados operacionais fictícios.
-- No phpMyAdmin: selecionar o banco, Importar, formato SQL, UTF-8.
-- Datetimes de negócio em UTC; fuso de apresentação em configuracao_sistema.
SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;
SET time_zone = '+00:00';
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- 01 · Configuração, unidades e identidade

CREATE TABLE versoes_modelo (
  versao VARCHAR(30) NOT NULL PRIMARY KEY,
  instalado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  descricao VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Identifica a versão instalada do esquema.';

-- Fila durável do Laravel. A recuperação armazena somente o identificador cifrado no payload.
CREATE TABLE jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  queue VARCHAR(255) NOT NULL,
  payload LONGTEXT NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL,
  reserved_at INT UNSIGNED NULL,
  available_at INT UNSIGNED NOT NULL,
  created_at INT UNSIGNED NOT NULL,
  KEY ix_jobs_queue (queue)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fila durável para tarefas assíncronas; payloads sensíveis devem estar cifrados.';

CREATE TABLE failed_jobs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  uuid VARCHAR(255) NOT NULL UNIQUE,
  connection TEXT NOT NULL,
  queue TEXT NOT NULL,
  payload LONGTEXT NOT NULL,
  exception LONGTEXT NOT NULL,
  failed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Falhas da fila Laravel; payload de recuperação contém somente identificador cifrado.';

CREATE TABLE unidades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  unidade_superior_id BIGINT UNSIGNED NULL,
  codigo VARCHAR(30) NOT NULL,
  nome VARCHAR(150) NOT NULL,
  endereco VARCHAR(255) NULL,
  cidade VARCHAR(100) NULL,
  uf CHAR(2) NULL,
  ativa TINYINT UNSIGNED NOT NULL DEFAULT 1,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_unidade_codigo (codigo),
  CONSTRAINT fk_unidade_superior FOREIGN KEY (unidade_superior_id) REFERENCES unidades(id),
  CONSTRAINT ck_unidade_ativa CHECK (ativa IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Unidades e setores internos de um único órgão.';

CREATE TABLE configuracao_sistema (
  id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
  nome_sistema VARCHAR(120) NOT NULL,
  nome_orgao VARCHAR(150) NOT NULL,
  sigla_orgao VARCHAR(30) NULL,
  fuso_horario VARCHAR(64) NOT NULL DEFAULT 'America/Porto_Velho',
  moeda CHAR(3) NOT NULL DEFAULT 'BRL',
  sessao_inatividade_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 30,
  limite_sem_comunicacao_minutos SMALLINT UNSIGNED NOT NULL DEFAULT 15,
  logo_arquivo_id BIGINT UNSIGNED NULL,
  bootstrap_concluido TINYINT UNSIGNED NOT NULL DEFAULT 0,
  atualizado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT ck_config_unica CHECK (id = 1),
  CONSTRAINT ck_config_sessao CHECK (sessao_inatividade_minutos BETWEEN 5 AND 1440),
  CONSTRAINT ck_config_bootstrap CHECK (bootstrap_concluido IN (0,1)),
  CONSTRAINT ck_config_comunicacao CHECK (limite_sem_comunicacao_minutos > 0),
  CONSTRAINT ck_config_moeda CHECK (moeda = 'BRL')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Uma única configuração do órgão; também serializa operações administrativas.';

CREATE TABLE usuarios (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  unidade_id BIGINT UNSIGNED NOT NULL,
  identificador VARCHAR(100) NOT NULL,
  nome VARCHAR(150) NOT NULL,
  email VARCHAR(254) NULL,
  telefone VARCHAR(30) NULL,
  senha_hash VARCHAR(255) NOT NULL,
  ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
  deve_trocar_senha TINYINT UNSIGNED NOT NULL DEFAULT 0,
  senha_alterada_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  versao BIGINT UNSIGNED NOT NULL DEFAULT 1,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  atualizado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_usuario_identificador (identificador),
  UNIQUE KEY uq_usuario_email (email),
  KEY ix_usuario_unidade_ativo (unidade_id, ativo),
  CONSTRAINT fk_usuario_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id),
  CONSTRAINT ck_usuario_flags CHECK (ativo IN (0,1) AND deve_trocar_senha IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Identidade institucional. Senha somente em hash gerado no backend.';

CREATE TABLE arquivos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  enviado_por BIGINT UNSIGNED NOT NULL,
  chave_armazenamento VARCHAR(255) NOT NULL,
  nome_original VARCHAR(255) NOT NULL,
  tipo_mime VARCHAR(120) NOT NULL,
  tamanho_bytes BIGINT UNSIGNED NOT NULL,
  sha256 BINARY(32) NOT NULL,
  situacao ENUM('pendente','disponivel','bloqueado','arquivado') NOT NULL DEFAULT 'pendente',
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_arquivo_chave (chave_armazenamento),
  KEY ix_arquivo_hash (sha256),
  CONSTRAINT fk_arquivo_autor FOREIGN KEY (enviado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_arquivo_tamanho CHECK (tamanho_bytes > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Metadados de documentos no armazenamento privado; não guarda bytes no banco.';

ALTER TABLE configuracao_sistema ADD CONSTRAINT fk_config_logo FOREIGN KEY (logo_arquivo_id) REFERENCES arquivos(id);

CREATE TABLE preferencias_usuario (
  usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  tema ENUM('claro','escuro','dispositivo') NOT NULL DEFAULT 'dispositivo',
  itens_por_pagina SMALLINT UNSIGNED NOT NULL DEFAULT 10,
  notificacoes_internas TINYINT UNSIGNED NOT NULL DEFAULT 1,
  atualizado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_preferencia_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT ck_preferencia_paginacao CHECK (itens_por_pagina IN (10,20,50,100)),
  CONSTRAINT ck_preferencia_notificacao CHECK (notificacoes_internas IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Preferências compartilhadas por computador e celular.';

CREATE TABLE motoristas (
  usuario_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  numero_cnh VARCHAR(20) NOT NULL,
  categoria_cnh VARCHAR(10) NOT NULL,
  categorias_autorizadas VARCHAR(5) NOT NULL,
  validade_cnh DATE NOT NULL,
  ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
  cnh_arquivo_id BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_motorista_cnh (numero_cnh),
  CONSTRAINT fk_motorista_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_motorista_documento FOREIGN KEY (cnh_arquivo_id) REFERENCES arquivos(id),
  CONSTRAINT ck_motorista_ativo CHECK (ativo IN (0,1)),
  CONSTRAINT ck_motorista_categoria CHECK (categorias_autorizadas REGEXP '^[ABCDE]{1,5}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Habilitação de usuários que podem efetivamente conduzir veículos.';


-- 02 · Perfis, permissões, sessões e rotas técnicas

CREATE TABLE modulos (
  codigo VARCHAR(40) NOT NULL PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
  CONSTRAINT ck_modulo_ativo CHECK (ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo dos módulos de negócio e de administração.';

CREATE TABLE acoes (
  codigo VARCHAR(40) NOT NULL PRIMARY KEY,
  nome VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ações verificadas pelo servidor, sem privilégio implícito por nome de perfil.';

CREATE TABLE modulo_acoes (
  modulo_codigo VARCHAR(40) NOT NULL,
  acao_codigo VARCHAR(40) NOT NULL,
  PRIMARY KEY (modulo_codigo, acao_codigo),
  CONSTRAINT fk_ma_modulo FOREIGN KEY (modulo_codigo) REFERENCES modulos(codigo),
  CONSTRAINT fk_ma_acao FOREIGN KEY (acao_codigo) REFERENCES acoes(codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Combinações de módulo e ação realmente suportadas pelo sistema.';

CREATE TABLE perfis (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(60) NOT NULL,
  nome VARCHAR(100) NOT NULL,
  descricao TEXT NULL,
  ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
  perfil_origem_id BIGINT UNSIGNED NULL,
  criado_por BIGINT UNSIGNED NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  atualizado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_perfil_codigo (codigo),
  CONSTRAINT fk_perfil_origem FOREIGN KEY (perfil_origem_id) REFERENCES perfis(id),
  CONSTRAINT fk_perfil_autor FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_perfil_ativo CHECK (ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quatro perfis iniciais e perfis personalizados.';

CREATE TABLE permissoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  modulo_codigo VARCHAR(40) NOT NULL,
  acao_codigo VARCHAR(40) NOT NULL,
  alcance ENUM('proprios','unidade','orgao') NOT NULL,
  UNIQUE KEY uq_permissao (modulo_codigo, acao_codigo, alcance),
  CONSTRAINT fk_permissao_modulo FOREIGN KEY (modulo_codigo) REFERENCES modulos(codigo),
  CONSTRAINT fk_permissao_acao FOREIGN KEY (acao_codigo) REFERENCES acoes(codigo),
  CONSTRAINT fk_permissao_combinacao FOREIGN KEY (modulo_codigo, acao_codigo) REFERENCES modulo_acoes(modulo_codigo, acao_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permissão formada por módulo, ação e alcance.';

CREATE TABLE perfil_permissoes (
  perfil_id BIGINT UNSIGNED NOT NULL,
  permissao_id BIGINT UNSIGNED NOT NULL,
  delegavel TINYINT UNSIGNED NOT NULL DEFAULT 0,
  concedido_por BIGINT UNSIGNED NULL,
  concedido_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (perfil_id, permissao_id),
  CONSTRAINT fk_pp_perfil FOREIGN KEY (perfil_id) REFERENCES perfis(id),
  CONSTRAINT fk_pp_permissao FOREIGN KEY (permissao_id) REFERENCES permissoes(id),
  CONSTRAINT fk_pp_autor FOREIGN KEY (concedido_por) REFERENCES usuarios(id),
  CONSTRAINT ck_pp_delegavel CHECK (delegavel IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Matriz de ações e alcances; marca concessões que podem ser delegadas.';

CREATE TABLE usuario_perfis (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  perfil_id BIGINT UNSIGNED NOT NULL,
  unidade_id BIGINT UNSIGNED NOT NULL,
  vigente_desde DATETIME(6) NOT NULL,
  vigente_ate DATETIME(6) NULL,
  ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
  concedido_por BIGINT UNSIGNED NULL,
  revogado_por BIGINT UNSIGNED NULL,
  motivo_revogacao VARCHAR(500) NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_vinculo_identidade (id, usuario_id),
  KEY ix_vinculo_validade (usuario_id, ativo, vigente_desde, vigente_ate),
  KEY ix_vinculo_perfil (perfil_id, ativo, vigente_ate),
  CONSTRAINT fk_up_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_up_perfil FOREIGN KEY (perfil_id) REFERENCES perfis(id),
  CONSTRAINT fk_up_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id),
  CONSTRAINT fk_up_concessor FOREIGN KEY (concedido_por) REFERENCES usuarios(id),
  CONSTRAINT fk_up_revogador FOREIGN KEY (revogado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_vinculo_datas CHECK (vigente_ate IS NULL OR vigente_ate > vigente_desde),
  CONSTRAINT ck_vinculo_ativo CHECK (ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vínculos independentes com início, fim opcional e unidade de alcance.';

CREATE TABLE sessoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  vinculo_ativo_id BIGINT UNSIGNED NULL,
  token_hash BINARY(32) NOT NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  ultima_atividade_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expira_em DATETIME(6) NOT NULL,
  encerrada_em DATETIME(6) NULL,
  motivo_encerramento ENUM('logout','inatividade','prazo','revogacao','troca_senha') NULL,
  ip VARBINARY(16) NULL,
  agente_usuario VARCHAR(500) NULL,
  UNIQUE KEY uq_sessao_token (token_hash),
  UNIQUE KEY uq_sessao_identidade (id, usuario_id),
  KEY ix_sessao_ativa (usuario_id, encerrada_em, expira_em),
  CONSTRAINT fk_sessao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_sessao_vinculo FOREIGN KEY (vinculo_ativo_id, usuario_id) REFERENCES usuario_perfis(id, usuario_id),
  CONSTRAINT ck_sessao_datas CHECK (expira_em > criado_em AND ultima_atividade_em >= criado_em),
  CONSTRAINT ck_sessao_fim CHECK ((encerrada_em IS NULL AND motivo_encerramento IS NULL) OR (encerrada_em IS NOT NULL AND motivo_encerramento IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sessões com token somente em hash; atividade, expiração e encerramento separados.';

CREATE TABLE controle_vinculos (
  vinculo_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  perfil_id BIGINT UNSIGNED NOT NULL,
  unidade_id BIGINT UNSIGNED NOT NULL,
  vigente_desde DATETIME(6) NOT NULL,
  vigente_ate DATETIME(6) NULL,
  ativo_vinculo TINYINT UNSIGNED NOT NULL,
  ativo_usuario TINYINT UNSIGNED NOT NULL,
  ativo_perfil TINYINT UNSIGNED NOT NULL,
  ativo_unidade TINYINT UNSIGNED NOT NULL,
  pode_administrar TINYINT UNSIGNED NOT NULL,
  KEY ix_cv_admin (pode_administrar, ativo_vinculo, vigente_ate),
  KEY ix_cv_overlap (usuario_id, perfil_id, ativo_vinculo, vigente_desde, vigente_ate),
  CONSTRAINT fk_cv_vinculo FOREIGN KEY (vinculo_id) REFERENCES usuario_perfis(id),
  CONSTRAINT fk_cv_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_cv_perfil FOREIGN KEY (perfil_id) REFERENCES perfis(id),
  CONSTRAINT fk_cv_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id),
  CONSTRAINT ck_cv_flags CHECK (ativo_vinculo IN (0,1) AND ativo_usuario IN (0,1) AND ativo_perfil IN (0,1) AND ativo_unidade IN (0,1) AND pode_administrar IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Materialização técnica mantida por triggers para locks de acesso e proteção administrativa.';

CREATE TABLE recuperacoes_senha (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id BIGINT UNSIGNED NOT NULL,
  token_hash BINARY(32) NOT NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  expira_em DATETIME(6) NOT NULL,
  usado_em DATETIME(6) NULL,
  invalidado_em DATETIME(6) NULL,
  UNIQUE KEY uq_recuperacao_token (token_hash),
  CONSTRAINT fk_recuperacao_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT ck_recuperacao_validade CHECK (expira_em > criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tokens de uso único com validade; envio e consumo pertencem ao backend.';

CREATE TABLE rotas_sistema (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(80) NOT NULL,
  nome VARCHAR(120) NOT NULL,
  caminho VARCHAR(255) NOT NULL,
  metodo_http ENUM('GET','POST','PUT','PATCH','DELETE') NOT NULL DEFAULT 'GET',
  modulo_codigo VARCHAR(40) NOT NULL,
  descricao TEXT NULL,
  ativa TINYINT UNSIGNED NOT NULL DEFAULT 1,
  visivel_menu TINYINT UNSIGNED NOT NULL DEFAULT 1,
  implementada TINYINT UNSIGNED NOT NULL DEFAULT 0,
  protegida TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  criado_por BIGINT UNSIGNED NULL,
  UNIQUE KEY uq_rota_chave (chave),
  UNIQUE KEY uq_rota_metodo_caminho (metodo_http, caminho),
  CONSTRAINT fk_rota_modulo FOREIGN KEY (modulo_codigo) REFERENCES modulos(codigo),
  CONSTRAINT fk_rota_autor FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_rota_caminho CHECK (LEFT(caminho,1) = '/'),
  CONSTRAINT ck_rota_flags CHECK (ativa IN (0,1) AND visivel_menu IN (0,1) AND implementada IN (0,1) AND protegida IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rotas HTTP e de interface. Não são trajetos de veículos nem código executável.';

CREATE TABLE rota_permissoes (
  rota_id BIGINT UNSIGNED NOT NULL,
  modulo_codigo VARCHAR(40) NOT NULL,
  acao_codigo VARCHAR(40) NOT NULL,
  PRIMARY KEY (rota_id, modulo_codigo, acao_codigo),
  CONSTRAINT fk_rp_rota FOREIGN KEY (rota_id) REFERENCES rotas_sistema(id),
  CONSTRAINT fk_rp_modulo FOREIGN KEY (modulo_codigo) REFERENCES modulos(codigo),
  CONSTRAINT fk_rp_acao FOREIGN KEY (acao_codigo) REFERENCES acoes(codigo),
  CONSTRAINT fk_rp_combinacao FOREIGN KEY (modulo_codigo, acao_codigo) REFERENCES modulo_acoes(modulo_codigo, acao_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Todas as ações requeridas por uma rota, sem elevar o alcance concedido.';

CREATE TABLE auditoria (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  ator_usuario_id BIGINT UNSIGNED NULL,
  ator_vinculo_id BIGINT UNSIGNED NULL,
  sessao_id BIGINT UNSIGNED NULL,
  ator_nome_snapshot VARCHAR(150) NULL,
  perfil_nome_snapshot VARCHAR(100) NULL,
  perfil_codigo_snapshot VARCHAR(60) NULL,
  evento VARCHAR(80) NOT NULL,
  entidade VARCHAR(60) NULL,
  entidade_id BIGINT UNSIGNED NULL,
  correlacao CHAR(36) NULL,
  descricao VARCHAR(1000) NULL,
  antes JSON NULL,
  depois JSON NULL,
  ip VARBINARY(16) NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY ix_auditoria_data (criado_em, id),
  KEY ix_auditoria_ator (ator_usuario_id, criado_em),
  KEY ix_auditoria_entidade (entidade, entidade_id, criado_em),
  CONSTRAINT fk_auditoria_usuario FOREIGN KEY (ator_usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_auditoria_vinculo FOREIGN KEY (ator_vinculo_id, ator_usuario_id) REFERENCES usuario_perfis(id, usuario_id),
  CONSTRAINT fk_auditoria_sessao FOREIGN KEY (sessao_id, ator_usuario_id) REFERENCES sessoes(id, usuario_id),
  CONSTRAINT ck_auditoria_contexto CHECK ((ator_vinculo_id IS NULL AND sessao_id IS NULL) OR ator_usuario_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log acrescentado sem edição. Não registrar senhas, tokens ou bytes de arquivos.';


-- 03 · Frota, documentos e dispositivos

CREATE TABLE categorias_veiculo (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(60) NOT NULL,
  categoria_cnh_requerida CHAR(1) NOT NULL DEFAULT 'B',
  ativa TINYINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_categoria_veiculo_nome (nome),
  CONSTRAINT ck_categoria_cnh CHECK (categoria_cnh_requerida IN ('A','B','C','D','E')),
  CONSTRAINT ck_categoria_veiculo_ativa CHECK (ativa IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorias de veículos e categoria mínima de habilitação.';

CREATE TABLE veiculos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  unidade_id BIGINT UNSIGNED NOT NULL,
  categoria_id SMALLINT UNSIGNED NOT NULL,
  nome VARCHAR(120) NOT NULL,
  placa CHAR(7) NOT NULL,
  renavam VARCHAR(20) NULL,
  chassi VARCHAR(30) NULL,
  marca VARCHAR(80) NULL,
  modelo VARCHAR(80) NULL,
  ano_fabricacao SMALLINT UNSIGNED NULL,
  ano_modelo SMALLINT UNSIGNED NULL,
  capacidade SMALLINT UNSIGNED NOT NULL,
  quilometragem_atual DECIMAL(12,1) NOT NULL DEFAULT 0,
  situacao_cadastro ENUM('ativo','inativo','baixado') NOT NULL DEFAULT 'ativo',
  observacoes TEXT NULL,
  criado_por BIGINT UNSIGNED NOT NULL,
  versao BIGINT UNSIGNED NOT NULL DEFAULT 1,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  atualizado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_veiculo_placa (placa),
  UNIQUE KEY uq_veiculo_renavam (renavam),
  UNIQUE KEY uq_veiculo_chassi (chassi),
  KEY ix_veiculo_unidade_status (unidade_id, situacao_cadastro),
  CONSTRAINT fk_veiculo_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id),
  CONSTRAINT fk_veiculo_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_veiculo(id),
  CONSTRAINT fk_veiculo_autor FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_veiculo_capacidade CHECK (capacidade BETWEEN 1 AND 100),
  CONSTRAINT ck_veiculo_km CHECK (quilometragem_atual >= 0),
  CONSTRAINT ck_veiculo_placa CHECK (CHAR_LENGTH(placa) = 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cadastro da frota. Em viagem e manutenção são estados derivados da agenda.';

CREATE TABLE documentos_veiculo (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  arquivo_id BIGINT UNSIGNED NOT NULL,
  tipo VARCHAR(60) NOT NULL,
  numero VARCHAR(80) NULL,
  exercicio SMALLINT UNSIGNED NULL,
  emitido_em DATE NULL,
  vence_em DATE NULL,
  substitui_documento_id BIGINT UNSIGNED NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_documento_veiculo_arquivo (veiculo_id, arquivo_id),
  KEY ix_documento_vencimento (vence_em, veiculo_id),
  CONSTRAINT fk_dv_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
  CONSTRAINT fk_dv_arquivo FOREIGN KEY (arquivo_id) REFERENCES arquivos(id),
  CONSTRAINT fk_dv_anterior FOREIGN KEY (substitui_documento_id) REFERENCES documentos_veiculo(id),
  CONSTRAINT ck_dv_datas CHECK (emitido_em IS NULL OR vence_em IS NULL OR vence_em >= emitido_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos e suas versões; vencimento independente do arquivo.';

CREATE TABLE rastreadores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  identificador_externo VARCHAR(100) NOT NULL,
  provedor VARCHAR(100) NOT NULL,
  tipo ENUM('gps','telematica','outro') NOT NULL DEFAULT 'gps',
  modelo VARCHAR(100) NULL,
  ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_rastreador_provedor (provedor, identificador_externo),
  CONSTRAINT ck_rastreador_ativo CHECK (ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dispositivo e identificação do provedor, sem credenciais da integração.';

CREATE TABLE veiculo_rastreadores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  rastreador_id BIGINT UNSIGNED NOT NULL,
  instalado_em DATETIME(6) NOT NULL,
  removido_em DATETIME(6) NULL,
  instalado_por BIGINT UNSIGNED NOT NULL,
  veiculo_aberto BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN removido_em IS NULL THEN veiculo_id ELSE NULL END) STORED,
  rastreador_aberto BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN removido_em IS NULL THEN rastreador_id ELSE NULL END) STORED,
  UNIQUE KEY uq_vr_veiculo_aberto (veiculo_aberto),
  UNIQUE KEY uq_vr_rastreador_aberto (rastreador_aberto),
  UNIQUE KEY uq_vr_identidade (id, veiculo_id),
  CONSTRAINT fk_vr_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
  CONSTRAINT fk_vr_rastreador FOREIGN KEY (rastreador_id) REFERENCES rastreadores(id),
  CONSTRAINT fk_vr_autor FOREIGN KEY (instalado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_vr_periodo CHECK (removido_em IS NULL OR removido_em > instalado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de instalação. Um veículo e um dispositivo só têm um vínculo aberto.';


-- 04 · Solicitações, revisões e disponibilidade

CREATE TABLE solicitacoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  protocolo VARCHAR(40) NOT NULL,
  solicitante_id BIGINT UNSIGNED NOT NULL,
  unidade_id BIGINT UNSIGNED NOT NULL,
  revisao_atual_id BIGINT UNSIGNED NULL,
  situacao ENUM('rascunho','aguardando_analise','ajustes_solicitados','aprovada','negada','cancelada') NOT NULL DEFAULT 'rascunho',
  versao BIGINT UNSIGNED NOT NULL DEFAULT 1,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  atualizado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_solicitacao_protocolo (protocolo),
  KEY ix_solicitacao_proprios (solicitante_id, situacao, criado_em, id),
  KEY ix_solicitacao_unidade (unidade_id, situacao, criado_em, id),
  CONSTRAINT fk_solicitacao_autor FOREIGN KEY (solicitante_id) REFERENCES usuarios(id),
  CONSTRAINT fk_solicitacao_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id),
  CONSTRAINT ck_solicitacao_revisao CHECK (situacao = 'rascunho' OR revisao_atual_id IS NOT NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Protocolo persistente da solicitação; aponta para a revisão em análise.';

CREATE TABLE solicitacao_revisoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  solicitacao_id BIGINT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  finalidade TEXT NULL,
  origem VARCHAR(255) NULL,
  destino VARCHAR(255) NULL,
  origem_latitude DECIMAL(10,7) NULL,
  origem_longitude DECIMAL(10,7) NULL,
  destino_latitude DECIMAL(10,7) NULL,
  destino_longitude DECIMAL(10,7) NULL,
  saida_prevista DATETIME(6) NULL,
  retorno_previsto DATETIME(6) NULL,
  quantidade_passageiros SMALLINT UNSIGNED NULL,
  necessita_motorista TINYINT UNSIGNED NOT NULL DEFAULT 1,
  motorista_sugerido_id BIGINT UNSIGNED NULL,
  veiculo_pretendido_id BIGINT UNSIGNED NULL,
  trajeto_planejado TEXT NULL,
  polilinha_planejada LONGTEXT NULL,
  distancia_prevista_km DECIMAL(12,3) NULL,
  observacoes TEXT NULL,
  etapa_atual TINYINT UNSIGNED NOT NULL DEFAULT 1,
  criado_por BIGINT UNSIGNED NOT NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  enviado_em DATETIME(6) NULL,
  UNIQUE KEY uq_revisao_numero (solicitacao_id, numero),
  UNIQUE KEY uq_revisao_solicitacao (solicitacao_id, id),
  CONSTRAINT fk_revisao_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id),
  CONSTRAINT fk_revisao_motorista FOREIGN KEY (motorista_sugerido_id) REFERENCES motoristas(usuario_id),
  CONSTRAINT fk_revisao_veiculo FOREIGN KEY (veiculo_pretendido_id) REFERENCES veiculos(id),
  CONSTRAINT fk_revisao_autor FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_revisao_periodo CHECK (saida_prevista IS NULL OR retorno_previsto IS NULL OR retorno_previsto > saida_prevista),
  CONSTRAINT ck_revisao_flags CHECK (necessita_motorista IN (0,1) AND etapa_atual BETWEEN 1 AND 4 AND numero > 0),
  CONSTRAINT ck_revisao_distancia CHECK (distancia_prevista_km IS NULL OR distancia_prevista_km >= 0),
  CONSTRAINT ck_revisao_latitude CHECK ((origem_latitude IS NULL OR origem_latitude BETWEEN -90 AND 90) AND (destino_latitude IS NULL OR destino_latitude BETWEEN -90 AND 90)),
  CONSTRAINT ck_revisao_longitude CHECK ((origem_longitude IS NULL OR origem_longitude BETWEEN -180 AND 180) AND (destino_longitude IS NULL OR destino_longitude BETWEEN -180 AND 180))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Uma versão do formulário de quatro etapas; imutável depois de enviada.';

ALTER TABLE solicitacoes ADD CONSTRAINT fk_solicitacao_revisao_atual FOREIGN KEY (id, revisao_atual_id) REFERENCES solicitacao_revisoes(solicitacao_id, id);

CREATE TABLE solicitacao_paradas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  revisao_id BIGINT UNSIGNED NOT NULL,
  ordem SMALLINT UNSIGNED NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  UNIQUE KEY uq_parada_ordem (revisao_id, ordem),
  CONSTRAINT fk_parada_revisao FOREIGN KEY (revisao_id) REFERENCES solicitacao_revisoes(id),
  CONSTRAINT ck_parada_coordenadas CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude IS NOT NULL AND longitude IS NOT NULL AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paradas ordenadas do percurso de uma revisão específica.';

CREATE TABLE solicitacao_passageiros (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  revisao_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NULL,
  nome VARCHAR(150) NOT NULL,
  contato VARCHAR(100) NULL,
  CONSTRAINT fk_passageiro_revisao FOREIGN KEY (revisao_id) REFERENCES solicitacao_revisoes(id),
  CONSTRAINT fk_passageiro_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lista opcional de passageiros. A contagem informada não inclui o motorista.';

CREATE TABLE solicitacao_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  solicitacao_id BIGINT UNSIGNED NOT NULL,
  revisao_id BIGINT UNSIGNED NOT NULL,
  ator_vinculo_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('criada','enviada','ajustes_solicitados','aprovada','negada','revisao_aberta','cancelada') NOT NULL,
  situacao_anterior VARCHAR(40) NULL,
  situacao_nova VARCHAR(40) NOT NULL,
  motivo TEXT NULL,
  veiculo_confirmado_id BIGINT UNSIGNED NULL,
  motorista_confirmado_id BIGINT UNSIGNED NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY ix_se_historico (solicitacao_id, criado_em, id),
  CONSTRAINT fk_se_revisao FOREIGN KEY (solicitacao_id, revisao_id) REFERENCES solicitacao_revisoes(solicitacao_id, id),
  CONSTRAINT fk_se_vinculo FOREIGN KEY (ator_vinculo_id) REFERENCES usuario_perfis(id),
  CONSTRAINT fk_se_veiculo FOREIGN KEY (veiculo_confirmado_id) REFERENCES veiculos(id),
  CONSTRAINT fk_se_motorista FOREIGN KEY (motorista_confirmado_id) REFERENCES motoristas(usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de envio, ajustes, decisão, revisão e cancelamento.';

CREATE TABLE reservas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  motorista_id BIGINT UNSIGNED NULL,
  revisao_id BIGINT UNSIGNED NULL,
  tipo ENUM('viagem','manutencao','indisponibilidade') NOT NULL,
  inicio DATETIME(6) NOT NULL,
  fim DATETIME(6) NOT NULL,
  situacao ENUM('ativa','liberada','cancelada') NOT NULL DEFAULT 'ativa',
  descricao VARCHAR(500) NULL,
  criado_por BIGINT UNSIGNED NOT NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  liberada_em DATETIME(6) NULL,
  UNIQUE KEY uq_reserva_revisao (revisao_id),
  UNIQUE KEY uq_reserva_viagem (id, veiculo_id, motorista_id, revisao_id),
  UNIQUE KEY uq_reserva_veiculo (id, veiculo_id),
  KEY ix_reserva_veiculo_periodo (veiculo_id, situacao, inicio, fim),
  KEY ix_reserva_motorista_periodo (motorista_id, situacao, inicio, fim),
  CONSTRAINT fk_reserva_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
  CONSTRAINT fk_reserva_motorista FOREIGN KEY (motorista_id) REFERENCES motoristas(usuario_id),
  CONSTRAINT fk_reserva_revisao FOREIGN KEY (revisao_id) REFERENCES solicitacao_revisoes(id),
  CONSTRAINT fk_reserva_autor FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_reserva_periodo CHECK (fim > inicio),
  CONSTRAINT ck_reserva_tipo CHECK ((tipo = 'viagem' AND revisao_id IS NOT NULL AND motorista_id IS NOT NULL) OR (tipo IN ('manutencao','indisponibilidade') AND revisao_id IS NULL AND motorista_id IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Agenda única de viagens e bloqueios. Triggers serializam a reserva por veículo e motorista.';


-- 05 · Viagens, checklists, ocorrências e posições

CREATE TABLE viagens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  protocolo VARCHAR(40) NOT NULL,
  revisao_id BIGINT UNSIGNED NOT NULL,
  reserva_id BIGINT UNSIGNED NOT NULL,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  motorista_id BIGINT UNSIGNED NOT NULL,
  situacao ENUM('programada','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'programada',
  saida_real DATETIME(6) NULL,
  retorno_real DATETIME(6) NULL,
  quilometragem_saida DECIMAL(12,1) NULL,
  quilometragem_retorno DECIMAL(12,1) NULL,
  saida_registrada_por BIGINT UNSIGNED NULL,
  retorno_registrado_por BIGINT UNSIGNED NULL,
  observacao_saida TEXT NULL,
  observacao_retorno TEXT NULL,
  versao BIGINT UNSIGNED NOT NULL DEFAULT 1,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_viagem_protocolo (protocolo),
  UNIQUE KEY uq_viagem_revisao (revisao_id),
  UNIQUE KEY uq_viagem_reserva (reserva_id),
  UNIQUE KEY uq_viagem_veiculo_motorista (id, veiculo_id, motorista_id),
  UNIQUE KEY uq_viagem_veiculo (id, veiculo_id),
  KEY ix_viagem_situacao (situacao, saida_real, id),
  KEY ix_viagem_historico_veiculo (veiculo_id, situacao, saida_real, retorno_real),
  KEY ix_viagem_historico_motorista (motorista_id, situacao, saida_real, retorno_real),
  CONSTRAINT fk_viagem_reserva FOREIGN KEY (reserva_id, veiculo_id, motorista_id, revisao_id) REFERENCES reservas(id, veiculo_id, motorista_id, revisao_id),
  CONSTRAINT fk_viagem_saida_autor FOREIGN KEY (saida_registrada_por) REFERENCES usuarios(id),
  CONSTRAINT fk_viagem_retorno_autor FOREIGN KEY (retorno_registrado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_viagem_km CHECK ((quilometragem_saida IS NULL OR quilometragem_saida >= 0) AND (quilometragem_retorno IS NULL OR quilometragem_retorno >= quilometragem_saida)),
  CONSTRAINT ck_viagem_periodo CHECK (retorno_real IS NULL OR (saida_real IS NOT NULL AND retorno_real > saida_real)),
  CONSTRAINT ck_viagem_estado CHECK ((situacao IN ('programada','cancelada') AND saida_real IS NULL AND retorno_real IS NULL AND quilometragem_saida IS NULL AND quilometragem_retorno IS NULL) OR (situacao = 'em_andamento' AND saida_real IS NOT NULL AND quilometragem_saida IS NOT NULL AND saida_registrada_por IS NOT NULL AND retorno_real IS NULL AND quilometragem_retorno IS NULL) OR (situacao = 'concluida' AND saida_real IS NOT NULL AND retorno_real IS NOT NULL AND quilometragem_saida IS NOT NULL AND quilometragem_retorno IS NOT NULL AND saida_registrada_por IS NOT NULL AND retorno_registrado_por IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aprovação gera viagem programada; saída e retorno são registros distintos.';

CREATE TABLE ocupacoes_agenda (
  reserva_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  motorista_id BIGINT UNSIGNED NULL,
  viagem_id BIGINT UNSIGNED NULL,
  em_andamento TINYINT UNSIGNED NOT NULL DEFAULT 0,
  inicio DATETIME(6) NOT NULL,
  fim DATETIME(6) NOT NULL,
  KEY ix_oa_veiculo (veiculo_id, inicio, fim),
  KEY ix_oa_motorista (motorista_id, inicio, fim),
  CONSTRAINT fk_oa_reserva FOREIGN KEY (reserva_id) REFERENCES reservas(id),
  CONSTRAINT fk_oa_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
  CONSTRAINT fk_oa_motorista FOREIGN KEY (motorista_id) REFERENCES motoristas(usuario_id),
  CONSTRAINT fk_oa_viagem FOREIGN KEY (viagem_id) REFERENCES viagens(id),
  CONSTRAINT ck_oa_periodo CHECK (fim > inicio),
  CONSTRAINT ck_oa_em_andamento CHECK (em_andamento IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Espelho técnico apenas das reservas ativas, mantido por triggers para leituras bloqueantes.';

CREATE TABLE checklist_itens (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(40) NOT NULL,
  descricao VARCHAR(150) NOT NULL,
  obrigatorio TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
  ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  UNIQUE KEY uq_checklist_codigo (codigo),
  CONSTRAINT ck_checklist_flags CHECK (obrigatorio IN (0,1) AND ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens padronizados das vistorias de saída e retorno.';

CREATE TABLE viagem_checklists (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  viagem_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('saida','retorno') NOT NULL,
  preenchido_por BIGINT UNSIGNED NOT NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  finalizado_em DATETIME(6) NULL,
  UNIQUE KEY uq_vc_tipo (viagem_id, tipo),
  CONSTRAINT fk_vc_viagem FOREIGN KEY (viagem_id) REFERENCES viagens(id),
  CONSTRAINT fk_vc_autor FOREIGN KEY (preenchido_por) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabeçalho de vistoria, preservado quando finalizado.';

CREATE TABLE viagem_checklist_respostas (
  checklist_id BIGINT UNSIGNED NOT NULL,
  item_id SMALLINT UNSIGNED NOT NULL,
  resultado ENUM('ok','problema','nao_aplicavel') NOT NULL,
  observacao VARCHAR(1000) NULL,
  descricao_item VARCHAR(150) NOT NULL DEFAULT '',
  obrigatorio_snapshot TINYINT UNSIGNED NOT NULL DEFAULT 1,
  PRIMARY KEY (checklist_id, item_id),
  CONSTRAINT fk_vcr_checklist FOREIGN KEY (checklist_id) REFERENCES viagem_checklists(id),
  CONSTRAINT fk_vcr_item FOREIGN KEY (item_id) REFERENCES checklist_itens(id),
  CONSTRAINT ck_vcr_observacao CHECK (resultado <> 'problema' OR (observacao IS NOT NULL AND CHAR_LENGTH(TRIM(observacao)) > 0)),
  CONSTRAINT ck_vcr_obrigatorio CHECK (obrigatorio_snapshot IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Resultado por item; problema exige observação.';

CREATE TABLE viagem_ocorrencias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  viagem_id BIGINT UNSIGNED NOT NULL,
  registrado_por BIGINT UNSIGNED NOT NULL,
  tipo ENUM('geral','desvio_trajeto','avaria','acidente','atraso') NOT NULL DEFAULT 'geral',
  ocorrido_em DATETIME(6) NOT NULL,
  descricao TEXT NOT NULL,
  latitude DECIMAL(10,7) NULL,
  longitude DECIMAL(10,7) NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY ix_ocorrencia_viagem (viagem_id, ocorrido_em),
  CONSTRAINT fk_ocorrencia_viagem FOREIGN KEY (viagem_id) REFERENCES viagens(id),
  CONSTRAINT fk_ocorrencia_autor FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_ocorrencia_coordenadas CHECK ((latitude IS NULL AND longitude IS NULL) OR (latitude IS NOT NULL AND longitude IS NOT NULL AND latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ocorrências e justificativas de desvio, separadas de telemetria e multas.';

CREATE TABLE posicoes_rastreamento (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  instalacao_id BIGINT UNSIGNED NOT NULL,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  viagem_id BIGINT UNSIGNED NULL,
  identificador_evento VARCHAR(120) NOT NULL,
  capturado_em DATETIME(6) NOT NULL,
  recebido_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  velocidade_kmh DECIMAL(7,2) NULL,
  precisao_metros DECIMAL(9,2) NULL,
  ignicao TINYINT UNSIGNED NULL,
  UNIQUE KEY uq_posicao_evento (instalacao_id, identificador_evento),
  KEY ix_posicao_veiculo (veiculo_id, capturado_em, id),
  KEY ix_posicao_viagem (viagem_id, capturado_em, id),
  CONSTRAINT fk_posicao_instalacao FOREIGN KEY (instalacao_id, veiculo_id) REFERENCES veiculo_rastreadores(id, veiculo_id),
  CONSTRAINT fk_posicao_viagem FOREIGN KEY (viagem_id, veiculo_id) REFERENCES viagens(id, veiculo_id),
  CONSTRAINT ck_posicao_coordenadas CHECK (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180),
  CONSTRAINT ck_posicao_medidas CHECK ((velocidade_kmh IS NULL OR velocidade_kmh >= 0) AND (precisao_metros IS NULL OR precisao_metros >= 0) AND (ignicao IS NULL OR ignicao IN (0,1)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pontos realmente recebidos do dispositivo instalado; nunca simular caminho executado.';

CREATE TABLE posicoes_manuais (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  viagem_id BIGINT UNSIGNED NOT NULL,
  registrado_por BIGINT UNSIGNED NOT NULL,
  registrado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  ocorrido_em DATETIME(6) NOT NULL,
  latitude DECIMAL(10,7) NOT NULL,
  longitude DECIMAL(10,7) NOT NULL,
  descricao VARCHAR(1000) NOT NULL,
  CONSTRAINT fk_pm_viagem FOREIGN KEY (viagem_id) REFERENCES viagens(id),
  CONSTRAINT fk_pm_autor FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_pm_coordenadas CHECK (latitude BETWEEN -90 AND 90 AND longitude BETWEEN -180 AND 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Posição declarada por usuário; identificada como manual, não como GPS.';


-- 06 · Despesas, abastecimentos, manutenção e pneus

CREATE TABLE categorias_despesa (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(40) NOT NULL,
  nome VARCHAR(80) NOT NULL,
  ativa TINYINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_categoria_despesa_codigo (codigo),
  CONSTRAINT ck_categoria_despesa_ativa CHECK (ativa IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorias de custo separadas dos registros de multa.';

CREATE TABLE fornecedores (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(150) NOT NULL,
  cpf_cnpj VARCHAR(14) NULL,
  email VARCHAR(254) NULL,
  telefone VARCHAR(30) NULL,
  ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_fornecedor_documento (cpf_cnpj),
  CONSTRAINT ck_fornecedor_ativo CHECK (ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fornecedor opcional para notas fiscais e ordens de serviço.';

CREATE TABLE despesas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  protocolo VARCHAR(40) NOT NULL,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  unidade_id BIGINT UNSIGNED NOT NULL,
  categoria_id SMALLINT UNSIGNED NOT NULL,
  fornecedor_id BIGINT UNSIGNED NULL,
  data_despesa DATE NOT NULL,
  valor DECIMAL(13,2) NOT NULL,
  descricao TEXT NOT NULL,
  numero_documento VARCHAR(100) NULL,
  situacao ENUM('registrada','em_conferencia','aprovada','paga','cancelada') NOT NULL DEFAULT 'registrada',
  criado_por BIGINT UNSIGNED NOT NULL,
  versao BIGINT UNSIGNED NOT NULL DEFAULT 1,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  atualizado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_despesa_protocolo (protocolo),
  UNIQUE KEY uq_despesa_veiculo (id, veiculo_id),
  KEY ix_despesa_relatorio (unidade_id, data_despesa, categoria_id, situacao),
  KEY ix_despesa_veiculo_data (veiculo_id, data_despesa),
  CONSTRAINT fk_despesa_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
  CONSTRAINT fk_despesa_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id),
  CONSTRAINT fk_despesa_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_despesa(id),
  CONSTRAINT fk_despesa_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id),
  CONSTRAINT fk_despesa_autor FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_despesa_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lançamento financeiro do veículo, em BRL com DECIMAL.';

CREATE TABLE despesa_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  despesa_id BIGINT UNSIGNED NOT NULL,
  ator_vinculo_id BIGINT UNSIGNED NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  situacao_anterior VARCHAR(40) NULL,
  situacao_nova VARCHAR(40) NOT NULL,
  motivo TEXT NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_de_despesa FOREIGN KEY (despesa_id) REFERENCES despesas(id),
  CONSTRAINT fk_de_vinculo FOREIGN KEY (ator_vinculo_id) REFERENCES usuario_perfis(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico da conferência e decisões financeiras de despesas.';

CREATE TABLE pagamentos_despesa (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  despesa_id BIGINT UNSIGNED NOT NULL,
  valor DECIMAL(13,2) NOT NULL,
  pago_em DATETIME(6) NOT NULL,
  comprovante_arquivo_id BIGINT UNSIGNED NOT NULL,
  confirmado_por_vinculo_id BIGINT UNSIGNED NOT NULL,
  confirmado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_pagamento_despesa (despesa_id),
  CONSTRAINT fk_pd_despesa FOREIGN KEY (despesa_id) REFERENCES despesas(id),
  CONSTRAINT fk_pd_arquivo FOREIGN KEY (comprovante_arquivo_id) REFERENCES arquivos(id),
  CONSTRAINT fk_pd_vinculo FOREIGN KEY (confirmado_por_vinculo_id) REFERENCES usuario_perfis(id),
  CONSTRAINT ck_pd_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pagamento confirmado separado da nota e do estado de conferência.';

CREATE TABLE abastecimentos (
  despesa_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  combustivel ENUM('gasolina','etanol','diesel','gnv','eletricidade','outro') NOT NULL,
  quantidade DECIMAL(12,3) NOT NULL,
  unidade_medida ENUM('litro','m3','kwh') NOT NULL DEFAULT 'litro',
  preco_unitario DECIMAL(12,4) NOT NULL,
  quilometragem DECIMAL(12,1) NOT NULL,
  tanque_completo TINYINT UNSIGNED NOT NULL DEFAULT 0,
  CONSTRAINT fk_abastecimento_despesa FOREIGN KEY (despesa_id, veiculo_id) REFERENCES despesas(id, veiculo_id),
  CONSTRAINT ck_abastecimento_valores CHECK (quantidade > 0 AND preco_unitario > 0 AND quilometragem >= 0),
  CONSTRAINT ck_abastecimento_tanque CHECK (tanque_completo IN (0,1)),
  CONSTRAINT ck_abastecimento_medida CHECK ((combustivel IN ('gasolina','etanol','diesel') AND unidade_medida='litro') OR (combustivel='gnv' AND unidade_medida='m3') OR (combustivel='eletricidade' AND unidade_medida='kwh') OR combustivel='outro')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalhes físicos de uma despesa de abastecimento.';

CREATE TABLE manutencoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  protocolo VARCHAR(40) NOT NULL,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  despesa_id BIGINT UNSIGNED NULL,
  reserva_id BIGINT UNSIGNED NULL,
  fornecedor_id BIGINT UNSIGNED NULL,
  tipo ENUM('preventiva','corretiva','vistoria') NOT NULL,
  situacao ENUM('planejada','em_execucao','concluida','cancelada') NOT NULL DEFAULT 'planejada',
  descricao TEXT NOT NULL,
  inicio_previsto DATETIME(6) NOT NULL,
  fim_previsto DATETIME(6) NOT NULL,
  inicio_real DATETIME(6) NULL,
  fim_real DATETIME(6) NULL,
  quilometragem DECIMAL(12,1) NULL,
  proxima_revisao_km DECIMAL(12,1) NULL,
  proxima_revisao_data DATE NULL,
  criado_por BIGINT UNSIGNED NOT NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_manutencao_protocolo (protocolo),
  UNIQUE KEY uq_manutencao_despesa (despesa_id),
  UNIQUE KEY uq_manutencao_reserva (reserva_id),
  KEY ix_manutencao_veiculo (veiculo_id, situacao, inicio_previsto),
  CONSTRAINT fk_manutencao_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
  CONSTRAINT fk_manutencao_despesa FOREIGN KEY (despesa_id, veiculo_id) REFERENCES despesas(id, veiculo_id),
  CONSTRAINT fk_manutencao_reserva FOREIGN KEY (reserva_id, veiculo_id) REFERENCES reservas(id, veiculo_id),
  CONSTRAINT fk_manutencao_fornecedor FOREIGN KEY (fornecedor_id) REFERENCES fornecedores(id),
  CONSTRAINT fk_manutencao_autor FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_manutencao_periodo CHECK (fim_previsto > inicio_previsto AND (fim_real IS NULL OR (inicio_real IS NOT NULL AND fim_real >= inicio_real))),
  CONSTRAINT ck_manutencao_km CHECK ((quilometragem IS NULL OR quilometragem >= 0) AND (proxima_revisao_km IS NULL OR proxima_revisao_km >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordem de manutenção com bloqueio da agenda e despesa opcional.';

CREATE TABLE manutencao_itens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  manutencao_id BIGINT UNSIGNED NOT NULL,
  tipo ENUM('peca','servico') NOT NULL,
  descricao VARCHAR(255) NOT NULL,
  quantidade DECIMAL(12,3) NOT NULL DEFAULT 1,
  valor_unitario DECIMAL(13,2) NOT NULL,
  CONSTRAINT fk_mi_manutencao FOREIGN KEY (manutencao_id) REFERENCES manutencoes(id),
  CONSTRAINT ck_mi_valores CHECK (quantidade > 0 AND valor_unitario >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Peças e serviços da ordem de manutenção.';

CREATE TABLE pneus (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  codigo VARCHAR(60) NOT NULL,
  numero_serie VARCHAR(100) NULL,
  marca VARCHAR(80) NULL,
  modelo VARCHAR(80) NULL,
  medida VARCHAR(40) NOT NULL,
  adquirido_em DATE NULL,
  despesa_aquisicao_id BIGINT UNSIGNED NULL,
  situacao ENUM('estoque','instalado','descartado') NOT NULL DEFAULT 'estoque',
  descartado_em DATETIME(6) NULL,
  motivo_descarte VARCHAR(500) NULL,
  UNIQUE KEY uq_pneu_codigo (codigo),
  UNIQUE KEY uq_pneu_serie (numero_serie),
  CONSTRAINT fk_pneu_despesa FOREIGN KEY (despesa_aquisicao_id) REFERENCES despesas(id),
  CONSTRAINT ck_pneu_descarte CHECK ((situacao = 'descartado' AND descartado_em IS NOT NULL AND motivo_descarte IS NOT NULL) OR (situacao <> 'descartado' AND descartado_em IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ativos identificáveis, com aquisição e descarte.';

CREATE TABLE pneu_instalacoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  pneu_id BIGINT UNSIGNED NOT NULL,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  posicao VARCHAR(40) NOT NULL,
  instalado_em DATETIME(6) NOT NULL,
  removido_em DATETIME(6) NULL,
  quilometragem_instalacao DECIMAL(12,1) NOT NULL,
  quilometragem_remocao DECIMAL(12,1) NULL,
  motivo_remocao VARCHAR(500) NULL,
  registrado_por BIGINT UNSIGNED NOT NULL,
  manutencao_id BIGINT UNSIGNED NULL,
  pneu_aberto BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN removido_em IS NULL THEN pneu_id ELSE NULL END) STORED,
  veiculo_aberto BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN removido_em IS NULL THEN veiculo_id ELSE NULL END) STORED,
  posicao_aberta VARCHAR(40) GENERATED ALWAYS AS (CASE WHEN removido_em IS NULL THEN posicao ELSE NULL END) STORED,
  UNIQUE KEY uq_pneu_instalado (pneu_aberto),
  UNIQUE KEY uq_pneu_posicao (veiculo_aberto, posicao_aberta),
  CONSTRAINT fk_pi_pneu FOREIGN KEY (pneu_id) REFERENCES pneus(id),
  CONSTRAINT fk_pi_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
  CONSTRAINT fk_pi_autor FOREIGN KEY (registrado_por) REFERENCES usuarios(id),
  CONSTRAINT fk_pi_manutencao FOREIGN KEY (manutencao_id) REFERENCES manutencoes(id),
  CONSTRAINT ck_pi_periodo CHECK (removido_em IS NULL OR removido_em >= instalado_em),
  CONSTRAINT ck_pi_km CHECK (quilometragem_instalacao >= 0 AND (quilometragem_remocao IS NULL OR quilometragem_remocao >= quilometragem_instalacao)),
  CONSTRAINT ck_pi_remocao CHECK ((removido_em IS NULL AND quilometragem_remocao IS NULL) OR (removido_em IS NOT NULL AND quilometragem_remocao IS NOT NULL AND motivo_remocao IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de montagem e rodízio; posição e pneu só têm uma instalação aberta.';


-- 07 · Multas, responsabilidade, comprovantes e quitação

CREATE TABLE multas (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  protocolo VARCHAR(40) NOT NULL,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  unidade_id BIGINT UNSIGNED NOT NULL,
  numero_auto VARCHAR(100) NULL,
  orgao_autuador VARCHAR(150) NULL,
  ocorrido_em DATETIME(6) NOT NULL,
  precisao_ocorrencia ENUM('dia','instante') NOT NULL DEFAULT 'instante',
  fim_dia_ocorrencia_utc DATETIME(6) NULL,
  data_vencimento DATE NULL,
  valor DECIMAL(13,2) NOT NULL,
  descricao TEXT NOT NULL,
  situacao ENUM('sem_responsavel','aguardando_comprovante','em_conferencia','quitada','contestada','cancelada') NOT NULL DEFAULT 'sem_responsavel',
  responsabilidade_atual_id BIGINT UNSIGNED NULL,
  criado_por BIGINT UNSIGNED NOT NULL,
  versao BIGINT UNSIGNED NOT NULL DEFAULT 1,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_multa_protocolo (protocolo),
  UNIQUE KEY uq_multa_auto (orgao_autuador, numero_auto),
  UNIQUE KEY uq_multa_veiculo (id, veiculo_id),
  KEY ix_multa_unidade_status (unidade_id, situacao, ocorrido_em),
  CONSTRAINT fk_multa_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
  CONSTRAINT fk_multa_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id),
  CONSTRAINT fk_multa_autor FOREIGN KEY (criado_por) REFERENCES usuarios(id),
  CONSTRAINT ck_multa_valor CHECK (valor > 0),
  CONSTRAINT ck_multa_precisao CHECK ((precisao_ocorrencia = 'instante' AND fim_dia_ocorrencia_utc IS NULL) OR (precisao_ocorrencia = 'dia' AND fim_dia_ocorrencia_utc IS NOT NULL AND fim_dia_ocorrencia_utc > ocorrido_em)),
  CONSTRAINT ck_multa_responsavel CHECK (situacao <> 'sem_responsavel' OR responsabilidade_atual_id IS NULL)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Autuação do veículo. Não atribui responsabilidade automaticamente ao solicitante.';

CREATE TABLE multa_responsabilidades (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  multa_id BIGINT UNSIGNED NOT NULL,
  veiculo_id BIGINT UNSIGNED NOT NULL,
  viagem_id BIGINT UNSIGNED NOT NULL,
  motorista_id BIGINT UNSIGNED NOT NULL,
  responsavel_id BIGINT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  confirmado_por_vinculo_id BIGINT UNSIGNED NOT NULL,
  confirmado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  justificativa TEXT NOT NULL,
  UNIQUE KEY uq_mr_versao (multa_id, numero),
  UNIQUE KEY uq_mr_multa (multa_id, id),
  CONSTRAINT fk_mr_multa FOREIGN KEY (multa_id, veiculo_id) REFERENCES multas(id, veiculo_id),
  CONSTRAINT fk_mr_viagem FOREIGN KEY (viagem_id, veiculo_id, motorista_id) REFERENCES viagens(id, veiculo_id, motorista_id),
  CONSTRAINT fk_mr_responsavel FOREIGN KEY (responsavel_id) REFERENCES usuarios(id),
  CONSTRAINT fk_mr_confirmador FOREIGN KEY (confirmado_por_vinculo_id) REFERENCES usuario_perfis(id),
  CONSTRAINT ck_mr_numero CHECK (numero > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Versões da apuração administrativa, com veículo e condutor da viagem comprovados.';

ALTER TABLE multas ADD CONSTRAINT fk_multa_responsabilidade_atual FOREIGN KEY (id, responsabilidade_atual_id) REFERENCES multa_responsabilidades(multa_id, id);

CREATE TABLE multa_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  multa_id BIGINT UNSIGNED NOT NULL,
  ator_vinculo_id BIGINT UNSIGNED NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  situacao_anterior VARCHAR(40) NULL,
  situacao_nova VARCHAR(40) NOT NULL,
  motivo TEXT NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY ix_me_historico (multa_id, criado_em, id),
  CONSTRAINT fk_me_multa FOREIGN KEY (multa_id) REFERENCES multas(id),
  CONSTRAINT fk_me_vinculo FOREIGN KEY (ator_vinculo_id) REFERENCES usuario_perfis(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico imutável de apuração, contestação, cancelamento e comprovantes.';

CREATE TABLE multa_comprovantes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  multa_id BIGINT UNSIGNED NOT NULL,
  numero INT UNSIGNED NOT NULL,
  responsabilidade_id BIGINT UNSIGNED NOT NULL,
  arquivo_id BIGINT UNSIGNED NOT NULL,
  enviado_por_vinculo_id BIGINT UNSIGNED NOT NULL,
  valor_declarado DECIMAL(13,2) NOT NULL,
  pagamento_declarado_em DATETIME(6) NOT NULL,
  observacao TEXT NULL,
  enviado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_mc_numero (multa_id, numero),
  UNIQUE KEY uq_mc_multa (multa_id, id),
  CONSTRAINT fk_mc_responsabilidade FOREIGN KEY (multa_id, responsabilidade_id) REFERENCES multa_responsabilidades(multa_id, id),
  CONSTRAINT fk_mc_arquivo FOREIGN KEY (arquivo_id) REFERENCES arquivos(id),
  CONSTRAINT fk_mc_vinculo FOREIGN KEY (enviado_por_vinculo_id) REFERENCES usuario_perfis(id),
  CONSTRAINT ck_mc_valor CHECK (valor_declarado > 0 AND numero > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cada envio é uma versão imutável. Enviar arquivo não significa quitar.';

CREATE TABLE multa_conferencias (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  multa_id BIGINT UNSIGNED NOT NULL,
  comprovante_id BIGINT UNSIGNED NOT NULL,
  conferido_por_vinculo_id BIGINT UNSIGNED NOT NULL,
  resultado ENUM('aceito','correcao_solicitada') NOT NULL,
  motivo TEXT NULL,
  valor_confirmado DECIMAL(13,2) NULL,
  pagamento_confirmado_em DATETIME(6) NULL,
  conferido_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_mcf_comprovante (comprovante_id),
  UNIQUE KEY uq_mcf_multa (multa_id, id),
  CONSTRAINT fk_mcf_comprovante FOREIGN KEY (multa_id, comprovante_id) REFERENCES multa_comprovantes(multa_id, id),
  CONSTRAINT fk_mcf_vinculo FOREIGN KEY (conferido_por_vinculo_id) REFERENCES usuario_perfis(id),
  CONSTRAINT ck_mcf_resultado CHECK ((resultado = 'aceito' AND valor_confirmado IS NOT NULL AND valor_confirmado > 0 AND pagamento_confirmado_em IS NOT NULL) OR (resultado = 'correcao_solicitada' AND motivo IS NOT NULL AND CHAR_LENGTH(TRIM(motivo)) > 0 AND valor_confirmado IS NULL AND pagamento_confirmado_em IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Decisão financeira de uma versão do comprovante; uma conferência por envio.';

CREATE TABLE pagamentos_multa (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  multa_id BIGINT UNSIGNED NOT NULL,
  conferencia_id BIGINT UNSIGNED NOT NULL,
  valor DECIMAL(13,2) NOT NULL,
  pago_em DATETIME(6) NOT NULL,
  registrado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_pagamento_multa (multa_id),
  UNIQUE KEY uq_pagamento_conferencia (conferencia_id),
  CONSTRAINT fk_pagamento_multa_conferencia FOREIGN KEY (multa_id, conferencia_id) REFERENCES multa_conferencias(multa_id, id),
  CONSTRAINT ck_pagamento_multa_valor CHECK (valor > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quitação única, criada somente após aceite financeiro.';


-- 08 · Chamados, notificações e anexos

CREATE TABLE categorias_chamado (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(80) NOT NULL,
  ativa TINYINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_categoria_chamado_nome (nome),
  CONSTRAINT ck_categoria_chamado_ativa CHECK (ativa IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorias de atendimento interno.';

CREATE TABLE chamados (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  protocolo VARCHAR(40) NOT NULL,
  solicitante_id BIGINT UNSIGNED NOT NULL,
  unidade_id BIGINT UNSIGNED NOT NULL,
  perfil_contexto_id BIGINT UNSIGNED NOT NULL,
  categoria_id SMALLINT UNSIGNED NOT NULL,
  assunto VARCHAR(200) NOT NULL,
  descricao TEXT NOT NULL,
  pagina_contexto VARCHAR(255) NULL,
  situacao ENUM('aberto','em_atendimento','aguardando_solicitante','resolvido') NOT NULL DEFAULT 'aberto',
  atribuido_a BIGINT UNSIGNED NULL,
  resolvido_em DATETIME(6) NULL,
  versao BIGINT UNSIGNED NOT NULL DEFAULT 1,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  atualizado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_chamado_protocolo (protocolo),
  KEY ix_chamado_proprios (solicitante_id, situacao, criado_em),
  KEY ix_chamado_fila (unidade_id, situacao, atribuido_a, criado_em),
  CONSTRAINT fk_chamado_solicitante FOREIGN KEY (solicitante_id) REFERENCES usuarios(id),
  CONSTRAINT fk_chamado_unidade FOREIGN KEY (unidade_id) REFERENCES unidades(id),
  CONSTRAINT fk_chamado_perfil FOREIGN KEY (perfil_contexto_id) REFERENCES perfis(id),
  CONSTRAINT fk_chamado_categoria FOREIGN KEY (categoria_id) REFERENCES categorias_chamado(id),
  CONSTRAINT fk_chamado_atendente FOREIGN KEY (atribuido_a) REFERENCES usuarios(id),
  CONSTRAINT ck_chamado_resolvido CHECK ((situacao = 'resolvido' AND resolvido_em IS NOT NULL) OR (situacao <> 'resolvido' AND resolvido_em IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Solicitante vê os próprios chamados; filas dependem de permissão.';

CREATE TABLE chamado_mensagens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chamado_id BIGINT UNSIGNED NOT NULL,
  autor_vinculo_id BIGINT UNSIGNED NOT NULL,
  mensagem TEXT NOT NULL,
  interna TINYINT UNSIGNED NOT NULL DEFAULT 0,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  KEY ix_cm_conversa (chamado_id, criado_em, id),
  CONSTRAINT fk_cm_chamado FOREIGN KEY (chamado_id) REFERENCES chamados(id),
  CONSTRAINT fk_cm_autor FOREIGN KEY (autor_vinculo_id) REFERENCES usuario_perfis(id),
  CONSTRAINT ck_cm_interna CHECK (interna IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conversa do chamado; mensagens internas não são exibidas ao solicitante.';

CREATE TABLE chamado_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chamado_id BIGINT UNSIGNED NOT NULL,
  ator_vinculo_id BIGINT UNSIGNED NOT NULL,
  tipo VARCHAR(40) NOT NULL,
  situacao_anterior VARCHAR(40) NULL,
  situacao_nova VARCHAR(40) NOT NULL,
  motivo TEXT NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_ce_chamado FOREIGN KEY (chamado_id) REFERENCES chamados(id),
  CONSTRAINT fk_ce_vinculo FOREIGN KEY (ator_vinculo_id) REFERENCES usuario_perfis(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Abertura, atribuição, mudança de estado e resolução.';

CREATE TABLE notificacao_eventos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chave_idempotencia VARCHAR(160) NOT NULL,
  tipo VARCHAR(60) NOT NULL,
  titulo VARCHAR(160) NOT NULL,
  mensagem VARCHAR(1000) NOT NULL,
  caminho_destino VARCHAR(255) NULL,
  solicitacao_id BIGINT UNSIGNED NULL,
  viagem_id BIGINT UNSIGNED NULL,
  multa_id BIGINT UNSIGNED NULL,
  despesa_id BIGINT UNSIGNED NULL,
  chamado_id BIGINT UNSIGNED NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  UNIQUE KEY uq_notificacao_evento (chave_idempotencia),
  CONSTRAINT fk_ne_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id),
  CONSTRAINT fk_ne_viagem FOREIGN KEY (viagem_id) REFERENCES viagens(id),
  CONSTRAINT fk_ne_multa FOREIGN KEY (multa_id) REFERENCES multas(id),
  CONSTRAINT fk_ne_despesa FOREIGN KEY (despesa_id) REFERENCES despesas(id),
  CONSTRAINT fk_ne_chamado FOREIGN KEY (chamado_id) REFERENCES chamados(id),
  CONSTRAINT ck_ne_alvo CHECK ((solicitacao_id IS NOT NULL) + (viagem_id IS NOT NULL) + (multa_id IS NOT NULL) + (despesa_id IS NOT NULL) + (chamado_id IS NOT NULL) <= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evento único de negócio; destinatários mantêm seus próprios estados de leitura.';

CREATE TABLE notificacao_destinatarios (
  evento_id BIGINT UNSIGNED NOT NULL,
  usuario_id BIGINT UNSIGNED NOT NULL,
  lida_em DATETIME(6) NULL,
  oculta_em DATETIME(6) NULL,
  versao BIGINT UNSIGNED NOT NULL DEFAULT 1,
  entregue_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (evento_id, usuario_id),
  KEY ix_nd_usuario (usuario_id, oculta_em, lida_em, entregue_em),
  CONSTRAINT fk_nd_evento FOREIGN KEY (evento_id) REFERENCES notificacao_eventos(id),
  CONSTRAINT fk_nd_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Leitura, ocultação e versão por usuário; sem apagar o evento.';


-- 09 · Relatórios e exportações

CREATE TABLE relatorio_campos (
  id SMALLINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  modulo_codigo VARCHAR(40) NOT NULL,
  chave VARCHAR(80) NOT NULL,
  rotulo VARCHAR(150) NOT NULL,
  tipo ENUM('texto','numero','moeda','data','data_hora','situacao') NOT NULL,
  acao_adicional VARCHAR(40) NULL,
  ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  ativo TINYINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_rc_campo (modulo_codigo, chave),
  UNIQUE KEY uq_rc_modulo (id, modulo_codigo),
  CONSTRAINT fk_rc_modulo FOREIGN KEY (modulo_codigo) REFERENCES modulos(codigo),
  CONSTRAINT fk_rc_acao FOREIGN KEY (acao_adicional) REFERENCES acoes(codigo),
  CONSTRAINT fk_rc_acao_modulo FOREIGN KEY (modulo_codigo, acao_adicional) REFERENCES modulo_acoes(modulo_codigo, acao_codigo),
  CONSTRAINT ck_rc_ativo CHECK (ativo IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lista autorizada de campos; campos sensíveis têm ação adicional obrigatória.';

CREATE TABLE exportacoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  solicitado_por_vinculo_id BIGINT UNSIGNED NOT NULL,
  modulo_codigo VARCHAR(40) NOT NULL,
  formato ENUM('md','csv','xlsx','pdf') NOT NULL,
  situacao ENUM('previa','fila','processando','concluida','falhou','expirada','cancelada') NOT NULL DEFAULT 'previa',
  periodo_inicio DATE NULL,
  periodo_fim DATE NULL,
  periodo_inicio_utc DATETIME(6) NULL,
  periodo_fim_utc DATETIME(6) NULL,
  fuso_apresentacao VARCHAR(64) NOT NULL DEFAULT 'America/Porto_Velho',
  filtros JSON NOT NULL,
  alcance_aplicado ENUM('proprios','unidade','orgao') NOT NULL,
  unidade_aplicada_id BIGINT UNSIGNED NULL,
  total_registros BIGINT UNSIGNED NOT NULL DEFAULT 0,
  arquivo_id BIGINT UNSIGNED NULL,
  erro_codigo VARCHAR(60) NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  iniciado_em DATETIME(6) NULL,
  concluido_em DATETIME(6) NULL,
  expira_em DATETIME(6) NULL,
  UNIQUE KEY uq_exportacao_modulo (id, modulo_codigo),
  KEY ix_exportacao_fila (situacao, criado_em),
  CONSTRAINT fk_exportacao_vinculo FOREIGN KEY (solicitado_por_vinculo_id) REFERENCES usuario_perfis(id),
  CONSTRAINT fk_exportacao_modulo FOREIGN KEY (modulo_codigo) REFERENCES modulos(codigo),
  CONSTRAINT fk_exportacao_unidade FOREIGN KEY (unidade_aplicada_id) REFERENCES unidades(id),
  CONSTRAINT fk_exportacao_arquivo FOREIGN KEY (arquivo_id) REFERENCES arquivos(id),
  CONSTRAINT ck_exportacao_periodo CHECK (periodo_inicio IS NULL OR periodo_fim IS NULL OR periodo_fim >= periodo_inicio),
  CONSTRAINT ck_exportacao_janela_utc CHECK (periodo_inicio_utc IS NULL OR periodo_fim_utc IS NULL OR periodo_fim_utc > periodo_inicio_utc),
  CONSTRAINT ck_exportacao_final CHECK (situacao <> 'concluida' OR (arquivo_id IS NOT NULL AND concluido_em IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pedido e snapshot da seleção; gerar arquivo exige revalidar permissão no servidor.';

CREATE TABLE exportacao_campos (
  exportacao_id BIGINT UNSIGNED NOT NULL,
  campo_id SMALLINT UNSIGNED NOT NULL,
  modulo_codigo VARCHAR(40) NOT NULL,
  ordem SMALLINT UNSIGNED NOT NULL,
  PRIMARY KEY (exportacao_id, campo_id),
  UNIQUE KEY uq_ec_ordem (exportacao_id, ordem),
  CONSTRAINT fk_ec_exportacao FOREIGN KEY (exportacao_id, modulo_codigo) REFERENCES exportacoes(id, modulo_codigo),
  CONSTRAINT fk_ec_campo FOREIGN KEY (campo_id, modulo_codigo) REFERENCES relatorio_campos(id, modulo_codigo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordem das colunas selecionadas e pertencimento ao mesmo módulo do relatório.';

CREATE TABLE exportacao_registros (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  exportacao_id BIGINT UNSIGNED NOT NULL,
  ordem BIGINT UNSIGNED NOT NULL,
  solicitacao_id BIGINT UNSIGNED NULL,
  viagem_id BIGINT UNSIGNED NULL,
  veiculo_id BIGINT UNSIGNED NULL,
  despesa_id BIGINT UNSIGNED NULL,
  multa_id BIGINT UNSIGNED NULL,
  chamado_id BIGINT UNSIGNED NULL,
  usuario_id BIGINT UNSIGNED NULL,
  perfil_id BIGINT UNSIGNED NULL,
  rota_id BIGINT UNSIGNED NULL,
  auditoria_id BIGINT UNSIGNED NULL,
  posicao_rastreamento_id BIGINT UNSIGNED NULL,
  posicao_manual_id BIGINT UNSIGNED NULL,
  snapshot JSON NOT NULL,
  UNIQUE KEY uq_er_ordem (exportacao_id, ordem),
  UNIQUE KEY uq_er_solicitacao (exportacao_id, solicitacao_id),
  UNIQUE KEY uq_er_viagem (exportacao_id, viagem_id),
  UNIQUE KEY uq_er_veiculo (exportacao_id, veiculo_id),
  UNIQUE KEY uq_er_despesa (exportacao_id, despesa_id),
  UNIQUE KEY uq_er_multa (exportacao_id, multa_id),
  UNIQUE KEY uq_er_chamado (exportacao_id, chamado_id),
  UNIQUE KEY uq_er_usuario (exportacao_id, usuario_id),
  UNIQUE KEY uq_er_perfil (exportacao_id, perfil_id),
  UNIQUE KEY uq_er_rota (exportacao_id, rota_id),
  UNIQUE KEY uq_er_auditoria (exportacao_id, auditoria_id),
  UNIQUE KEY uq_er_posicao (exportacao_id, posicao_rastreamento_id),
  UNIQUE KEY uq_er_posicao_manual (exportacao_id, posicao_manual_id),
  CONSTRAINT fk_er_exportacao FOREIGN KEY (exportacao_id) REFERENCES exportacoes(id),
  CONSTRAINT fk_er_solicitacao FOREIGN KEY (solicitacao_id) REFERENCES solicitacoes(id),
  CONSTRAINT fk_er_viagem FOREIGN KEY (viagem_id) REFERENCES viagens(id),
  CONSTRAINT fk_er_veiculo FOREIGN KEY (veiculo_id) REFERENCES veiculos(id),
  CONSTRAINT fk_er_despesa FOREIGN KEY (despesa_id) REFERENCES despesas(id),
  CONSTRAINT fk_er_multa FOREIGN KEY (multa_id) REFERENCES multas(id),
  CONSTRAINT fk_er_chamado FOREIGN KEY (chamado_id) REFERENCES chamados(id),
  CONSTRAINT fk_er_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  CONSTRAINT fk_er_perfil FOREIGN KEY (perfil_id) REFERENCES perfis(id),
  CONSTRAINT fk_er_rota FOREIGN KEY (rota_id) REFERENCES rotas_sistema(id),
  CONSTRAINT fk_er_auditoria FOREIGN KEY (auditoria_id) REFERENCES auditoria(id),
  CONSTRAINT fk_er_posicao FOREIGN KEY (posicao_rastreamento_id) REFERENCES posicoes_rastreamento(id),
  CONSTRAINT fk_er_posicao_manual FOREIGN KEY (posicao_manual_id) REFERENCES posicoes_manuais(id),
  CONSTRAINT ck_er_alvo CHECK ((solicitacao_id IS NOT NULL) + (viagem_id IS NOT NULL) + (veiculo_id IS NOT NULL) + (despesa_id IS NOT NULL) + (multa_id IS NOT NULL) + (chamado_id IS NOT NULL) + (usuario_id IS NOT NULL) + (perfil_id IS NOT NULL) + (rota_id IS NOT NULL) + (auditoria_id IS NOT NULL) + (posicao_rastreamento_id IS NOT NULL) + (posicao_manual_id IS NOT NULL) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Seleção reproduzível de registros com FKs reais e snapshot apenas dos campos permitidos.';

CREATE TABLE anexos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  arquivo_id BIGINT UNSIGNED NOT NULL,
  revisao_id BIGINT UNSIGNED NULL,
  ocorrencia_id BIGINT UNSIGNED NULL,
  checklist_id BIGINT UNSIGNED NULL,
  despesa_id BIGINT UNSIGNED NULL,
  manutencao_id BIGINT UNSIGNED NULL,
  multa_id BIGINT UNSIGNED NULL,
  chamado_id BIGINT UNSIGNED NULL,
  mensagem_id BIGINT UNSIGNED NULL,
  descricao VARCHAR(255) NULL,
  criado_em DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  CONSTRAINT fk_anexo_arquivo FOREIGN KEY (arquivo_id) REFERENCES arquivos(id),
  CONSTRAINT fk_anexo_revisao FOREIGN KEY (revisao_id) REFERENCES solicitacao_revisoes(id),
  CONSTRAINT fk_anexo_ocorrencia FOREIGN KEY (ocorrencia_id) REFERENCES viagem_ocorrencias(id),
  CONSTRAINT fk_anexo_checklist FOREIGN KEY (checklist_id) REFERENCES viagem_checklists(id),
  CONSTRAINT fk_anexo_despesa FOREIGN KEY (despesa_id) REFERENCES despesas(id),
  CONSTRAINT fk_anexo_manutencao FOREIGN KEY (manutencao_id) REFERENCES manutencoes(id),
  CONSTRAINT fk_anexo_multa FOREIGN KEY (multa_id) REFERENCES multas(id),
  CONSTRAINT fk_anexo_chamado FOREIGN KEY (chamado_id) REFERENCES chamados(id),
  CONSTRAINT fk_anexo_mensagem FOREIGN KEY (mensagem_id) REFERENCES chamado_mensagens(id),
  CONSTRAINT ck_anexo_alvo CHECK ((revisao_id IS NOT NULL) + (ocorrencia_id IS NOT NULL) + (checklist_id IS NOT NULL) + (despesa_id IS NOT NULL) + (manutencao_id IS NOT NULL) + (multa_id IS NOT NULL) + (chamado_id IS NOT NULL) + (mensagem_id IS NOT NULL) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vínculos tipados de arquivo. Exatamente um destino por anexo, todos com FK.';


-- Catálogos iniciais: sem usuários, veículos ou pagamentos fictícios.
INSERT INTO versoes_modelo (versao,descricao) VALUES
('1.0.0','Modelo completo dos fluxos do protótipo Frota · PF / Frota Pública RO'),
('1.0.1','Justificativa e delegação validada nos procedimentos administrativos.'),
('1.0.2','Fila durável de recuperação e expiração absoluta de sessão.');

INSERT INTO unidades (id,codigo,nome,cidade,uf) VALUES
(1,'SEDE','Sede administrativa','Porto Velho','RO');

INSERT INTO configuracao_sistema (id,nome_sistema,nome_orgao,sigla_orgao) VALUES
(1,'Frota Pública RO','PF','PF');

INSERT INTO modulos (codigo,nome,ordem) VALUES
('painel','Painel',10),
('solicitacoes','Solicitações',20),
('viagens','Viagens',30),
('frota','Frota',40),
('rastreamento','Monitoramento',50),
('despesas','Despesas',60),
('multas','Multas',70),
('usuarios','Usuários',80),
('perfis','Perfis e permissões',90),
('rotas','Rotas técnicas',100),
('auditoria','Auditoria',110),
('configuracoes','Configurações',120),
('relatorios','Relatórios',130),
('chamados','Chamados',140),
('conta','Minha conta',150),
('notificacoes','Notificações',160);

INSERT INTO acoes (codigo,nome) VALUES
('consultar','Consultar'),
('criar','Criar'),
('editar','Editar'),
('enviar','Enviar'),
('aprovar','Aprovar'),
('negar','Negar'),
('solicitar_ajustes','Solicitar ajustes'),
('cancelar','Cancelar'),
('registrar_saida','Registrar saída'),
('registrar_retorno','Registrar retorno'),
('registrar_ocorrencia','Registrar ocorrência'),
('atribuir_responsavel','Atribuir responsável'),
('enviar_comprovante','Enviar comprovante'),
('validar_pagamento','Validar pagamento'),
('contestar','Contestar'),
('exportar','Exportar'),
('gerenciar','Gerenciar'),
('responder','Responder'),
('atender','Atender'),
('resolver','Resolver'),
('delegar','Delegar permissões'),
('ver_localizacao','Ver localização'),
('ver_valores','Ver valores financeiros'),
('selecionar','Selecionar veículo como referência');

INSERT INTO perfis (id,codigo,nome,descricao) VALUES
(1,'servidor','Servidor','Solicitações, viagens e pendências próprias.'),
(2,'gestor','Gestor','Frota e análise de solicitações da unidade.'),
(3,'financeiro','Financeiro','Despesas, multas e conferência de pagamentos do órgão.'),
(4,'administrador','Administrador','Usuários, perfis, rotas, configurações e auditoria. Sem acesso financeiro ou localização implícitos.');

INSERT INTO modulo_acoes (modulo_codigo,acao_codigo) VALUES
('painel','consultar'),
('conta','consultar'),
('conta','editar'),
('notificacoes','consultar'),
('notificacoes','editar'),
('relatorios','consultar'),
('solicitacoes','consultar'),
('solicitacoes','criar'),
('solicitacoes','editar'),
('solicitacoes','enviar'),
('solicitacoes','aprovar'),
('solicitacoes','negar'),
('solicitacoes','solicitar_ajustes'),
('solicitacoes','cancelar'),
('solicitacoes','exportar'),
('viagens','consultar'),
('viagens','registrar_saida'),
('viagens','registrar_retorno'),
('viagens','registrar_ocorrencia'),
('viagens','cancelar'),
('viagens','exportar'),
('frota','consultar'),
('frota','selecionar'),
('frota','criar'),
('frota','editar'),
('frota','gerenciar'),
('frota','exportar'),
('rastreamento','consultar'),
('rastreamento','ver_localizacao'),
('rastreamento','exportar'),
('despesas','consultar'),
('despesas','criar'),
('despesas','editar'),
('despesas','aprovar'),
('despesas','cancelar'),
('despesas','validar_pagamento'),
('despesas','exportar'),
('despesas','ver_valores'),
('multas','consultar'),
('multas','criar'),
('multas','editar'),
('multas','atribuir_responsavel'),
('multas','enviar_comprovante'),
('multas','validar_pagamento'),
('multas','contestar'),
('multas','cancelar'),
('multas','exportar'),
('multas','ver_valores'),
('chamados','consultar'),
('chamados','criar'),
('chamados','responder'),
('chamados','atender'),
('chamados','resolver'),
('chamados','exportar'),
('usuarios','consultar'),
('usuarios','criar'),
('usuarios','editar'),
('usuarios','gerenciar'),
('usuarios','delegar'),
('usuarios','exportar'),
('perfis','consultar'),
('perfis','criar'),
('perfis','editar'),
('perfis','gerenciar'),
('perfis','delegar'),
('perfis','exportar'),
('rotas','consultar'),
('rotas','criar'),
('rotas','editar'),
('rotas','gerenciar'),
('rotas','exportar'),
('auditoria','consultar'),
('auditoria','exportar'),
('configuracoes','consultar'),
('configuracoes','editar'),
('configuracoes','gerenciar'),
('configuracoes','exportar');

INSERT INTO permissoes (id,modulo_codigo,acao_codigo,alcance) VALUES
(1,'painel','consultar','proprios'),
(2,'painel','consultar','unidade'),
(3,'painel','consultar','orgao'),
(4,'solicitacoes','consultar','proprios'),
(5,'solicitacoes','consultar','unidade'),
(6,'solicitacoes','consultar','orgao'),
(7,'solicitacoes','criar','proprios'),
(8,'solicitacoes','criar','unidade'),
(9,'solicitacoes','criar','orgao'),
(10,'solicitacoes','editar','proprios'),
(11,'solicitacoes','editar','unidade'),
(12,'solicitacoes','editar','orgao'),
(13,'solicitacoes','enviar','proprios'),
(14,'solicitacoes','enviar','unidade'),
(15,'solicitacoes','enviar','orgao'),
(16,'solicitacoes','aprovar','proprios'),
(17,'solicitacoes','aprovar','unidade'),
(18,'solicitacoes','aprovar','orgao'),
(19,'solicitacoes','negar','proprios'),
(20,'solicitacoes','negar','unidade'),
(21,'solicitacoes','negar','orgao'),
(22,'solicitacoes','solicitar_ajustes','proprios'),
(23,'solicitacoes','solicitar_ajustes','unidade'),
(24,'solicitacoes','solicitar_ajustes','orgao'),
(25,'solicitacoes','cancelar','proprios'),
(26,'solicitacoes','cancelar','unidade'),
(27,'solicitacoes','cancelar','orgao'),
(28,'solicitacoes','exportar','proprios'),
(29,'solicitacoes','exportar','unidade'),
(30,'solicitacoes','exportar','orgao'),
(31,'viagens','consultar','proprios'),
(32,'viagens','consultar','unidade'),
(33,'viagens','consultar','orgao'),
(34,'viagens','registrar_saida','proprios'),
(35,'viagens','registrar_saida','unidade'),
(36,'viagens','registrar_saida','orgao'),
(37,'viagens','registrar_retorno','proprios'),
(38,'viagens','registrar_retorno','unidade'),
(39,'viagens','registrar_retorno','orgao'),
(40,'viagens','registrar_ocorrencia','proprios'),
(41,'viagens','registrar_ocorrencia','unidade'),
(42,'viagens','registrar_ocorrencia','orgao'),
(43,'viagens','cancelar','proprios'),
(44,'viagens','cancelar','unidade'),
(45,'viagens','cancelar','orgao'),
(46,'viagens','exportar','proprios'),
(47,'viagens','exportar','unidade'),
(48,'viagens','exportar','orgao'),
(49,'frota','consultar','proprios'),
(50,'frota','consultar','unidade'),
(51,'frota','consultar','orgao'),
(52,'frota','selecionar','proprios'),
(53,'frota','selecionar','unidade'),
(54,'frota','selecionar','orgao'),
(55,'frota','criar','proprios'),
(56,'frota','criar','unidade'),
(57,'frota','criar','orgao'),
(58,'frota','editar','proprios'),
(59,'frota','editar','unidade'),
(60,'frota','editar','orgao'),
(61,'frota','gerenciar','proprios'),
(62,'frota','gerenciar','unidade'),
(63,'frota','gerenciar','orgao'),
(64,'frota','exportar','proprios'),
(65,'frota','exportar','unidade'),
(66,'frota','exportar','orgao'),
(67,'rastreamento','consultar','proprios'),
(68,'rastreamento','consultar','unidade'),
(69,'rastreamento','consultar','orgao'),
(70,'rastreamento','ver_localizacao','proprios'),
(71,'rastreamento','ver_localizacao','unidade'),
(72,'rastreamento','ver_localizacao','orgao'),
(73,'rastreamento','exportar','proprios'),
(74,'rastreamento','exportar','unidade'),
(75,'rastreamento','exportar','orgao'),
(76,'despesas','consultar','proprios'),
(77,'despesas','consultar','unidade'),
(78,'despesas','consultar','orgao'),
(79,'despesas','criar','proprios'),
(80,'despesas','criar','unidade'),
(81,'despesas','criar','orgao'),
(82,'despesas','editar','proprios'),
(83,'despesas','editar','unidade'),
(84,'despesas','editar','orgao'),
(85,'despesas','aprovar','proprios'),
(86,'despesas','aprovar','unidade'),
(87,'despesas','aprovar','orgao'),
(88,'despesas','cancelar','proprios'),
(89,'despesas','cancelar','unidade'),
(90,'despesas','cancelar','orgao'),
(91,'despesas','validar_pagamento','proprios'),
(92,'despesas','validar_pagamento','unidade'),
(93,'despesas','validar_pagamento','orgao'),
(94,'despesas','exportar','proprios'),
(95,'despesas','exportar','unidade'),
(96,'despesas','exportar','orgao'),
(97,'despesas','ver_valores','proprios'),
(98,'despesas','ver_valores','unidade'),
(99,'despesas','ver_valores','orgao'),
(100,'multas','consultar','proprios'),
(101,'multas','consultar','unidade'),
(102,'multas','consultar','orgao'),
(103,'multas','criar','proprios'),
(104,'multas','criar','unidade'),
(105,'multas','criar','orgao'),
(106,'multas','editar','proprios'),
(107,'multas','editar','unidade'),
(108,'multas','editar','orgao'),
(109,'multas','atribuir_responsavel','proprios'),
(110,'multas','atribuir_responsavel','unidade'),
(111,'multas','atribuir_responsavel','orgao'),
(112,'multas','enviar_comprovante','proprios'),
(113,'multas','enviar_comprovante','unidade'),
(114,'multas','enviar_comprovante','orgao'),
(115,'multas','validar_pagamento','proprios'),
(116,'multas','validar_pagamento','unidade'),
(117,'multas','validar_pagamento','orgao'),
(118,'multas','contestar','proprios'),
(119,'multas','contestar','unidade'),
(120,'multas','contestar','orgao'),
(121,'multas','cancelar','proprios'),
(122,'multas','cancelar','unidade'),
(123,'multas','cancelar','orgao'),
(124,'multas','exportar','proprios'),
(125,'multas','exportar','unidade'),
(126,'multas','exportar','orgao'),
(127,'multas','ver_valores','proprios'),
(128,'multas','ver_valores','unidade'),
(129,'multas','ver_valores','orgao'),
(130,'usuarios','consultar','proprios'),
(131,'usuarios','consultar','unidade'),
(132,'usuarios','consultar','orgao'),
(133,'usuarios','criar','proprios'),
(134,'usuarios','criar','unidade'),
(135,'usuarios','criar','orgao'),
(136,'usuarios','editar','proprios'),
(137,'usuarios','editar','unidade'),
(138,'usuarios','editar','orgao'),
(139,'usuarios','gerenciar','proprios'),
(140,'usuarios','gerenciar','unidade'),
(141,'usuarios','gerenciar','orgao'),
(142,'usuarios','delegar','proprios'),
(143,'usuarios','delegar','unidade'),
(144,'usuarios','delegar','orgao'),
(145,'usuarios','exportar','proprios'),
(146,'usuarios','exportar','unidade'),
(147,'usuarios','exportar','orgao'),
(148,'perfis','consultar','proprios'),
(149,'perfis','consultar','unidade'),
(150,'perfis','consultar','orgao'),
(151,'perfis','criar','proprios'),
(152,'perfis','criar','unidade'),
(153,'perfis','criar','orgao'),
(154,'perfis','editar','proprios'),
(155,'perfis','editar','unidade'),
(156,'perfis','editar','orgao'),
(157,'perfis','gerenciar','proprios'),
(158,'perfis','gerenciar','unidade'),
(159,'perfis','gerenciar','orgao'),
(160,'perfis','delegar','proprios'),
(161,'perfis','delegar','unidade'),
(162,'perfis','delegar','orgao'),
(163,'perfis','exportar','proprios'),
(164,'perfis','exportar','unidade'),
(165,'perfis','exportar','orgao'),
(166,'rotas','consultar','proprios'),
(167,'rotas','consultar','unidade'),
(168,'rotas','consultar','orgao'),
(169,'rotas','criar','proprios'),
(170,'rotas','criar','unidade'),
(171,'rotas','criar','orgao'),
(172,'rotas','editar','proprios'),
(173,'rotas','editar','unidade'),
(174,'rotas','editar','orgao'),
(175,'rotas','gerenciar','proprios'),
(176,'rotas','gerenciar','unidade'),
(177,'rotas','gerenciar','orgao'),
(178,'rotas','exportar','proprios'),
(179,'rotas','exportar','unidade'),
(180,'rotas','exportar','orgao'),
(181,'auditoria','consultar','proprios'),
(182,'auditoria','consultar','unidade'),
(183,'auditoria','consultar','orgao'),
(184,'auditoria','exportar','proprios'),
(185,'auditoria','exportar','unidade'),
(186,'auditoria','exportar','orgao'),
(187,'configuracoes','consultar','proprios'),
(188,'configuracoes','consultar','unidade'),
(189,'configuracoes','consultar','orgao'),
(190,'configuracoes','editar','proprios'),
(191,'configuracoes','editar','unidade'),
(192,'configuracoes','editar','orgao'),
(193,'configuracoes','gerenciar','proprios'),
(194,'configuracoes','gerenciar','unidade'),
(195,'configuracoes','gerenciar','orgao'),
(196,'configuracoes','exportar','proprios'),
(197,'configuracoes','exportar','unidade'),
(198,'configuracoes','exportar','orgao'),
(199,'relatorios','consultar','proprios'),
(200,'relatorios','consultar','unidade'),
(201,'relatorios','consultar','orgao'),
(202,'chamados','consultar','proprios'),
(203,'chamados','consultar','unidade'),
(204,'chamados','consultar','orgao'),
(205,'chamados','criar','proprios'),
(206,'chamados','criar','unidade'),
(207,'chamados','criar','orgao'),
(208,'chamados','responder','proprios'),
(209,'chamados','responder','unidade'),
(210,'chamados','responder','orgao'),
(211,'chamados','atender','proprios'),
(212,'chamados','atender','unidade'),
(213,'chamados','atender','orgao'),
(214,'chamados','resolver','proprios'),
(215,'chamados','resolver','unidade'),
(216,'chamados','resolver','orgao'),
(217,'chamados','exportar','proprios'),
(218,'chamados','exportar','unidade'),
(219,'chamados','exportar','orgao'),
(220,'conta','consultar','proprios'),
(221,'conta','consultar','unidade'),
(222,'conta','consultar','orgao'),
(223,'conta','editar','proprios'),
(224,'conta','editar','unidade'),
(225,'conta','editar','orgao'),
(226,'notificacoes','consultar','proprios'),
(227,'notificacoes','consultar','unidade'),
(228,'notificacoes','consultar','orgao'),
(229,'notificacoes','editar','proprios'),
(230,'notificacoes','editar','unidade'),
(231,'notificacoes','editar','orgao');

INSERT INTO perfil_permissoes (perfil_id,permissao_id,delegavel) VALUES
(1,202,0),
(1,205,0),
(1,208,0),
(1,220,0),
(1,223,0),
(1,53,0),
(1,100,0),
(1,118,0),
(1,112,0),
(1,124,0),
(1,127,0),
(1,226,0),
(1,229,0),
(1,1,0),
(1,199,0),
(1,25,0),
(1,4,0),
(1,7,0),
(1,10,0),
(1,13,0),
(1,28,0),
(1,31,0),
(1,46,0),
(1,40,0),
(1,37,0),
(1,34,0),
(2,202,0),
(2,205,0),
(2,208,0),
(2,220,0),
(2,223,0),
(2,51,0),
(2,57,0),
(2,60,0),
(2,66,0),
(2,63,0),
(2,54,0),
(2,226,0),
(2,229,0),
(2,1,0),
(2,69,0),
(2,75,0),
(2,72,0),
(2,199,0),
(2,18,0),
(2,27,0),
(2,6,0),
(2,30,0),
(2,21,0),
(2,24,0),
(2,33,0),
(2,48,0),
(2,42,0),
(2,39,0),
(2,36,0),
(3,202,0),
(3,205,0),
(3,208,0),
(3,220,0),
(3,223,0),
(3,87,0),
(3,90,0),
(3,78,0),
(3,81,0),
(3,84,0),
(3,96,0),
(3,93,0),
(3,99,0),
(3,54,0),
(3,111,0),
(3,123,0),
(3,102,0),
(3,120,0),
(3,105,0),
(3,108,0),
(3,126,0),
(3,117,0),
(3,129,0),
(3,226,0),
(3,229,0),
(3,1,0),
(3,199,0),
(4,183,1),
(4,186,1),
(4,213,1),
(4,204,1),
(4,202,1),
(4,205,1),
(4,219,1),
(4,216,1),
(4,210,1),
(4,208,1),
(4,189,1),
(4,192,1),
(4,198,1),
(4,195,1),
(4,220,1),
(4,223,1),
(4,226,1),
(4,229,1),
(4,1,1),
(4,150,1),
(4,153,1),
(4,162,1),
(4,156,1),
(4,165,1),
(4,159,1),
(4,199,1),
(4,168,1),
(4,171,1),
(4,174,1),
(4,180,1),
(4,177,1),
(4,132,1),
(4,135,1),
(4,144,1),
(4,138,1),
(4,147,1),
(4,141,1);

INSERT INTO categorias_veiculo (id,nome,categoria_cnh_requerida) VALUES
(1,'Passeio','B'),
(2,'Picape','B'),
(3,'Utilitário','B'),
(4,'Van','D'),
(5,'Motocicleta','A'),
(6,'Caminhão','C'),
(7,'Ônibus','D');

INSERT INTO categorias_despesa (id,codigo,nome) VALUES
(1,'abastecimento','Abastecimento'),
(2,'manutencao','Manutenção'),
(3,'pneus','Pneus'),
(4,'documentacao','Documentação'),
(5,'outros','Outros');

INSERT INTO categorias_chamado (id,nome) VALUES
(1,'Solicitações'),
(2,'Viagens'),
(3,'Frota'),
(4,'Financeiro'),
(5,'Acesso ao sistema'),
(6,'Outros');

INSERT INTO checklist_itens (id,codigo,descricao,ordem) VALUES
(1,'documentos','Documentos',10),
(2,'pneus','Pneus',20),
(3,'combustivel','Combustível',30);

INSERT INTO rotas_sistema (id,chave,nome,caminho,modulo_codigo,implementada,protegida,ordem) VALUES
(1,'painel','Painel','/painel','painel',1,0,10),
(2,'solicitacoes','Solicitações','/solicitacoes','solicitacoes',1,0,20),
(3,'viagens','Viagens','/viagens','viagens',1,0,30),
(4,'frota','Frota','/frota','frota',1,0,40),
(5,'rastreamento','Monitoramento','/monitoramento','rastreamento',1,0,50),
(6,'despesas','Despesas','/financeiro/despesas','despesas',1,0,60),
(7,'multas','Multas','/financeiro/multas','multas',1,0,70),
(8,'usuarios','Usuários','/administracao/usuarios','usuarios',1,1,80),
(9,'perfis','Perfis e permissões','/administracao/perfis','perfis',1,1,90),
(10,'rotas','Rotas técnicas','/administracao/rotas','rotas',1,1,100),
(11,'auditoria','Auditoria','/administracao/auditoria','auditoria',1,0,110),
(12,'configuracoes','Configurações','/administracao/configuracoes','configuracoes',1,1,120),
(13,'relatorios','Relatórios','/relatorios','relatorios',1,0,130),
(14,'chamados','Chamados','/chamados','chamados',1,0,140),
(15,'conta','Minha conta','/conta','conta',1,0,150),
(16,'notificacoes','Notificações','/notificacoes','notificacoes',1,0,160);

INSERT INTO rota_permissoes (rota_id,modulo_codigo,acao_codigo) VALUES
(1,'painel','consultar'),
(2,'solicitacoes','consultar'),
(3,'viagens','consultar'),
(4,'frota','consultar'),
(5,'rastreamento','consultar'),
(6,'despesas','consultar'),
(7,'multas','consultar'),
(8,'usuarios','consultar'),
(9,'perfis','consultar'),
(10,'rotas','consultar'),
(11,'auditoria','consultar'),
(12,'configuracoes','consultar'),
(13,'relatorios','consultar'),
(14,'chamados','consultar'),
(15,'conta','consultar'),
(16,'notificacoes','consultar'),
(5,'rastreamento','ver_localizacao');

INSERT INTO relatorio_campos (id,modulo_codigo,chave,rotulo,tipo,acao_adicional,ordem) VALUES
(1,'solicitacoes','protocolo','Protocolo','texto',NULL,1),
(2,'solicitacoes','situacao','Situação','situacao',NULL,2),
(3,'solicitacoes','finalidade','Finalidade','texto',NULL,3),
(4,'solicitacoes','origem','Origem','texto',NULL,4),
(5,'solicitacoes','destino','Destino','texto',NULL,5),
(6,'solicitacoes','saida_prevista','Saída prevista','data_hora',NULL,6),
(7,'solicitacoes','retorno_previsto','Retorno previsto','data_hora',NULL,7),
(8,'solicitacoes','passageiros','Passageiros','numero',NULL,8),
(9,'solicitacoes','unidade','Unidade','texto',NULL,9),
(10,'viagens','protocolo','Protocolo','texto',NULL,1),
(11,'viagens','situacao','Situação','situacao',NULL,2),
(12,'viagens','veiculo','Veículo','texto',NULL,3),
(13,'viagens','motorista','Motorista','texto',NULL,4),
(14,'viagens','saida_real','Saída real','data_hora',NULL,5),
(15,'viagens','retorno_real','Retorno real','data_hora',NULL,6),
(16,'viagens','quilometragem_saida','Km de saída','numero',NULL,7),
(17,'viagens','quilometragem_retorno','Km de retorno','numero',NULL,8),
(18,'viagens','distancia','Distância percorrida','numero',NULL,9),
(19,'frota','placa','Placa','texto',NULL,1),
(20,'frota','nome','Veículo','texto',NULL,2),
(21,'frota','categoria','Categoria','texto',NULL,3),
(22,'frota','capacidade','Capacidade','numero',NULL,4),
(23,'frota','unidade','Unidade','texto',NULL,5),
(24,'frota','situacao','Situação','situacao',NULL,6),
(25,'frota','quilometragem','Quilometragem','numero',NULL,7),
(26,'rastreamento','placa','Placa','texto','ver_localizacao',1),
(27,'rastreamento','capturado_em','Data da posição','data_hora','ver_localizacao',2),
(28,'rastreamento','latitude','Latitude','numero','ver_localizacao',3),
(29,'rastreamento','longitude','Longitude','numero','ver_localizacao',4),
(30,'rastreamento','velocidade','Velocidade','numero','ver_localizacao',5),
(31,'rastreamento','fonte','Fonte','texto','ver_localizacao',6),
(32,'despesas','protocolo','Protocolo','texto',NULL,1),
(33,'despesas','veiculo','Veículo','texto',NULL,2),
(34,'despesas','categoria','Categoria','texto',NULL,3),
(35,'despesas','data_despesa','Data','data',NULL,4),
(36,'despesas','descricao','Descrição','texto',NULL,5),
(37,'despesas','valor','Valor','moeda','ver_valores',6),
(38,'despesas','situacao','Situação','situacao',NULL,7),
(39,'multas','protocolo','Protocolo','texto',NULL,1),
(40,'multas','veiculo','Veículo','texto',NULL,2),
(41,'multas','ocorrido_em','Data da autuação','data',NULL,3),
(42,'multas','precisao_ocorrencia','Precisão da ocorrência','texto',NULL,4),
(43,'multas','responsavel','Responsável','texto',NULL,5),
(44,'multas','descricao','Descrição','texto',NULL,6),
(45,'multas','valor','Valor','moeda','ver_valores',7),
(46,'multas','situacao','Situação','situacao',NULL,8),
(47,'chamados','protocolo','Protocolo','texto',NULL,1),
(48,'chamados','assunto','Assunto','texto',NULL,2),
(49,'chamados','categoria','Categoria','texto',NULL,3),
(50,'chamados','situacao','Situação','situacao',NULL,4),
(51,'chamados','criado_em','Abertura','data_hora',NULL,5),
(52,'usuarios','identificador','Identificador','texto',NULL,1),
(53,'usuarios','nome','Nome','texto',NULL,2),
(54,'usuarios','unidade','Unidade','texto',NULL,3),
(55,'usuarios','ativo','Situação','situacao',NULL,4),
(56,'perfis','codigo','Código','texto',NULL,1),
(57,'perfis','nome','Nome','texto',NULL,2),
(58,'perfis','descricao','Descrição','texto',NULL,3),
(59,'perfis','ativo','Situação','situacao',NULL,4),
(60,'rotas','chave','Chave','texto',NULL,1),
(61,'rotas','nome','Nome','texto',NULL,2),
(62,'rotas','caminho','Caminho','texto',NULL,3),
(63,'rotas','modulo','Módulo','texto',NULL,4),
(64,'rotas','ativa','Situação','situacao',NULL,5),
(65,'auditoria','evento','Evento','texto',NULL,1),
(66,'auditoria','ator','Usuário','texto',NULL,2),
(67,'auditoria','perfil','Perfil ativo','texto',NULL,3),
(68,'auditoria','entidade','Entidade','texto',NULL,4),
(69,'auditoria','criado_em','Data','data_hora',NULL,5);

-- Views não autenticam o usuário HTTP. Aplicar o alcance do vínculo no backend.
CREATE SQL SECURITY INVOKER VIEW vw_vinculos_ativos AS
SELECT up.id AS vinculo_id,up.usuario_id,up.perfil_id,up.unidade_id,up.vigente_desde,up.vigente_ate
FROM usuario_perfis up
JOIN usuarios u ON u.id=up.usuario_id AND u.ativo=1
JOIN unidades un ON un.id=up.unidade_id AND un.ativa=1
JOIN perfis pf ON pf.id=up.perfil_id AND pf.ativo=1
WHERE up.ativo=1 AND up.vigente_desde<=UTC_TIMESTAMP(6)
AND (up.vigente_ate IS NULL OR up.vigente_ate>UTC_TIMESTAMP(6));

CREATE SQL SECURITY INVOKER VIEW vw_permissoes_efetivas AS
SELECT va.vinculo_id,va.usuario_id,va.perfil_id,va.unidade_id,
p.modulo_codigo,p.acao_codigo,p.alcance,pp.delegavel,
CASE p.alcance WHEN 'proprios' THEN 1 WHEN 'unidade' THEN 2 ELSE 3 END AS nivel_alcance
FROM vw_vinculos_ativos va
JOIN perfil_permissoes pp ON pp.perfil_id=va.perfil_id
JOIN permissoes p ON p.id=pp.permissao_id
JOIN modulos m ON m.codigo=p.modulo_codigo AND m.ativo=1;

CREATE SQL SECURITY INVOKER VIEW vw_administradores_permanentes AS
SELECT pe.vinculo_id,pe.usuario_id,pe.perfil_id
FROM vw_permissoes_efetivas pe
JOIN usuario_perfis up ON up.id=pe.vinculo_id AND up.vigente_ate IS NULL
WHERE pe.acao_codigo='gerenciar' AND pe.alcance='orgao'
AND pe.modulo_codigo IN ('usuarios','perfis','rotas','configuracoes')
GROUP BY pe.vinculo_id,pe.usuario_id,pe.perfil_id
HAVING COUNT(DISTINCT pe.modulo_codigo)=4;

CREATE SQL SECURITY INVOKER VIEW vw_solicitacoes_atuais AS
SELECT s.id,s.protocolo,s.solicitante_id,s.unidade_id,s.situacao,s.versao,s.criado_em,
r.id AS revisao_id,r.numero,r.finalidade,r.origem,r.destino,r.saida_prevista,r.retorno_previsto,
r.quantidade_passageiros,r.necessita_motorista,r.veiculo_pretendido_id,r.motorista_sugerido_id,
r.trajeto_planejado,r.distancia_prevista_km,r.enviado_em,
u.nome AS solicitante,un.nome AS unidade
FROM solicitacoes s
JOIN solicitacao_revisoes r ON r.id=s.revisao_atual_id AND r.solicitacao_id=s.id
JOIN usuarios u ON u.id=s.solicitante_id
JOIN unidades un ON un.id=s.unidade_id;

CREATE SQL SECURITY INVOKER VIEW vw_viagens_detalhadas AS
SELECT v.*,s.id AS solicitacao_id,s.solicitante_id,s.unidade_id,
r.saida_prevista,r.retorno_previsto,r.finalidade,r.origem,r.destino,r.trajeto_planejado,
ve.placa,ve.nome AS veiculo,u.nome AS motorista,
CASE WHEN v.quilometragem_retorno IS NOT NULL THEN v.quilometragem_retorno-v.quilometragem_saida ELSE NULL END AS distancia_real_km
FROM viagens v
JOIN solicitacao_revisoes r ON r.id=v.revisao_id
JOIN solicitacoes s ON s.id=r.solicitacao_id
JOIN veiculos ve ON ve.id=v.veiculo_id
JOIN usuarios u ON u.id=v.motorista_id;

CREATE SQL SECURITY INVOKER VIEW vw_frota AS
SELECT v.*,c.nome AS categoria,un.nome AS unidade,
CASE WHEN v.situacao_cadastro<>'ativo' THEN v.situacao_cadastro
WHEN EXISTS (SELECT 1 FROM viagens t WHERE t.veiculo_id=v.id AND t.situacao='em_andamento') THEN 'em_viagem'
WHEN EXISTS (SELECT 1 FROM reservas r WHERE r.veiculo_id=v.id AND r.situacao='ativa' AND r.tipo='manutencao' AND r.inicio<=UTC_TIMESTAMP(6) AND r.fim>UTC_TIMESTAMP(6))
OR EXISTS (SELECT 1 FROM manutencoes m WHERE m.veiculo_id=v.id AND m.situacao='em_execucao') THEN 'manutencao'
WHEN EXISTS (SELECT 1 FROM reservas r WHERE r.veiculo_id=v.id AND r.situacao='ativa' AND r.tipo='indisponibilidade' AND r.inicio<=UTC_TIMESTAMP(6) AND r.fim>UTC_TIMESTAMP(6)) THEN 'indisponivel'
ELSE 'disponivel' END AS situacao_operacional
FROM veiculos v JOIN categorias_veiculo c ON c.id=v.categoria_id JOIN unidades un ON un.id=v.unidade_id;

CREATE SQL SECURITY INVOKER VIEW vw_ultima_posicao_rastreador AS
SELECT p.* FROM posicoes_rastreamento p
WHERE NOT EXISTS (SELECT 1 FROM posicoes_rastreamento newer
WHERE newer.instalacao_id=p.instalacao_id AND (newer.capturado_em>p.capturado_em OR (newer.capturado_em=p.capturado_em AND newer.id>p.id)));

CREATE SQL SECURITY INVOKER VIEW vw_monitoramento AS
SELECT v.id AS veiculo_id,v.placa,v.nome,v.unidade_id,vr.id AS instalacao_id,
r.identificador_externo,r.provedor,p.capturado_em,p.recebido_em,p.latitude,p.longitude,p.velocidade_kmh,
CASE WHEN vr.id IS NULL THEN 'sem_rastreador'
WHEN r.ativo=0 THEN 'rastreador_inativo'
WHEN p.id IS NULL OR p.capturado_em<TIMESTAMPADD(MINUTE,-cfg.limite_sem_comunicacao_minutos,UTC_TIMESTAMP(6)) THEN 'sem_comunicacao'
ELSE 'transmitindo' END AS situacao_comunicacao
FROM veiculos v CROSS JOIN configuracao_sistema cfg
LEFT JOIN veiculo_rastreadores vr ON vr.veiculo_id=v.id AND vr.removido_em IS NULL
LEFT JOIN rastreadores r ON r.id=vr.rastreador_id
LEFT JOIN vw_ultima_posicao_rastreador p ON p.instalacao_id=vr.id;

CREATE SQL SECURITY INVOKER VIEW vw_multas_detalhadas AS
SELECT m.*,r.responsavel_id,r.motorista_id,r.viagem_id,
u.nome AS responsavel,ve.placa,ve.nome AS veiculo,
(SELECT MAX(c.numero) FROM multa_comprovantes c WHERE c.multa_id=m.id) AS ultima_versao_comprovante,
pg.valor AS valor_quitado,pg.pago_em
FROM multas m JOIN veiculos ve ON ve.id=m.veiculo_id
LEFT JOIN multa_responsabilidades r ON r.id=m.responsabilidade_atual_id AND r.multa_id=m.id
LEFT JOIN usuarios u ON u.id=r.responsavel_id
LEFT JOIN pagamentos_multa pg ON pg.multa_id=m.id;

CREATE SQL SECURITY INVOKER VIEW vw_despesas_detalhadas AS
SELECT d.*,v.placa,v.nome AS veiculo,c.nome AS categoria,
f.nome AS fornecedor,p.valor AS valor_pago,p.pago_em
FROM despesas d JOIN veiculos v ON v.id=d.veiculo_id
JOIN categorias_despesa c ON c.id=d.categoria_id
LEFT JOIN fornecedores f ON f.id=d.fornecedor_id
LEFT JOIN pagamentos_despesa p ON p.despesa_id=d.id;

CREATE SQL SECURITY INVOKER VIEW vw_custos_veiculo_mensais AS
SELECT veiculo_id,unidade_id,DATE_FORMAT(data_despesa,'%Y-%m-01') AS mes,
SUM(valor) AS valor_despesas,COUNT(*) AS quantidade_despesas
FROM despesas WHERE situacao<>'cancelada'
GROUP BY veiculo_id,unidade_id,DATE_FORMAT(data_despesa,'%Y-%m-01');

CREATE SQL SECURITY INVOKER VIEW vw_notificacoes_usuario AS
SELECT nd.usuario_id,nd.lida_em,nd.oculta_em,nd.versao,nd.entregue_em,ne.*
FROM notificacao_destinatarios nd JOIN notificacao_eventos ne ON ne.id=nd.evento_id;

CREATE SQL SECURITY INVOKER VIEW vw_trajetos_observados AS
SELECT p.id AS registro_id,'rastreador' AS fonte,p.veiculo_id,p.viagem_id,p.capturado_em AS ocorrido_em,p.latitude,p.longitude,p.velocidade_kmh,NULL AS descricao
FROM posicoes_rastreamento p
UNION ALL
SELECT p.id AS registro_id,'manual' AS fonte,v.veiculo_id,p.viagem_id,p.ocorrido_em,p.latitude,p.longitude,NULL AS velocidade_kmh,p.descricao
FROM posicoes_manuais p JOIN viagens v ON v.id=p.viagem_id;

CREATE SQL SECURITY INVOKER VIEW vw_resumo_financeiro_veiculo AS
SELECT v.id AS veiculo_id,v.placa,v.unidade_id,
COALESCE((SELECT SUM(d.valor) FROM despesas d WHERE d.veiculo_id=v.id AND d.situacao<>'cancelada'),0) AS despesas_registradas,
COALESCE((SELECT SUM(p.valor) FROM pagamentos_despesa p JOIN despesas d ON d.id=p.despesa_id WHERE d.veiculo_id=v.id),0) AS despesas_pagas,
COALESCE((SELECT SUM(m.valor) FROM multas m WHERE m.veiculo_id=v.id AND m.situacao<>'cancelada'),0) AS multas_registradas,
COALESCE((SELECT SUM(p.valor) FROM pagamentos_multa p JOIN multas m ON m.id=p.multa_id WHERE m.veiculo_id=v.id),0) AS multas_quitadas,
(SELECT COUNT(*) FROM multas m WHERE m.veiculo_id=v.id AND m.situacao IN ('sem_responsavel','aguardando_comprovante','em_conferencia','contestada')) AS multas_pendentes
FROM veiculos v;

-- Helpers: não iniciar transação dentro deles. Rotinas de negócio são chamadas isoladamente.
DELIMITER $$
CREATE PROCEDURE sp_exigir_permissao(IN p_vinculo BIGINT UNSIGNED,IN p_modulo VARCHAR(40),IN p_acao VARCHAR(40),IN p_dono BIGINT UNSIGNED,IN p_unidade BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_ok BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_lock BIGINT UNSIGNED DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_ok=NULL;
  SELECT vinculo_id INTO v_lock FROM controle_vinculos
  WHERE vinculo_id=p_vinculo AND ativo_vinculo=1 AND ativo_usuario=1 AND ativo_perfil=1 AND ativo_unidade=1
  AND vigente_desde<=UTC_TIMESTAMP(6) AND (vigente_ate IS NULL OR vigente_ate>UTC_TIMESTAMP(6)) LOCK IN SHARE MODE;
  IF v_lock IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Acesso negado: vínculo inativo ou expirado.'; END IF;
  SELECT pe.vinculo_id INTO v_ok FROM vw_permissoes_efetivas pe
  WHERE pe.vinculo_id=p_vinculo AND pe.modulo_codigo=p_modulo AND pe.acao_codigo=p_acao
  AND (pe.alcance='orgao' OR (pe.alcance='unidade' AND pe.unidade_id=p_unidade)
       OR (pe.alcance='proprios' AND pe.usuario_id=p_dono)) LIMIT 1 LOCK IN SHARE MODE;
  IF v_ok IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Acesso negado: vínculo, ação ou alcance inválido.'; END IF;
END$$

CREATE PROCEDURE sp_validar_arquivo(IN p_usuario BIGINT UNSIGNED,IN p_arquivo BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_ok INT DEFAULT 0;
  SELECT COUNT(*) INTO v_ok FROM arquivos WHERE id=p_arquivo AND enviado_por=p_usuario AND situacao='disponivel';
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Arquivo indisponível ou não pertence ao usuário autenticado.'; END IF;
END$$

CREATE PROCEDURE sp_auditar(IN p_vinculo BIGINT UNSIGNED,IN p_evento VARCHAR(80),IN p_entidade VARCHAR(60),IN p_id BIGINT UNSIGNED,IN p_descricao VARCHAR(1000))
SQL SECURITY INVOKER
BEGIN
  INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,evento,entidade,entidade_id,descricao)
  SELECT usuario_id,id,p_evento,p_entidade,p_id,p_descricao FROM usuario_perfis WHERE id=p_vinculo;
END$$

CREATE PROCEDURE sp_notificar(IN p_chave VARCHAR(160),IN p_tipo VARCHAR(60),IN p_titulo VARCHAR(160),IN p_mensagem VARCHAR(1000),IN p_caminho VARCHAR(255),IN p_solicitacao BIGINT UNSIGNED,IN p_multa BIGINT UNSIGNED,IN p_usuario BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_id BIGINT UNSIGNED;
  -- Chave única conserva o conteúdo original em tentativas repetidas.
  SELECT MAX(id) INTO v_id FROM notificacao_eventos WHERE chave_idempotencia=p_chave;
  IF v_id IS NULL THEN
    INSERT INTO notificacao_eventos(chave_idempotencia,tipo,titulo,mensagem,caminho_destino,solicitacao_id,multa_id)
    VALUES(p_chave,p_tipo,p_titulo,p_mensagem,p_caminho,p_solicitacao,p_multa);
    SET v_id=LAST_INSERT_ID();
  END IF;
  INSERT INTO notificacao_destinatarios(evento_id,usuario_id) VALUES(v_id,p_usuario)
  ON DUPLICATE KEY UPDATE evento_id=VALUES(evento_id);
END$$

CREATE PROCEDURE sp_exigir_admin_restante(IN p_excluir_usuario BIGINT UNSIGNED,IN p_excluir_perfil BIGINT UNSIGNED,IN p_excluir_vinculo BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_lock INT;
  DECLARE v_admin BIGINT UNSIGNED DEFAULT NULL;
  DECLARE v_bootstrap INT;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_admin=NULL;
  SELECT id,bootstrap_concluido INTO v_lock,v_bootstrap FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  IF v_bootstrap=1 THEN
    -- Leitura bloqueante: mesmo com duas revogações concorrentes, somente uma pode remover o acesso.
    SELECT cv.vinculo_id INTO v_admin FROM controle_vinculos cv
    WHERE cv.ativo_vinculo=1 AND cv.ativo_usuario=1 AND cv.ativo_perfil=1 AND cv.ativo_unidade=1
    AND cv.pode_administrar=1 AND cv.vigente_desde<=UTC_TIMESTAMP(6) AND cv.vigente_ate IS NULL
    AND (p_excluir_usuario IS NULL OR cv.usuario_id<>p_excluir_usuario)
    AND (p_excluir_perfil IS NULL OR cv.perfil_id<>p_excluir_perfil)
    AND (p_excluir_vinculo IS NULL OR cv.vinculo_id<>p_excluir_vinculo)
    LIMIT 1 FOR UPDATE;
    IF v_admin IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Mantenha ao menos um administrador ativo com vínculo sem término.'; END IF;
  END IF;
END$$

CREATE PROCEDURE sp_criar_administrador_inicial(IN p_identificador VARCHAR(100),IN p_nome VARCHAR(150),IN p_email VARCHAR(254),IN p_senha_hash VARCHAR(255))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_bootstrap INT;
  DECLARE v_usuario BIGINT UNSIGNED;
  DECLARE v_perfil BIGINT UNSIGNED;
  DECLARE v_vinculo BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT bootstrap_concluido INTO v_bootstrap FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  IF v_bootstrap=1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='O administrador inicial já foi criado.'; END IF;
  IF p_identificador IS NULL OR CHAR_LENGTH(TRIM(p_identificador))=0 OR p_nome IS NULL OR CHAR_LENGTH(TRIM(p_nome))=0
    OR p_senha_hash IS NULL OR NOT (p_senha_hash LIKE '$2y$%' OR p_senha_hash LIKE '$2b$%' OR p_senha_hash LIKE '$argon2id$%') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe identificador, nome e hash bcrypt/Argon2id gerado no backend.';
  END IF;
  SELECT id INTO v_perfil FROM perfis WHERE codigo='administrador' AND ativo=1;
  INSERT INTO usuarios(unidade_id,identificador,nome,email,senha_hash) VALUES(1,p_identificador,p_nome,p_email,p_senha_hash);
  SET v_usuario=LAST_INSERT_ID();
  INSERT INTO preferencias_usuario(usuario_id) VALUES(v_usuario);
  INSERT INTO usuario_perfis(usuario_id,perfil_id,unidade_id,vigente_desde,concedido_por)
  VALUES(v_usuario,v_perfil,1,UTC_TIMESTAMP(6),v_usuario);
  SET v_vinculo=LAST_INSERT_ID();
  UPDATE configuracao_sistema SET bootstrap_concluido=1 WHERE id=1;
  CALL sp_auditar(v_vinculo,'administrador_inicial_criado','usuarios',v_usuario,'Administrador inicial configurado; senha não registrada na auditoria.');
  COMMIT;
  SELECT v_usuario AS usuario_id,v_vinculo AS vinculo_id;
END$$

CREATE PROCEDURE sp_conceder_permissao(IN p_ator_vinculo BIGINT UNSIGNED,IN p_perfil BIGINT UNSIGNED,IN p_permissao BIGINT UNSIGNED,IN p_delegavel TINYINT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_modulo VARCHAR(40); DECLARE v_acao VARCHAR(40); DECLARE v_nivel INT; DECLARE v_pode INT;
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_lock INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  CALL sp_exigir_permissao(p_ator_vinculo,'perfis','delegar',NULL,NULL);
  SELECT modulo_codigo,acao_codigo,CASE alcance WHEN 'proprios' THEN 1 WHEN 'unidade' THEN 2 ELSE 3 END
  INTO v_modulo,v_acao,v_nivel FROM permissoes WHERE id=p_permissao;
  SELECT MAX(usuario_id),COUNT(*) INTO v_usuario,v_pode FROM vw_permissoes_efetivas
  WHERE vinculo_id=p_ator_vinculo AND modulo_codigo=v_modulo AND acao_codigo=v_acao AND delegavel=1 AND nivel_alcance>=v_nivel;
  IF v_pode=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode delegar ação ou alcance que não recebeu como delegável.'; END IF;
  INSERT INTO perfil_permissoes(perfil_id,permissao_id,delegavel,concedido_por) VALUES(p_perfil,p_permissao,p_delegavel,v_usuario)
  ON DUPLICATE KEY UPDATE delegavel=VALUES(delegavel),concedido_por=VALUES(concedido_por),concedido_em=UTC_TIMESTAMP(6);
  CALL sp_auditar(p_ator_vinculo,'permissao_concedida','perfis',p_perfil,CONCAT('Permissão ',p_permissao,' concedida.'));
  COMMIT;
END$$

CREATE PROCEDURE sp_marcar_notificacao(IN p_usuario BIGINT UNSIGNED,IN p_evento BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_lida TINYINT,IN p_oculta TINYINT)
SQL SECURITY INVOKER
BEGIN
  IF p_lida NOT IN (0,1) OR p_oculta NOT IN (0,1) OR p_lida IS NULL OR p_oculta IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Estados de leitura e ocultação inválidos.';
  END IF;
  UPDATE notificacao_destinatarios SET lida_em=CASE WHEN p_lida=1 THEN COALESCE(lida_em,UTC_TIMESTAMP(6)) ELSE NULL END,
    oculta_em=CASE WHEN p_oculta=1 THEN COALESCE(oculta_em,UTC_TIMESTAMP(6)) ELSE NULL END,versao=versao+1
  WHERE usuario_id=p_usuario AND evento_id=p_evento AND versao=p_versao;
  IF ROW_COUNT()<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Notificação não encontrada ou versão já alterada.'; END IF;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_criar_solicitacao(IN p_vinculo BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED;
  DECLARE v_id BIGINT UNSIGNED; DECLARE v_revisao BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT usuario_id,unidade_id INTO v_usuario,v_unidade FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','criar',v_usuario,v_unidade);
  INSERT INTO solicitacoes(protocolo,solicitante_id,unidade_id) VALUES(CONCAT('SOL-',REPLACE(UUID(),'-','')),v_usuario,v_unidade);
  SET v_id=LAST_INSERT_ID();
  INSERT INTO solicitacao_revisoes(solicitacao_id,numero,criado_por) VALUES(v_id,1,v_usuario);
  SET v_revisao=LAST_INSERT_ID();
  UPDATE solicitacoes SET revisao_atual_id=v_revisao,protocolo=CONCAT('SOL-',LPAD(v_id,8,'0')) WHERE id=v_id;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_nova)
  VALUES(v_id,v_revisao,p_vinculo,'criada','rascunho');
  CALL sp_auditar(p_vinculo,'solicitacao_criada','solicitacoes',v_id,'Rascunho da primeira revisão criado.');
  COMMIT;
  SELECT v_id AS solicitacao_id,v_revisao AS revisao_id;
END$$

CREATE PROCEDURE sp_enviar_solicitacao(IN p_vinculo BIGINT UNSIGNED,IN p_solicitacao BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_revisao BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,revisao_atual_id,versao,situacao INTO v_dono,v_unidade,v_revisao,v_versao,v_situacao
  FROM solicitacoes WHERE id=p_solicitacao FOR UPDATE;
  IF v_revisao IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','enviar',v_dono,v_unidade);
  IF v_situacao<>'rascunho' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Somente rascunho pode ser enviado; abra uma nova revisão para alterar.'; END IF;
  UPDATE solicitacao_revisoes SET enviado_em=UTC_TIMESTAMP(6),etapa_atual=4 WHERE id=v_revisao;
  UPDATE solicitacoes SET situacao='aguardando_analise',versao=versao+1 WHERE id=p_solicitacao;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova)
  VALUES(p_solicitacao,v_revisao,p_vinculo,'enviada',v_situacao,'aguardando_analise');
  CALL sp_auditar(p_vinculo,'solicitacao_enviada','solicitacoes',p_solicitacao,'Revisão enviada para análise.');
  COMMIT;
END$$

CREATE PROCEDURE sp_aprovar_solicitacao(IN p_vinculo BIGINT UNSIGNED,IN p_solicitacao BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_veiculo BIGINT UNSIGNED,IN p_motorista BIGINT UNSIGNED,IN p_motivo TEXT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED;
  DECLARE v_revisao BIGINT UNSIGNED; DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40);
  DECLARE v_inicio DATETIME(6); DECLARE v_fim DATETIME(6); DECLARE v_reserva BIGINT UNSIGNED; DECLARE v_viagem BIGINT UNSIGNED;
  DECLARE v_unidade_veiculo BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,revisao_atual_id,versao,situacao INTO v_dono,v_unidade,v_revisao,v_versao,v_situacao
  FROM solicitacoes WHERE id=p_solicitacao FOR UPDATE;
  IF v_revisao IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','aprovar',v_dono,v_unidade);
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  IF v_usuario=v_dono THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Usuário não pode aprovar a própria solicitação, mesmo trocando de perfil.'; END IF;
  IF v_situacao<>'aguardando_analise' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não está aguardando análise.'; END IF;
  SELECT saida_prevista,retorno_previsto INTO v_inicio,v_fim FROM solicitacao_revisoes WHERE id=v_revisao AND enviado_em IS NOT NULL;
  IF v_inicio IS NULL OR v_fim IS NULL OR p_motorista IS NULL OR p_veiculo IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisão, veículo ou motorista incompletos.'; END IF;
  SELECT unidade_id INTO v_unidade_veiculo FROM veiculos WHERE id=p_veiculo FOR UPDATE;
  CALL sp_exigir_permissao(p_vinculo,'frota','consultar',NULL,v_unidade_veiculo);
  -- A trigger da agenda toma locks e revalida veículo, motorista, capacidade e sobreposição.
  INSERT INTO reservas(veiculo_id,motorista_id,revisao_id,tipo,inicio,fim,criado_por,descricao)
  VALUES(p_veiculo,p_motorista,v_revisao,'viagem',v_inicio,v_fim,v_usuario,p_motivo);
  SET v_reserva=LAST_INSERT_ID();
  INSERT INTO viagens(protocolo,revisao_id,reserva_id,veiculo_id,motorista_id)
  VALUES(CONCAT('VIA-',LPAD(p_solicitacao,8,'0'),'-',LPAD((SELECT numero FROM solicitacao_revisoes WHERE id=v_revisao),2,'0')),v_revisao,v_reserva,p_veiculo,p_motorista);
  SET v_viagem=LAST_INSERT_ID();
  UPDATE solicitacoes SET situacao='aprovada',versao=versao+1 WHERE id=p_solicitacao;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo,veiculo_confirmado_id,motorista_confirmado_id)
  VALUES(p_solicitacao,v_revisao,p_vinculo,'aprovada',v_situacao,'aprovada',p_motivo,p_veiculo,p_motorista);
  CALL sp_auditar(p_vinculo,'solicitacao_aprovada','solicitacoes',p_solicitacao,'Veículo e motorista reservados. Saída ainda não registrada.');
  CALL sp_notificar(CONCAT('solicitacao:',p_solicitacao,':revisao:',v_revisao,':aprovada'),'solicitacao_aprovada','Solicitação aprovada','Consulte os dados da viagem programada.',CONCAT('/solicitacoes/',p_solicitacao),p_solicitacao,NULL,v_dono);
  COMMIT;
  SELECT v_reserva AS reserva_id,v_viagem AS viagem_id;
END$$

CREATE PROCEDURE sp_decidir_solicitacao(IN p_vinculo BIGINT UNSIGNED,IN p_solicitacao BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_decisao VARCHAR(40),IN p_motivo TEXT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_revisao BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_acao VARCHAR(40);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,revisao_atual_id,versao,situacao INTO v_dono,v_unidade,v_revisao,v_versao,v_situacao
  FROM solicitacoes WHERE id=p_solicitacao FOR UPDATE;
  IF v_revisao IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não encontrada ou versão alterada.'; END IF;
  IF p_decisao IS NULL OR p_decisao NOT IN ('negada','ajustes_solicitados') OR p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Decisão ou motivo inválido.'; END IF;
  SET v_acao=CASE p_decisao WHEN 'negada' THEN 'negar' ELSE 'solicitar_ajustes' END;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes',v_acao,v_dono,v_unidade);
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  IF v_usuario=v_dono THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Usuário não pode decidir a própria solicitação.'; END IF;
  IF v_situacao<>'aguardando_analise' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não está aguardando análise.'; END IF;
  UPDATE solicitacoes SET situacao=p_decisao,versao=versao+1 WHERE id=p_solicitacao;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(p_solicitacao,v_revisao,p_vinculo,p_decisao,v_situacao,p_decisao,p_motivo);
  CALL sp_auditar(p_vinculo,CONCAT('solicitacao_',p_decisao),'solicitacoes',p_solicitacao,p_motivo);
  CALL sp_notificar(CONCAT('solicitacao:',p_solicitacao,':revisao:',v_revisao,':',p_decisao),'solicitacao_decidida','Solicitação analisada',LEFT(p_motivo,1000),CONCAT('/solicitacoes/',p_solicitacao),p_solicitacao,NULL,v_dono);
  COMMIT;
END$$

CREATE PROCEDURE sp_abrir_revisao(IN p_vinculo BIGINT UNSIGNED,IN p_solicitacao BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_motivo TEXT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_antiga BIGINT UNSIGNED;
  DECLARE v_nova BIGINT UNSIGNED; DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_bloqueio INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,revisao_atual_id,versao,situacao INTO v_dono,v_unidade,v_antiga,v_versao,v_situacao
  FROM solicitacoes WHERE id=p_solicitacao FOR UPDATE;
  IF v_antiga IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','editar',v_dono,v_unidade);
  IF v_situacao NOT IN ('aguardando_analise','ajustes_solicitados','aprovada') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Esta situação não permite abrir revisão; edite o rascunho ou crie nova solicitação.'; END IF;
  SELECT COUNT(*) INTO v_bloqueio FROM viagens WHERE revisao_id=v_antiga AND situacao IN ('em_andamento','concluida') FOR UPDATE;
  IF v_bloqueio>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem iniciada ou concluída não permite revisar a solicitação original.'; END IF;
  IF p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe o motivo da revisão.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  UPDATE viagens SET situacao='cancelada',versao=versao+1 WHERE revisao_id=v_antiga AND situacao='programada';
  UPDATE reservas SET situacao='cancelada',liberada_em=UTC_TIMESTAMP(6) WHERE revisao_id=v_antiga AND situacao='ativa';
  INSERT INTO solicitacao_revisoes(solicitacao_id,numero,finalidade,origem,destino,origem_latitude,origem_longitude,destino_latitude,destino_longitude,saida_prevista,retorno_previsto,quantidade_passageiros,necessita_motorista,motorista_sugerido_id,veiculo_pretendido_id,trajeto_planejado,polilinha_planejada,distancia_prevista_km,observacoes,criado_por)
  SELECT solicitacao_id,numero+1,finalidade,origem,destino,origem_latitude,origem_longitude,destino_latitude,destino_longitude,saida_prevista,retorno_previsto,quantidade_passageiros,necessita_motorista,motorista_sugerido_id,veiculo_pretendido_id,trajeto_planejado,polilinha_planejada,distancia_prevista_km,observacoes,v_usuario
  FROM solicitacao_revisoes WHERE id=v_antiga;
  SET v_nova=LAST_INSERT_ID();
  INSERT INTO solicitacao_paradas(revisao_id,ordem,descricao,latitude,longitude) SELECT v_nova,ordem,descricao,latitude,longitude FROM solicitacao_paradas WHERE revisao_id=v_antiga;
  INSERT INTO solicitacao_passageiros(revisao_id,usuario_id,nome,contato) SELECT v_nova,usuario_id,nome,contato FROM solicitacao_passageiros WHERE revisao_id=v_antiga;
  INSERT INTO anexos(arquivo_id,revisao_id,descricao) SELECT arquivo_id,v_nova,descricao FROM anexos WHERE revisao_id=v_antiga;
  UPDATE solicitacoes SET revisao_atual_id=v_nova,situacao='rascunho',versao=versao+1 WHERE id=p_solicitacao;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(p_solicitacao,v_nova,p_vinculo,'revisao_aberta',v_situacao,'rascunho',p_motivo);
  CALL sp_auditar(p_vinculo,'revisao_aberta','solicitacoes',p_solicitacao,'Revisão anterior preservada; reserva e aprovação anteriores encerradas.');
  COMMIT;
  SELECT v_nova AS revisao_id;
END$$

CREATE PROCEDURE sp_cancelar_solicitacao(IN p_vinculo BIGINT UNSIGNED,IN p_solicitacao BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_motivo TEXT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_revisao BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_bloqueio INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,revisao_atual_id,versao,situacao INTO v_dono,v_unidade,v_revisao,v_versao,v_situacao FROM solicitacoes WHERE id=p_solicitacao FOR UPDATE;
  IF v_revisao IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitação não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'solicitacoes','cancelar',v_dono,v_unidade);
  IF p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 OR v_situacao IN ('cancelada','negada') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Cancelamento inválido ou sem motivo.'; END IF;
  SELECT COUNT(*) INTO v_bloqueio FROM viagens v JOIN solicitacao_revisoes r ON r.id=v.revisao_id WHERE r.solicitacao_id=p_solicitacao AND v.situacao IN ('em_andamento','concluida') FOR UPDATE;
  IF v_bloqueio>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode cancelar solicitação com viagem iniciada.'; END IF;
  UPDATE viagens SET situacao='cancelada',versao=versao+1 WHERE revisao_id=v_revisao AND situacao='programada';
  UPDATE reservas SET situacao='cancelada',liberada_em=UTC_TIMESTAMP(6) WHERE revisao_id=v_revisao AND situacao='ativa';
  UPDATE solicitacoes SET situacao='cancelada',versao=versao+1 WHERE id=p_solicitacao;
  INSERT INTO solicitacao_eventos(solicitacao_id,revisao_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(p_solicitacao,v_revisao,p_vinculo,'cancelada',v_situacao,'cancelada',p_motivo);
  CALL sp_auditar(p_vinculo,'solicitacao_cancelada','solicitacoes',p_solicitacao,p_motivo);
  COMMIT;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_veiculos_disponiveis(IN p_vinculo BIGINT UNSIGNED,IN p_inicio DATETIME(6),IN p_fim DATETIME(6),IN p_passageiros SMALLINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED;
  SELECT usuario_id,unidade_id INTO v_usuario,v_unidade FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'frota','selecionar',NULL,v_unidade);
  IF p_inicio IS NULL OR p_fim IS NULL OR p_fim<=p_inicio OR p_passageiros IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe período e quantidade de passageiros.'; END IF;
  SELECT v.id,v.nome,v.placa,c.nome AS categoria,v.capacidade,v.unidade_id
  FROM veiculos v JOIN categorias_veiculo c ON c.id=v.categoria_id
  WHERE v.situacao_cadastro='ativo' AND v.capacidade>=p_passageiros+1
  AND EXISTS(SELECT 1 FROM vw_permissoes_efetivas pe WHERE pe.vinculo_id=p_vinculo AND pe.modulo_codigo='frota' AND pe.acao_codigo='selecionar'
  AND (pe.alcance='orgao' OR (pe.alcance='unidade' AND pe.unidade_id=v.unidade_id)))
  AND NOT EXISTS(SELECT 1 FROM ocupacoes_agenda oa WHERE oa.veiculo_id=v.id AND oa.inicio<p_fim AND oa.fim>p_inicio)
  ORDER BY v.nome,v.id;
  -- Consulta informativa: a aprovação revalida e efetivamente reserva o veículo.
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_gravar_checklist(IN p_usuario BIGINT UNSIGNED,IN p_viagem BIGINT UNSIGNED,IN p_tipo VARCHAR(10),IN p_respostas JSON,OUT p_id BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_indice INT DEFAULT 0; DECLARE v_total INT; DECLARE v_item INT;
  DECLARE v_resultado VARCHAR(30); DECLARE v_observacao VARCHAR(1000);
  IF p_respostas IS NULL OR JSON_TYPE(p_respostas)<>'ARRAY' OR JSON_LENGTH(p_respostas)=0 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe as respostas do checklist em um array JSON.';
  END IF;
  INSERT INTO viagem_checklists(viagem_id,tipo,preenchido_por) VALUES(p_viagem,p_tipo,p_usuario);
  SET p_id=LAST_INSERT_ID(); SET v_total=JSON_LENGTH(p_respostas);
  WHILE v_indice<v_total DO
    SET v_item=CAST(JSON_UNQUOTE(JSON_EXTRACT(p_respostas,CONCAT('$[',v_indice,'].item_id'))) AS UNSIGNED);
    SET v_resultado=JSON_UNQUOTE(JSON_EXTRACT(p_respostas,CONCAT('$[',v_indice,'].resultado')));
    SET v_observacao=NULLIF(JSON_UNQUOTE(JSON_EXTRACT(p_respostas,CONCAT('$[',v_indice,'].observacao'))),'null');
    INSERT INTO viagem_checklist_respostas(checklist_id,item_id,resultado,observacao) VALUES(p_id,v_item,v_resultado,v_observacao);
    SET v_indice=v_indice+1;
  END WHILE;
  UPDATE viagem_checklists SET finalizado_em=UTC_TIMESTAMP(6) WHERE id=p_id;
END$$

CREATE PROCEDURE sp_registrar_saida(IN p_vinculo BIGINT UNSIGNED,IN p_viagem BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_saida DATETIME(6),IN p_km DECIMAL(12,1),IN p_observacao TEXT,IN p_checklist JSON)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED;
  DECLARE v_veiculo BIGINT UNSIGNED; DECLARE v_reserva BIGINT UNSIGNED; DECLARE v_checklist BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_fim DATETIME(6);
  DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT v.veiculo_id,v.reserva_id,v.versao,v.situacao,s.solicitante_id,s.unidade_id,v.motorista_id
  INTO v_veiculo,v_reserva,v_versao,v_situacao,v_dono,v_unidade,v_motorista
  FROM viagens v JOIN solicitacao_revisoes r ON r.id=v.revisao_id JOIN solicitacoes s ON s.id=r.solicitacao_id
  WHERE v.id=p_viagem FOR UPDATE;
  IF v_veiculo IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem não encontrada ou versão alterada.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'viagens','registrar_saida',IF(v_usuario=v_motorista,v_usuario,v_dono),v_unidade);
  IF v_situacao<>'programada' OR p_saida IS NULL OR p_saida>UTC_TIMESTAMP(6) OR p_km IS NULL OR p_km<0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Saída, quilometragem ou estado da viagem inválido.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  SELECT fim INTO v_fim FROM reservas WHERE id=v_reserva AND situacao='ativa' FOR UPDATE;
  IF v_fim IS NULL OR p_saida>=v_fim THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reserva encerrada: revise o período antes de registrar a saída.'; END IF;
  -- Saída antecipada amplia o intervalo e revalida a disponibilidade sob lock.
  UPDATE reservas SET inicio=LEAST(inicio,p_saida) WHERE id=v_reserva;
  CALL sp_gravar_checklist(v_usuario,p_viagem,'saida',p_checklist,v_checklist);
  UPDATE viagens SET situacao='em_andamento',saida_real=p_saida,quilometragem_saida=p_km,
    saida_registrada_por=v_usuario,observacao_saida=p_observacao,versao=versao+1 WHERE id=p_viagem;
  UPDATE veiculos SET quilometragem_atual=GREATEST(quilometragem_atual,p_km),versao=versao+1 WHERE id=v_veiculo;
  CALL sp_auditar(p_vinculo,'saida_registrada','viagens',p_viagem,'Saída efetiva e checklist registrados.');
  COMMIT;
END$$

CREATE PROCEDURE sp_registrar_retorno(IN p_vinculo BIGINT UNSIGNED,IN p_viagem BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_retorno DATETIME(6),IN p_km DECIMAL(12,1),IN p_observacao TEXT,IN p_checklist JSON)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED;
  DECLARE v_veiculo BIGINT UNSIGNED; DECLARE v_reserva BIGINT UNSIGNED; DECLARE v_checklist BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_saida DATETIME(6); DECLARE v_km DECIMAL(12,1);
  DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT v.veiculo_id,v.reserva_id,v.versao,v.situacao,v.saida_real,v.quilometragem_saida,s.solicitante_id,s.unidade_id,v.motorista_id
  INTO v_veiculo,v_reserva,v_versao,v_situacao,v_saida,v_km,v_dono,v_unidade,v_motorista
  FROM viagens v JOIN solicitacao_revisoes r ON r.id=v.revisao_id JOIN solicitacoes s ON s.id=r.solicitacao_id
  WHERE v.id=p_viagem FOR UPDATE;
  IF v_veiculo IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem não encontrada ou versão alterada.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'viagens','registrar_retorno',IF(v_usuario=v_motorista,v_usuario,v_dono),v_unidade);
  IF v_situacao<>'em_andamento' OR p_retorno IS NULL OR p_retorno<=v_saida OR p_retorno>UTC_TIMESTAMP(6) OR p_km IS NULL OR p_km<v_km THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retorno, quilometragem ou estado da viagem inválido.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_gravar_checklist(v_usuario,p_viagem,'retorno',p_checklist,v_checklist);
  UPDATE viagens SET situacao='concluida',retorno_real=p_retorno,quilometragem_retorno=p_km,
    retorno_registrado_por=v_usuario,observacao_retorno=p_observacao,versao=versao+1 WHERE id=p_viagem;
  UPDATE reservas SET situacao='liberada',liberada_em=UTC_TIMESTAMP(6) WHERE id=v_reserva;
  UPDATE veiculos SET quilometragem_atual=GREATEST(quilometragem_atual,p_km),versao=versao+1 WHERE id=v_veiculo;
  CALL sp_auditar(p_vinculo,'retorno_registrado','viagens',p_viagem,'Retorno efetivo, checklist e liberação do veículo registrados.');
  COMMIT;
END$$

CREATE PROCEDURE sp_registrar_ocorrencia(IN p_vinculo BIGINT UNSIGNED,IN p_viagem BIGINT UNSIGNED,IN p_tipo VARCHAR(30),IN p_ocorrido DATETIME(6),IN p_descricao TEXT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_id BIGINT UNSIGNED;
  DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT s.solicitante_id,s.unidade_id,v.motorista_id INTO v_dono,v_unidade,v_motorista FROM viagens v
  JOIN solicitacao_revisoes r ON r.id=v.revisao_id JOIN solicitacoes s ON s.id=r.solicitacao_id WHERE v.id=p_viagem FOR UPDATE;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'viagens','registrar_ocorrencia',IF(v_usuario=v_motorista,v_usuario,v_dono),v_unidade);
  IF p_descricao IS NULL OR CHAR_LENGTH(TRIM(p_descricao))=0 OR p_ocorrido IS NULL OR p_ocorrido>UTC_TIMESTAMP(6) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Ocorrência precisa de data válida e descrição.'; END IF;
  INSERT INTO viagem_ocorrencias(viagem_id,registrado_por,tipo,ocorrido_em,descricao) VALUES(p_viagem,v_usuario,p_tipo,p_ocorrido,p_descricao);
  SET v_id=LAST_INSERT_ID();
  CALL sp_auditar(p_vinculo,'ocorrencia_registrada','viagem_ocorrencias',v_id,LEFT(p_descricao,1000));
  COMMIT;
  SELECT v_id AS ocorrencia_id;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_atribuir_responsavel_multa(IN p_vinculo BIGINT UNSIGNED,IN p_multa BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_viagem BIGINT UNSIGNED,IN p_responsavel BIGINT UNSIGNED,IN p_justificativa TEXT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_veiculo BIGINT UNSIGNED; DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_estado VARCHAR(40); DECLARE v_numero INT; DECLARE v_id BIGINT UNSIGNED; DECLARE v_pendencias INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT unidade_id,veiculo_id,versao,situacao INTO v_unidade,v_veiculo,v_versao,v_estado FROM multas WHERE id=p_multa FOR UPDATE;
  IF v_veiculo IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multa não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'multas','atribuir_responsavel',NULL,v_unidade);
  IF v_estado NOT IN ('sem_responsavel','aguardando_comprovante','contestada') OR p_justificativa IS NULL OR CHAR_LENGTH(TRIM(p_justificativa))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Atribuição inválida ou sem justificativa.'; END IF;
  SELECT COUNT(*) INTO v_pendencias FROM multa_comprovantes WHERE multa_id=p_multa;
  IF v_pendencias>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Responsabilidade com comprovantes preservados não pode ser substituída por este fluxo.'; END IF;
  SELECT motorista_id INTO v_motorista FROM viagens WHERE id=p_viagem AND veiculo_id=v_veiculo;
  IF v_motorista IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem não corresponde ao veículo da multa.'; END IF;
  SELECT COALESCE(MAX(numero),0)+1 INTO v_numero FROM multa_responsabilidades WHERE multa_id=p_multa;
  INSERT INTO multa_responsabilidades(multa_id,veiculo_id,viagem_id,motorista_id,responsavel_id,numero,confirmado_por_vinculo_id,justificativa)
  VALUES(p_multa,v_veiculo,p_viagem,v_motorista,p_responsavel,v_numero,p_vinculo,p_justificativa);
  SET v_id=LAST_INSERT_ID();
  UPDATE multas SET responsabilidade_atual_id=v_id,situacao='aguardando_comprovante',versao=versao+1 WHERE id=p_multa;
  INSERT INTO multa_eventos(multa_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(p_multa,p_vinculo,'responsabilidade_confirmada',v_estado,'aguardando_comprovante',p_justificativa);
  CALL sp_auditar(p_vinculo,'responsabilidade_confirmada','multas',p_multa,p_justificativa);
  CALL sp_notificar(CONCAT('multa:',p_multa,':responsabilidade:',v_id),'multa_atribuida','Pendência de multa','A responsabilidade foi conferida. Consulte o registro.',CONCAT('/financeiro/multas/',p_multa),NULL,p_multa,p_responsavel);
  COMMIT;
END$$

CREATE PROCEDURE sp_enviar_comprovante_multa(IN p_vinculo BIGINT UNSIGNED,IN p_multa BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_arquivo BIGINT UNSIGNED,IN p_valor DECIMAL(13,2),IN p_pagamento DATETIME(6),IN p_observacao TEXT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_responsabilidade BIGINT UNSIGNED; DECLARE v_responsavel BIGINT UNSIGNED;
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_versao BIGINT UNSIGNED; DECLARE v_estado VARCHAR(40); DECLARE v_numero INT; DECLARE v_id BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT unidade_id,responsabilidade_atual_id,versao,situacao INTO v_unidade,v_responsabilidade,v_versao,v_estado FROM multas WHERE id=p_multa FOR UPDATE;
  IF v_responsabilidade IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multa sem responsável ou versão alterada.'; END IF;
  SELECT responsavel_id INTO v_responsavel FROM multa_responsabilidades WHERE id=v_responsabilidade;
  CALL sp_exigir_permissao(p_vinculo,'multas','enviar_comprovante',v_responsavel,v_unidade);
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_validar_arquivo(v_usuario,p_arquivo);
  IF v_estado<>'aguardando_comprovante' OR p_valor IS NULL OR p_valor<=0 OR p_pagamento IS NULL OR p_pagamento>UTC_TIMESTAMP(6) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multa, valor ou data não permite envio de comprovante.'; END IF;
  SELECT COALESCE(MAX(numero),0)+1 INTO v_numero FROM multa_comprovantes WHERE multa_id=p_multa;
  INSERT INTO multa_comprovantes(multa_id,numero,responsabilidade_id,arquivo_id,enviado_por_vinculo_id,valor_declarado,pagamento_declarado_em,observacao)
  VALUES(p_multa,v_numero,v_responsabilidade,p_arquivo,p_vinculo,p_valor,p_pagamento,p_observacao);
  SET v_id=LAST_INSERT_ID();
  UPDATE multas SET situacao='em_conferencia',versao=versao+1 WHERE id=p_multa;
  INSERT INTO multa_eventos(multa_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(p_multa,p_vinculo,'comprovante_enviado',v_estado,'em_conferencia',p_observacao);
  CALL sp_auditar(p_vinculo,'comprovante_enviado','multas',p_multa,CONCAT('Versão ',v_numero,' enviada, sem quitar automaticamente.'));
  COMMIT;
  SELECT v_id AS comprovante_id;
END$$

CREATE PROCEDURE sp_conferir_comprovante_multa(IN p_vinculo BIGINT UNSIGNED,IN p_comprovante BIGINT UNSIGNED,IN p_resultado VARCHAR(30),IN p_motivo TEXT,IN p_valor DECIMAL(13,2),IN p_pago_em DATETIME(6))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_multa BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_responsavel BIGINT UNSIGNED;
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_autor BIGINT UNSIGNED; DECLARE v_numero INT; DECLARE v_ultimo INT;
  DECLARE v_estado VARCHAR(40); DECLARE v_novo VARCHAR(40); DECLARE v_conferencia BIGINT UNSIGNED; DECLARE v_valor_multa DECIMAL(13,2);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT MAX(multa_id) INTO v_multa FROM multa_comprovantes WHERE id=p_comprovante;
  IF v_multa IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Comprovante não encontrado.'; END IF;
  SELECT unidade_id,situacao,valor INTO v_unidade,v_estado,v_valor_multa FROM multas WHERE id=v_multa FOR UPDATE;
  SELECT c.numero,up.usuario_id,r.responsavel_id INTO v_numero,v_autor,v_responsavel FROM multa_comprovantes c
  JOIN usuario_perfis up ON up.id=c.enviado_por_vinculo_id JOIN multa_responsabilidades r ON r.id=c.responsabilidade_id WHERE c.id=p_comprovante;
  CALL sp_exigir_permissao(p_vinculo,'multas','validar_pagamento',v_responsavel,v_unidade);
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  IF v_usuario=v_autor OR v_usuario=v_responsavel THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conferência de pagamento próprio exige outro usuário financeiro.'; END IF;
  SELECT MAX(numero) INTO v_ultimo FROM multa_comprovantes WHERE multa_id=v_multa;
  IF v_estado<>'em_conferencia' OR v_numero<>v_ultimo OR p_resultado IS NULL OR p_resultado NOT IN ('aceito','correcao_solicitada') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Somente a versão mais recente pendente pode ser conferida.'; END IF;
  IF p_resultado='aceito' AND (p_valor IS NULL OR p_valor<=0 OR p_pago_em IS NULL OR p_pago_em>UTC_TIMESTAMP(6)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aceite exige valor positivo e data real de pagamento.'; END IF;
  IF (p_resultado='correcao_solicitada' OR (p_resultado='aceito' AND p_valor<>v_valor_multa)) AND (p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe o motivo da correção ou diferença de valor.'; END IF;
  INSERT INTO multa_conferencias(multa_id,comprovante_id,conferido_por_vinculo_id,resultado,motivo,valor_confirmado,pagamento_confirmado_em)
  VALUES(v_multa,p_comprovante,p_vinculo,p_resultado,p_motivo,CASE WHEN p_resultado='aceito' THEN p_valor ELSE NULL END,CASE WHEN p_resultado='aceito' THEN p_pago_em ELSE NULL END);
  SET v_conferencia=LAST_INSERT_ID();
  SET v_novo=CASE p_resultado WHEN 'aceito' THEN 'quitada' ELSE 'aguardando_comprovante' END;
  IF p_resultado='aceito' THEN INSERT INTO pagamentos_multa(multa_id,conferencia_id,valor,pago_em) VALUES(v_multa,v_conferencia,p_valor,p_pago_em); END IF;
  UPDATE multas SET situacao=v_novo,versao=versao+1 WHERE id=v_multa;
  INSERT INTO multa_eventos(multa_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo)
  VALUES(v_multa,p_vinculo,CONCAT('comprovante_',p_resultado),v_estado,v_novo,p_motivo);
  CALL sp_auditar(p_vinculo,CONCAT('comprovante_',p_resultado),'multas',v_multa,COALESCE(p_motivo,'Comprovante aceito e quitação registrada.'));
  CALL sp_notificar(CONCAT('multa:',v_multa,':conferencia:',v_conferencia),'comprovante_conferido','Comprovante conferido',CASE WHEN p_resultado='aceito' THEN 'Pagamento confirmado pelo financeiro.' ELSE LEFT(p_motivo,1000) END,CONCAT('/financeiro/multas/',v_multa),NULL,v_multa,v_responsavel);
  COMMIT;
END$$

CREATE PROCEDURE sp_alterar_situacao_multa(IN p_vinculo BIGINT UNSIGNED,IN p_multa BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_situacao VARCHAR(30),IN p_motivo TEXT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_dono BIGINT UNSIGNED; DECLARE v_estado VARCHAR(40);
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_acao VARCHAR(40); DECLARE v_responsabilidade BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT unidade_id,responsabilidade_atual_id,situacao,versao INTO v_unidade,v_responsabilidade,v_estado,v_versao FROM multas WHERE id=p_multa FOR UPDATE;
  IF v_unidade IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multa não encontrada ou versão alterada.'; END IF;
  SELECT MAX(responsavel_id) INTO v_dono FROM multa_responsabilidades WHERE id=v_responsabilidade;
  IF p_situacao IS NULL OR p_situacao NOT IN ('contestada','cancelada') OR p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 OR v_estado IN ('quitada','cancelada','em_conferencia') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Contestação/cancelamento inválido; confira primeiro os comprovantes pendentes.'; END IF;
  SET v_acao=CASE p_situacao WHEN 'contestada' THEN 'contestar' ELSE 'cancelar' END;
  CALL sp_exigir_permissao(p_vinculo,'multas',v_acao,v_dono,v_unidade);
  UPDATE multas SET situacao=p_situacao,versao=versao+1 WHERE id=p_multa;
  INSERT INTO multa_eventos(multa_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo) VALUES(p_multa,p_vinculo,p_situacao,v_estado,p_situacao,p_motivo);
  CALL sp_auditar(p_vinculo,CONCAT('multa_',p_situacao),'multas',p_multa,p_motivo);
  COMMIT;
END$$

CREATE PROCEDURE sp_pagar_despesa(IN p_vinculo BIGINT UNSIGNED,IN p_despesa BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_arquivo BIGINT UNSIGNED,IN p_pago_em DATETIME(6))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_autor BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED;
  DECLARE v_versao BIGINT UNSIGNED; DECLARE v_estado VARCHAR(30); DECLARE v_valor DECIMAL(13,2);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT unidade_id,criado_por,versao,situacao,valor INTO v_unidade,v_autor,v_versao,v_estado,v_valor FROM despesas WHERE id=p_despesa FOR UPDATE;
  IF v_unidade IS NULL OR p_versao IS NULL OR v_versao<>p_versao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Despesa não encontrada ou versão alterada.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'despesas','validar_pagamento',v_autor,v_unidade);
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  CALL sp_validar_arquivo(v_usuario,p_arquivo);
  IF v_estado<>'aprovada' OR p_pago_em IS NULL OR p_pago_em>UTC_TIMESTAMP(6) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pagamento exige despesa aprovada e data válida.'; END IF;
  INSERT INTO pagamentos_despesa(despesa_id,valor,pago_em,comprovante_arquivo_id,confirmado_por_vinculo_id) VALUES(p_despesa,v_valor,p_pago_em,p_arquivo,p_vinculo);
  UPDATE despesas SET situacao='paga',versao=versao+1 WHERE id=p_despesa;
  INSERT INTO despesa_eventos(despesa_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova) VALUES(p_despesa,p_vinculo,'pagamento_confirmado',v_estado,'paga');
  CALL sp_auditar(p_vinculo,'despesa_paga','despesas',p_despesa,'Pagamento da despesa confirmado.');
  COMMIT;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_criar_usuario(IN p_ator_vinculo BIGINT UNSIGNED,IN p_unidade BIGINT UNSIGNED,IN p_identificador VARCHAR(100),IN p_nome VARCHAR(150),IN p_email VARCHAR(254),IN p_hash VARCHAR(255))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_id BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  CALL sp_exigir_permissao(p_ator_vinculo,'usuarios','criar',NULL,p_unidade);
  IF p_nome IS NULL OR CHAR_LENGTH(TRIM(p_nome))=0 OR p_identificador IS NULL OR CHAR_LENGTH(TRIM(p_identificador))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nome e identificador são obrigatórios.'; END IF;
  INSERT INTO usuarios(unidade_id,identificador,nome,email,senha_hash,deve_trocar_senha) VALUES(p_unidade,p_identificador,p_nome,p_email,p_hash,1);
  SET v_id=LAST_INSERT_ID();
  INSERT INTO preferencias_usuario(usuario_id) VALUES(v_id);
  CALL sp_auditar(p_ator_vinculo,'usuario_criado','usuarios',v_id,'Identidade criada; atribua vínculo para habilitar acesso.');
  COMMIT;
  SELECT v_id AS usuario_id;
END$$

CREATE PROCEDURE sp_vincular_perfil(IN p_ator_vinculo BIGINT UNSIGNED,IN p_usuario BIGINT UNSIGNED,IN p_perfil BIGINT UNSIGNED,IN p_unidade BIGINT UNSIGNED,IN p_inicio DATETIME(6),IN p_fim DATETIME(6),IN p_justificativa VARCHAR(1000))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_ator BIGINT UNSIGNED; DECLARE v_id BIGINT UNSIGNED; DECLARE v_max_atribuicao INT; DECLARE v_max_perfil INT; DECLARE v_nao_delegavel INT; DECLARE v_lock INT; DECLARE v_ativo INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  CALL sp_exigir_permissao(p_ator_vinculo,'usuarios','delegar',p_usuario,p_unidade);
  SELECT usuario_id INTO v_ator FROM usuario_perfis WHERE id=p_ator_vinculo;
  IF v_ator=p_usuario THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Atribuição de perfil ao próprio usuário exige outro administrador autorizado.'; END IF;
  SELECT COUNT(*) INTO v_ativo FROM perfis WHERE id=p_perfil AND ativo=1;
  IF v_ativo=0 OR p_inicio IS NULL OR (p_fim IS NOT NULL AND p_fim<=p_inicio) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil inativo ou período de vínculo inválido.'; END IF;
  IF p_justificativa IS NULL OR CHAR_LENGTH(TRIM(p_justificativa))<5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe a justificativa para atribuir o perfil.'; END IF;
  SELECT MAX(nivel_alcance) INTO v_max_atribuicao FROM vw_permissoes_efetivas WHERE vinculo_id=p_ator_vinculo AND modulo_codigo='usuarios' AND acao_codigo='delegar';
  SELECT MAX(CASE p.alcance WHEN 'proprios' THEN 1 WHEN 'unidade' THEN 2 ELSE 3 END) INTO v_max_perfil
  FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id WHERE pp.perfil_id=p_perfil;
  IF COALESCE(v_max_perfil,0)>COALESCE(v_max_atribuicao,0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil pretendido excede o alcance autorizado para atribuição.'; END IF;
  SELECT COUNT(*) INTO v_nao_delegavel FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id WHERE pp.perfil_id=p_perfil
  AND NOT EXISTS(SELECT 1 FROM vw_permissoes_efetivas pe WHERE pe.vinculo_id=p_ator_vinculo AND pe.modulo_codigo=p.modulo_codigo AND pe.acao_codigo=p.acao_codigo AND pe.delegavel=1
  AND pe.nivel_alcance>=CASE p.alcance WHEN 'proprios' THEN 1 WHEN 'unidade' THEN 2 ELSE 3 END);
  IF v_nao_delegavel>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil pretendido contém ações que este vínculo não pode delegar.'; END IF;
  INSERT INTO usuario_perfis(usuario_id,perfil_id,unidade_id,vigente_desde,vigente_ate,concedido_por) VALUES(p_usuario,p_perfil,p_unidade,p_inicio,p_fim,v_ator);
  SET v_id=LAST_INSERT_ID();
  CALL sp_auditar(p_ator_vinculo,'perfil_atribuido','usuario_perfis',v_id,p_justificativa);
  COMMIT;
  SELECT v_id AS vinculo_id;
END$$

CREATE PROCEDURE sp_desativar_vinculo(IN p_ator_vinculo BIGINT UNSIGNED,IN p_vinculo BIGINT UNSIGNED,IN p_motivo VARCHAR(500))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_ator BIGINT UNSIGNED; DECLARE v_lock INT; DECLARE v_ativo INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  SELECT usuario_id,unidade_id,ativo INTO v_usuario,v_unidade,v_ativo FROM usuario_perfis WHERE id=p_vinculo FOR UPDATE;
  IF v_ativo IS NULL OR v_ativo<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Vínculo inexistente ou já revogado.'; END IF;
  CALL sp_exigir_permissao(p_ator_vinculo,'usuarios','editar',v_usuario,v_unidade);
  SELECT usuario_id INTO v_ator FROM usuario_perfis WHERE id=p_ator_vinculo;
  IF p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe o motivo da revogação.'; END IF;
  UPDATE usuario_perfis SET ativo=0,revogado_por=v_ator,motivo_revogacao=p_motivo WHERE id=p_vinculo;
  UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento='revogacao' WHERE vinculo_ativo_id=p_vinculo AND encerrada_em IS NULL;
  CALL sp_auditar(p_ator_vinculo,'vinculo_revogado','usuario_perfis',p_vinculo,p_motivo);
  COMMIT;
END$$

CREATE PROCEDURE sp_duplicar_perfil(IN p_ator_vinculo BIGINT UNSIGNED,IN p_origem BIGINT UNSIGNED,IN p_codigo VARCHAR(60),IN p_nome VARCHAR(100),IN p_justificativa VARCHAR(1000))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_nao_delegavel INT; DECLARE v_id BIGINT UNSIGNED; DECLARE v_lock INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  CALL sp_exigir_permissao(p_ator_vinculo,'perfis','criar',NULL,NULL);
  IF p_justificativa IS NULL OR CHAR_LENGTH(TRIM(p_justificativa))<5 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe a justificativa para duplicar o perfil.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_ator_vinculo;
  SELECT COUNT(*) INTO v_nao_delegavel FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id WHERE pp.perfil_id=p_origem
  AND NOT EXISTS(SELECT 1 FROM vw_permissoes_efetivas pe WHERE pe.vinculo_id=p_ator_vinculo AND pe.modulo_codigo=p.modulo_codigo AND pe.acao_codigo=p.acao_codigo AND pe.delegavel=1
  AND pe.nivel_alcance>=CASE p.alcance WHEN 'proprios' THEN 1 WHEN 'unidade' THEN 2 ELSE 3 END);
  IF v_nao_delegavel>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil de origem contém ações que este vínculo não pode delegar.'; END IF;
  INSERT INTO perfis(codigo,nome,descricao,perfil_origem_id,criado_por) SELECT p_codigo,p_nome,descricao,id,v_usuario FROM perfis WHERE id=p_origem;
  IF ROW_COUNT()<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil de origem não encontrado.'; END IF;
  SET v_id=LAST_INSERT_ID();
  INSERT INTO perfil_permissoes(perfil_id,permissao_id,delegavel,concedido_por) SELECT v_id,permissao_id,delegavel,v_usuario FROM perfil_permissoes WHERE perfil_id=p_origem;
  CALL sp_auditar(p_ator_vinculo,'perfil_duplicado','perfis',v_id,p_justificativa);
  COMMIT;
  SELECT v_id AS perfil_id;
END$$

CREATE PROCEDURE sp_criar_sessao(IN p_usuario BIGINT UNSIGNED,IN p_token_hash VARBINARY(32),IN p_ip VARBINARY(16),IN p_agente VARCHAR(500))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_ok INT; DECLARE v_minutos INT; DECLARE v_id BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  -- Chamar SOMENTE depois de password_verify e autenticação institucional no backend.
  START TRANSACTION;
  SELECT COUNT(*) INTO v_ok FROM vw_vinculos_ativos WHERE usuario_id=p_usuario;
  IF v_ok=0 OR p_token_hash IS NULL OR OCTET_LENGTH(p_token_hash)<>32 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Usuário sem acesso vigente ou token inválido.'; END IF;
  SELECT sessao_inatividade_minutos INTO v_minutos FROM configuracao_sistema WHERE id=1;
  INSERT INTO sessoes(usuario_id,token_hash,expira_em,ip,agente_usuario) VALUES(p_usuario,p_token_hash,TIMESTAMPADD(MINUTE,v_minutos,UTC_TIMESTAMP(6)),p_ip,p_agente);
  SET v_id=LAST_INSERT_ID();
  INSERT INTO auditoria(ator_usuario_id,sessao_id,evento,entidade,entidade_id,descricao,ip) VALUES(p_usuario,v_id,'login','sessoes',v_id,'Autenticação verificada pelo backend.',p_ip);
  COMMIT;
  SELECT v_id AS sessao_id;
END$$

CREATE PROCEDURE sp_trocar_perfil_sessao(IN p_sessao BIGINT UNSIGNED,IN p_usuario BIGINT UNSIGNED,IN p_vinculo BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_ok INT; DECLARE v_anterior BIGINT UNSIGNED; DECLARE v_expira DATETIME(6); DECLARE v_encerrada DATETIME(6);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT vinculo_ativo_id,expira_em,encerrada_em INTO v_anterior,v_expira,v_encerrada FROM sessoes WHERE id=p_sessao AND usuario_id=p_usuario FOR UPDATE;
  SELECT COUNT(*) INTO v_ok FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo AND usuario_id=p_usuario;
  IF v_expira IS NULL OR v_expira<=UTC_TIMESTAMP(6) OR v_encerrada IS NOT NULL OR v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão ou perfil expirado/inválido.'; END IF;
  UPDATE sessoes SET vinculo_ativo_id=p_vinculo WHERE id=p_sessao;
  IF NOT(v_anterior<=>p_vinculo) THEN
    INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,sessao_id,evento,entidade,entidade_id,antes,depois)
    VALUES(p_usuario,p_vinculo,p_sessao,'troca_perfil','sessoes',p_sessao,JSON_OBJECT('vinculo_id',v_anterior),JSON_OBJECT('vinculo_id',p_vinculo));
  END IF;
  COMMIT;
END$$

CREATE PROCEDURE sp_registrar_atividade(IN p_sessao BIGINT UNSIGNED,IN p_usuario BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_ok INT; DECLARE v_minutos INT; DECLARE v_expira DATETIME(6); DECLARE v_encerrada DATETIME(6); DECLARE v_vinculo BIGINT UNSIGNED; DECLARE v_motivo VARCHAR(30);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT expira_em,encerrada_em,vinculo_ativo_id INTO v_expira,v_encerrada,v_vinculo FROM sessoes WHERE id=p_sessao AND usuario_id=p_usuario FOR UPDATE;
  IF v_expira IS NULL OR v_encerrada IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão não encontrada ou encerrada.'; END IF;
  SELECT COUNT(*) INTO v_ok FROM vw_vinculos_ativos WHERE usuario_id=p_usuario AND (v_vinculo IS NULL OR vinculo_id=v_vinculo);
  IF v_expira<=UTC_TIMESTAMP(6) OR v_ok=0 THEN
    SET v_motivo=CASE WHEN v_expira<=UTC_TIMESTAMP(6) THEN 'inatividade' ELSE 'revogacao' END;
    UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento=v_motivo WHERE id=p_sessao;
    INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,sessao_id,evento,entidade,entidade_id,descricao) VALUES(p_usuario,v_vinculo,p_sessao,'sessao_expirada','sessoes',p_sessao,v_motivo);
    COMMIT;
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão expirada ou acesso revogado.';
  END IF;
  SELECT sessao_inatividade_minutos INTO v_minutos FROM configuracao_sistema WHERE id=1;
  UPDATE sessoes SET ultima_atividade_em=UTC_TIMESTAMP(6),expira_em=TIMESTAMPADD(MINUTE,v_minutos,UTC_TIMESTAMP(6)) WHERE id=p_sessao;
  INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,sessao_id,evento,entidade,entidade_id) VALUES(p_usuario,v_vinculo,p_sessao,'atividade','sessoes',p_sessao);
  COMMIT;
END$$

CREATE PROCEDURE sp_expirar_sessao(IN p_sessao BIGINT UNSIGNED,IN p_usuario BIGINT UNSIGNED,IN p_limite_minutos SMALLINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_inicio DATETIME(6); DECLARE v_vinculo BIGINT UNSIGNED; DECLARE v_fim DATETIME(6);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT criado_em,vinculo_ativo_id,encerrada_em INTO v_inicio,v_vinculo,v_fim
  FROM sessoes WHERE id=p_sessao AND usuario_id=p_usuario FOR UPDATE;
  IF v_inicio IS NULL OR v_fim IS NOT NULL OR p_limite_minutos NOT BETWEEN 60 AND 1440 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão ou prazo absoluto inválido.';
  END IF;
  IF v_inicio>TIMESTAMPADD(MINUTE,-p_limite_minutos,UTC_TIMESTAMP(6)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão ainda não atingiu o prazo absoluto.';
  END IF;
  UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento='prazo' WHERE id=p_sessao;
  INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,sessao_id,evento,entidade,entidade_id,descricao)
  VALUES(p_usuario,v_vinculo,p_sessao,'sessao_expirada','sessoes',p_sessao,'prazo_absoluto');
  COMMIT;
END$$

CREATE PROCEDURE sp_encerrar_sessao(IN p_sessao BIGINT UNSIGNED,IN p_usuario BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_vinculo BIGINT UNSIGNED; DECLARE v_fim DATETIME(6); DECLARE v_id BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT id,vinculo_ativo_id,encerrada_em INTO v_id,v_vinculo,v_fim FROM sessoes WHERE id=p_sessao AND usuario_id=p_usuario FOR UPDATE;
  IF v_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Sessão não encontrada.'; END IF;
  IF v_fim IS NULL THEN
    UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento='logout' WHERE id=p_sessao;
    INSERT INTO auditoria(ator_usuario_id,ator_vinculo_id,sessao_id,evento,entidade,entidade_id) VALUES(p_usuario,v_vinculo,p_sessao,'logout','sessoes',p_sessao);
  END IF;
  COMMIT;
END$$

CREATE PROCEDURE sp_consumir_recuperacao(IN p_token_hash VARBINARY(32),IN p_novo_hash VARCHAR(255))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_id BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_usuario_bloqueado BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT usuario_id INTO v_usuario FROM recuperacoes_senha WHERE token_hash=p_token_hash;
  IF v_usuario IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Token de recuperação inválido, vencido ou já utilizado.'; END IF;
  SELECT id INTO v_usuario_bloqueado FROM usuarios WHERE id=v_usuario FOR UPDATE;
  SELECT id INTO v_id FROM recuperacoes_senha WHERE token_hash=p_token_hash AND usuario_id=v_usuario
    AND usado_em IS NULL AND invalidado_em IS NULL AND expira_em>UTC_TIMESTAMP(6) FOR UPDATE;
  IF v_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Token de recuperação inválido, vencido ou já utilizado.'; END IF;
  UPDATE usuarios SET senha_hash=p_novo_hash,senha_alterada_em=UTC_TIMESTAMP(6),deve_trocar_senha=0 WHERE id=v_usuario;
  UPDATE recuperacoes_senha SET usado_em=UTC_TIMESTAMP(6) WHERE id=v_id;
  UPDATE recuperacoes_senha SET invalidado_em=UTC_TIMESTAMP(6) WHERE usuario_id=v_usuario AND id<>v_id AND usado_em IS NULL AND invalidado_em IS NULL;
  UPDATE sessoes SET encerrada_em=UTC_TIMESTAMP(6),motivo_encerramento='troca_senha' WHERE usuario_id=v_usuario AND encerrada_em IS NULL;
  INSERT INTO auditoria(ator_usuario_id,evento,entidade,entidade_id,descricao) VALUES(v_usuario,'senha_recuperada','usuarios',v_usuario,'Token consumido uma única vez; sessões anteriores encerradas.');
  COMMIT;
END$$
DELIMITER ;

DELIMITER $$
CREATE PROCEDURE sp_abrir_chamado(IN p_vinculo BIGINT UNSIGNED,IN p_categoria SMALLINT UNSIGNED,IN p_assunto VARCHAR(200),IN p_descricao TEXT,IN p_pagina VARCHAR(255))
SQL SECURITY INVOKER
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_perfil BIGINT UNSIGNED; DECLARE v_id BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT usuario_id,unidade_id,perfil_id INTO v_usuario,v_unidade,v_perfil FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'chamados','criar',v_usuario,v_unidade);
  IF p_assunto IS NULL OR CHAR_LENGTH(TRIM(p_assunto))=0 OR p_descricao IS NULL OR CHAR_LENGTH(TRIM(p_descricao))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Assunto e descrição são obrigatórios.'; END IF;
  INSERT INTO chamados(protocolo,solicitante_id,unidade_id,perfil_contexto_id,categoria_id,assunto,descricao,pagina_contexto)
  VALUES(CONCAT('CHA-',REPLACE(UUID(),'-','')),v_usuario,v_unidade,v_perfil,p_categoria,p_assunto,p_descricao,SUBSTRING_INDEX(p_pagina,'?',1));
  SET v_id=LAST_INSERT_ID();
  UPDATE chamados SET protocolo=CONCAT('CHA-',LPAD(v_id,8,'0')) WHERE id=v_id;
  INSERT INTO chamado_eventos(chamado_id,ator_vinculo_id,tipo,situacao_nova) VALUES(v_id,p_vinculo,'aberto','aberto');
  CALL sp_auditar(p_vinculo,'chamado_aberto','chamados',v_id,'Chamado aberto; contexto contém somente caminho da página.');
  COMMIT;
  SELECT v_id AS chamado_id;
END$$

CREATE PROCEDURE sp_responder_chamado(IN p_vinculo BIGINT UNSIGNED,IN p_chamado BIGINT UNSIGNED,IN p_mensagem TEXT,IN p_interna TINYINT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_id BIGINT UNSIGNED; DECLARE v_usuario BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,situacao INTO v_dono,v_unidade,v_situacao FROM chamados WHERE id=p_chamado FOR UPDATE;
  CALL sp_exigir_permissao(p_vinculo,'chamados','responder',v_dono,v_unidade);
  IF p_interna=1 THEN CALL sp_exigir_permissao(p_vinculo,'chamados','atender',v_dono,v_unidade); END IF;
  IF v_situacao IS NULL OR v_situacao='resolvido' OR p_mensagem IS NULL OR CHAR_LENGTH(TRIM(p_mensagem))=0 OR p_interna IS NULL OR p_interna NOT IN (0,1) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Resposta inválida ou chamado já resolvido.'; END IF;
  INSERT INTO chamado_mensagens(chamado_id,autor_vinculo_id,mensagem,interna) VALUES(p_chamado,p_vinculo,p_mensagem,p_interna);
  SET v_id=LAST_INSERT_ID();
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  IF v_usuario=v_dono AND v_situacao='aguardando_solicitante' THEN
    UPDATE chamados SET situacao='em_atendimento',versao=versao+1 WHERE id=p_chamado;
    INSERT INTO chamado_eventos(chamado_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova) VALUES(p_chamado,p_vinculo,'solicitante_respondeu',v_situacao,'em_atendimento');
  END IF;
  CALL sp_auditar(p_vinculo,'chamado_respondido','chamados',p_chamado,'Mensagem acrescentada à conversa.');
  COMMIT;
  SELECT v_id AS mensagem_id;
END$$

CREATE PROCEDURE sp_atender_chamado(IN p_vinculo BIGINT UNSIGNED,IN p_chamado BIGINT UNSIGNED,IN p_versao BIGINT UNSIGNED,IN p_situacao VARCHAR(40),IN p_atendente BIGINT UNSIGNED,IN p_motivo TEXT)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_situacao VARCHAR(40); DECLARE v_versao BIGINT UNSIGNED; DECLARE v_ok INT;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitante_id,unidade_id,situacao,versao INTO v_dono,v_unidade,v_situacao,v_versao FROM chamados WHERE id=p_chamado FOR UPDATE;
  IF v_dono IS NULL OR p_versao IS NULL OR p_versao<>v_versao OR p_situacao IS NULL OR p_situacao NOT IN ('em_atendimento','aguardando_solicitante','resolvido') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chamado, versão ou situação inválida.'; END IF;
  CALL sp_exigir_permissao(p_vinculo,'chamados',CASE WHEN p_situacao='resolvido' THEN 'resolver' ELSE 'atender' END,v_dono,v_unidade);
  IF p_motivo IS NULL OR CHAR_LENGTH(TRIM(p_motivo))=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Informe o motivo do atendimento ou resolução.'; END IF;
  IF p_atendente IS NOT NULL THEN
    SELECT COUNT(*) INTO v_ok FROM vw_permissoes_efetivas WHERE usuario_id=p_atendente AND modulo_codigo='chamados' AND acao_codigo='atender' AND (alcance='orgao' OR (alcance='unidade' AND unidade_id=v_unidade));
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Atendente sem vínculo vigente para esta fila.'; END IF;
  END IF;
  UPDATE chamados SET situacao=p_situacao,atribuido_a=COALESCE(p_atendente,atribuido_a),resolvido_em=CASE WHEN p_situacao='resolvido' THEN UTC_TIMESTAMP(6) ELSE NULL END,versao=versao+1 WHERE id=p_chamado;
  INSERT INTO chamado_eventos(chamado_id,ator_vinculo_id,tipo,situacao_anterior,situacao_nova,motivo) VALUES(p_chamado,p_vinculo,'situacao_alterada',v_situacao,p_situacao,p_motivo);
  CALL sp_auditar(p_vinculo,'chamado_atendido','chamados',p_chamado,LEFT(p_motivo,1000));
  COMMIT;
END$$
DELIMITER ;

-- Nenhum caminho ou nome de campo do cliente é executado como SQL.
DELIMITER $$
CREATE PROCEDURE sp_contexto_relatorio(IN p_modulo VARCHAR(40),IN p_id BIGINT UNSIGNED,OUT p_dono BIGINT UNSIGNED,OUT p_unidade BIGINT UNSIGNED,OUT p_data DATETIME(6),OUT p_veiculo BIGINT UNSIGNED,OUT p_dados JSON)
SQL SECURITY INVOKER
BEGIN
  SET p_dono=NULL; SET p_unidade=NULL; SET p_data=NULL; SET p_veiculo=NULL; SET p_dados=NULL;
  CASE p_modulo
    WHEN 'solicitacoes' THEN
      SELECT solicitante_id,unidade_id,COALESCE(saida_prevista,criado_em),veiculo_pretendido_id,
      JSON_OBJECT('protocolo',protocolo,'situacao',situacao,'finalidade',finalidade,'origem',origem,'destino',destino,'saida_prevista',saida_prevista,'retorno_previsto',retorno_previsto,'passageiros',quantidade_passageiros,'unidade',unidade)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM vw_solicitacoes_atuais WHERE id=p_id;
    WHEN 'viagens' THEN
      SELECT solicitante_id,unidade_id,COALESCE(saida_real,saida_prevista),veiculo_id,
      JSON_OBJECT('protocolo',protocolo,'situacao',situacao,'veiculo',veiculo,'motorista',motorista,'saida_real',saida_real,'retorno_real',retorno_real,'quilometragem_saida',quilometragem_saida,'quilometragem_retorno',quilometragem_retorno,'distancia',distancia_real_km)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM vw_viagens_detalhadas WHERE id=p_id;
    WHEN 'frota' THEN
      SELECT NULL,unidade_id,criado_em,id,JSON_OBJECT('placa',placa,'nome',nome,'categoria',categoria,'capacidade',capacidade,'unidade',unidade,'situacao',situacao_operacional,'quilometragem',quilometragem_atual)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM vw_frota WHERE id=p_id;
    WHEN 'rastreamento' THEN
      SELECT NULL,v.unidade_id,p.capturado_em,p.veiculo_id,JSON_OBJECT('placa',v.placa,'capturado_em',p.capturado_em,'latitude',p.latitude,'longitude',p.longitude,'velocidade',p.velocidade_kmh,'fonte','rastreador')
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM posicoes_rastreamento p JOIN veiculos v ON v.id=p.veiculo_id WHERE p.id=p_id;
    WHEN 'despesas' THEN
      SELECT criado_por,unidade_id,data_despesa,veiculo_id,JSON_OBJECT('protocolo',protocolo,'veiculo',veiculo,'categoria',categoria,'data_despesa',data_despesa,'descricao',descricao,'valor',valor,'situacao',situacao)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM vw_despesas_detalhadas WHERE id=p_id;
    WHEN 'rastreamento_manual' THEN
      SELECT s.solicitante_id,s.unidade_id,p.ocorrido_em,v.veiculo_id,JSON_OBJECT('placa',ve.placa,'capturado_em',p.ocorrido_em,'latitude',p.latitude,'longitude',p.longitude,'velocidade',NULL,'fonte','manual')
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM posicoes_manuais p JOIN viagens v ON v.id=p.viagem_id
      JOIN veiculos ve ON ve.id=v.veiculo_id JOIN solicitacao_revisoes r ON r.id=v.revisao_id JOIN solicitacoes s ON s.id=r.solicitacao_id WHERE p.id=p_id;
    WHEN 'multas' THEN
      SELECT responsavel_id,unidade_id,ocorrido_em,veiculo_id,JSON_OBJECT('protocolo',protocolo,'veiculo',veiculo,'ocorrido_em',ocorrido_em,'precisao_ocorrencia',precisao_ocorrencia,'responsavel',responsavel,'descricao',descricao,'valor',valor,'situacao',situacao)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM vw_multas_detalhadas WHERE id=p_id;
    WHEN 'chamados' THEN
      SELECT c.solicitante_id,c.unidade_id,c.criado_em,NULL,JSON_OBJECT('protocolo',c.protocolo,'assunto',c.assunto,'categoria',cat.nome,'situacao',c.situacao,'criado_em',c.criado_em)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM chamados c JOIN categorias_chamado cat ON cat.id=c.categoria_id WHERE c.id=p_id;
    WHEN 'usuarios' THEN
      SELECT u.id,u.unidade_id,u.criado_em,NULL,JSON_OBJECT('identificador',u.identificador,'nome',u.nome,'unidade',un.nome,'ativo',u.ativo)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM usuarios u JOIN unidades un ON un.id=u.unidade_id WHERE u.id=p_id;
    WHEN 'perfis' THEN
      SELECT criado_por,NULL,criado_em,NULL,JSON_OBJECT('codigo',codigo,'nome',nome,'descricao',descricao,'ativo',ativo)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM perfis WHERE id=p_id;
    WHEN 'rotas' THEN
      SELECT criado_por,NULL,NULL,NULL,JSON_OBJECT('chave',chave,'nome',nome,'caminho',caminho,'modulo',modulo_codigo,'ativa',ativa)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM rotas_sistema WHERE id=p_id;
    WHEN 'auditoria' THEN
      SELECT a.ator_usuario_id,u.unidade_id,a.criado_em,NULL,JSON_OBJECT('evento',a.evento,'ator',a.ator_nome_snapshot,'perfil',a.perfil_nome_snapshot,'entidade',a.entidade,'criado_em',a.criado_em)
      INTO p_dono,p_unidade,p_data,p_veiculo,p_dados FROM auditoria a LEFT JOIN usuarios u ON u.id=a.ator_usuario_id
      LEFT JOIN usuario_perfis up ON up.id=a.ator_vinculo_id LEFT JOIN perfis pf ON pf.id=up.perfil_id WHERE a.id=p_id;
    ELSE SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Módulo não possui relatório implementado.';
  END CASE;
  IF p_dados IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro de relatório não encontrado.'; END IF;
END$$

CREATE PROCEDURE sp_preparar_exportacao(IN p_vinculo BIGINT UNSIGNED,IN p_modulo VARCHAR(40),IN p_formato VARCHAR(10),IN p_inicio DATE,IN p_fim DATE,IN p_filtros JSON,IN p_campos JSON,IN p_registros JSON)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_unidade_ator BIGINT UNSIGNED; DECLARE v_nivel INT;
  DECLARE v_id BIGINT UNSIGNED; DECLARE v_registro BIGINT UNSIGNED; DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_data DATETIME(6); DECLARE v_veiculo BIGINT UNSIGNED;
  DECLARE v_payload JSON; DECLARE v_snapshot JSON; DECLARE v_i INT DEFAULT 0; DECLARE v_j INT DEFAULT 0; DECLARE v_campo INT; DECLARE v_key VARCHAR(80); DECLARE v_extra VARCHAR(40); DECLARE v_ok INT;
  DECLARE v_filtro_unidade BIGINT UNSIGNED; DECLARE v_filtro_veiculo BIGINT UNSIGNED; DECLARE v_filtro_situacao VARCHAR(40); DECLARE v_filtro_key VARCHAR(80);
  DECLARE v_fonte VARCHAR(30); DECLARE v_item JSON; DECLARE v_contexto_modulo VARCHAR(40);
  DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE v_inicio_utc DATETIME(6); DECLARE v_fim_utc DATETIME(6); DECLARE v_fuso VARCHAR(64);
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT usuario_id,unidade_id INTO v_usuario,v_unidade_ator FROM vw_vinculos_ativos WHERE vinculo_id=p_vinculo;
  CALL sp_exigir_permissao(p_vinculo,'relatorios','consultar',v_usuario,v_unidade_ator);
  IF p_formato IS NULL OR p_formato NOT IN ('md','csv','xlsx','pdf') OR (p_inicio IS NOT NULL AND p_fim IS NOT NULL AND p_fim<p_inicio)
    OR p_campos IS NULL OR JSON_TYPE(p_campos)<>'ARRAY' OR JSON_LENGTH(p_campos)=0
    OR p_registros IS NULL OR JSON_TYPE(p_registros)<>'ARRAY' OR JSON_LENGTH(p_registros)=0 OR JSON_LENGTH(p_registros)>10000
    OR p_filtros IS NULL OR JSON_TYPE(p_filtros)<>'OBJECT' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Formato, datas, campos, filtros ou seleção de até 10.000 registros inválidos.'; END IF;
  SELECT MAX(nivel_alcance) INTO v_nivel FROM vw_permissoes_efetivas WHERE vinculo_id=p_vinculo AND modulo_codigo=p_modulo AND acao_codigo='exportar';
  IF v_nivel IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Perfil não pode exportar este módulo.'; END IF;
  WHILE v_i<JSON_LENGTH(p_filtros) DO
    SET v_filtro_key=JSON_UNQUOTE(JSON_EXTRACT(JSON_KEYS(p_filtros),CONCAT('$[',v_i,']')));
    IF v_filtro_key NOT IN ('situacao','unidade_id','veiculo_id','inicio_utc','fim_utc') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Filtro não implementado; não usar fragmentos SQL nos filtros.'; END IF;
    SET v_i=v_i+1;
  END WHILE;
  -- Separar JSON null de texto evita coerção implícita entre tipos no MySQL.
  IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.situacao'))<>'NULL' THEN
    IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.situacao'))<>'STRING' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Situação do filtro deve ser texto.'; END IF;
    SET v_filtro_situacao=JSON_UNQUOTE(JSON_EXTRACT(p_filtros,'$.situacao'));
  END IF;
  IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.unidade_id'))<>'NULL' THEN
    IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.unidade_id'))<>'INTEGER' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unidade do filtro deve ser um identificador inteiro.'; END IF;
    SET v_filtro_unidade=JSON_UNQUOTE(JSON_EXTRACT(p_filtros,'$.unidade_id'));
  END IF;
  IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.veiculo_id'))<>'NULL' THEN
    IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.veiculo_id'))<>'INTEGER' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo do filtro deve ser um identificador inteiro.'; END IF;
    SET v_filtro_veiculo=JSON_UNQUOTE(JSON_EXTRACT(p_filtros,'$.veiculo_id'));
  END IF;
  IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.inicio_utc'))<>'NULL' THEN
    IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.inicio_utc'))<>'STRING' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Início UTC do filtro deve ser texto no formato de data e hora SQL.'; END IF;
    SET v_inicio_utc=JSON_UNQUOTE(JSON_EXTRACT(p_filtros,'$.inicio_utc'));
  END IF;
  IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.fim_utc'))<>'NULL' THEN
    IF JSON_TYPE(JSON_EXTRACT(p_filtros,'$.fim_utc'))<>'STRING' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fim UTC do filtro deve ser texto no formato de data e hora SQL.'; END IF;
    SET v_fim_utc=JSON_UNQUOTE(JSON_EXTRACT(p_filtros,'$.fim_utc'));
  END IF;
  IF (v_inicio_utc IS NOT NULL AND v_fim_utc IS NOT NULL AND v_fim_utc<=v_inicio_utc)
  OR (p_modulo<>'despesas' AND ((p_inicio IS NOT NULL AND v_inicio_utc IS NULL) OR (p_fim IS NOT NULL AND v_fim_utc IS NULL))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Converta o período local para limites UTC no backend; fim é exclusivo.'; END IF;
  SELECT fuso_horario INTO v_fuso FROM configuracao_sistema WHERE id=1;
  INSERT INTO exportacoes(solicitado_por_vinculo_id,modulo_codigo,formato,periodo_inicio,periodo_fim,periodo_inicio_utc,periodo_fim_utc,fuso_apresentacao,filtros,alcance_aplicado,unidade_aplicada_id)
  VALUES(p_vinculo,p_modulo,p_formato,p_inicio,p_fim,v_inicio_utc,v_fim_utc,v_fuso,p_filtros,CASE v_nivel WHEN 1 THEN 'proprios' WHEN 2 THEN 'unidade' ELSE 'orgao' END,CASE WHEN v_nivel=2 THEN v_unidade_ator ELSE NULL END);
  SET v_id=LAST_INSERT_ID(); SET v_i=0;
  WHILE v_i<JSON_LENGTH(p_campos) DO
    SET v_campo=CAST(JSON_UNQUOTE(JSON_EXTRACT(p_campos,CONCAT('$[',v_i,']'))) AS UNSIGNED);
    SELECT COUNT(*) INTO v_ok FROM relatorio_campos WHERE id=v_campo AND modulo_codigo=p_modulo AND ativo=1;
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Campo não autorizado ou de outro módulo.'; END IF;
    INSERT INTO exportacao_campos(exportacao_id,campo_id,modulo_codigo,ordem) VALUES(v_id,v_campo,p_modulo,v_i+1);
    SET v_i=v_i+1;
  END WHILE;
  SET v_i=0;
  WHILE v_i<JSON_LENGTH(p_registros) DO
    SET v_item=JSON_EXTRACT(p_registros,CONCAT('$[',v_i,']')); SET v_fonte='rastreador'; SET v_contexto_modulo=p_modulo;
    IF p_modulo='rastreamento' AND JSON_TYPE(v_item)='OBJECT' THEN
      SET v_fonte=JSON_UNQUOTE(JSON_EXTRACT(v_item,'$.fonte'));
      IF v_fonte IS NULL OR v_fonte NOT IN ('manual','rastreador') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Fonte de posição inválida.'; END IF;
      SET v_registro=CAST(JSON_UNQUOTE(JSON_EXTRACT(v_item,'$.id')) AS UNSIGNED);
      IF v_fonte='manual' THEN SET v_contexto_modulo='rastreamento_manual'; END IF;
    ELSE SET v_registro=CAST(JSON_UNQUOTE(v_item) AS UNSIGNED); END IF;
    CALL sp_contexto_relatorio(v_contexto_modulo,v_registro,v_dono,v_unidade,v_data,v_veiculo,v_payload);
    IF p_modulo='viagens' THEN
      SELECT motorista_id INTO v_motorista FROM viagens WHERE id=v_registro;
      IF v_motorista=v_usuario THEN SET v_dono=v_usuario; END IF;
    END IF;
    CALL sp_exigir_permissao(p_vinculo,p_modulo,'consultar',v_dono,v_unidade);
    CALL sp_exigir_permissao(p_vinculo,p_modulo,'exportar',v_dono,v_unidade);
    IF (p_modulo='despesas' AND ((p_inicio IS NOT NULL AND (v_data IS NULL OR DATE(v_data)<p_inicio)) OR (p_fim IS NOT NULL AND (v_data IS NULL OR DATE(v_data)>p_fim))))
      OR (p_modulo<>'despesas' AND ((v_inicio_utc IS NOT NULL AND (v_data IS NULL OR v_data<v_inicio_utc)) OR (v_fim_utc IS NOT NULL AND (v_data IS NULL OR v_data>=v_fim_utc))))
      OR (v_filtro_unidade IS NOT NULL AND NOT(v_unidade<=>v_filtro_unidade)) OR (v_filtro_veiculo IS NOT NULL AND NOT(v_veiculo<=>v_filtro_veiculo))
      OR (v_filtro_situacao IS NOT NULL AND NOT(JSON_UNQUOTE(JSON_EXTRACT(v_payload,'$.situacao'))<=>v_filtro_situacao)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro selecionado não atende aos filtros da prévia.'; END IF;
    SET v_snapshot=JSON_OBJECT(); SET v_j=0;
    WHILE v_j<JSON_LENGTH(p_campos) DO
      SET v_campo=CAST(JSON_UNQUOTE(JSON_EXTRACT(p_campos,CONCAT('$[',v_j,']'))) AS UNSIGNED);
      SELECT chave,acao_adicional INTO v_key,v_extra FROM relatorio_campos WHERE id=v_campo;
      IF v_extra IS NOT NULL THEN CALL sp_exigir_permissao(p_vinculo,p_modulo,v_extra,v_dono,v_unidade); END IF;
      SET v_snapshot=JSON_SET(v_snapshot,CONCAT('$.',v_key),JSON_EXTRACT(v_payload,CONCAT('$.',v_key)));
      SET v_j=v_j+1;
    END WHILE;
    INSERT INTO exportacao_registros(exportacao_id,ordem,solicitacao_id,viagem_id,veiculo_id,despesa_id,multa_id,chamado_id,usuario_id,perfil_id,rota_id,auditoria_id,posicao_rastreamento_id,posicao_manual_id,snapshot)
    VALUES(v_id,v_i+1,IF(p_modulo='solicitacoes',v_registro,NULL),IF(p_modulo='viagens',v_registro,NULL),IF(p_modulo='frota',v_registro,NULL),IF(p_modulo='despesas',v_registro,NULL),IF(p_modulo='multas',v_registro,NULL),IF(p_modulo='chamados',v_registro,NULL),IF(p_modulo='usuarios',v_registro,NULL),IF(p_modulo='perfis',v_registro,NULL),IF(p_modulo='rotas',v_registro,NULL),IF(p_modulo='auditoria',v_registro,NULL),IF(p_modulo='rastreamento' AND v_fonte='rastreador',v_registro,NULL),IF(p_modulo='rastreamento' AND v_fonte='manual',v_registro,NULL),v_snapshot);
    SET v_i=v_i+1;
  END WHILE;
  UPDATE exportacoes SET total_registros=JSON_LENGTH(p_registros) WHERE id=v_id;
  CALL sp_auditar(p_vinculo,'exportacao_preparada','exportacoes',v_id,'Prévia com filtros, registros e campos autorizados; nenhum arquivo gerado ainda.');
  COMMIT;
  SELECT v_id AS exportacao_id;
END$$

CREATE PROCEDURE sp_enfileirar_exportacao(IN p_vinculo BIGINT UNSIGNED,IN p_exportacao BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_vinculo BIGINT UNSIGNED; DECLARE v_modulo VARCHAR(40); DECLARE v_estado VARCHAR(40);
  DECLARE v_count INT; DECLARE v_i INT DEFAULT 1; DECLARE v_registro BIGINT UNSIGNED; DECLARE v_dono BIGINT UNSIGNED; DECLARE v_unidade BIGINT UNSIGNED; DECLARE v_data DATETIME(6); DECLARE v_veiculo BIGINT UNSIGNED; DECLARE v_dados JSON;
  DECLARE v_extras INT; DECLARE v_j INT; DECLARE v_extra VARCHAR(40);
  DECLARE v_manual BIGINT UNSIGNED; DECLARE v_contexto_modulo VARCHAR(40);
  DECLARE v_usuario BIGINT UNSIGNED; DECLARE v_motorista BIGINT UNSIGNED;
  DECLARE EXIT HANDLER FOR SQLEXCEPTION BEGIN ROLLBACK; RESIGNAL; END;
  START TRANSACTION;
  SELECT solicitado_por_vinculo_id,modulo_codigo,situacao,total_registros INTO v_vinculo,v_modulo,v_estado,v_count FROM exportacoes WHERE id=p_exportacao FOR UPDATE;
  IF v_vinculo IS NULL OR v_vinculo<>p_vinculo OR v_estado<>'previa' OR v_count=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Prévia ausente, vazia, confirmada ou pertencente a outro vínculo.'; END IF;
  SELECT usuario_id INTO v_usuario FROM usuario_perfis WHERE id=p_vinculo;
  WHILE v_i<=v_count DO
    SELECT COALESCE(solicitacao_id,viagem_id,veiculo_id,despesa_id,multa_id,chamado_id,usuario_id,perfil_id,rota_id,auditoria_id,posicao_rastreamento_id,posicao_manual_id),posicao_manual_id
    INTO v_registro,v_manual FROM exportacao_registros WHERE exportacao_id=p_exportacao AND ordem=v_i;
    SET v_contexto_modulo=IF(v_manual IS NOT NULL,'rastreamento_manual',v_modulo);
    CALL sp_contexto_relatorio(v_contexto_modulo,v_registro,v_dono,v_unidade,v_data,v_veiculo,v_dados);
    IF v_modulo='viagens' THEN
      SELECT motorista_id INTO v_motorista FROM viagens WHERE id=v_registro;
      IF v_motorista=v_usuario THEN SET v_dono=v_usuario; END IF;
    END IF;
    CALL sp_exigir_permissao(p_vinculo,v_modulo,'consultar',v_dono,v_unidade);
    CALL sp_exigir_permissao(p_vinculo,v_modulo,'exportar',v_dono,v_unidade);
    SELECT COUNT(*) INTO v_extras FROM exportacao_campos ec JOIN relatorio_campos rc ON rc.id=ec.campo_id WHERE ec.exportacao_id=p_exportacao AND rc.acao_adicional IS NOT NULL;
    SET v_j=0;
    WHILE v_j<v_extras DO
      SELECT rc.acao_adicional INTO v_extra FROM exportacao_campos ec JOIN relatorio_campos rc ON rc.id=ec.campo_id WHERE ec.exportacao_id=p_exportacao AND rc.acao_adicional IS NOT NULL ORDER BY ec.ordem LIMIT v_j,1;
      CALL sp_exigir_permissao(p_vinculo,v_modulo,v_extra,v_dono,v_unidade);
      SET v_j=v_j+1;
    END WHILE;
    SET v_i=v_i+1;
  END WHILE;
  UPDATE exportacoes SET situacao='fila' WHERE id=p_exportacao;
  CALL sp_auditar(p_vinculo,'exportacao_confirmada','exportacoes',p_exportacao,'Seleção confirmada após revalidar permissão de cada registro e campo sensível.');
  COMMIT;
END$$
DELIMITER ;

-- Triggers de integridade: executadas também em INSERT/UPDATE diretos.
DELIMITER $$

CREATE PROCEDURE sp_validar_reserva(IN p_id BIGINT UNSIGNED,IN p_veiculo BIGINT UNSIGNED,IN p_motorista BIGINT UNSIGNED,IN p_revisao BIGINT UNSIGNED,IN p_tipo VARCHAR(30),IN p_inicio DATETIME(6),IN p_fim DATETIME(6),IN p_autor BIGINT UNSIGNED)
SQL SECURITY INVOKER
BEGIN
  DECLARE v_capacidade INT; DECLARE v_ativo VARCHAR(30); DECLARE v_categoria CHAR(1);
  DECLARE v_conflito BIGINT UNSIGNED DEFAULT NULL; DECLARE v_driver BIGINT UNSIGNED;
  DECLARE v_cnh VARCHAR(10); DECLARE v_validade DATE; DECLARE v_passageiros INT;
  DECLARE v_enviado DATETIME(6); DECLARE v_atual BIGINT UNSIGNED; DECLARE v_dono BIGINT UNSIGNED;
  DECLARE v_situacao VARCHAR(40); DECLARE v_resposta INT;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_conflito=NULL;
  IF p_inicio IS NULL OR p_fim IS NULL OR p_fim<=p_inicio THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Período de reserva inválido.'; END IF;
  -- O veículo é o mutex da agenda. Leitura bloqueante evita usar snapshot antigo.
  SELECT v.capacidade,v.situacao_cadastro,c.categoria_cnh_requerida INTO v_capacidade,v_ativo,v_categoria
  FROM veiculos v JOIN categorias_veiculo c ON c.id=v.categoria_id WHERE v.id=p_veiculo FOR UPDATE;
  IF v_ativo IS NULL OR v_ativo<>'ativo' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo inexistente, inativo ou baixado.'; END IF;
  SET v_conflito=NULL;
  SELECT reserva_id INTO v_conflito FROM ocupacoes_agenda WHERE veiculo_id=p_veiculo
  AND (p_id IS NULL OR reserva_id<>p_id) AND inicio<p_fim AND fim>p_inicio LIMIT 1 FOR UPDATE;
  IF v_conflito IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo reservado ou bloqueado neste período.'; END IF;
  SET v_conflito=NULL;
  SELECT id INTO v_conflito FROM viagens WHERE situacao='concluida' AND veiculo_id=p_veiculo
  AND saida_real<p_fim AND retorno_real>p_inicio LIMIT 1 FOR UPDATE;
  IF v_conflito IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Período conflita com viagem já realizada por este veículo.'; END IF;
  IF p_tipo='viagem' THEN
    IF p_motorista IS NULL OR p_revisao IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reserva de viagem exige revisão e motorista.'; END IF;
    -- Mesma ordem de locks em todas as reservas: veículo e depois motorista.
    SELECT m.usuario_id,m.categorias_autorizadas,m.validade_cnh INTO v_driver,v_cnh,v_validade
    FROM motoristas m JOIN usuarios u ON u.id=m.usuario_id AND u.ativo=1
    WHERE m.usuario_id=p_motorista AND m.ativo=1 FOR UPDATE;
    IF v_driver IS NULL OR v_validade<DATE(p_fim) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Motorista inexistente, inativo ou CNH vencida para o período.'; END IF;
    -- Categorias operacionais autorizadas são cadastradas após conferir a habilitação.
    IF LOCATE(v_categoria,v_cnh)=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Categoria do veículo não autorizada no cadastro do motorista.'; END IF;
    SET v_conflito=NULL;
    SELECT reserva_id INTO v_conflito FROM ocupacoes_agenda WHERE motorista_id=p_motorista
    AND (p_id IS NULL OR reserva_id<>p_id) AND inicio<p_fim AND fim>p_inicio LIMIT 1 FOR UPDATE;
    IF v_conflito IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Motorista já reservado neste período.'; END IF;
    SET v_conflito=NULL;
    SELECT id INTO v_conflito FROM viagens WHERE situacao='concluida' AND motorista_id=p_motorista
    AND saida_real<p_fim AND retorno_real>p_inicio LIMIT 1 FOR UPDATE;
    IF v_conflito IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Período conflita com viagem já realizada por este motorista.'; END IF;
    SELECT r.quantidade_passageiros,r.enviado_em,s.revisao_atual_id,s.solicitante_id,s.situacao
    INTO v_passageiros,v_enviado,v_atual,v_dono,v_situacao
    FROM solicitacao_revisoes r JOIN solicitacoes s ON s.id=r.solicitacao_id WHERE r.id=p_revisao FOR UPDATE;
    IF v_enviado IS NULL OR v_atual<>p_revisao OR v_situacao NOT IN ('aguardando_analise','aprovada') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisão não enviada, substituída ou fora de análise/aprovação.'; END IF;
    IF p_autor=v_dono THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitante não pode confirmar a própria reserva.'; END IF;
    IF v_passageiros IS NULL OR v_passageiros+1>v_capacidade THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Passageiros mais motorista excedem a capacidade do veículo.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_config_bu BEFORE UPDATE ON configuracao_sistema FOR EACH ROW
BEGIN

  IF NEW.id<>OLD.id OR (OLD.bootstrap_concluido=1 AND NEW.bootstrap_concluido=0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade e inicialização do sistema não podem ser revertidas.'; END IF;
END$$

CREATE TRIGGER tg_config_bd BEFORE DELETE ON configuracao_sistema FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='A configuração única do sistema não pode ser excluída.';
END$$

CREATE TRIGGER tg_unidade_bu BEFORE UPDATE ON unidades FOR EACH ROW
BEGIN

  DECLARE v_count INT;
  IF NEW.unidade_superior_id=OLD.id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Unidade não pode ser superior de si mesma.'; END IF;
  IF OLD.ativa=1 AND NEW.ativa=0 THEN
    SELECT COUNT(*) INTO v_count FROM usuarios WHERE unidade_id=OLD.id AND ativo=1;
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Realoque ou desative os usuários antes de desativar a unidade.'; END IF;
    SELECT COUNT(*) INTO v_count FROM veiculos WHERE unidade_id=OLD.id AND situacao_cadastro='ativo';
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Realoque os veículos ativos antes de desativar a unidade.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_usuario_bi BEFORE INSERT ON usuarios FOR EACH ROW
BEGIN

  IF NOT ((NEW.senha_hash LIKE '$2y$%' OR NEW.senha_hash LIKE '$2b$%') AND CHAR_LENGTH(NEW.senha_hash)=60)
     AND NOT (NEW.senha_hash LIKE '$argon2id$%' AND CHAR_LENGTH(NEW.senha_hash)>=90) THEN
     SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Senha deve ser um hash bcrypt ou Argon2id válido gerado no backend.';
  END IF;
END$$

CREATE TRIGGER tg_usuario_bu BEFORE UPDATE ON usuarios FOR EACH ROW
BEGIN

  IF OLD.ativo=1 AND NEW.ativo=0 THEN CALL sp_exigir_admin_restante(OLD.id,NULL,NULL); END IF;
  IF NOT ((NEW.senha_hash LIKE '$2y$%' OR NEW.senha_hash LIKE '$2b$%') AND CHAR_LENGTH(NEW.senha_hash)=60)
     AND NOT (NEW.senha_hash LIKE '$argon2id$%' AND CHAR_LENGTH(NEW.senha_hash)>=90) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Senha deve ser um hash bcrypt ou Argon2id.'; END IF;
  SET NEW.versao=OLD.versao+1;
END$$

CREATE TRIGGER tg_usuario_au AFTER UPDATE ON usuarios FOR EACH ROW
BEGIN

  UPDATE controle_vinculos SET ativo_usuario=NEW.ativo WHERE usuario_id=NEW.id;
  INSERT INTO auditoria(evento,entidade,entidade_id,antes,depois)
  VALUES('usuario_atualizado','usuarios',NEW.id,
  JSON_OBJECT('nome',OLD.nome,'unidade_id',OLD.unidade_id,'ativo',OLD.ativo),
  JSON_OBJECT('nome',NEW.nome,'unidade_id',NEW.unidade_id,'ativo',NEW.ativo,'senha_alterada',NOT(NEW.senha_hash<=>OLD.senha_hash)));
END$$

CREATE TRIGGER tg_usuario_bd BEFORE DELETE ON usuarios FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Desative o usuário para preservar seus vínculos e histórico.';
END$$

CREATE TRIGGER tg_perfil_bu BEFORE UPDATE ON perfis FOR EACH ROW
BEGIN

  IF NEW.codigo<>OLD.codigo THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Código estável do perfil não pode ser alterado.'; END IF;
  IF OLD.ativo=1 AND NEW.ativo=0 THEN CALL sp_exigir_admin_restante(NULL,OLD.id,NULL); END IF;
END$$

CREATE TRIGGER tg_perfil_bd BEFORE DELETE ON perfis FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Desative o perfil para preservar o histórico.';
END$$

CREATE TRIGGER tg_perfil_au AFTER UPDATE ON perfis FOR EACH ROW
BEGIN
  UPDATE controle_vinculos SET ativo_perfil=NEW.ativo WHERE perfil_id=NEW.id;
END$$

CREATE TRIGGER tg_pp_bd BEFORE DELETE ON perfil_permissoes FOR EACH ROW
BEGIN

  DECLARE v_obrigatoria INT;
  SELECT COUNT(*) INTO v_obrigatoria FROM permissoes WHERE id=OLD.permissao_id AND alcance='orgao' AND acao_codigo='gerenciar' AND modulo_codigo IN ('usuarios','perfis','rotas','configuracoes');
  IF v_obrigatoria>0 THEN CALL sp_exigir_admin_restante(NULL,OLD.perfil_id,NULL); END IF;
END$$

CREATE TRIGGER tg_pp_bu BEFORE UPDATE ON perfil_permissoes FOR EACH ROW
BEGIN

  IF OLD.perfil_id<>NEW.perfil_id OR OLD.permissao_id<>NEW.permissao_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revogue e conceda a permissão em vez de alterar sua identidade.'; END IF;
END$$

CREATE TRIGGER tg_pp_bi BEFORE INSERT ON perfil_permissoes FOR EACH ROW
BEGIN

  DECLARE v_lock INT;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
END$$

CREATE TRIGGER tg_pp_controle_ai AFTER INSERT ON perfil_permissoes FOR EACH ROW
BEGIN

  DECLARE v_capacidade INT;
  SELECT COUNT(DISTINCT p.modulo_codigo) INTO v_capacidade FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id
  WHERE pp.perfil_id=NEW.perfil_id AND p.acao_codigo='gerenciar' AND p.alcance='orgao' AND p.modulo_codigo IN ('usuarios','perfis','rotas','configuracoes');
  UPDATE controle_vinculos SET pode_administrar=(v_capacidade=4) WHERE perfil_id=NEW.perfil_id;
END$$

CREATE TRIGGER tg_pp_controle_ad AFTER DELETE ON perfil_permissoes FOR EACH ROW
BEGIN

  DECLARE v_capacidade INT;
  SELECT COUNT(DISTINCT p.modulo_codigo) INTO v_capacidade FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id
  WHERE pp.perfil_id=OLD.perfil_id AND p.acao_codigo='gerenciar' AND p.alcance='orgao' AND p.modulo_codigo IN ('usuarios','perfis','rotas','configuracoes');
  UPDATE controle_vinculos SET pode_administrar=(v_capacidade=4) WHERE perfil_id=OLD.perfil_id;
END$$

CREATE TRIGGER tg_modulo_bu BEFORE UPDATE ON modulos FOR EACH ROW
BEGIN

  IF NEW.codigo<>OLD.codigo OR (OLD.codigo IN ('usuarios','perfis','rotas','configuracoes') AND NEW.ativo=0) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Módulos administrativos essenciais devem permanecer ativos.'; END IF;
END$$

CREATE TRIGGER tg_vinculo_bi BEFORE INSERT ON usuario_perfis FOR EACH ROW
BEGIN

  DECLARE v_lock INT; DECLARE v_conflito BIGINT UNSIGNED DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_conflito=NULL;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  IF NEW.ativo=1 THEN
    SELECT vinculo_id INTO v_conflito FROM controle_vinculos WHERE usuario_id=NEW.usuario_id AND perfil_id=NEW.perfil_id AND ativo_vinculo=1
    AND vigente_desde<COALESCE(NEW.vigente_ate,'9999-12-31 23:59:59') AND COALESCE(vigente_ate,'9999-12-31 23:59:59')>NEW.vigente_desde LIMIT 1 FOR UPDATE;
    IF v_conflito IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Já existe vínculo ativo desse perfil no mesmo período.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_vinculo_ai AFTER INSERT ON usuario_perfis FOR EACH ROW
BEGIN

  DECLARE v_capacidade INT;
  SELECT COUNT(DISTINCT p.modulo_codigo) INTO v_capacidade FROM perfil_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id
  WHERE pp.perfil_id=NEW.perfil_id AND p.acao_codigo='gerenciar' AND p.alcance='orgao' AND p.modulo_codigo IN ('usuarios','perfis','rotas','configuracoes');
  INSERT INTO controle_vinculos(vinculo_id,usuario_id,perfil_id,unidade_id,vigente_desde,vigente_ate,ativo_vinculo,ativo_usuario,ativo_perfil,ativo_unidade,pode_administrar)
  SELECT NEW.id,NEW.usuario_id,NEW.perfil_id,NEW.unidade_id,NEW.vigente_desde,NEW.vigente_ate,NEW.ativo,u.ativo,pf.ativo,un.ativa,(v_capacidade=4)
  FROM usuarios u JOIN perfis pf ON pf.id=NEW.perfil_id JOIN unidades un ON un.id=NEW.unidade_id WHERE u.id=NEW.usuario_id;
END$$

CREATE TRIGGER tg_vinculo_bu BEFORE UPDATE ON usuario_perfis FOR EACH ROW
BEGIN

  DECLARE v_lock INT; DECLARE v_conflito BIGINT UNSIGNED DEFAULT NULL;
  DECLARE CONTINUE HANDLER FOR NOT FOUND SET v_conflito=NULL;
  SELECT id INTO v_lock FROM configuracao_sistema WHERE id=1 FOR UPDATE;
  IF OLD.usuario_id<>NEW.usuario_id OR OLD.perfil_id<>NEW.perfil_id OR OLD.unidade_id<>NEW.unidade_id OR OLD.vigente_desde<>NEW.vigente_desde THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade e início do vínculo são históricos; crie outro vínculo.'; END IF;
  IF NEW.ativo=0 OR NEW.vigente_ate IS NOT NULL THEN CALL sp_exigir_admin_restante(NULL,NULL,OLD.id); END IF;
END$$

CREATE TRIGGER tg_unidade_au AFTER UPDATE ON unidades FOR EACH ROW
BEGIN
  UPDATE controle_vinculos SET ativo_unidade=NEW.ativa WHERE unidade_id=NEW.id;
END$$

CREATE TRIGGER tg_vinculo_bd BEFORE DELETE ON usuario_perfis FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revogue o vínculo, preservando o histórico de acesso.';
END$$

CREATE TRIGGER tg_vinculo_au AFTER UPDATE ON usuario_perfis FOR EACH ROW
BEGIN
  UPDATE controle_vinculos SET ativo_vinculo=NEW.ativo,vigente_ate=NEW.vigente_ate WHERE vinculo_id=NEW.id;
END$$

CREATE TRIGGER tg_rota_bu BEFORE UPDATE ON rotas_sistema FOR EACH ROW
BEGIN

  IF NEW.chave<>OLD.chave THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Chave técnica da rota não pode ser alterada.'; END IF;
  IF OLD.protegida=1 AND (NEW.ativa=0 OR NEW.protegida=0 OR NEW.caminho<>OLD.caminho OR NEW.metodo_http<>OLD.metodo_http OR NEW.modulo_codigo<>OLD.modulo_codigo) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve a rota administrativa necessária para reativar acessos.'; END IF;
END$$

CREATE TRIGGER tg_auditoria_bi BEFORE INSERT ON auditoria FOR EACH ROW
BEGIN

  DECLARE v_nome VARCHAR(150); DECLARE v_perfil VARCHAR(100); DECLARE v_codigo VARCHAR(60);
  IF NEW.ator_usuario_id IS NOT NULL THEN SELECT nome INTO v_nome FROM usuarios WHERE id=NEW.ator_usuario_id; END IF;
  IF NEW.ator_vinculo_id IS NOT NULL THEN SELECT p.nome,p.codigo INTO v_perfil,v_codigo FROM usuario_perfis up JOIN perfis p ON p.id=up.perfil_id WHERE up.id=NEW.ator_vinculo_id; END IF;
  SET NEW.ator_nome_snapshot=v_nome; SET NEW.perfil_nome_snapshot=v_perfil; SET NEW.perfil_codigo_snapshot=v_codigo;
END$$

CREATE TRIGGER tg_revisao_bi BEFORE INSERT ON solicitacao_revisoes FOR EACH ROW
BEGIN

  DECLARE v_lista INT;
  IF NEW.enviado_em IS NOT NULL THEN
    IF NEW.finalidade IS NULL OR CHAR_LENGTH(TRIM(NEW.finalidade))=0 OR NEW.origem IS NULL OR CHAR_LENGTH(TRIM(NEW.origem))=0
      OR NEW.destino IS NULL OR CHAR_LENGTH(TRIM(NEW.destino))=0 OR NEW.saida_prevista IS NULL OR NEW.retorno_previsto IS NULL
      OR NEW.quantidade_passageiros IS NULL OR NEW.veiculo_pretendido_id IS NULL OR NEW.etapa_atual<>4 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Complete as quatro etapas antes de enviar a solicitação.';
    END IF;
    SELECT COUNT(*) INTO v_lista FROM solicitacao_passageiros WHERE revisao_id=NEW.id;
    IF v_lista>NEW.quantidade_passageiros THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lista de passageiros excede a quantidade informada.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_revisao_bu BEFORE UPDATE ON solicitacao_revisoes FOR EACH ROW
BEGIN

  DECLARE v_lista INT;
  IF OLD.enviado_em IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisão enviada é imutável: abra uma nova revisão.'; END IF;
  IF OLD.solicitacao_id<>NEW.solicitacao_id OR OLD.numero<>NEW.numero OR OLD.criado_por<>NEW.criado_por THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade da revisão é imutável.'; END IF;

  IF NEW.enviado_em IS NOT NULL THEN
    IF NEW.finalidade IS NULL OR CHAR_LENGTH(TRIM(NEW.finalidade))=0 OR NEW.origem IS NULL OR CHAR_LENGTH(TRIM(NEW.origem))=0
      OR NEW.destino IS NULL OR CHAR_LENGTH(TRIM(NEW.destino))=0 OR NEW.saida_prevista IS NULL OR NEW.retorno_previsto IS NULL
      OR NEW.quantidade_passageiros IS NULL OR NEW.veiculo_pretendido_id IS NULL OR NEW.etapa_atual<>4 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Complete as quatro etapas antes de enviar a solicitação.';
    END IF;
    SELECT COUNT(*) INTO v_lista FROM solicitacao_passageiros WHERE revisao_id=NEW.id;
    IF v_lista>NEW.quantidade_passageiros THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Lista de passageiros excede a quantidade informada.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_revisao_bd BEFORE DELETE ON solicitacao_revisoes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Revisões e decisões não podem ser excluídas.';
END$$

CREATE TRIGGER tg_solicitacao_bu BEFORE UPDATE ON solicitacoes FOR EACH ROW
BEGIN

  DECLARE v_ok INT;
  IF NEW.solicitante_id<>OLD.solicitante_id OR NEW.unidade_id<>OLD.unidade_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Solicitante e unidade de origem são históricos.'; END IF;
  IF OLD.revisao_atual_id IS NOT NULL AND NEW.protocolo<>OLD.protocolo THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Protocolo estável da solicitação não pode ser alterado.'; END IF;
  IF NEW.situacao<>OLD.situacao THEN
    IF NOT ((OLD.situacao='rascunho' AND NEW.situacao IN ('aguardando_analise','cancelada'))
    OR (OLD.situacao='aguardando_analise' AND NEW.situacao IN ('aprovada','negada','ajustes_solicitados','rascunho','cancelada'))
    OR (OLD.situacao IN ('aprovada','ajustes_solicitados') AND NEW.situacao IN ('rascunho','cancelada'))) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transição da solicitação inválida.'; END IF;
    IF NEW.situacao='rascunho' AND OLD.revisao_atual_id=NEW.revisao_atual_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reabertura exige nova revisão, preservando a anterior.'; END IF;
    IF NEW.situacao='aguardando_analise' THEN
      SELECT COUNT(*) INTO v_ok FROM solicitacao_revisoes WHERE id=NEW.revisao_atual_id AND enviado_em IS NOT NULL;
      IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Envie a revisão completa antes da análise.'; END IF;
    END IF;
    IF NEW.situacao='aprovada' THEN
      SELECT COUNT(*) INTO v_ok FROM reservas r JOIN viagens v ON v.reserva_id=r.id WHERE r.revisao_id=NEW.revisao_atual_id AND r.situacao='ativa' AND v.situacao='programada';
      IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aprovação exige reserva e viagem programada na mesma transação.'; END IF;
    END IF;
  END IF;
END$$

CREATE TRIGGER tg_solicitacao_paradas_bi BEFORE INSERT ON solicitacao_paradas FOR EACH ROW
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=NEW.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
END$$

CREATE TRIGGER tg_solicitacao_paradas_bu BEFORE UPDATE ON solicitacao_paradas FOR EACH ROW
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=OLD.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
  IF OLD.revisao_id<>NEW.revisao_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode transferir registro entre revisões.'; END IF;
END$$

CREATE TRIGGER tg_solicitacao_paradas_bd BEFORE DELETE ON solicitacao_paradas FOR EACH ROW
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=OLD.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
END$$

CREATE TRIGGER tg_solicitacao_passageiros_bi BEFORE INSERT ON solicitacao_passageiros FOR EACH ROW
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=NEW.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
END$$

CREATE TRIGGER tg_solicitacao_passageiros_bu BEFORE UPDATE ON solicitacao_passageiros FOR EACH ROW
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=OLD.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
  IF OLD.revisao_id<>NEW.revisao_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode transferir registro entre revisões.'; END IF;
END$$

CREATE TRIGGER tg_solicitacao_passageiros_bd BEFORE DELETE ON solicitacao_passageiros FOR EACH ROW
BEGIN

  DECLARE v_enviado DATETIME(6);
  SELECT enviado_em INTO v_enviado FROM solicitacao_revisoes WHERE id=OLD.revisao_id FOR UPDATE;
  IF v_enviado IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Itens de revisão enviada são imutáveis.'; END IF;
END$$

CREATE TRIGGER tg_reserva_bi BEFORE INSERT ON reservas FOR EACH ROW
BEGIN

  IF NEW.situacao<>'ativa' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nova reserva deve começar ativa.'; END IF;
  CALL sp_validar_reserva(NULL,NEW.veiculo_id,NEW.motorista_id,NEW.revisao_id,NEW.tipo,NEW.inicio,NEW.fim,NEW.criado_por);
END$$

CREATE TRIGGER tg_reserva_bu BEFORE UPDATE ON reservas FOR EACH ROW
BEGIN

  DECLARE v_em_andamento INT;
  IF NEW.veiculo_id<>OLD.veiculo_id OR NOT(NEW.motorista_id<=>OLD.motorista_id) OR NOT(NEW.revisao_id<=>OLD.revisao_id) OR NEW.tipo<>OLD.tipo OR NEW.criado_por<>OLD.criado_por THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade de reserva é imutável; reserve outra revisão.'; END IF;
  IF OLD.situacao<>'ativa' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Reserva encerrada não pode ser reativada ou editada.'; END IF;
  IF NEW.situacao<>'ativa' THEN
    SELECT COUNT(*) INTO v_em_andamento FROM ocupacoes_agenda WHERE reserva_id=OLD.id AND em_andamento=1 FOR UPDATE;
    IF v_em_andamento>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registre o retorno antes de liberar a reserva de viagem em andamento.'; END IF;
  END IF;
  IF NEW.situacao='ativa' THEN CALL sp_validar_reserva(OLD.id,NEW.veiculo_id,NEW.motorista_id,NEW.revisao_id,NEW.tipo,NEW.inicio,NEW.fim,NEW.criado_por);
  ELSE SET NEW.liberada_em=COALESCE(NEW.liberada_em,UTC_TIMESTAMP(6)); END IF;
END$$

CREATE TRIGGER tg_reserva_bd BEFORE DELETE ON reservas FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Encerre a reserva para preservar a agenda histórica.';
END$$

CREATE TRIGGER tg_reserva_ai AFTER INSERT ON reservas FOR EACH ROW
BEGIN
  INSERT INTO ocupacoes_agenda(reserva_id,veiculo_id,motorista_id,inicio,fim) VALUES(NEW.id,NEW.veiculo_id,NEW.motorista_id,NEW.inicio,NEW.fim);
END$$

CREATE TRIGGER tg_reserva_au AFTER UPDATE ON reservas FOR EACH ROW
BEGIN

  IF NEW.situacao='ativa' THEN UPDATE ocupacoes_agenda SET inicio=NEW.inicio,fim=NEW.fim WHERE reserva_id=NEW.id;
  ELSE DELETE FROM ocupacoes_agenda WHERE reserva_id=NEW.id; END IF;
END$$

CREATE TRIGGER tg_viagem_bi BEFORE INSERT ON viagens FOR EACH ROW
BEGIN

  DECLARE v_ok INT;
  IF NEW.situacao<>'programada' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Nova viagem começa programada.'; END IF;
  SELECT COUNT(*) INTO v_ok FROM reservas WHERE id=NEW.reserva_id AND tipo='viagem' AND situacao='ativa';
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem exige reserva ativa.'; END IF;
END$$

CREATE TRIGGER tg_viagem_ai AFTER INSERT ON viagens FOR EACH ROW
BEGIN
  UPDATE ocupacoes_agenda SET viagem_id=NEW.id WHERE reserva_id=NEW.reserva_id;
END$$

CREATE TRIGGER tg_viagem_bu BEFORE UPDATE ON viagens FOR EACH ROW
BEGIN

  DECLARE v_lock BIGINT UNSIGNED; DECLARE v_count INT; DECLARE v_km DECIMAL(12,1); DECLARE v_ok INT;
  IF OLD.veiculo_id<>NEW.veiculo_id OR OLD.motorista_id<>NEW.motorista_id OR OLD.revisao_id<>NEW.revisao_id OR OLD.reserva_id<>NEW.reserva_id OR OLD.protocolo<>NEW.protocolo THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade da viagem é imutável.'; END IF;
  IF OLD.situacao IN ('concluida','cancelada') THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Viagem encerrada não pode ser editada.'; END IF;
  IF NOT(NEW.situacao=OLD.situacao OR (OLD.situacao='programada' AND NEW.situacao IN ('em_andamento','cancelada')) OR (OLD.situacao='em_andamento' AND NEW.situacao='concluida')) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Transição de viagem inválida.'; END IF;
  IF OLD.situacao='em_andamento' AND (NOT(OLD.saida_real<=>NEW.saida_real) OR NOT(OLD.quilometragem_saida<=>NEW.quilometragem_saida) OR NOT(OLD.saida_registrada_por<=>NEW.saida_registrada_por)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Saída efetiva já registrada é imutável.'; END IF;
  IF NEW.situacao='em_andamento' AND OLD.situacao='programada' THEN
    SELECT id,quilometragem_atual INTO v_lock,v_km FROM veiculos WHERE id=NEW.veiculo_id FOR UPDATE;
    SELECT usuario_id INTO v_lock FROM motoristas WHERE usuario_id=NEW.motorista_id FOR UPDATE;
    SELECT COUNT(*) INTO v_ok FROM solicitacao_revisoes r JOIN solicitacoes s ON s.id=r.solicitacao_id
    JOIN reservas rs ON rs.id=NEW.reserva_id WHERE r.id=NEW.revisao_id AND s.revisao_atual_id=r.id AND s.situacao='aprovada' AND rs.situacao='ativa' AND NEW.saida_real<rs.fim;
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Aprovação/reserva atual inválida para a saída.'; END IF;
    SELECT COUNT(*) INTO v_count FROM ocupacoes_agenda WHERE reserva_id<>OLD.reserva_id AND em_andamento=1 AND (veiculo_id=NEW.veiculo_id OR motorista_id=NEW.motorista_id) FOR UPDATE;
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo ou motorista ainda está em outra viagem.'; END IF;
    SELECT COUNT(*) INTO v_count FROM manutencoes WHERE veiculo_id=NEW.veiculo_id AND situacao='em_execucao' FOR UPDATE;
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Veículo permanece em manutenção.'; END IF;
    IF NEW.quilometragem_saida<v_km OR NEW.saida_real>UTC_TIMESTAMP(6) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Saída não pode reduzir odômetro nem ocorrer no futuro.'; END IF;
    SELECT COUNT(*) INTO v_ok FROM viagem_checklists WHERE viagem_id=OLD.id AND tipo='saida' AND finalizado_em IS NOT NULL;
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Finalize o checklist de saída.'; END IF;
  END IF;
  IF NEW.situacao='concluida' THEN
    SELECT COUNT(*) INTO v_ok FROM viagem_checklists WHERE viagem_id=OLD.id AND tipo='retorno' AND finalizado_em IS NOT NULL;
    IF v_ok=0 OR NEW.retorno_real>UTC_TIMESTAMP(6) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Retorno exige checklist finalizado e data real.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_viagem_au AFTER UPDATE ON viagens FOR EACH ROW
BEGIN
  UPDATE ocupacoes_agenda SET em_andamento=(NEW.situacao='em_andamento') WHERE reserva_id=NEW.reserva_id;
END$$

CREATE TRIGGER tg_vcheck_bu BEFORE UPDATE ON viagem_checklists FOR EACH ROW
BEGIN

  DECLARE v_faltam INT;
  IF OLD.finalizado_em IS NOT NULL OR OLD.viagem_id<>NEW.viagem_id OR OLD.tipo<>NEW.tipo OR OLD.preenchido_por<>NEW.preenchido_por THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Checklist finalizado ou identidade não pode ser alterado.'; END IF;
  IF NEW.finalizado_em IS NOT NULL THEN
    SELECT COUNT(*) INTO v_faltam FROM checklist_itens i WHERE i.ativo=1 AND i.obrigatorio=1
    AND NOT EXISTS(SELECT 1 FROM viagem_checklist_respostas r WHERE r.checklist_id=OLD.id AND r.item_id=i.id);
    IF v_faltam>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Responda todos os itens obrigatórios do checklist.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_vresposta_bi BEFORE INSERT ON viagem_checklist_respostas FOR EACH ROW
BEGIN

  DECLARE v_final DATETIME(6); DECLARE v_descricao VARCHAR(150); DECLARE v_obrigatorio INT;
  SELECT finalizado_em INTO v_final FROM viagem_checklists WHERE id=NEW.checklist_id FOR UPDATE;
  IF v_final IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Respostas de checklist finalizado são imutáveis.'; END IF;
  SELECT descricao,obrigatorio INTO v_descricao,v_obrigatorio FROM checklist_itens WHERE id=NEW.item_id;
  SET NEW.descricao_item=v_descricao; SET NEW.obrigatorio_snapshot=v_obrigatorio;
END$$

CREATE TRIGGER tg_vresposta_bu BEFORE UPDATE ON viagem_checklist_respostas FOR EACH ROW
BEGIN

  DECLARE v_final DATETIME(6); DECLARE v_descricao VARCHAR(150); DECLARE v_obrigatorio INT;
  SELECT finalizado_em INTO v_final FROM viagem_checklists WHERE id=OLD.checklist_id FOR UPDATE;
  IF v_final IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Respostas de checklist finalizado são imutáveis.'; END IF;
  IF OLD.checklist_id<>NEW.checklist_id OR OLD.item_id<>NEW.item_id THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode transferir resposta entre checklists.'; END IF;
END$$

CREATE TRIGGER tg_vresposta_bd BEFORE DELETE ON viagem_checklist_respostas FOR EACH ROW
BEGIN

  DECLARE v_final DATETIME(6); DECLARE v_descricao VARCHAR(150); DECLARE v_obrigatorio INT;
  SELECT finalizado_em INTO v_final FROM viagem_checklists WHERE id=OLD.checklist_id FOR UPDATE;
  IF v_final IS NOT NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Respostas de checklist finalizado são imutáveis.'; END IF;
END$$

CREATE TRIGGER tg_responsabilidade_bi BEFORE INSERT ON multa_responsabilidades FOR EACH ROW
BEGIN

  DECLARE v_ocorrido DATETIME(6); DECLARE v_inicio DATETIME(6); DECLARE v_fim DATETIME(6); DECLARE v_estado VARCHAR(40);
  DECLARE v_precisao VARCHAR(20); DECLARE v_fim_dia DATETIME(6);
  SELECT ocorrido_em,precisao_ocorrencia,fim_dia_ocorrencia_utc INTO v_ocorrido,v_precisao,v_fim_dia FROM multas WHERE id=NEW.multa_id FOR UPDATE;
  SELECT saida_real,retorno_real,situacao INTO v_inicio,v_fim,v_estado FROM viagens WHERE id=NEW.viagem_id;
  IF v_estado IS NULL OR v_estado NOT IN ('em_andamento','concluida') OR v_inicio IS NULL
    OR (v_precisao='instante' AND (v_ocorrido<v_inicio OR v_ocorrido>COALESCE(v_fim,UTC_TIMESTAMP(6))))
    OR (v_precisao='dia' AND (v_ocorrido>=COALESCE(v_fim,UTC_TIMESTAMP(6)) OR v_fim_dia<=v_inicio))
    THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Confira o veículo, condutor e período real da viagem antes de atribuir a multa.'; END IF;
END$$

CREATE TRIGGER tg_pagamento_multa_bi BEFORE INSERT ON pagamentos_multa FOR EACH ROW
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM multa_conferencias WHERE id=NEW.conferencia_id AND multa_id=NEW.multa_id AND resultado='aceito' AND valor_confirmado=NEW.valor AND pagamento_confirmado_em=NEW.pago_em;
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Quitação exige uma conferência financeira aceita com os mesmos dados.'; END IF;
END$$

CREATE TRIGGER tg_multa_bu BEFORE UPDATE ON multas FOR EACH ROW
BEGIN

  DECLARE v_count INT;
  IF OLD.veiculo_id<>NEW.veiculo_id OR OLD.protocolo<>NEW.protocolo OR OLD.ocorrido_em<>NEW.ocorrido_em OR OLD.precisao_ocorrencia<>NEW.precisao_ocorrencia OR NOT(OLD.fim_dia_ocorrencia_utc<=>NEW.fim_dia_ocorrencia_utc) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Identidade da autuação é imutável.'; END IF;
  IF NEW.situacao IN ('aguardando_comprovante','em_conferencia','quitada') AND NEW.responsabilidade_atual_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Confirme um responsável antes de processar comprovante ou quitação.'; END IF;
  SELECT COUNT(*) INTO v_count FROM pagamentos_multa WHERE multa_id=OLD.id;
  IF NEW.situacao='quitada' AND v_count=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Arquivo enviado não é quitação: confirme o pagamento.'; END IF;
  IF v_count>0 AND (NEW.situacao<>'quitada' OR NEW.valor<>OLD.valor OR NOT(NEW.responsabilidade_atual_id<=>OLD.responsabilidade_atual_id)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Multa quitada exige fluxo de estorno específico, não edição direta.'; END IF;
  IF NEW.valor<>OLD.valor THEN
    SELECT COUNT(*) INTO v_count FROM multa_comprovantes WHERE multa_id=OLD.id;
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Valor com comprovantes históricos não pode ser reescrito.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_comprovante_bi BEFORE INSERT ON multa_comprovantes FOR EACH ROW
BEGIN

  DECLARE v_count INT;
  SELECT COUNT(*) INTO v_count FROM multas m JOIN arquivos a ON a.id=NEW.arquivo_id
  WHERE m.id=NEW.multa_id AND m.responsabilidade_atual_id=NEW.responsabilidade_id AND m.situacao='aguardando_comprovante' AND a.situacao='disponivel';
  IF v_count=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Comprovante exige responsabilidade atual e arquivo disponível.'; END IF;
END$$

CREATE TRIGGER tg_conferencia_bi BEFORE INSERT ON multa_conferencias FOR EACH ROW
BEGIN

  DECLARE v_ok INT; DECLARE v_ator BIGINT UNSIGNED; DECLARE v_autor BIGINT UNSIGNED; DECLARE v_responsavel BIGINT UNSIGNED;
  SELECT COUNT(*) INTO v_ok FROM multa_comprovantes c JOIN multas m ON m.id=c.multa_id
  WHERE c.id=NEW.comprovante_id AND c.multa_id=NEW.multa_id AND m.situacao='em_conferencia'
  AND c.numero=(SELECT MAX(numero) FROM multa_comprovantes WHERE multa_id=NEW.multa_id);
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Conferência exige o último comprovante pendente.'; END IF;
  SELECT up.usuario_id,r.responsavel_id INTO v_autor,v_responsavel FROM multa_comprovantes c JOIN usuario_perfis up ON up.id=c.enviado_por_vinculo_id JOIN multa_responsabilidades r ON r.id=c.responsabilidade_id WHERE c.id=NEW.comprovante_id;
  SELECT usuario_id INTO v_ator FROM usuario_perfis WHERE id=NEW.conferido_por_vinculo_id;
  IF v_ator=v_autor OR v_ator=v_responsavel THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Não pode conferir o próprio comprovante.'; END IF;
END$$

CREATE TRIGGER tg_pagamento_despesa_bi BEFORE INSERT ON pagamentos_despesa FOR EACH ROW
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM despesas d JOIN arquivos a ON a.id=NEW.comprovante_arquivo_id WHERE d.id=NEW.despesa_id AND d.situacao='aprovada' AND d.valor=NEW.valor AND a.situacao='disponivel';
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Pagamento exige despesa aprovada, valor integral e comprovante disponível.'; END IF;
END$$

CREATE TRIGGER tg_despesa_bu BEFORE UPDATE ON despesas FOR EACH ROW
BEGIN

  DECLARE v_count INT;
  SELECT COUNT(*) INTO v_count FROM pagamentos_despesa WHERE despesa_id=OLD.id;
  IF NEW.situacao='paga' AND v_count=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Despesa paga exige registro de pagamento confirmado.'; END IF;
  IF v_count>0 AND (NEW.situacao<>'paga' OR NEW.valor<>OLD.valor OR NEW.veiculo_id<>OLD.veiculo_id OR NEW.categoria_id<>OLD.categoria_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Despesa paga não pode ser reescrita ou cancelada sem estorno.'; END IF;
END$$

CREATE TRIGGER tg_abastecimento_bi BEFORE INSERT ON abastecimentos FOR EACH ROW
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM despesas d JOIN categorias_despesa c ON c.id=d.categoria_id
  WHERE d.id=NEW.despesa_id AND c.codigo='abastecimento' AND ABS(d.valor-ROUND(NEW.quantidade*NEW.preco_unitario,2))<=0.01;
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Abastecimento deve corresponder à categoria e ao total da despesa.'; END IF;
END$$

CREATE TRIGGER tg_manutencao_bi BEFORE INSERT ON manutencoes FOR EACH ROW
BEGIN

  DECLARE v_ok INT;
  IF NEW.reserva_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_ok FROM reservas WHERE id=NEW.reserva_id AND tipo='manutencao' AND situacao='ativa' AND inicio=NEW.inicio_previsto AND fim=NEW.fim_previsto;
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Manutenção exige bloqueio correspondente da agenda.'; END IF;
  END IF;
  IF NEW.situacao IN ('planejada','em_execucao') AND NEW.reserva_id IS NULL THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Manutenção aberta precisa bloquear a agenda.'; END IF;
END$$

CREATE TRIGGER tg_posicao_bi BEFORE INSERT ON posicoes_rastreamento FOR EACH ROW
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM veiculo_rastreadores WHERE id=NEW.instalacao_id AND instalado_em<=NEW.capturado_em AND (removido_em IS NULL OR removido_em>NEW.capturado_em);
  IF v_ok=0 OR NEW.capturado_em>TIMESTAMPADD(MINUTE,5,UTC_TIMESTAMP(6)) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posição não corresponde ao período de instalação do dispositivo.'; END IF;
  IF NEW.viagem_id IS NOT NULL THEN
    SELECT COUNT(*) INTO v_ok FROM viagens WHERE id=NEW.viagem_id AND saida_real IS NOT NULL AND saida_real<=NEW.capturado_em AND (retorno_real IS NULL OR retorno_real>=NEW.capturado_em);
    IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Posição vinculada deve estar no período real da viagem.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_arquivo_bu BEFORE UPDATE ON arquivos FOR EACH ROW
BEGIN

  IF OLD.situacao<>'pendente' AND (NEW.chave_armazenamento<>OLD.chave_armazenamento OR NEW.nome_original<>OLD.nome_original OR NEW.tipo_mime<>OLD.tipo_mime OR NEW.tamanho_bytes<>OLD.tamanho_bytes OR NEW.sha256<>OLD.sha256 OR NEW.enviado_por<>OLD.enviado_por) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Arquivo publicado é imutável; envie uma nova versão.'; END IF;
END$$

CREATE TRIGGER tg_instalacao_rastreador_bu BEFORE UPDATE ON veiculo_rastreadores FOR EACH ROW
BEGIN

  IF OLD.removido_em IS NOT NULL OR OLD.veiculo_id<>NEW.veiculo_id OR OLD.rastreador_id<>NEW.rastreador_id OR OLD.instalado_em<>NEW.instalado_em OR OLD.instalado_por<>NEW.instalado_por THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Encerre a instalação e registre outro vínculo, preservando o histórico.'; END IF;
END$$

CREATE TRIGGER tg_exportacao_campos_bi BEFORE INSERT ON exportacao_campos FOR EACH ROW
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM exportacoes WHERE id=NEW.exportacao_id AND situacao='previa' AND total_registros=0;
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Seleção da exportação já foi consolidada; prepare outra prévia.'; END IF;
END$$

CREATE TRIGGER tg_exportacao_registros_bi BEFORE INSERT ON exportacao_registros FOR EACH ROW
BEGIN

  DECLARE v_ok INT;
  SELECT COUNT(*) INTO v_ok FROM exportacoes WHERE id=NEW.exportacao_id AND situacao='previa' AND total_registros=0;
  IF v_ok=0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Seleção da exportação já foi consolidada; prepare outra prévia.'; END IF;
END$$

CREATE TRIGGER tg_pneu_instalacao_bi BEFORE INSERT ON pneu_instalacoes FOR EACH ROW
BEGIN

  DECLARE v_estado VARCHAR(30);
  SELECT situacao INTO v_estado FROM pneus WHERE id=NEW.pneu_id FOR UPDATE;
  IF v_estado IS NULL OR v_estado<>'estoque' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Somente pneu em estoque pode ser instalado.'; END IF;
END$$

CREATE TRIGGER tg_pneu_instalacao_ai AFTER INSERT ON pneu_instalacoes FOR EACH ROW
BEGIN
  UPDATE pneus SET situacao='instalado' WHERE id=NEW.pneu_id;
END$$

CREATE TRIGGER tg_pneu_instalacao_bu BEFORE UPDATE ON pneu_instalacoes FOR EACH ROW
BEGIN

  IF OLD.removido_em IS NOT NULL OR OLD.pneu_id<>NEW.pneu_id OR OLD.veiculo_id<>NEW.veiculo_id OR OLD.posicao<>NEW.posicao OR OLD.instalado_em<>NEW.instalado_em OR OLD.quilometragem_instalacao<>NEW.quilometragem_instalacao THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Encerre a instalação e crie outra para registrar o rodízio.'; END IF;
END$$

CREATE TRIGGER tg_pneu_instalacao_au AFTER UPDATE ON pneu_instalacoes FOR EACH ROW
BEGIN

  IF OLD.removido_em IS NULL AND NEW.removido_em IS NOT NULL THEN UPDATE pneus SET situacao='estoque' WHERE id=NEW.pneu_id; END IF;
END$$

CREATE TRIGGER tg_pneu_bu BEFORE UPDATE ON pneus FOR EACH ROW
BEGIN

  DECLARE v_count INT;
  IF NEW.situacao='descartado' THEN
    SELECT COUNT(*) INTO v_count FROM pneu_instalacoes WHERE pneu_id=OLD.id AND removido_em IS NULL;
    IF v_count>0 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Remova o pneu do veículo antes de descartá-lo.'; END IF;
  END IF;
END$$

CREATE TRIGGER tg_imutavel_auditoria_bu BEFORE UPDATE ON auditoria FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_auditoria_bd BEFORE DELETE ON auditoria FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_solicitacao_eventos_bu BEFORE UPDATE ON solicitacao_eventos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_solicitacao_eventos_bd BEFORE DELETE ON solicitacao_eventos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_multa_responsabilidades_bu BEFORE UPDATE ON multa_responsabilidades FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_multa_responsabilidades_bd BEFORE DELETE ON multa_responsabilidades FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_multa_eventos_bu BEFORE UPDATE ON multa_eventos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_multa_eventos_bd BEFORE DELETE ON multa_eventos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_multa_comprovantes_bu BEFORE UPDATE ON multa_comprovantes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_multa_comprovantes_bd BEFORE DELETE ON multa_comprovantes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_multa_conferencias_bu BEFORE UPDATE ON multa_conferencias FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_multa_conferencias_bd BEFORE DELETE ON multa_conferencias FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_pagamentos_multa_bu BEFORE UPDATE ON pagamentos_multa FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_pagamentos_multa_bd BEFORE DELETE ON pagamentos_multa FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_pagamentos_despesa_bu BEFORE UPDATE ON pagamentos_despesa FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_pagamentos_despesa_bd BEFORE DELETE ON pagamentos_despesa FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_despesa_eventos_bu BEFORE UPDATE ON despesa_eventos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_despesa_eventos_bd BEFORE DELETE ON despesa_eventos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_viagem_ocorrencias_bu BEFORE UPDATE ON viagem_ocorrencias FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_viagem_ocorrencias_bd BEFORE DELETE ON viagem_ocorrencias FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_posicoes_rastreamento_bu BEFORE UPDATE ON posicoes_rastreamento FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_posicoes_rastreamento_bd BEFORE DELETE ON posicoes_rastreamento FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_posicoes_manuais_bu BEFORE UPDATE ON posicoes_manuais FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_posicoes_manuais_bd BEFORE DELETE ON posicoes_manuais FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_chamado_mensagens_bu BEFORE UPDATE ON chamado_mensagens FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_chamado_mensagens_bd BEFORE DELETE ON chamado_mensagens FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_chamado_eventos_bu BEFORE UPDATE ON chamado_eventos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_chamado_eventos_bd BEFORE DELETE ON chamado_eventos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_notificacao_eventos_bu BEFORE UPDATE ON notificacao_eventos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_notificacao_eventos_bd BEFORE DELETE ON notificacao_eventos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_permissoes_bu BEFORE UPDATE ON permissoes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_permissoes_bd BEFORE DELETE ON permissoes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_modulo_acoes_bu BEFORE UPDATE ON modulo_acoes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_modulo_acoes_bd BEFORE DELETE ON modulo_acoes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_exportacao_campos_bu BEFORE UPDATE ON exportacao_campos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_exportacao_campos_bd BEFORE DELETE ON exportacao_campos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_exportacao_registros_bu BEFORE UPDATE ON exportacao_registros FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_exportacao_registros_bd BEFORE DELETE ON exportacao_registros FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_documentos_veiculo_bu BEFORE UPDATE ON documentos_veiculo FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_imutavel_documentos_veiculo_bd BEFORE DELETE ON documentos_veiculo FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Registro histórico ou catálogo estável: edição/exclusão não permitida.';
END$$

CREATE TRIGGER tg_preservar_veiculos_bd BEFORE DELETE ON veiculos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_viagens_bd BEFORE DELETE ON viagens FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_multas_bd BEFORE DELETE ON multas FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_despesas_bd BEFORE DELETE ON despesas FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_manutencoes_bd BEFORE DELETE ON manutencoes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_pneus_bd BEFORE DELETE ON pneus FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_pneu_instalacoes_bd BEFORE DELETE ON pneu_instalacoes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_arquivos_bd BEFORE DELETE ON arquivos FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_chamados_bd BEFORE DELETE ON chamados FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_rotas_sistema_bd BEFORE DELETE ON rotas_sistema FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_veiculo_rastreadores_bd BEFORE DELETE ON veiculo_rastreadores FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_viagem_checklists_bd BEFORE DELETE ON viagem_checklists FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

CREATE TRIGGER tg_preservar_solicitacoes_bd BEFORE DELETE ON solicitacoes FOR EACH ROW
BEGIN
  SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Preserve o histórico: encerre, arquive ou desative o registro.';
END$$

DELIMITER ;


-- Verificação simples de término da importação.
SELECT 'Frota instalada: versão 1.0.2' AS resultado, COUNT(*) AS tabelas FROM information_schema.tables WHERE table_schema=DATABASE() AND table_type='BASE TABLE';
