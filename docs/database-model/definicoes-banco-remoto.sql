-- Snapshot de documentação; não executar como migração.
-- Capturado em 16/10/2026 22:42 Manaus. Schema e definers sanitizados.

-- Objeto: abastecimentos
CREATE TABLE `abastecimentos` (
  `despesa_id` bigint(20) unsigned NOT NULL,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `combustivel` enum('gasolina','etanol','diesel','gnv','eletricidade','outro') NOT NULL,
  `quantidade` decimal(12,3) NOT NULL,
  `unidade_medida` enum('litro','m3','kwh') NOT NULL DEFAULT 'litro',
  `preco_unitario` decimal(12,4) NOT NULL,
  `quilometragem` decimal(12,1) NOT NULL,
  `tanque_completo` tinyint(3) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`despesa_id`),
  KEY `fk_abastecimento_despesa` (`despesa_id`,`veiculo_id`),
  CONSTRAINT `fk_abastecimento_despesa` FOREIGN KEY (`despesa_id`, `veiculo_id`) REFERENCES `despesas` (`id`, `veiculo_id`),
  CONSTRAINT `ck_abastecimento_valores` CHECK (`quantidade` > 0 and `preco_unitario` > 0 and `quilometragem` >= 0),
  CONSTRAINT `ck_abastecimento_tanque` CHECK (`tanque_completo` in (0,1)),
  CONSTRAINT `ck_abastecimento_medida` CHECK (`combustivel` in ('gasolina','etanol','diesel') and `unidade_medida` = 'litro' or `combustivel` = 'gnv' and `unidade_medida` = 'm3' or `combustivel` = 'eletricidade' and `unidade_medida` = 'kwh' or `combustivel` = 'outro')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Detalhes físicos de uma despesa de abastecimento.';

-- Objeto: acoes
CREATE TABLE `acoes` (
  `codigo` varchar(40) NOT NULL,
  `nome` varchar(100) NOT NULL,
  PRIMARY KEY (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ações verificadas pelo servidor, sem privilégio implícito por nome de perfil.';

-- Objeto: anexos
CREATE TABLE `anexos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `arquivo_id` bigint(20) unsigned NOT NULL,
  `revisao_id` bigint(20) unsigned DEFAULT NULL,
  `ocorrencia_id` bigint(20) unsigned DEFAULT NULL,
  `checklist_id` bigint(20) unsigned DEFAULT NULL,
  `despesa_id` bigint(20) unsigned DEFAULT NULL,
  `manutencao_id` bigint(20) unsigned DEFAULT NULL,
  `multa_id` bigint(20) unsigned DEFAULT NULL,
  `chamado_id` bigint(20) unsigned DEFAULT NULL,
  `mensagem_id` bigint(20) unsigned DEFAULT NULL,
  `descricao` varchar(255) DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `fk_anexo_arquivo` (`arquivo_id`),
  KEY `fk_anexo_revisao` (`revisao_id`),
  KEY `fk_anexo_ocorrencia` (`ocorrencia_id`),
  KEY `fk_anexo_checklist` (`checklist_id`),
  KEY `fk_anexo_despesa` (`despesa_id`),
  KEY `fk_anexo_manutencao` (`manutencao_id`),
  KEY `fk_anexo_multa` (`multa_id`),
  KEY `fk_anexo_chamado` (`chamado_id`),
  KEY `fk_anexo_mensagem` (`mensagem_id`),
  CONSTRAINT `fk_anexo_arquivo` FOREIGN KEY (`arquivo_id`) REFERENCES `arquivos` (`id`),
  CONSTRAINT `fk_anexo_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`),
  CONSTRAINT `fk_anexo_checklist` FOREIGN KEY (`checklist_id`) REFERENCES `viagem_checklists` (`id`),
  CONSTRAINT `fk_anexo_despesa` FOREIGN KEY (`despesa_id`) REFERENCES `despesas` (`id`),
  CONSTRAINT `fk_anexo_manutencao` FOREIGN KEY (`manutencao_id`) REFERENCES `manutencoes` (`id`),
  CONSTRAINT `fk_anexo_mensagem` FOREIGN KEY (`mensagem_id`) REFERENCES `chamado_mensagens` (`id`),
  CONSTRAINT `fk_anexo_multa` FOREIGN KEY (`multa_id`) REFERENCES `multas` (`id`),
  CONSTRAINT `fk_anexo_ocorrencia` FOREIGN KEY (`ocorrencia_id`) REFERENCES `viagem_ocorrencias` (`id`),
  CONSTRAINT `fk_anexo_revisao` FOREIGN KEY (`revisao_id`) REFERENCES `solicitacao_revisoes` (`id`),
  CONSTRAINT `ck_anexo_alvo` CHECK ((`revisao_id` is not null) + (`ocorrencia_id` is not null) + (`checklist_id` is not null) + (`despesa_id` is not null) + (`manutencao_id` is not null) + (`multa_id` is not null) + (`chamado_id` is not null) + (`mensagem_id` is not null) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vínculos tipados de arquivo. Exatamente um destino por anexo, todos com FK.';

-- Objeto: aprovacoes_mfa
CREATE TABLE `aprovacoes_mfa` (
  `recuperacao_id` bigint(20) unsigned NOT NULL,
  `aprovada_por` bigint(20) unsigned NOT NULL,
  `vinculo_id` bigint(20) unsigned NOT NULL,
  `aprovada_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`recuperacao_id`,`aprovada_por`),
  KEY `fk_aprovacao_mfa_usuario` (`aprovada_por`),
  KEY `fk_aprovacao_mfa_vinculo` (`vinculo_id`,`aprovada_por`),
  CONSTRAINT `fk_aprovacao_mfa_pedido` FOREIGN KEY (`recuperacao_id`) REFERENCES `recuperacoes_mfa` (`id`),
  CONSTRAINT `fk_aprovacao_mfa_usuario` FOREIGN KEY (`aprovada_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_aprovacao_mfa_vinculo` FOREIGN KEY (`vinculo_id`, `aprovada_por`) REFERENCES `usuario_perfis` (`id`, `usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Objeto: arquivos
CREATE TABLE `arquivos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `enviado_por` bigint(20) unsigned NOT NULL,
  `chave_armazenamento` varchar(255) NOT NULL,
  `nome_original` varchar(255) NOT NULL,
  `tipo_mime` varchar(120) NOT NULL,
  `tamanho_bytes` bigint(20) unsigned NOT NULL,
  `sha256` binary(32) NOT NULL,
  `situacao` enum('pendente','disponivel','bloqueado','arquivado') NOT NULL DEFAULT 'pendente',
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_arquivo_chave` (`chave_armazenamento`),
  KEY `ix_arquivo_hash` (`sha256`),
  KEY `fk_arquivo_autor` (`enviado_por`),
  CONSTRAINT `fk_arquivo_autor` FOREIGN KEY (`enviado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_arquivo_tamanho` CHECK (`tamanho_bytes` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Metadados de documentos no armazenamento privado; não guarda bytes no banco.';

-- Objeto: auditoria
CREATE TABLE `auditoria` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `ator_usuario_id` bigint(20) unsigned DEFAULT NULL,
  `ator_vinculo_id` bigint(20) unsigned DEFAULT NULL,
  `sessao_id` bigint(20) unsigned DEFAULT NULL,
  `ator_nome_snapshot` varchar(150) DEFAULT NULL,
  `perfil_nome_snapshot` varchar(100) DEFAULT NULL,
  `perfil_codigo_snapshot` varchar(60) DEFAULT NULL,
  `evento` varchar(80) NOT NULL,
  `entidade` varchar(60) DEFAULT NULL,
  `entidade_id` bigint(20) unsigned DEFAULT NULL,
  `correlacao` char(36) DEFAULT NULL,
  `descricao` varchar(1000) DEFAULT NULL,
  `antes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`antes`)),
  `depois` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`depois`)),
  `ip` varbinary(16) DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `ix_auditoria_data` (`criado_em`,`id`),
  KEY `ix_auditoria_ator` (`ator_usuario_id`,`criado_em`),
  KEY `ix_auditoria_entidade` (`entidade`,`entidade_id`,`criado_em`),
  KEY `fk_auditoria_vinculo` (`ator_vinculo_id`,`ator_usuario_id`),
  KEY `fk_auditoria_sessao` (`sessao_id`,`ator_usuario_id`),
  CONSTRAINT `fk_auditoria_sessao` FOREIGN KEY (`sessao_id`, `ator_usuario_id`) REFERENCES `sessoes` (`id`, `usuario_id`),
  CONSTRAINT `fk_auditoria_usuario` FOREIGN KEY (`ator_usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_auditoria_vinculo` FOREIGN KEY (`ator_vinculo_id`, `ator_usuario_id`) REFERENCES `usuario_perfis` (`id`, `usuario_id`),
  CONSTRAINT `ck_auditoria_contexto` CHECK (`ator_vinculo_id` is null and `sessao_id` is null or `ator_usuario_id` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Log acrescentado sem edição. Não registrar senhas, tokens ou bytes de arquivos.';

-- Objeto: categorias_chamado
CREATE TABLE `categorias_chamado` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `ativa` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categoria_chamado_nome` (`nome`),
  CONSTRAINT `ck_categoria_chamado_ativa` CHECK (`ativa` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorias de atendimento interno.';

-- Objeto: categorias_despesa
CREATE TABLE `categorias_despesa` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) NOT NULL,
  `nome` varchar(80) NOT NULL,
  `ativa` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categoria_despesa_codigo` (`codigo`),
  CONSTRAINT `ck_categoria_despesa_ativa` CHECK (`ativa` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorias de custo separadas dos registros de multa.';

-- Objeto: categorias_veiculo
CREATE TABLE `categorias_veiculo` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(60) NOT NULL,
  `categoria_cnh_requerida` char(1) NOT NULL DEFAULT 'B',
  `ativa` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_categoria_veiculo_nome` (`nome`),
  CONSTRAINT `ck_categoria_cnh` CHECK (`categoria_cnh_requerida` in ('A','B','C','D','E')),
  CONSTRAINT `ck_categoria_veiculo_ativa` CHECK (`ativa` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Categorias de veículos e categoria mínima de habilitação.';

-- Objeto: chamados
CREATE TABLE `chamados` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `protocolo` varchar(40) NOT NULL,
  `solicitante_id` bigint(20) unsigned NOT NULL,
  `unidade_id` bigint(20) unsigned NOT NULL,
  `perfil_contexto_id` bigint(20) unsigned NOT NULL,
  `categoria_id` smallint(5) unsigned NOT NULL,
  `assunto` varchar(200) NOT NULL,
  `descricao` text NOT NULL,
  `pagina_contexto` varchar(255) DEFAULT NULL,
  `situacao` enum('aberto','em_atendimento','aguardando_solicitante','resolvido') NOT NULL DEFAULT 'aberto',
  `atribuido_a` bigint(20) unsigned DEFAULT NULL,
  `resolvido_em` datetime(6) DEFAULT NULL,
  `versao` bigint(20) unsigned NOT NULL DEFAULT 1,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `atualizado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_chamado_protocolo` (`protocolo`),
  KEY `ix_chamado_proprios` (`solicitante_id`,`situacao`,`criado_em`),
  KEY `ix_chamado_fila` (`unidade_id`,`situacao`,`atribuido_a`,`criado_em`),
  KEY `fk_chamado_perfil` (`perfil_contexto_id`),
  KEY `fk_chamado_categoria` (`categoria_id`),
  KEY `fk_chamado_atendente` (`atribuido_a`),
  CONSTRAINT `fk_chamado_atendente` FOREIGN KEY (`atribuido_a`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_chamado_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_chamado` (`id`),
  CONSTRAINT `fk_chamado_perfil` FOREIGN KEY (`perfil_contexto_id`) REFERENCES `perfis` (`id`),
  CONSTRAINT `fk_chamado_solicitante` FOREIGN KEY (`solicitante_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_chamado_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `ck_chamado_resolvido` CHECK (`situacao` = 'resolvido' and `resolvido_em` is not null or `situacao` <> 'resolvido' and `resolvido_em` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Solicitante vê os próprios chamados; filas dependem de permissão.';

-- Objeto: chamado_eventos
CREATE TABLE `chamado_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chamado_id` bigint(20) unsigned NOT NULL,
  `ator_vinculo_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `situacao_anterior` varchar(40) DEFAULT NULL,
  `situacao_nova` varchar(40) NOT NULL,
  `motivo` text DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `fk_ce_chamado` (`chamado_id`),
  KEY `fk_ce_vinculo` (`ator_vinculo_id`),
  CONSTRAINT `fk_ce_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`),
  CONSTRAINT `fk_ce_vinculo` FOREIGN KEY (`ator_vinculo_id`) REFERENCES `usuario_perfis` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Abertura, atribuição, mudança de estado e resolução.';

-- Objeto: chamado_mensagens
CREATE TABLE `chamado_mensagens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chamado_id` bigint(20) unsigned NOT NULL,
  `autor_vinculo_id` bigint(20) unsigned NOT NULL,
  `mensagem` text NOT NULL,
  `interna` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `ix_cm_conversa` (`chamado_id`,`criado_em`,`id`),
  KEY `fk_cm_autor` (`autor_vinculo_id`),
  CONSTRAINT `fk_cm_autor` FOREIGN KEY (`autor_vinculo_id`) REFERENCES `usuario_perfis` (`id`),
  CONSTRAINT `fk_cm_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`),
  CONSTRAINT `ck_cm_interna` CHECK (`interna` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Conversa do chamado; mensagens internas não são exibidas ao solicitante.';

-- Objeto: checklist_itens
CREATE TABLE `checklist_itens` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(40) NOT NULL,
  `descricao` varchar(150) NOT NULL,
  `obrigatorio` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `ativo` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `ordem` smallint(5) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_checklist_codigo` (`codigo`),
  CONSTRAINT `ck_checklist_flags` CHECK (`obrigatorio` in (0,1) and `ativo` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Itens padronizados das vistorias de saída e retorno.';

-- Objeto: configuracao_sistema
CREATE TABLE `configuracao_sistema` (
  `id` tinyint(3) unsigned NOT NULL,
  `nome_sistema` varchar(120) NOT NULL,
  `nome_orgao` varchar(150) NOT NULL,
  `sigla_orgao` varchar(30) DEFAULT NULL,
  `fuso_horario` varchar(64) NOT NULL DEFAULT 'America/Porto_Velho',
  `moeda` char(3) NOT NULL DEFAULT 'BRL',
  `sessao_inatividade_minutos` smallint(5) unsigned NOT NULL DEFAULT 30,
  `limite_sem_comunicacao_minutos` smallint(5) unsigned NOT NULL DEFAULT 15,
  `logo_arquivo_id` bigint(20) unsigned DEFAULT NULL,
  `bootstrap_concluido` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `atualizado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `fk_config_logo` (`logo_arquivo_id`),
  CONSTRAINT `fk_config_logo` FOREIGN KEY (`logo_arquivo_id`) REFERENCES `arquivos` (`id`),
  CONSTRAINT `ck_config_unica` CHECK (`id` = 1),
  CONSTRAINT `ck_config_sessao` CHECK (`sessao_inatividade_minutos` between 5 and 1440),
  CONSTRAINT `ck_config_bootstrap` CHECK (`bootstrap_concluido` in (0,1)),
  CONSTRAINT `ck_config_comunicacao` CHECK (`limite_sem_comunicacao_minutos` > 0),
  CONSTRAINT `ck_config_moeda` CHECK (`moeda` = 'BRL')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Uma única configuração do órgão; também serializa operações administrativas.';

-- Objeto: confirmacoes_identidade
CREATE TABLE `confirmacoes_identidade` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `vinculo_id` bigint(20) unsigned DEFAULT NULL,
  `finalidade` enum('ativacao','email') NOT NULL,
  `email_destino` varchar(254) NOT NULL,
  `versao_seguranca` bigint(20) unsigned NOT NULL,
  `token_hash` binary(32) NOT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `expira_em` datetime(6) NOT NULL,
  `usado_em` datetime(6) DEFAULT NULL,
  `invalidado_em` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_confirmacao_token` (`token_hash`),
  KEY `ix_confirmacao_usuario_versao` (`usuario_id`,`versao_seguranca`,`finalidade`,`invalidado_em`,`usado_em`),
  KEY `fk_confirmacao_vinculo` (`vinculo_id`,`usuario_id`),
  CONSTRAINT `fk_confirmacao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_confirmacao_vinculo` FOREIGN KEY (`vinculo_id`, `usuario_id`) REFERENCES `usuario_perfis` (`id`, `usuario_id`),
  CONSTRAINT `ck_confirmacao_finalidade` CHECK (`finalidade` <> 'ativacao' or `vinculo_id` is not null),
  CONSTRAINT `ck_confirmacao_validade` CHECK (`expira_em` > `criado_em` and `versao_seguranca` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Objeto: controle_vinculos
CREATE TABLE `controle_vinculos` (
  `vinculo_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `perfil_id` bigint(20) unsigned NOT NULL,
  `unidade_id` bigint(20) unsigned NOT NULL,
  `vigente_desde` datetime(6) NOT NULL,
  `vigente_ate` datetime(6) DEFAULT NULL,
  `ativo_vinculo` tinyint(3) unsigned NOT NULL,
  `ativo_usuario` tinyint(3) unsigned NOT NULL,
  `ativo_perfil` tinyint(3) unsigned NOT NULL,
  `ativo_unidade` tinyint(3) unsigned NOT NULL,
  `pode_administrar` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`vinculo_id`),
  KEY `ix_cv_admin` (`pode_administrar`,`ativo_vinculo`,`vigente_ate`),
  KEY `ix_cv_overlap` (`usuario_id`,`perfil_id`,`ativo_vinculo`,`vigente_desde`,`vigente_ate`),
  KEY `fk_cv_perfil` (`perfil_id`),
  KEY `fk_cv_unidade` (`unidade_id`),
  CONSTRAINT `fk_cv_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`),
  CONSTRAINT `fk_cv_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `fk_cv_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_cv_vinculo` FOREIGN KEY (`vinculo_id`) REFERENCES `usuario_perfis` (`id`),
  CONSTRAINT `ck_cv_flags` CHECK (`ativo_vinculo` in (0,1) and `ativo_usuario` in (0,1) and `ativo_perfil` in (0,1) and `ativo_unidade` in (0,1) and `pode_administrar` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Materialização técnica mantida por triggers para locks de acesso e proteção administrativa.';

-- Objeto: credenciais_mfa
CREATE TABLE `credenciais_mfa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `credential_id` varchar(1366) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `registro` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`registro`)),
  `criada_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `usada_em` datetime(6) DEFAULT NULL,
  `revogada_em` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_credencial_mfa` (`credential_id`),
  KEY `ix_credenciais_usuario` (`usuario_id`,`revogada_em`),
  CONSTRAINT `fk_credencial_mfa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Objeto: desafios_mfa
CREATE TABLE `desafios_mfa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `sessao_id` bigint(20) unsigned NOT NULL,
  `vinculo_id` bigint(20) unsigned NOT NULL,
  `versao_seguranca` bigint(20) unsigned NOT NULL,
  `http_hash` binary(32) NOT NULL,
  `finalidade` enum('registration','assertion') NOT NULL,
  `opcoes` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`opcoes`)),
  `expira_em` datetime(6) NOT NULL,
  `usado_em` datetime(6) DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `ix_desafio_sessao` (`sessao_id`,`usado_em`),
  KEY `fk_desafio_mfa_usuario` (`usuario_id`),
  KEY `fk_desafio_mfa_sessao` (`sessao_id`,`usuario_id`),
  KEY `fk_desafio_mfa_vinculo` (`vinculo_id`,`usuario_id`),
  CONSTRAINT `fk_desafio_mfa_sessao` FOREIGN KEY (`sessao_id`, `usuario_id`) REFERENCES `sessoes` (`id`, `usuario_id`),
  CONSTRAINT `fk_desafio_mfa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_desafio_mfa_vinculo` FOREIGN KEY (`vinculo_id`, `usuario_id`) REFERENCES `usuario_perfis` (`id`, `usuario_id`),
  CONSTRAINT `ck_desafio_mfa_prazo` CHECK (`expira_em` > `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Objeto: despesas
CREATE TABLE `despesas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `protocolo` varchar(40) NOT NULL,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `unidade_id` bigint(20) unsigned NOT NULL,
  `categoria_id` smallint(5) unsigned NOT NULL,
  `fornecedor_id` bigint(20) unsigned DEFAULT NULL,
  `data_despesa` date NOT NULL,
  `valor` decimal(13,2) NOT NULL,
  `descricao` text NOT NULL,
  `numero_documento` varchar(100) DEFAULT NULL,
  `situacao` enum('registrada','em_conferencia','aprovada','paga','cancelada') NOT NULL DEFAULT 'registrada',
  `criado_por` bigint(20) unsigned NOT NULL,
  `versao` bigint(20) unsigned NOT NULL DEFAULT 1,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `atualizado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_despesa_protocolo` (`protocolo`),
  UNIQUE KEY `uq_despesa_veiculo` (`id`,`veiculo_id`),
  KEY `ix_despesa_relatorio` (`unidade_id`,`data_despesa`,`categoria_id`,`situacao`),
  KEY `ix_despesa_veiculo_data` (`veiculo_id`,`data_despesa`),
  KEY `fk_despesa_categoria` (`categoria_id`),
  KEY `fk_despesa_fornecedor` (`fornecedor_id`),
  KEY `fk_despesa_autor` (`criado_por`),
  CONSTRAINT `fk_despesa_autor` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_despesa_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_despesa` (`id`),
  CONSTRAINT `fk_despesa_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`),
  CONSTRAINT `fk_despesa_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `fk_despesa_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `ck_despesa_valor` CHECK (`valor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lançamento financeiro do veículo, em BRL com DECIMAL.';

-- Objeto: despesa_eventos
CREATE TABLE `despesa_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `despesa_id` bigint(20) unsigned NOT NULL,
  `ator_vinculo_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `situacao_anterior` varchar(40) DEFAULT NULL,
  `situacao_nova` varchar(40) NOT NULL,
  `motivo` text DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `fk_de_despesa` (`despesa_id`),
  KEY `fk_de_vinculo` (`ator_vinculo_id`),
  CONSTRAINT `fk_de_despesa` FOREIGN KEY (`despesa_id`) REFERENCES `despesas` (`id`),
  CONSTRAINT `fk_de_vinculo` FOREIGN KEY (`ator_vinculo_id`) REFERENCES `usuario_perfis` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico da conferência e decisões financeiras de despesas.';

-- Objeto: documentos_veiculo
CREATE TABLE `documentos_veiculo` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `arquivo_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(60) NOT NULL,
  `numero` varchar(80) DEFAULT NULL,
  `exercicio` smallint(5) unsigned DEFAULT NULL,
  `emitido_em` date DEFAULT NULL,
  `vence_em` date DEFAULT NULL,
  `substitui_documento_id` bigint(20) unsigned DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_documento_veiculo_arquivo` (`veiculo_id`,`arquivo_id`),
  KEY `ix_documento_vencimento` (`vence_em`,`veiculo_id`),
  KEY `fk_dv_arquivo` (`arquivo_id`),
  KEY `fk_dv_anterior` (`substitui_documento_id`),
  CONSTRAINT `fk_dv_anterior` FOREIGN KEY (`substitui_documento_id`) REFERENCES `documentos_veiculo` (`id`),
  CONSTRAINT `fk_dv_arquivo` FOREIGN KEY (`arquivo_id`) REFERENCES `arquivos` (`id`),
  CONSTRAINT `fk_dv_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `ck_dv_datas` CHECK (`emitido_em` is null or `vence_em` is null or `vence_em` >= `emitido_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Documentos e suas versões; vencimento independente do arquivo.';

-- Objeto: email_outbox
CREATE TABLE `email_outbox` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `evento_chave` char(36) NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `finalidade` enum('ativacao','email','recuperacao','aviso') NOT NULL,
  `versao_seguranca` bigint(20) unsigned NOT NULL,
  `destinatario_cifrado` text DEFAULT NULL,
  `token_cifrado` text DEFAULT NULL,
  `recuperacao_id` bigint(20) unsigned DEFAULT NULL,
  `confirmacao_id` bigint(20) unsigned DEFAULT NULL,
  `aviso_codigo` varchar(80) DEFAULT NULL,
  `estado` enum('pendente','enfileirado','enviando','aceito','cancelado','falhou') NOT NULL DEFAULT 'pendente',
  `tentativas` smallint(5) unsigned NOT NULL DEFAULT 0,
  `proxima_tentativa_em` datetime(6) NOT NULL,
  `expira_em` datetime(6) NOT NULL,
  `lease_id` char(36) DEFAULT NULL,
  `lease_ate` datetime(6) DEFAULT NULL,
  `aceito_smtp_em` datetime(6) DEFAULT NULL,
  `provedor_id` varchar(150) DEFAULT NULL,
  `codigo_erro` varchar(60) DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_outbox_evento` (`evento_chave`),
  KEY `ix_outbox_relay` (`estado`,`proxima_tentativa_em`,`lease_ate`),
  KEY `ix_outbox_identidade` (`usuario_id`,`versao_seguranca`,`estado`),
  KEY `fk_outbox_recuperacao` (`recuperacao_id`),
  KEY `fk_outbox_confirmacao` (`confirmacao_id`),
  CONSTRAINT `fk_outbox_confirmacao` FOREIGN KEY (`confirmacao_id`) REFERENCES `confirmacoes_identidade` (`id`),
  CONSTRAINT `fk_outbox_recuperacao` FOREIGN KEY (`recuperacao_id`) REFERENCES `recuperacoes_senha` (`id`),
  CONSTRAINT `fk_outbox_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_outbox_validade` CHECK (`expira_em` > `criado_em` and `versao_seguranca` > 0),
  CONSTRAINT `ck_outbox_pedido` CHECK (`finalidade` = 'recuperacao' and `recuperacao_id` is not null and `confirmacao_id` is null or `finalidade` in ('ativacao','email') and `confirmacao_id` is not null and `recuperacao_id` is null or `finalidade` = 'aviso' and `confirmacao_id` is null and `recuperacao_id` is null),
  CONSTRAINT `ck_outbox_segredo` CHECK (`estado` in ('aceito','cancelado','falhou') or `destinatario_cifrado` is not null and (`finalidade` = 'aviso' or `token_cifrado` is not null))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Durable intent; temporary secret encrypted with application key, erased at terminal state/expiry.';

-- Objeto: exportacao_campos
CREATE TABLE `exportacao_campos` (
  `exportacao_id` bigint(20) unsigned NOT NULL,
  `campo_id` smallint(5) unsigned NOT NULL,
  `modulo_codigo` varchar(40) NOT NULL,
  `ordem` smallint(5) unsigned NOT NULL,
  PRIMARY KEY (`exportacao_id`,`campo_id`),
  UNIQUE KEY `uq_ec_ordem` (`exportacao_id`,`ordem`),
  KEY `fk_ec_exportacao` (`exportacao_id`,`modulo_codigo`),
  KEY `fk_ec_campo` (`campo_id`,`modulo_codigo`),
  CONSTRAINT `fk_ec_campo` FOREIGN KEY (`campo_id`, `modulo_codigo`) REFERENCES `relatorio_campos` (`id`, `modulo_codigo`),
  CONSTRAINT `fk_ec_exportacao` FOREIGN KEY (`exportacao_id`, `modulo_codigo`) REFERENCES `exportacoes` (`id`, `modulo_codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordem das colunas selecionadas e pertencimento ao mesmo módulo do relatório.';

-- Objeto: exportacao_registros
CREATE TABLE `exportacao_registros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `exportacao_id` bigint(20) unsigned NOT NULL,
  `ordem` bigint(20) unsigned NOT NULL,
  `solicitacao_id` bigint(20) unsigned DEFAULT NULL,
  `viagem_id` bigint(20) unsigned DEFAULT NULL,
  `veiculo_id` bigint(20) unsigned DEFAULT NULL,
  `despesa_id` bigint(20) unsigned DEFAULT NULL,
  `multa_id` bigint(20) unsigned DEFAULT NULL,
  `chamado_id` bigint(20) unsigned DEFAULT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `perfil_id` bigint(20) unsigned DEFAULT NULL,
  `rota_id` bigint(20) unsigned DEFAULT NULL,
  `auditoria_id` bigint(20) unsigned DEFAULT NULL,
  `posicao_rastreamento_id` bigint(20) unsigned DEFAULT NULL,
  `posicao_manual_id` bigint(20) unsigned DEFAULT NULL,
  `snapshot` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`snapshot`)),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_er_ordem` (`exportacao_id`,`ordem`),
  UNIQUE KEY `uq_er_solicitacao` (`exportacao_id`,`solicitacao_id`),
  UNIQUE KEY `uq_er_viagem` (`exportacao_id`,`viagem_id`),
  UNIQUE KEY `uq_er_veiculo` (`exportacao_id`,`veiculo_id`),
  UNIQUE KEY `uq_er_despesa` (`exportacao_id`,`despesa_id`),
  UNIQUE KEY `uq_er_multa` (`exportacao_id`,`multa_id`),
  UNIQUE KEY `uq_er_chamado` (`exportacao_id`,`chamado_id`),
  UNIQUE KEY `uq_er_usuario` (`exportacao_id`,`usuario_id`),
  UNIQUE KEY `uq_er_perfil` (`exportacao_id`,`perfil_id`),
  UNIQUE KEY `uq_er_rota` (`exportacao_id`,`rota_id`),
  UNIQUE KEY `uq_er_auditoria` (`exportacao_id`,`auditoria_id`),
  UNIQUE KEY `uq_er_posicao` (`exportacao_id`,`posicao_rastreamento_id`),
  UNIQUE KEY `uq_er_posicao_manual` (`exportacao_id`,`posicao_manual_id`),
  KEY `fk_er_solicitacao` (`solicitacao_id`),
  KEY `fk_er_viagem` (`viagem_id`),
  KEY `fk_er_veiculo` (`veiculo_id`),
  KEY `fk_er_despesa` (`despesa_id`),
  KEY `fk_er_multa` (`multa_id`),
  KEY `fk_er_chamado` (`chamado_id`),
  KEY `fk_er_usuario` (`usuario_id`),
  KEY `fk_er_perfil` (`perfil_id`),
  KEY `fk_er_rota` (`rota_id`),
  KEY `fk_er_auditoria` (`auditoria_id`),
  KEY `fk_er_posicao` (`posicao_rastreamento_id`),
  KEY `fk_er_posicao_manual` (`posicao_manual_id`),
  CONSTRAINT `fk_er_auditoria` FOREIGN KEY (`auditoria_id`) REFERENCES `auditoria` (`id`),
  CONSTRAINT `fk_er_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`),
  CONSTRAINT `fk_er_despesa` FOREIGN KEY (`despesa_id`) REFERENCES `despesas` (`id`),
  CONSTRAINT `fk_er_exportacao` FOREIGN KEY (`exportacao_id`) REFERENCES `exportacoes` (`id`),
  CONSTRAINT `fk_er_multa` FOREIGN KEY (`multa_id`) REFERENCES `multas` (`id`),
  CONSTRAINT `fk_er_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`),
  CONSTRAINT `fk_er_posicao` FOREIGN KEY (`posicao_rastreamento_id`) REFERENCES `posicoes_rastreamento` (`id`),
  CONSTRAINT `fk_er_posicao_manual` FOREIGN KEY (`posicao_manual_id`) REFERENCES `posicoes_manuais` (`id`),
  CONSTRAINT `fk_er_rota` FOREIGN KEY (`rota_id`) REFERENCES `rotas_sistema` (`id`),
  CONSTRAINT `fk_er_solicitacao` FOREIGN KEY (`solicitacao_id`) REFERENCES `solicitacoes` (`id`),
  CONSTRAINT `fk_er_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_er_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `fk_er_viagem` FOREIGN KEY (`viagem_id`) REFERENCES `viagens` (`id`),
  CONSTRAINT `ck_er_alvo` CHECK ((`solicitacao_id` is not null) + (`viagem_id` is not null) + (`veiculo_id` is not null) + (`despesa_id` is not null) + (`multa_id` is not null) + (`chamado_id` is not null) + (`usuario_id` is not null) + (`perfil_id` is not null) + (`rota_id` is not null) + (`auditoria_id` is not null) + (`posicao_rastreamento_id` is not null) + (`posicao_manual_id` is not null) = 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Seleção reproduzível de registros com FKs reais e snapshot apenas dos campos permitidos.';

-- Objeto: exportacoes
CREATE TABLE `exportacoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `solicitado_por_vinculo_id` bigint(20) unsigned NOT NULL,
  `modulo_codigo` varchar(40) NOT NULL,
  `formato` enum('md','csv','xlsx','pdf') NOT NULL,
  `situacao` enum('previa','fila','processando','concluida','falhou','expirada','cancelada') NOT NULL DEFAULT 'previa',
  `periodo_inicio` date DEFAULT NULL,
  `periodo_fim` date DEFAULT NULL,
  `periodo_inicio_utc` datetime(6) DEFAULT NULL,
  `periodo_fim_utc` datetime(6) DEFAULT NULL,
  `fuso_apresentacao` varchar(64) NOT NULL DEFAULT 'America/Porto_Velho',
  `filtros` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`filtros`)),
  `alcance_aplicado` enum('proprios','unidade','orgao') NOT NULL,
  `unidade_aplicada_id` bigint(20) unsigned DEFAULT NULL,
  `total_registros` bigint(20) unsigned NOT NULL DEFAULT 0,
  `arquivo_id` bigint(20) unsigned DEFAULT NULL,
  `erro_codigo` varchar(60) DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `iniciado_em` datetime(6) DEFAULT NULL,
  `concluido_em` datetime(6) DEFAULT NULL,
  `expira_em` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_exportacao_modulo` (`id`,`modulo_codigo`),
  KEY `ix_exportacao_fila` (`situacao`,`criado_em`),
  KEY `fk_exportacao_vinculo` (`solicitado_por_vinculo_id`),
  KEY `fk_exportacao_modulo` (`modulo_codigo`),
  KEY `fk_exportacao_unidade` (`unidade_aplicada_id`),
  KEY `fk_exportacao_arquivo` (`arquivo_id`),
  CONSTRAINT `fk_exportacao_arquivo` FOREIGN KEY (`arquivo_id`) REFERENCES `arquivos` (`id`),
  CONSTRAINT `fk_exportacao_modulo` FOREIGN KEY (`modulo_codigo`) REFERENCES `modulos` (`codigo`),
  CONSTRAINT `fk_exportacao_unidade` FOREIGN KEY (`unidade_aplicada_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `fk_exportacao_vinculo` FOREIGN KEY (`solicitado_por_vinculo_id`) REFERENCES `usuario_perfis` (`id`),
  CONSTRAINT `ck_exportacao_periodo` CHECK (`periodo_inicio` is null or `periodo_fim` is null or `periodo_fim` >= `periodo_inicio`),
  CONSTRAINT `ck_exportacao_janela_utc` CHECK (`periodo_inicio_utc` is null or `periodo_fim_utc` is null or `periodo_fim_utc` > `periodo_inicio_utc`),
  CONSTRAINT `ck_exportacao_final` CHECK (`situacao` <> 'concluida' or `arquivo_id` is not null and `concluido_em` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pedido e snapshot da seleção; gerar arquivo exige revalidar permissão no servidor.';

-- Objeto: failed_jobs
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Falhas da fila Laravel; payload de recuperação contém somente identificador cifrado.';

-- Objeto: fornecedores
CREATE TABLE `fornecedores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(150) NOT NULL,
  `cpf_cnpj` varchar(14) DEFAULT NULL,
  `email` varchar(254) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `ativo` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_fornecedor_documento` (`cpf_cnpj`),
  CONSTRAINT `ck_fornecedor_ativo` CHECK (`ativo` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fornecedor opcional para notas fiscais e ordens de serviço.';

-- Objeto: jobs
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `ix_jobs_queue` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Fila durável para tarefas assíncronas; payloads sensíveis devem estar cifrados.';

-- Objeto: manutencao_itens
CREATE TABLE `manutencao_itens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `manutencao_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('peca','servico') NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `quantidade` decimal(12,3) NOT NULL DEFAULT 1.000,
  `valor_unitario` decimal(13,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_mi_manutencao` (`manutencao_id`),
  CONSTRAINT `fk_mi_manutencao` FOREIGN KEY (`manutencao_id`) REFERENCES `manutencoes` (`id`),
  CONSTRAINT `ck_mi_valores` CHECK (`quantidade` > 0 and `valor_unitario` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Peças e serviços da ordem de manutenção.';

-- Objeto: manutencoes
CREATE TABLE `manutencoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `protocolo` varchar(40) NOT NULL,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `despesa_id` bigint(20) unsigned DEFAULT NULL,
  `reserva_id` bigint(20) unsigned DEFAULT NULL,
  `fornecedor_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` enum('preventiva','corretiva','vistoria') NOT NULL,
  `situacao` enum('planejada','em_execucao','concluida','cancelada') NOT NULL DEFAULT 'planejada',
  `descricao` text NOT NULL,
  `inicio_previsto` datetime(6) NOT NULL,
  `fim_previsto` datetime(6) NOT NULL,
  `inicio_real` datetime(6) DEFAULT NULL,
  `fim_real` datetime(6) DEFAULT NULL,
  `quilometragem` decimal(12,1) DEFAULT NULL,
  `proxima_revisao_km` decimal(12,1) DEFAULT NULL,
  `proxima_revisao_data` date DEFAULT NULL,
  `criado_por` bigint(20) unsigned NOT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_manutencao_protocolo` (`protocolo`),
  UNIQUE KEY `uq_manutencao_despesa` (`despesa_id`),
  UNIQUE KEY `uq_manutencao_reserva` (`reserva_id`),
  KEY `ix_manutencao_veiculo` (`veiculo_id`,`situacao`,`inicio_previsto`),
  KEY `fk_manutencao_despesa` (`despesa_id`,`veiculo_id`),
  KEY `fk_manutencao_reserva` (`reserva_id`,`veiculo_id`),
  KEY `fk_manutencao_fornecedor` (`fornecedor_id`),
  KEY `fk_manutencao_autor` (`criado_por`),
  CONSTRAINT `fk_manutencao_autor` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_manutencao_despesa` FOREIGN KEY (`despesa_id`, `veiculo_id`) REFERENCES `despesas` (`id`, `veiculo_id`),
  CONSTRAINT `fk_manutencao_fornecedor` FOREIGN KEY (`fornecedor_id`) REFERENCES `fornecedores` (`id`),
  CONSTRAINT `fk_manutencao_reserva` FOREIGN KEY (`reserva_id`, `veiculo_id`) REFERENCES `reservas` (`id`, `veiculo_id`),
  CONSTRAINT `fk_manutencao_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `ck_manutencao_periodo` CHECK (`fim_previsto` > `inicio_previsto` and (`fim_real` is null or `inicio_real` is not null and `fim_real` >= `inicio_real`)),
  CONSTRAINT `ck_manutencao_km` CHECK ((`quilometragem` is null or `quilometragem` >= 0) and (`proxima_revisao_km` is null or `proxima_revisao_km` >= 0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ordem de manutenção com bloqueio da agenda e despesa opcional.';

-- Objeto: modulos
CREATE TABLE `modulos` (
  `codigo` varchar(40) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `ordem` smallint(5) unsigned NOT NULL DEFAULT 0,
  `ativo` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`codigo`),
  CONSTRAINT `ck_modulo_ativo` CHECK (`ativo` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Catálogo dos módulos de negócio e de administração.';

-- Objeto: modulo_acoes
CREATE TABLE `modulo_acoes` (
  `modulo_codigo` varchar(40) NOT NULL,
  `acao_codigo` varchar(40) NOT NULL,
  PRIMARY KEY (`modulo_codigo`,`acao_codigo`),
  KEY `fk_ma_acao` (`acao_codigo`),
  CONSTRAINT `fk_ma_acao` FOREIGN KEY (`acao_codigo`) REFERENCES `acoes` (`codigo`),
  CONSTRAINT `fk_ma_modulo` FOREIGN KEY (`modulo_codigo`) REFERENCES `modulos` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Combinações de módulo e ação realmente suportadas pelo sistema.';

-- Objeto: motoristas
CREATE TABLE `motoristas` (
  `usuario_id` bigint(20) unsigned NOT NULL,
  `numero_cnh` varchar(20) NOT NULL,
  `categoria_cnh` varchar(10) NOT NULL,
  `categorias_autorizadas` varchar(5) NOT NULL,
  `validade_cnh` date NOT NULL,
  `ativo` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `cnh_arquivo_id` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`usuario_id`),
  UNIQUE KEY `uq_motorista_cnh` (`numero_cnh`),
  KEY `fk_motorista_documento` (`cnh_arquivo_id`),
  CONSTRAINT `fk_motorista_documento` FOREIGN KEY (`cnh_arquivo_id`) REFERENCES `arquivos` (`id`),
  CONSTRAINT `fk_motorista_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_motorista_ativo` CHECK (`ativo` in (0,1)),
  CONSTRAINT `ck_motorista_categoria` CHECK (`categorias_autorizadas` regexp '^[ABCDE]{1,5}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Habilitação de usuários que podem efetivamente conduzir veículos.';

-- Objeto: multas
CREATE TABLE `multas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `protocolo` varchar(40) NOT NULL,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `unidade_id` bigint(20) unsigned NOT NULL,
  `numero_auto` varchar(100) DEFAULT NULL,
  `orgao_autuador` varchar(150) DEFAULT NULL,
  `ocorrido_em` datetime(6) NOT NULL,
  `precisao_ocorrencia` enum('dia','instante') NOT NULL DEFAULT 'instante',
  `fim_dia_ocorrencia_utc` datetime(6) DEFAULT NULL,
  `data_vencimento` date DEFAULT NULL,
  `valor` decimal(13,2) NOT NULL,
  `descricao` text NOT NULL,
  `situacao` enum('sem_responsavel','aguardando_comprovante','em_conferencia','quitada','contestada','cancelada') NOT NULL DEFAULT 'sem_responsavel',
  `responsabilidade_atual_id` bigint(20) unsigned DEFAULT NULL,
  `criado_por` bigint(20) unsigned NOT NULL,
  `versao` bigint(20) unsigned NOT NULL DEFAULT 1,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_multa_protocolo` (`protocolo`),
  UNIQUE KEY `uq_multa_veiculo` (`id`,`veiculo_id`),
  UNIQUE KEY `uq_multa_auto` (`orgao_autuador`,`numero_auto`),
  KEY `ix_multa_unidade_status` (`unidade_id`,`situacao`,`ocorrido_em`),
  KEY `fk_multa_veiculo` (`veiculo_id`),
  KEY `fk_multa_autor` (`criado_por`),
  KEY `fk_multa_responsabilidade_atual` (`id`,`responsabilidade_atual_id`),
  CONSTRAINT `fk_multa_autor` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_multa_responsabilidade_atual` FOREIGN KEY (`id`, `responsabilidade_atual_id`) REFERENCES `multa_responsabilidades` (`multa_id`, `id`),
  CONSTRAINT `fk_multa_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `fk_multa_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `ck_multa_valor` CHECK (`valor` > 0),
  CONSTRAINT `ck_multa_precisao` CHECK (`precisao_ocorrencia` = 'instante' and `fim_dia_ocorrencia_utc` is null or `precisao_ocorrencia` = 'dia' and `fim_dia_ocorrencia_utc` is not null and `fim_dia_ocorrencia_utc` > `ocorrido_em`),
  CONSTRAINT `ck_multa_responsavel` CHECK (`situacao` <> 'sem_responsavel' or `responsabilidade_atual_id` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Autuação do veículo. Não atribui responsabilidade automaticamente ao solicitante.';

-- Objeto: multa_comprovantes
CREATE TABLE `multa_comprovantes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `multa_id` bigint(20) unsigned NOT NULL,
  `numero` int(10) unsigned NOT NULL,
  `responsabilidade_id` bigint(20) unsigned NOT NULL,
  `arquivo_id` bigint(20) unsigned NOT NULL,
  `enviado_por_vinculo_id` bigint(20) unsigned NOT NULL,
  `valor_declarado` decimal(13,2) NOT NULL,
  `pagamento_declarado_em` datetime(6) NOT NULL,
  `observacao` text DEFAULT NULL,
  `enviado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mc_numero` (`multa_id`,`numero`),
  UNIQUE KEY `uq_mc_multa` (`multa_id`,`id`),
  KEY `fk_mc_responsabilidade` (`multa_id`,`responsabilidade_id`),
  KEY `fk_mc_arquivo` (`arquivo_id`),
  KEY `fk_mc_vinculo` (`enviado_por_vinculo_id`),
  CONSTRAINT `fk_mc_arquivo` FOREIGN KEY (`arquivo_id`) REFERENCES `arquivos` (`id`),
  CONSTRAINT `fk_mc_responsabilidade` FOREIGN KEY (`multa_id`, `responsabilidade_id`) REFERENCES `multa_responsabilidades` (`multa_id`, `id`),
  CONSTRAINT `fk_mc_vinculo` FOREIGN KEY (`enviado_por_vinculo_id`) REFERENCES `usuario_perfis` (`id`),
  CONSTRAINT `ck_mc_valor` CHECK (`valor_declarado` > 0 and `numero` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cada envio é uma versão imutável. Enviar arquivo não significa quitar.';

-- Objeto: multa_conferencias
CREATE TABLE `multa_conferencias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `multa_id` bigint(20) unsigned NOT NULL,
  `comprovante_id` bigint(20) unsigned NOT NULL,
  `conferido_por_vinculo_id` bigint(20) unsigned NOT NULL,
  `resultado` enum('aceito','correcao_solicitada') NOT NULL,
  `motivo` text DEFAULT NULL,
  `valor_confirmado` decimal(13,2) DEFAULT NULL,
  `pagamento_confirmado_em` datetime(6) DEFAULT NULL,
  `conferido_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mcf_comprovante` (`comprovante_id`),
  UNIQUE KEY `uq_mcf_multa` (`multa_id`,`id`),
  KEY `fk_mcf_comprovante` (`multa_id`,`comprovante_id`),
  KEY `fk_mcf_vinculo` (`conferido_por_vinculo_id`),
  CONSTRAINT `fk_mcf_comprovante` FOREIGN KEY (`multa_id`, `comprovante_id`) REFERENCES `multa_comprovantes` (`multa_id`, `id`),
  CONSTRAINT `fk_mcf_vinculo` FOREIGN KEY (`conferido_por_vinculo_id`) REFERENCES `usuario_perfis` (`id`),
  CONSTRAINT `ck_mcf_resultado` CHECK (`resultado` = 'aceito' and `valor_confirmado` is not null and `valor_confirmado` > 0 and `pagamento_confirmado_em` is not null or `resultado` = 'correcao_solicitada' and `motivo` is not null and char_length(trim(`motivo`)) > 0 and `valor_confirmado` is null and `pagamento_confirmado_em` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Decisão financeira de uma versão do comprovante; uma conferência por envio.';

-- Objeto: multa_eventos
CREATE TABLE `multa_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `multa_id` bigint(20) unsigned NOT NULL,
  `ator_vinculo_id` bigint(20) unsigned NOT NULL,
  `tipo` varchar(40) NOT NULL,
  `situacao_anterior` varchar(40) DEFAULT NULL,
  `situacao_nova` varchar(40) NOT NULL,
  `motivo` text DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `ix_me_historico` (`multa_id`,`criado_em`,`id`),
  KEY `fk_me_vinculo` (`ator_vinculo_id`),
  CONSTRAINT `fk_me_multa` FOREIGN KEY (`multa_id`) REFERENCES `multas` (`id`),
  CONSTRAINT `fk_me_vinculo` FOREIGN KEY (`ator_vinculo_id`) REFERENCES `usuario_perfis` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico imutável de apuração, contestação, cancelamento e comprovantes.';

-- Objeto: multa_responsabilidades
CREATE TABLE `multa_responsabilidades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `multa_id` bigint(20) unsigned NOT NULL,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `viagem_id` bigint(20) unsigned NOT NULL,
  `motorista_id` bigint(20) unsigned NOT NULL,
  `responsavel_id` bigint(20) unsigned NOT NULL,
  `numero` int(10) unsigned NOT NULL,
  `confirmado_por_vinculo_id` bigint(20) unsigned NOT NULL,
  `confirmado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `justificativa` text NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_mr_versao` (`multa_id`,`numero`),
  UNIQUE KEY `uq_mr_multa` (`multa_id`,`id`),
  KEY `fk_mr_multa` (`multa_id`,`veiculo_id`),
  KEY `fk_mr_viagem` (`viagem_id`,`veiculo_id`,`motorista_id`),
  KEY `fk_mr_responsavel` (`responsavel_id`),
  KEY `fk_mr_confirmador` (`confirmado_por_vinculo_id`),
  CONSTRAINT `fk_mr_confirmador` FOREIGN KEY (`confirmado_por_vinculo_id`) REFERENCES `usuario_perfis` (`id`),
  CONSTRAINT `fk_mr_multa` FOREIGN KEY (`multa_id`, `veiculo_id`) REFERENCES `multas` (`id`, `veiculo_id`),
  CONSTRAINT `fk_mr_responsavel` FOREIGN KEY (`responsavel_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_mr_viagem` FOREIGN KEY (`viagem_id`, `veiculo_id`, `motorista_id`) REFERENCES `viagens` (`id`, `veiculo_id`, `motorista_id`),
  CONSTRAINT `ck_mr_numero` CHECK (`numero` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Versões da apuração administrativa, com veículo e condutor da viagem comprovados.';

-- Objeto: notificacao_destinatarios
CREATE TABLE `notificacao_destinatarios` (
  `evento_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `lida_em` datetime(6) DEFAULT NULL,
  `oculta_em` datetime(6) DEFAULT NULL,
  `versao` bigint(20) unsigned NOT NULL DEFAULT 1,
  `entregue_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`evento_id`,`usuario_id`),
  KEY `ix_nd_usuario` (`usuario_id`,`oculta_em`,`lida_em`,`entregue_em`),
  CONSTRAINT `fk_nd_evento` FOREIGN KEY (`evento_id`) REFERENCES `notificacao_eventos` (`id`),
  CONSTRAINT `fk_nd_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Leitura, ocultação e versão por usuário; sem apagar o evento.';

-- Objeto: notificacao_eventos
CREATE TABLE `notificacao_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chave_idempotencia` varchar(160) NOT NULL,
  `tipo` varchar(60) NOT NULL,
  `titulo` varchar(160) NOT NULL,
  `mensagem` varchar(1000) NOT NULL,
  `caminho_destino` varchar(255) DEFAULT NULL,
  `solicitacao_id` bigint(20) unsigned DEFAULT NULL,
  `viagem_id` bigint(20) unsigned DEFAULT NULL,
  `multa_id` bigint(20) unsigned DEFAULT NULL,
  `despesa_id` bigint(20) unsigned DEFAULT NULL,
  `chamado_id` bigint(20) unsigned DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_notificacao_evento` (`chave_idempotencia`),
  KEY `fk_ne_solicitacao` (`solicitacao_id`),
  KEY `fk_ne_viagem` (`viagem_id`),
  KEY `fk_ne_multa` (`multa_id`),
  KEY `fk_ne_despesa` (`despesa_id`),
  KEY `fk_ne_chamado` (`chamado_id`),
  CONSTRAINT `fk_ne_chamado` FOREIGN KEY (`chamado_id`) REFERENCES `chamados` (`id`),
  CONSTRAINT `fk_ne_despesa` FOREIGN KEY (`despesa_id`) REFERENCES `despesas` (`id`),
  CONSTRAINT `fk_ne_multa` FOREIGN KEY (`multa_id`) REFERENCES `multas` (`id`),
  CONSTRAINT `fk_ne_solicitacao` FOREIGN KEY (`solicitacao_id`) REFERENCES `solicitacoes` (`id`),
  CONSTRAINT `fk_ne_viagem` FOREIGN KEY (`viagem_id`) REFERENCES `viagens` (`id`),
  CONSTRAINT `ck_ne_alvo` CHECK ((`solicitacao_id` is not null) + (`viagem_id` is not null) + (`multa_id` is not null) + (`despesa_id` is not null) + (`chamado_id` is not null) <= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Evento único de negócio; destinatários mantêm seus próprios estados de leitura.';

-- Objeto: ocupacoes_agenda
CREATE TABLE `ocupacoes_agenda` (
  `reserva_id` bigint(20) unsigned NOT NULL,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `motorista_id` bigint(20) unsigned DEFAULT NULL,
  `viagem_id` bigint(20) unsigned DEFAULT NULL,
  `em_andamento` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `inicio` datetime(6) NOT NULL,
  `fim` datetime(6) NOT NULL,
  PRIMARY KEY (`reserva_id`),
  KEY `ix_oa_veiculo` (`veiculo_id`,`inicio`,`fim`),
  KEY `ix_oa_motorista` (`motorista_id`,`inicio`,`fim`),
  KEY `fk_oa_viagem` (`viagem_id`),
  CONSTRAINT `fk_oa_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`usuario_id`),
  CONSTRAINT `fk_oa_reserva` FOREIGN KEY (`reserva_id`) REFERENCES `reservas` (`id`),
  CONSTRAINT `fk_oa_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `fk_oa_viagem` FOREIGN KEY (`viagem_id`) REFERENCES `viagens` (`id`),
  CONSTRAINT `ck_oa_periodo` CHECK (`fim` > `inicio`),
  CONSTRAINT `ck_oa_em_andamento` CHECK (`em_andamento` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Espelho técnico apenas das reservas ativas, mantido por triggers para leituras bloqueantes.';

-- Objeto: pagamentos_despesa
CREATE TABLE `pagamentos_despesa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `despesa_id` bigint(20) unsigned NOT NULL,
  `valor` decimal(13,2) NOT NULL,
  `pago_em` datetime(6) NOT NULL,
  `comprovante_arquivo_id` bigint(20) unsigned NOT NULL,
  `confirmado_por_vinculo_id` bigint(20) unsigned NOT NULL,
  `confirmado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pagamento_despesa` (`despesa_id`),
  KEY `fk_pd_arquivo` (`comprovante_arquivo_id`),
  KEY `fk_pd_vinculo` (`confirmado_por_vinculo_id`),
  CONSTRAINT `fk_pd_arquivo` FOREIGN KEY (`comprovante_arquivo_id`) REFERENCES `arquivos` (`id`),
  CONSTRAINT `fk_pd_despesa` FOREIGN KEY (`despesa_id`) REFERENCES `despesas` (`id`),
  CONSTRAINT `fk_pd_vinculo` FOREIGN KEY (`confirmado_por_vinculo_id`) REFERENCES `usuario_perfis` (`id`),
  CONSTRAINT `ck_pd_valor` CHECK (`valor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pagamento confirmado separado da nota e do estado de conferência.';

-- Objeto: pagamentos_multa
CREATE TABLE `pagamentos_multa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `multa_id` bigint(20) unsigned NOT NULL,
  `conferencia_id` bigint(20) unsigned NOT NULL,
  `valor` decimal(13,2) NOT NULL,
  `pago_em` datetime(6) NOT NULL,
  `registrado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pagamento_multa` (`multa_id`),
  UNIQUE KEY `uq_pagamento_conferencia` (`conferencia_id`),
  KEY `fk_pagamento_multa_conferencia` (`multa_id`,`conferencia_id`),
  CONSTRAINT `fk_pagamento_multa_conferencia` FOREIGN KEY (`multa_id`, `conferencia_id`) REFERENCES `multa_conferencias` (`multa_id`, `id`),
  CONSTRAINT `ck_pagamento_multa_valor` CHECK (`valor` > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quitação única, criada somente após aceite financeiro.';

-- Objeto: perfil_permissoes
CREATE TABLE `perfil_permissoes` (
  `perfil_id` bigint(20) unsigned NOT NULL,
  `permissao_id` bigint(20) unsigned NOT NULL,
  `delegavel` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `concedido_por` bigint(20) unsigned DEFAULT NULL,
  `concedido_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`perfil_id`,`permissao_id`),
  KEY `fk_pp_permissao` (`permissao_id`),
  KEY `fk_pp_autor` (`concedido_por`),
  CONSTRAINT `fk_pp_autor` FOREIGN KEY (`concedido_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_pp_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`),
  CONSTRAINT `fk_pp_permissao` FOREIGN KEY (`permissao_id`) REFERENCES `permissoes` (`id`),
  CONSTRAINT `ck_pp_delegavel` CHECK (`delegavel` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Matriz de ações e alcances; marca concessões que podem ser delegadas.';

-- Objeto: perfis
CREATE TABLE `perfis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(60) NOT NULL,
  `nome` varchar(100) NOT NULL,
  `descricao` text DEFAULT NULL,
  `ativo` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `perfil_origem_id` bigint(20) unsigned DEFAULT NULL,
  `criado_por` bigint(20) unsigned DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `atualizado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_perfil_codigo` (`codigo`),
  KEY `fk_perfil_origem` (`perfil_origem_id`),
  KEY `fk_perfil_autor` (`criado_por`),
  CONSTRAINT `fk_perfil_autor` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_perfil_origem` FOREIGN KEY (`perfil_origem_id`) REFERENCES `perfis` (`id`),
  CONSTRAINT `ck_perfil_ativo` CHECK (`ativo` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Quatro perfis iniciais e perfis personalizados.';

-- Objeto: permissoes
CREATE TABLE `permissoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `modulo_codigo` varchar(40) NOT NULL,
  `acao_codigo` varchar(40) NOT NULL,
  `alcance` enum('proprios','unidade','orgao') NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_permissao` (`modulo_codigo`,`acao_codigo`,`alcance`),
  KEY `fk_permissao_acao` (`acao_codigo`),
  CONSTRAINT `fk_permissao_acao` FOREIGN KEY (`acao_codigo`) REFERENCES `acoes` (`codigo`),
  CONSTRAINT `fk_permissao_combinacao` FOREIGN KEY (`modulo_codigo`, `acao_codigo`) REFERENCES `modulo_acoes` (`modulo_codigo`, `acao_codigo`),
  CONSTRAINT `fk_permissao_modulo` FOREIGN KEY (`modulo_codigo`) REFERENCES `modulos` (`codigo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Permissão formada por módulo, ação e alcance.';

-- Objeto: pneus
CREATE TABLE `pneus` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `codigo` varchar(60) NOT NULL,
  `numero_serie` varchar(100) DEFAULT NULL,
  `marca` varchar(80) DEFAULT NULL,
  `modelo` varchar(80) DEFAULT NULL,
  `medida` varchar(40) NOT NULL,
  `adquirido_em` date DEFAULT NULL,
  `despesa_aquisicao_id` bigint(20) unsigned DEFAULT NULL,
  `situacao` enum('estoque','instalado','descartado') NOT NULL DEFAULT 'estoque',
  `descartado_em` datetime(6) DEFAULT NULL,
  `motivo_descarte` varchar(500) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pneu_codigo` (`codigo`),
  UNIQUE KEY `uq_pneu_serie` (`numero_serie`),
  KEY `fk_pneu_despesa` (`despesa_aquisicao_id`),
  CONSTRAINT `fk_pneu_despesa` FOREIGN KEY (`despesa_aquisicao_id`) REFERENCES `despesas` (`id`),
  CONSTRAINT `ck_pneu_descarte` CHECK (`situacao` = 'descartado' and `descartado_em` is not null and `motivo_descarte` is not null or `situacao` <> 'descartado' and `descartado_em` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ativos identificáveis, com aquisição e descarte.';

-- Objeto: pneu_instalacoes
CREATE TABLE `pneu_instalacoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `pneu_id` bigint(20) unsigned NOT NULL,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `posicao` varchar(40) NOT NULL,
  `instalado_em` datetime(6) NOT NULL,
  `removido_em` datetime(6) DEFAULT NULL,
  `quilometragem_instalacao` decimal(12,1) NOT NULL,
  `quilometragem_remocao` decimal(12,1) DEFAULT NULL,
  `motivo_remocao` varchar(500) DEFAULT NULL,
  `registrado_por` bigint(20) unsigned NOT NULL,
  `manutencao_id` bigint(20) unsigned DEFAULT NULL,
  `pneu_aberto` bigint(20) unsigned GENERATED ALWAYS AS (case when `removido_em` is null then `pneu_id` else NULL end) STORED,
  `veiculo_aberto` bigint(20) unsigned GENERATED ALWAYS AS (case when `removido_em` is null then `veiculo_id` else NULL end) STORED,
  `posicao_aberta` varchar(40) GENERATED ALWAYS AS (case when `removido_em` is null then `posicao` else NULL end) STORED,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_pneu_instalado` (`pneu_aberto`),
  UNIQUE KEY `uq_pneu_posicao` (`veiculo_aberto`,`posicao_aberta`),
  KEY `fk_pi_pneu` (`pneu_id`),
  KEY `fk_pi_veiculo` (`veiculo_id`),
  KEY `fk_pi_autor` (`registrado_por`),
  KEY `fk_pi_manutencao` (`manutencao_id`),
  CONSTRAINT `fk_pi_autor` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_pi_manutencao` FOREIGN KEY (`manutencao_id`) REFERENCES `manutencoes` (`id`),
  CONSTRAINT `fk_pi_pneu` FOREIGN KEY (`pneu_id`) REFERENCES `pneus` (`id`),
  CONSTRAINT `fk_pi_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `ck_pi_periodo` CHECK (`removido_em` is null or `removido_em` >= `instalado_em`),
  CONSTRAINT `ck_pi_km` CHECK (`quilometragem_instalacao` >= 0 and (`quilometragem_remocao` is null or `quilometragem_remocao` >= `quilometragem_instalacao`)),
  CONSTRAINT `ck_pi_remocao` CHECK (`removido_em` is null and `quilometragem_remocao` is null or `removido_em` is not null and `quilometragem_remocao` is not null and `motivo_remocao` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de montagem e rodízio; posição e pneu só têm uma instalação aberta.';

-- Objeto: posicoes_manuais
CREATE TABLE `posicoes_manuais` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `viagem_id` bigint(20) unsigned NOT NULL,
  `registrado_por` bigint(20) unsigned NOT NULL,
  `registrado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `ocorrido_em` datetime(6) NOT NULL,
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `descricao` varchar(1000) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_pm_viagem` (`viagem_id`),
  KEY `fk_pm_autor` (`registrado_por`),
  CONSTRAINT `fk_pm_autor` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_pm_viagem` FOREIGN KEY (`viagem_id`) REFERENCES `viagens` (`id`),
  CONSTRAINT `ck_pm_coordenadas` CHECK (`latitude` between -90 and 90 and `longitude` between -180 and 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Posição declarada por usuário; identificada como manual, não como GPS.';

-- Objeto: posicoes_rastreamento
CREATE TABLE `posicoes_rastreamento` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `instalacao_id` bigint(20) unsigned NOT NULL,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `viagem_id` bigint(20) unsigned DEFAULT NULL,
  `identificador_evento` varchar(120) NOT NULL,
  `capturado_em` datetime(6) NOT NULL,
  `recebido_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `latitude` decimal(10,7) NOT NULL,
  `longitude` decimal(10,7) NOT NULL,
  `velocidade_kmh` decimal(7,2) DEFAULT NULL,
  `precisao_metros` decimal(9,2) DEFAULT NULL,
  `ignicao` tinyint(3) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_posicao_evento` (`instalacao_id`,`identificador_evento`),
  KEY `ix_posicao_veiculo` (`veiculo_id`,`capturado_em`,`id`),
  KEY `ix_posicao_viagem` (`viagem_id`,`capturado_em`,`id`),
  KEY `fk_posicao_instalacao` (`instalacao_id`,`veiculo_id`),
  KEY `fk_posicao_viagem` (`viagem_id`,`veiculo_id`),
  CONSTRAINT `fk_posicao_viagem` FOREIGN KEY (`viagem_id`, `veiculo_id`) REFERENCES `viagens` (`id`, `veiculo_id`),
  CONSTRAINT `ck_posicao_coordenadas` CHECK (`latitude` between -90 and 90 and `longitude` between -180 and 180),
  CONSTRAINT `ck_posicao_medidas` CHECK ((`velocidade_kmh` is null or `velocidade_kmh` >= 0) and (`precisao_metros` is null or `precisao_metros` >= 0) and (`ignicao` is null or `ignicao` in (0,1)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Pontos realmente recebidos do dispositivo instalado; nunca simular caminho executado.';

-- Objeto: preferencias_usuario
CREATE TABLE `preferencias_usuario` (
  `usuario_id` bigint(20) unsigned NOT NULL,
  `tema` enum('claro','escuro','dispositivo') NOT NULL DEFAULT 'dispositivo',
  `itens_por_pagina` smallint(5) unsigned NOT NULL DEFAULT 10,
  `notificacoes_internas` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `atualizado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`usuario_id`),
  CONSTRAINT `fk_preferencia_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_preferencia_paginacao` CHECK (`itens_por_pagina` in (10,20,50,100)),
  CONSTRAINT `ck_preferencia_notificacao` CHECK (`notificacoes_internas` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Preferências compartilhadas por computador e celular.';

-- Objeto: rastreadores
CREATE TABLE `rastreadores` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `identificador_externo` varchar(100) NOT NULL,
  `provedor` varchar(100) NOT NULL,
  `tipo` enum('gps','telematica','outro') NOT NULL DEFAULT 'gps',
  `modelo` varchar(100) DEFAULT NULL,
  `ativo` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rastreador_provedor` (`provedor`,`identificador_externo`),
  CONSTRAINT `ck_rastreador_ativo` CHECK (`ativo` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Dispositivo e identificação do provedor, sem credenciais da integração.';

-- Objeto: recuperacoes_mfa
CREATE TABLE `recuperacoes_mfa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `solicitada_por` bigint(20) unsigned NOT NULL,
  `versao_seguranca` bigint(20) unsigned NOT NULL,
  `justificativa` varchar(500) NOT NULL,
  `criada_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `expira_em` datetime(6) NOT NULL,
  `concluida_em` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_recuperacao_mfa_usuario` (`usuario_id`),
  KEY `fk_recuperacao_mfa_solicitante` (`solicitada_por`),
  CONSTRAINT `fk_recuperacao_mfa_solicitante` FOREIGN KEY (`solicitada_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_recuperacao_mfa_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_recuperacao_mfa_pessoas` CHECK (`usuario_id` <> `solicitada_por`),
  CONSTRAINT `ck_recuperacao_mfa_prazo` CHECK (`expira_em` > `criada_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Objeto: recuperacoes_senha
CREATE TABLE `recuperacoes_senha` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `token_hash` binary(32) NOT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `expira_em` datetime(6) NOT NULL,
  `usado_em` datetime(6) DEFAULT NULL,
  `invalidado_em` datetime(6) DEFAULT NULL,
  `email_destino` varchar(254) DEFAULT NULL,
  `versao_seguranca` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_recuperacao_token` (`token_hash`),
  KEY `ix_recuperacao_usuario_versao` (`usuario_id`,`versao_seguranca`,`invalidado_em`,`usado_em`),
  CONSTRAINT `fk_recuperacao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_recuperacao_validade` CHECK (`expira_em` > `criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Tokens de uso único com validade; envio e consumo pertencem ao backend.';

-- Objeto: relatorio_campos
CREATE TABLE `relatorio_campos` (
  `id` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `modulo_codigo` varchar(40) NOT NULL,
  `chave` varchar(80) NOT NULL,
  `rotulo` varchar(150) NOT NULL,
  `tipo` enum('texto','numero','moeda','data','data_hora','situacao') NOT NULL,
  `acao_adicional` varchar(40) DEFAULT NULL,
  `ordem` smallint(5) unsigned NOT NULL DEFAULT 0,
  `ativo` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rc_campo` (`modulo_codigo`,`chave`),
  UNIQUE KEY `uq_rc_modulo` (`id`,`modulo_codigo`),
  KEY `fk_rc_acao` (`acao_adicional`),
  KEY `fk_rc_acao_modulo` (`modulo_codigo`,`acao_adicional`),
  CONSTRAINT `fk_rc_acao` FOREIGN KEY (`acao_adicional`) REFERENCES `acoes` (`codigo`),
  CONSTRAINT `fk_rc_acao_modulo` FOREIGN KEY (`modulo_codigo`, `acao_adicional`) REFERENCES `modulo_acoes` (`modulo_codigo`, `acao_codigo`),
  CONSTRAINT `fk_rc_modulo` FOREIGN KEY (`modulo_codigo`) REFERENCES `modulos` (`codigo`),
  CONSTRAINT `ck_rc_ativo` CHECK (`ativo` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lista autorizada de campos; campos sensíveis têm ação adicional obrigatória.';

-- Objeto: reservas
CREATE TABLE `reservas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `motorista_id` bigint(20) unsigned DEFAULT NULL,
  `revisao_id` bigint(20) unsigned DEFAULT NULL,
  `tipo` enum('viagem','manutencao','indisponibilidade') NOT NULL,
  `inicio` datetime(6) NOT NULL,
  `fim` datetime(6) NOT NULL,
  `situacao` enum('ativa','liberada','cancelada') NOT NULL DEFAULT 'ativa',
  `descricao` varchar(500) DEFAULT NULL,
  `criado_por` bigint(20) unsigned NOT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `liberada_em` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_reserva_veiculo` (`id`,`veiculo_id`),
  UNIQUE KEY `uq_reserva_revisao` (`revisao_id`),
  UNIQUE KEY `uq_reserva_viagem` (`id`,`veiculo_id`,`motorista_id`,`revisao_id`),
  KEY `ix_reserva_veiculo_periodo` (`veiculo_id`,`situacao`,`inicio`,`fim`),
  KEY `ix_reserva_motorista_periodo` (`motorista_id`,`situacao`,`inicio`,`fim`),
  KEY `fk_reserva_autor` (`criado_por`),
  CONSTRAINT `fk_reserva_autor` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_reserva_motorista` FOREIGN KEY (`motorista_id`) REFERENCES `motoristas` (`usuario_id`),
  CONSTRAINT `fk_reserva_revisao` FOREIGN KEY (`revisao_id`) REFERENCES `solicitacao_revisoes` (`id`),
  CONSTRAINT `fk_reserva_veiculo` FOREIGN KEY (`veiculo_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `ck_reserva_periodo` CHECK (`fim` > `inicio`),
  CONSTRAINT `ck_reserva_tipo` CHECK (`tipo` = 'viagem' and `revisao_id` is not null and `motorista_id` is not null or `tipo` in ('manutencao','indisponibilidade') and `revisao_id` is null and `motorista_id` is null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Agenda única de viagens e bloqueios. Triggers serializam a reserva por veículo e motorista.';

-- Objeto: rotas_sistema
CREATE TABLE `rotas_sistema` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `chave` varchar(80) NOT NULL,
  `nome` varchar(120) NOT NULL,
  `caminho` varchar(255) NOT NULL,
  `metodo_http` enum('GET','POST','PUT','PATCH','DELETE') NOT NULL DEFAULT 'GET',
  `modulo_codigo` varchar(40) NOT NULL,
  `descricao` text DEFAULT NULL,
  `ativa` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `visivel_menu` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `implementada` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `protegida` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `ordem` smallint(5) unsigned NOT NULL DEFAULT 0,
  `criado_por` bigint(20) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_rota_chave` (`chave`),
  UNIQUE KEY `uq_rota_metodo_caminho` (`metodo_http`,`caminho`),
  KEY `fk_rota_modulo` (`modulo_codigo`),
  KEY `fk_rota_autor` (`criado_por`),
  CONSTRAINT `fk_rota_autor` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_rota_modulo` FOREIGN KEY (`modulo_codigo`) REFERENCES `modulos` (`codigo`),
  CONSTRAINT `ck_rota_caminho` CHECK (left(`caminho`,1) = '/'),
  CONSTRAINT `ck_rota_flags` CHECK (`ativa` in (0,1) and `visivel_menu` in (0,1) and `implementada` in (0,1) and `protegida` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Rotas HTTP e de interface. Não são trajetos de veículos nem código executável.';

-- Objeto: rota_permissoes
CREATE TABLE `rota_permissoes` (
  `rota_id` bigint(20) unsigned NOT NULL,
  `modulo_codigo` varchar(40) NOT NULL,
  `acao_codigo` varchar(40) NOT NULL,
  PRIMARY KEY (`rota_id`,`modulo_codigo`,`acao_codigo`),
  KEY `fk_rp_acao` (`acao_codigo`),
  KEY `fk_rp_combinacao` (`modulo_codigo`,`acao_codigo`),
  CONSTRAINT `fk_rp_acao` FOREIGN KEY (`acao_codigo`) REFERENCES `acoes` (`codigo`),
  CONSTRAINT `fk_rp_combinacao` FOREIGN KEY (`modulo_codigo`, `acao_codigo`) REFERENCES `modulo_acoes` (`modulo_codigo`, `acao_codigo`),
  CONSTRAINT `fk_rp_modulo` FOREIGN KEY (`modulo_codigo`) REFERENCES `modulos` (`codigo`),
  CONSTRAINT `fk_rp_rota` FOREIGN KEY (`rota_id`) REFERENCES `rotas_sistema` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Todas as ações requeridas por uma rota, sem elevar o alcance concedido.';

-- Objeto: sessoes
CREATE TABLE `sessoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `vinculo_ativo_id` bigint(20) unsigned DEFAULT NULL,
  `token_hash` binary(32) NOT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `ultima_atividade_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `expira_em` datetime(6) NOT NULL,
  `encerrada_em` datetime(6) DEFAULT NULL,
  `motivo_encerramento` enum('logout','inatividade','prazo','revogacao','troca_senha') DEFAULT NULL,
  `ip` varbinary(16) DEFAULT NULL,
  `agente_usuario` varchar(500) DEFAULT NULL,
  `autenticada_em` datetime(6) DEFAULT NULL,
  `reautenticar_ate` datetime(6) DEFAULT NULL,
  `mfa_verificada_em` datetime(6) DEFAULT NULL,
  `mfa_vinculo_id` bigint(20) unsigned DEFAULT NULL,
  `mfa_versao` bigint(20) unsigned DEFAULT NULL,
  `mfa_http_hash` binary(32) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_sessao_token` (`token_hash`),
  UNIQUE KEY `uq_sessao_identidade` (`id`,`usuario_id`),
  KEY `ix_sessao_ativa` (`usuario_id`,`encerrada_em`,`expira_em`),
  KEY `fk_sessao_vinculo` (`vinculo_ativo_id`,`usuario_id`),
  KEY `fk_sessao_mfa_vinculo` (`mfa_vinculo_id`,`usuario_id`),
  CONSTRAINT `fk_sessao_mfa_vinculo` FOREIGN KEY (`mfa_vinculo_id`, `usuario_id`) REFERENCES `usuario_perfis` (`id`, `usuario_id`),
  CONSTRAINT `fk_sessao_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_sessao_vinculo` FOREIGN KEY (`vinculo_ativo_id`, `usuario_id`) REFERENCES `usuario_perfis` (`id`, `usuario_id`),
  CONSTRAINT `ck_sessao_datas` CHECK (`expira_em` > `criado_em` and `ultima_atividade_em` >= `criado_em`),
  CONSTRAINT `ck_sessao_fim` CHECK (`encerrada_em` is null and `motivo_encerramento` is null or `encerrada_em` is not null and `motivo_encerramento` is not null),
  CONSTRAINT `ck_sessao_reautenticacao` CHECK (`reautenticar_ate` is null or `autenticada_em` is not null and `reautenticar_ate` > `autenticada_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sessões com token somente em hash; atividade, expiração e encerramento separados.';

-- Objeto: solicitacao_eventos
CREATE TABLE `solicitacao_eventos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `solicitacao_id` bigint(20) unsigned NOT NULL,
  `revisao_id` bigint(20) unsigned NOT NULL,
  `ator_vinculo_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('criada','enviada','ajustes_solicitados','aprovada','negada','revisao_aberta','cancelada') NOT NULL,
  `situacao_anterior` varchar(40) DEFAULT NULL,
  `situacao_nova` varchar(40) NOT NULL,
  `motivo` text DEFAULT NULL,
  `veiculo_confirmado_id` bigint(20) unsigned DEFAULT NULL,
  `motorista_confirmado_id` bigint(20) unsigned DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `ix_se_historico` (`solicitacao_id`,`criado_em`,`id`),
  KEY `fk_se_revisao` (`solicitacao_id`,`revisao_id`),
  KEY `fk_se_vinculo` (`ator_vinculo_id`),
  KEY `fk_se_veiculo` (`veiculo_confirmado_id`),
  KEY `fk_se_motorista` (`motorista_confirmado_id`),
  CONSTRAINT `fk_se_motorista` FOREIGN KEY (`motorista_confirmado_id`) REFERENCES `motoristas` (`usuario_id`),
  CONSTRAINT `fk_se_revisao` FOREIGN KEY (`solicitacao_id`, `revisao_id`) REFERENCES `solicitacao_revisoes` (`solicitacao_id`, `id`),
  CONSTRAINT `fk_se_veiculo` FOREIGN KEY (`veiculo_confirmado_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `fk_se_vinculo` FOREIGN KEY (`ator_vinculo_id`) REFERENCES `usuario_perfis` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Histórico de envio, ajustes, decisão, revisão e cancelamento.';

-- Objeto: solicitacao_paradas
CREATE TABLE `solicitacao_paradas` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `revisao_id` bigint(20) unsigned NOT NULL,
  `ordem` smallint(5) unsigned NOT NULL,
  `descricao` varchar(255) NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_parada_ordem` (`revisao_id`,`ordem`),
  CONSTRAINT `fk_parada_revisao` FOREIGN KEY (`revisao_id`) REFERENCES `solicitacao_revisoes` (`id`),
  CONSTRAINT `ck_parada_coordenadas` CHECK (`latitude` is null and `longitude` is null or `latitude` is not null and `longitude` is not null and `latitude` between -90 and 90 and `longitude` between -180 and 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Paradas ordenadas do percurso de uma revisão específica.';

-- Objeto: solicitacao_passageiros
CREATE TABLE `solicitacao_passageiros` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `revisao_id` bigint(20) unsigned NOT NULL,
  `usuario_id` bigint(20) unsigned DEFAULT NULL,
  `nome` varchar(150) NOT NULL,
  `contato` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_passageiro_revisao` (`revisao_id`),
  KEY `fk_passageiro_usuario` (`usuario_id`),
  CONSTRAINT `fk_passageiro_revisao` FOREIGN KEY (`revisao_id`) REFERENCES `solicitacao_revisoes` (`id`),
  CONSTRAINT `fk_passageiro_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Lista opcional de passageiros. A contagem informada não inclui o motorista.';

-- Objeto: solicitacao_revisoes
CREATE TABLE `solicitacao_revisoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `solicitacao_id` bigint(20) unsigned NOT NULL,
  `numero` int(10) unsigned NOT NULL,
  `finalidade` text DEFAULT NULL,
  `origem` varchar(255) DEFAULT NULL,
  `destino` varchar(255) DEFAULT NULL,
  `origem_latitude` decimal(10,7) DEFAULT NULL,
  `origem_longitude` decimal(10,7) DEFAULT NULL,
  `destino_latitude` decimal(10,7) DEFAULT NULL,
  `destino_longitude` decimal(10,7) DEFAULT NULL,
  `saida_prevista` datetime(6) DEFAULT NULL,
  `retorno_previsto` datetime(6) DEFAULT NULL,
  `quantidade_passageiros` smallint(5) unsigned DEFAULT NULL,
  `necessita_motorista` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `motorista_sugerido_id` bigint(20) unsigned DEFAULT NULL,
  `veiculo_pretendido_id` bigint(20) unsigned DEFAULT NULL,
  `trajeto_planejado` text DEFAULT NULL,
  `polilinha_planejada` longtext DEFAULT NULL,
  `distancia_prevista_km` decimal(12,3) DEFAULT NULL,
  `observacoes` text DEFAULT NULL,
  `etapa_atual` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `criado_por` bigint(20) unsigned NOT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `enviado_em` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_revisao_numero` (`solicitacao_id`,`numero`),
  UNIQUE KEY `uq_revisao_solicitacao` (`solicitacao_id`,`id`),
  KEY `fk_revisao_motorista` (`motorista_sugerido_id`),
  KEY `fk_revisao_veiculo` (`veiculo_pretendido_id`),
  KEY `fk_revisao_autor` (`criado_por`),
  CONSTRAINT `fk_revisao_autor` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_revisao_motorista` FOREIGN KEY (`motorista_sugerido_id`) REFERENCES `motoristas` (`usuario_id`),
  CONSTRAINT `fk_revisao_solicitacao` FOREIGN KEY (`solicitacao_id`) REFERENCES `solicitacoes` (`id`),
  CONSTRAINT `fk_revisao_veiculo` FOREIGN KEY (`veiculo_pretendido_id`) REFERENCES `veiculos` (`id`),
  CONSTRAINT `ck_revisao_periodo` CHECK (`saida_prevista` is null or `retorno_previsto` is null or `retorno_previsto` > `saida_prevista`),
  CONSTRAINT `ck_revisao_flags` CHECK (`necessita_motorista` in (0,1) and `etapa_atual` between 1 and 4 and `numero` > 0),
  CONSTRAINT `ck_revisao_distancia` CHECK (`distancia_prevista_km` is null or `distancia_prevista_km` >= 0),
  CONSTRAINT `ck_revisao_latitude` CHECK ((`origem_latitude` is null or `origem_latitude` between -90 and 90) and (`destino_latitude` is null or `destino_latitude` between -90 and 90)),
  CONSTRAINT `ck_revisao_longitude` CHECK ((`origem_longitude` is null or `origem_longitude` between -180 and 180) and (`destino_longitude` is null or `destino_longitude` between -180 and 180))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Uma versão do formulário de quatro etapas; imutável depois de enviada.';

-- Objeto: solicitacoes
CREATE TABLE `solicitacoes` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `protocolo` varchar(40) NOT NULL,
  `solicitante_id` bigint(20) unsigned NOT NULL,
  `unidade_id` bigint(20) unsigned NOT NULL,
  `revisao_atual_id` bigint(20) unsigned DEFAULT NULL,
  `situacao` enum('rascunho','aguardando_analise','ajustes_solicitados','aprovada','negada','cancelada') NOT NULL DEFAULT 'rascunho',
  `versao` bigint(20) unsigned NOT NULL DEFAULT 1,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `atualizado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_solicitacao_protocolo` (`protocolo`),
  KEY `ix_solicitacao_proprios` (`solicitante_id`,`situacao`,`criado_em`,`id`),
  KEY `ix_solicitacao_unidade` (`unidade_id`,`situacao`,`criado_em`,`id`),
  KEY `fk_solicitacao_revisao_atual` (`id`,`revisao_atual_id`),
  CONSTRAINT `fk_solicitacao_autor` FOREIGN KEY (`solicitante_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_solicitacao_revisao_atual` FOREIGN KEY (`id`, `revisao_atual_id`) REFERENCES `solicitacao_revisoes` (`solicitacao_id`, `id`),
  CONSTRAINT `fk_solicitacao_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `ck_solicitacao_revisao` CHECK (`situacao` = 'rascunho' or `revisao_atual_id` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Protocolo persistente da solicitação; aponta para a revisão em análise.';

-- Objeto: unidades
CREATE TABLE `unidades` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unidade_superior_id` bigint(20) unsigned DEFAULT NULL,
  `codigo` varchar(30) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `endereco` varchar(255) DEFAULT NULL,
  `cidade` varchar(100) DEFAULT NULL,
  `uf` char(2) DEFAULT NULL,
  `ativa` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_unidade_codigo` (`codigo`),
  KEY `fk_unidade_superior` (`unidade_superior_id`),
  CONSTRAINT `fk_unidade_superior` FOREIGN KEY (`unidade_superior_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `ck_unidade_ativa` CHECK (`ativa` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Unidades e setores internos de um único órgão.';

-- Objeto: usuarios
CREATE TABLE `usuarios` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unidade_id` bigint(20) unsigned NOT NULL,
  `identificador` varchar(100) NOT NULL,
  `nome` varchar(150) NOT NULL,
  `email` varchar(254) DEFAULT NULL,
  `telefone` varchar(30) DEFAULT NULL,
  `senha_hash` varchar(255) NOT NULL,
  `ativo` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `deve_trocar_senha` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `senha_alterada_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `versao` bigint(20) unsigned NOT NULL DEFAULT 1,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `atualizado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  `estado_identidade` enum('legado','pendente','ativa') NOT NULL DEFAULT 'legado',
  `email_verificado_em` datetime(6) DEFAULT NULL,
  `email_pendente` varchar(254) DEFAULT NULL,
  `versao_seguranca` bigint(20) unsigned NOT NULL DEFAULT 1,
  `senha_temporaria_emitida_em` datetime(6) DEFAULT NULL,
  `senha_temporaria_expira_em` datetime(6) DEFAULT NULL,
  `senha_temporaria_usada_em` datetime(6) DEFAULT NULL,
  `senha_temporaria_revogada_em` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_usuario_identificador` (`identificador`),
  UNIQUE KEY `uq_usuario_email` (`email`),
  KEY `ix_usuario_unidade_ativo` (`unidade_id`,`ativo`),
  CONSTRAINT `fk_usuario_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `ck_usuario_flags` CHECK (`ativo` in (0,1) and `deve_trocar_senha` in (0,1)),
  CONSTRAINT `ck_identidade_versao` CHECK (`versao_seguranca` > 0),
  CONSTRAINT `ck_identidade_pendente` CHECK (`estado_identidade` <> 'pendente' or `deve_trocar_senha` = 1),
  CONSTRAINT `ck_senha_temporaria_validade` CHECK (`senha_temporaria_expira_em` is null or `senha_temporaria_emitida_em` is not null and `senha_temporaria_expira_em` > `senha_temporaria_emitida_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Identidade institucional. Senha somente em hash gerado no backend.';

-- Objeto: usuario_perfis
CREATE TABLE `usuario_perfis` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `usuario_id` bigint(20) unsigned NOT NULL,
  `perfil_id` bigint(20) unsigned NOT NULL,
  `unidade_id` bigint(20) unsigned NOT NULL,
  `vigente_desde` datetime(6) NOT NULL,
  `vigente_ate` datetime(6) DEFAULT NULL,
  `ativo` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `concedido_por` bigint(20) unsigned DEFAULT NULL,
  `revogado_por` bigint(20) unsigned DEFAULT NULL,
  `motivo_revogacao` varchar(500) DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vinculo_identidade` (`id`,`usuario_id`),
  KEY `ix_vinculo_validade` (`usuario_id`,`ativo`,`vigente_desde`,`vigente_ate`),
  KEY `ix_vinculo_perfil` (`perfil_id`,`ativo`,`vigente_ate`),
  KEY `fk_up_unidade` (`unidade_id`),
  KEY `fk_up_concessor` (`concedido_por`),
  KEY `fk_up_revogador` (`revogado_por`),
  CONSTRAINT `fk_up_concessor` FOREIGN KEY (`concedido_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_up_perfil` FOREIGN KEY (`perfil_id`) REFERENCES `perfis` (`id`),
  CONSTRAINT `fk_up_revogador` FOREIGN KEY (`revogado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_up_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `fk_up_usuario` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_vinculo_datas` CHECK (`vigente_ate` is null or `vigente_ate` > `vigente_desde`),
  CONSTRAINT `ck_vinculo_ativo` CHECK (`ativo` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Vínculos independentes com início, fim opcional e unidade de alcance.';

-- Objeto: veiculos
CREATE TABLE `veiculos` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `unidade_id` bigint(20) unsigned NOT NULL,
  `categoria_id` smallint(5) unsigned NOT NULL,
  `nome` varchar(120) NOT NULL,
  `placa` varchar(10) NOT NULL,
  `renavam` varchar(20) DEFAULT NULL,
  `chassi` varchar(30) DEFAULT NULL,
  `marca` varchar(80) DEFAULT NULL,
  `modelo` varchar(80) DEFAULT NULL,
  `ano_fabricacao` smallint(5) unsigned DEFAULT NULL,
  `ano_modelo` smallint(5) unsigned DEFAULT NULL,
  `capacidade` smallint(5) unsigned NOT NULL,
  `quilometragem_atual` decimal(12,1) NOT NULL DEFAULT 0.0,
  `situacao_cadastro` enum('ativo','inativo','baixado') NOT NULL DEFAULT 'ativo',
  `observacoes` text DEFAULT NULL,
  `criado_por` bigint(20) unsigned NOT NULL,
  `versao` bigint(20) unsigned NOT NULL DEFAULT 1,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `atualizado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6) ON UPDATE current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_veiculo_placa` (`placa`),
  UNIQUE KEY `uq_veiculo_renavam` (`renavam`),
  UNIQUE KEY `uq_veiculo_chassi` (`chassi`),
  KEY `ix_veiculo_unidade_status` (`unidade_id`,`situacao_cadastro`),
  KEY `fk_veiculo_categoria` (`categoria_id`),
  KEY `fk_veiculo_autor` (`criado_por`),
  CONSTRAINT `fk_veiculo_autor` FOREIGN KEY (`criado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_veiculo_categoria` FOREIGN KEY (`categoria_id`) REFERENCES `categorias_veiculo` (`id`),
  CONSTRAINT `fk_veiculo_unidade` FOREIGN KEY (`unidade_id`) REFERENCES `unidades` (`id`),
  CONSTRAINT `ck_veiculo_capacidade` CHECK (`capacidade` between 1 and 100),
  CONSTRAINT `ck_veiculo_km` CHECK (`quilometragem_atual` >= 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cadastro da frota. Em viagem e manutenção são estados derivados da agenda.';

-- Objeto: versoes_modelo
CREATE TABLE `versoes_modelo` (
  `versao` varchar(30) NOT NULL,
  `instalado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `descricao` varchar(255) NOT NULL,
  PRIMARY KEY (`versao`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Identifica a versão instalada do esquema.';

-- Objeto: viagem_checklists
CREATE TABLE `viagem_checklists` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `viagem_id` bigint(20) unsigned NOT NULL,
  `tipo` enum('saida','retorno') NOT NULL,
  `preenchido_por` bigint(20) unsigned NOT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  `finalizado_em` datetime(6) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_vc_tipo` (`viagem_id`,`tipo`),
  KEY `fk_vc_autor` (`preenchido_por`),
  CONSTRAINT `fk_vc_autor` FOREIGN KEY (`preenchido_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_vc_viagem` FOREIGN KEY (`viagem_id`) REFERENCES `viagens` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Cabeçalho de vistoria, preservado quando finalizado.';

-- Objeto: viagem_checklist_respostas
CREATE TABLE `viagem_checklist_respostas` (
  `checklist_id` bigint(20) unsigned NOT NULL,
  `item_id` smallint(5) unsigned NOT NULL,
  `resultado` enum('ok','problema','nao_aplicavel') NOT NULL,
  `observacao` varchar(1000) DEFAULT NULL,
  `descricao_item` varchar(150) NOT NULL DEFAULT '',
  `obrigatorio_snapshot` tinyint(3) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`checklist_id`,`item_id`),
  KEY `fk_vcr_item` (`item_id`),
  CONSTRAINT `fk_vcr_checklist` FOREIGN KEY (`checklist_id`) REFERENCES `viagem_checklists` (`id`),
  CONSTRAINT `fk_vcr_item` FOREIGN KEY (`item_id`) REFERENCES `checklist_itens` (`id`),
  CONSTRAINT `ck_vcr_observacao` CHECK (`resultado` <> 'problema' or `observacao` is not null and char_length(trim(`observacao`)) > 0),
  CONSTRAINT `ck_vcr_obrigatorio` CHECK (`obrigatorio_snapshot` in (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Resultado por item; problema exige observação.';

-- Objeto: viagem_ocorrencias
CREATE TABLE `viagem_ocorrencias` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `viagem_id` bigint(20) unsigned NOT NULL,
  `registrado_por` bigint(20) unsigned NOT NULL,
  `tipo` enum('geral','desvio_trajeto','avaria','acidente','atraso') NOT NULL DEFAULT 'geral',
  `ocorrido_em` datetime(6) NOT NULL,
  `descricao` text NOT NULL,
  `latitude` decimal(10,7) DEFAULT NULL,
  `longitude` decimal(10,7) DEFAULT NULL,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  KEY `ix_ocorrencia_viagem` (`viagem_id`,`ocorrido_em`),
  KEY `fk_ocorrencia_autor` (`registrado_por`),
  CONSTRAINT `fk_ocorrencia_autor` FOREIGN KEY (`registrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_ocorrencia_viagem` FOREIGN KEY (`viagem_id`) REFERENCES `viagens` (`id`),
  CONSTRAINT `ck_ocorrencia_coordenadas` CHECK (`latitude` is null and `longitude` is null or `latitude` is not null and `longitude` is not null and `latitude` between -90 and 90 and `longitude` between -180 and 180)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Ocorrências e justificativas de desvio, separadas de telemetria e multas.';

-- Objeto: viagens
CREATE TABLE `viagens` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `protocolo` varchar(40) NOT NULL,
  `revisao_id` bigint(20) unsigned NOT NULL,
  `reserva_id` bigint(20) unsigned NOT NULL,
  `veiculo_id` bigint(20) unsigned NOT NULL,
  `motorista_id` bigint(20) unsigned NOT NULL,
  `situacao` enum('programada','em_andamento','concluida','cancelada') NOT NULL DEFAULT 'programada',
  `saida_real` datetime(6) DEFAULT NULL,
  `retorno_real` datetime(6) DEFAULT NULL,
  `quilometragem_saida` decimal(12,1) DEFAULT NULL,
  `quilometragem_retorno` decimal(12,1) DEFAULT NULL,
  `saida_registrada_por` bigint(20) unsigned DEFAULT NULL,
  `retorno_registrado_por` bigint(20) unsigned DEFAULT NULL,
  `observacao_saida` text DEFAULT NULL,
  `observacao_retorno` text DEFAULT NULL,
  `versao` bigint(20) unsigned NOT NULL DEFAULT 1,
  `criado_em` datetime(6) NOT NULL DEFAULT current_timestamp(6),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_viagem_protocolo` (`protocolo`),
  UNIQUE KEY `uq_viagem_revisao` (`revisao_id`),
  UNIQUE KEY `uq_viagem_reserva` (`reserva_id`),
  UNIQUE KEY `uq_viagem_veiculo_motorista` (`id`,`veiculo_id`,`motorista_id`),
  UNIQUE KEY `uq_viagem_veiculo` (`id`,`veiculo_id`),
  KEY `ix_viagem_situacao` (`situacao`,`saida_real`,`id`),
  KEY `ix_viagem_historico_veiculo` (`veiculo_id`,`situacao`,`saida_real`,`retorno_real`),
  KEY `ix_viagem_historico_motorista` (`motorista_id`,`situacao`,`saida_real`,`retorno_real`),
  KEY `fk_viagem_reserva` (`reserva_id`,`veiculo_id`,`motorista_id`,`revisao_id`),
  KEY `fk_viagem_saida_autor` (`saida_registrada_por`),
  KEY `fk_viagem_retorno_autor` (`retorno_registrado_por`),
  CONSTRAINT `fk_viagem_reserva` FOREIGN KEY (`reserva_id`, `veiculo_id`, `motorista_id`, `revisao_id`) REFERENCES `reservas` (`id`, `veiculo_id`, `motorista_id`, `revisao_id`),
  CONSTRAINT `fk_viagem_retorno_autor` FOREIGN KEY (`retorno_registrado_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `fk_viagem_saida_autor` FOREIGN KEY (`saida_registrada_por`) REFERENCES `usuarios` (`id`),
  CONSTRAINT `ck_viagem_km` CHECK ((`quilometragem_saida` is null or `quilometragem_saida` >= 0) and (`quilometragem_retorno` is null or `quilometragem_retorno` >= `quilometragem_saida`)),
  CONSTRAINT `ck_viagem_periodo` CHECK (`retorno_real` is null or `saida_real` is not null and `retorno_real` > `saida_real`),
  CONSTRAINT `ck_viagem_estado` CHECK (`situacao` in ('programada','cancelada') and `saida_real` is null and `retorno_real` is null and `quilometragem_saida` is null and `quilometragem_retorno` is null or `situacao` = 'em_andamento' and `saida_real` is not null and `quilometragem_saida` is not null and `saida_registrada_por` is not null and `retorno_real` is null and `quilometragem_retorno` is null or `situacao` = 'concluida' and `saida_real` is not null and `retorno_real` is not null and `quilometragem_saida` is not null and `quilometragem_retorno` is not null and `saida_registrada_por` is not null and `retorno_registrado_por` is not null)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Aprovação gera viagem programada; saída e retorno são registros distintos.';

-- Objeto: vw_administradores_permanentes
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_administradores_permanentes` AS select `pe`.`vinculo_id` AS `vinculo_id`,`pe`.`usuario_id` AS `usuario_id`,`pe`.`perfil_id` AS `perfil_id` from (`vw_permissoes_efetivas` `pe` join `usuario_perfis` `up` on(`up`.`id` = `pe`.`vinculo_id` and `up`.`vigente_ate` is null)) where `pe`.`acao_codigo` = 'gerenciar' and `pe`.`alcance` = 'orgao' and `pe`.`modulo_codigo` in ('usuarios','perfis','rotas','configuracoes') group by `pe`.`vinculo_id`,`pe`.`usuario_id`,`pe`.`perfil_id` having count(distinct `pe`.`modulo_codigo`) = 4;

-- Objeto: vw_custos_veiculo_mensais
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_custos_veiculo_mensais` AS select `despesas`.`veiculo_id` AS `veiculo_id`,`despesas`.`unidade_id` AS `unidade_id`,date_format(`despesas`.`data_despesa`,'%Y-%m-01') AS `mes`,sum(`despesas`.`valor`) AS `valor_despesas`,count(0) AS `quantidade_despesas` from `despesas` where `despesas`.`situacao` <> 'cancelada' group by `despesas`.`veiculo_id`,`despesas`.`unidade_id`,date_format(`despesas`.`data_despesa`,'%Y-%m-01');

-- Objeto: vw_despesas_detalhadas
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_despesas_detalhadas` AS select `d`.`id` AS `id`,`d`.`protocolo` AS `protocolo`,`d`.`veiculo_id` AS `veiculo_id`,`d`.`unidade_id` AS `unidade_id`,`d`.`categoria_id` AS `categoria_id`,`d`.`fornecedor_id` AS `fornecedor_id`,`d`.`data_despesa` AS `data_despesa`,`d`.`valor` AS `valor`,`d`.`descricao` AS `descricao`,`d`.`numero_documento` AS `numero_documento`,`d`.`situacao` AS `situacao`,`d`.`criado_por` AS `criado_por`,`d`.`versao` AS `versao`,`d`.`criado_em` AS `criado_em`,`d`.`atualizado_em` AS `atualizado_em`,`v`.`placa` AS `placa`,`v`.`nome` AS `veiculo`,`c`.`nome` AS `categoria`,`f`.`nome` AS `fornecedor`,`p`.`valor` AS `valor_pago`,`p`.`pago_em` AS `pago_em` from ((((`despesas` `d` join `veiculos` `v` on(`v`.`id` = `d`.`veiculo_id`)) join `categorias_despesa` `c` on(`c`.`id` = `d`.`categoria_id`)) left join `fornecedores` `f` on(`f`.`id` = `d`.`fornecedor_id`)) left join `pagamentos_despesa` `p` on(`p`.`despesa_id` = `d`.`id`));

-- Objeto: vw_frota
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_frota` AS select `v`.`id` AS `id`,`v`.`unidade_id` AS `unidade_id`,`v`.`categoria_id` AS `categoria_id`,`v`.`nome` AS `nome`,`v`.`placa` AS `placa`,`v`.`renavam` AS `renavam`,`v`.`chassi` AS `chassi`,`v`.`marca` AS `marca`,`v`.`modelo` AS `modelo`,`v`.`ano_fabricacao` AS `ano_fabricacao`,`v`.`ano_modelo` AS `ano_modelo`,`v`.`capacidade` AS `capacidade`,`v`.`quilometragem_atual` AS `quilometragem_atual`,`v`.`situacao_cadastro` AS `situacao_cadastro`,`v`.`observacoes` AS `observacoes`,`v`.`criado_por` AS `criado_por`,`v`.`versao` AS `versao`,`v`.`criado_em` AS `criado_em`,`v`.`atualizado_em` AS `atualizado_em`,`c`.`nome` AS `categoria`,`un`.`nome` AS `unidade`,case when `v`.`situacao_cadastro` <> 'ativo' then `v`.`situacao_cadastro` when exists(select 1 from `viagens` `t` where `t`.`veiculo_id` = `v`.`id` and `t`.`situacao` = 'em_andamento' limit 1) then 'em_viagem' when exists(select 1 from `reservas` `r` where `r`.`veiculo_id` = `v`.`id` and `r`.`situacao` = 'ativa' and `r`.`tipo` = 'manutencao' and `r`.`inicio` <= utc_timestamp(6) and `r`.`fim` > utc_timestamp(6) limit 1) or exists(select 1 from `manutencoes` `m` where `m`.`veiculo_id` = `v`.`id` and `m`.`situacao` = 'em_execucao' limit 1) then 'manutencao' when exists(select 1 from `reservas` `r` where `r`.`veiculo_id` = `v`.`id` and `r`.`situacao` = 'ativa' and `r`.`tipo` = 'indisponibilidade' and `r`.`inicio` <= utc_timestamp(6) and `r`.`fim` > utc_timestamp(6) limit 1) then 'indisponivel' else 'disponivel' end AS `situacao_operacional` from ((`veiculos` `v` join `categorias_veiculo` `c` on(`c`.`id` = `v`.`categoria_id`)) join `unidades` `un` on(`un`.`id` = `v`.`unidade_id`));

-- Objeto: vw_monitoramento
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_monitoramento` AS select `v`.`id` AS `veiculo_id`,`v`.`placa` AS `placa`,`v`.`nome` AS `nome`,`v`.`unidade_id` AS `unidade_id`,`p`.`instalacao_id` AS `instalacao_id`,cast(NULL as char(100) charset utf8mb4) AS `identificador_externo`,cast(NULL as char(100) charset utf8mb4) AS `provedor`,`p`.`capturado_em` AS `capturado_em`,`p`.`recebido_em` AS `recebido_em`,`p`.`latitude` AS `latitude`,`p`.`longitude` AS `longitude`,`p`.`velocidade_kmh` AS `velocidade_kmh`,case when `p`.`id` is null then 'sem_rastreador' when `p`.`capturado_em` < utc_timestamp(6) + interval -`cfg`.`limite_sem_comunicacao_minutos` minute then 'sem_comunicacao' else 'transmitindo' end AS `situacao_comunicacao` from ((`veiculos` `v` join `configuracao_sistema` `cfg`) left join `posicoes_rastreamento` `p` on(`p`.`veiculo_id` = `v`.`id` and !exists(select 1 from `posicoes_rastreamento` `newer` where `newer`.`veiculo_id` = `p`.`veiculo_id` and (`newer`.`capturado_em` > `p`.`capturado_em` or `newer`.`capturado_em` = `p`.`capturado_em` and `newer`.`id` > `p`.`id`) limit 1)));

-- Objeto: vw_multas_detalhadas
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_multas_detalhadas` AS select `m`.`id` AS `id`,`m`.`protocolo` AS `protocolo`,`m`.`veiculo_id` AS `veiculo_id`,`m`.`unidade_id` AS `unidade_id`,`m`.`numero_auto` AS `numero_auto`,`m`.`orgao_autuador` AS `orgao_autuador`,`m`.`ocorrido_em` AS `ocorrido_em`,`m`.`precisao_ocorrencia` AS `precisao_ocorrencia`,`m`.`fim_dia_ocorrencia_utc` AS `fim_dia_ocorrencia_utc`,`m`.`data_vencimento` AS `data_vencimento`,`m`.`valor` AS `valor`,`m`.`descricao` AS `descricao`,`m`.`situacao` AS `situacao`,`m`.`responsabilidade_atual_id` AS `responsabilidade_atual_id`,`m`.`criado_por` AS `criado_por`,`m`.`versao` AS `versao`,`m`.`criado_em` AS `criado_em`,`r`.`responsavel_id` AS `responsavel_id`,`r`.`motorista_id` AS `motorista_id`,`r`.`viagem_id` AS `viagem_id`,`u`.`nome` AS `responsavel`,`ve`.`placa` AS `placa`,`ve`.`nome` AS `veiculo`,(select max(`c`.`numero`) from `multa_comprovantes` `c` where `c`.`multa_id` = `m`.`id`) AS `ultima_versao_comprovante`,`pg`.`valor` AS `valor_quitado`,`pg`.`pago_em` AS `pago_em` from ((((`multas` `m` join `veiculos` `ve` on(`ve`.`id` = `m`.`veiculo_id`)) left join `multa_responsabilidades` `r` on(`r`.`id` = `m`.`responsabilidade_atual_id` and `r`.`multa_id` = `m`.`id`)) left join `usuarios` `u` on(`u`.`id` = `r`.`responsavel_id`)) left join `pagamentos_multa` `pg` on(`pg`.`multa_id` = `m`.`id`));

-- Objeto: vw_notificacoes_usuario
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_notificacoes_usuario` AS select `nd`.`usuario_id` AS `usuario_id`,`nd`.`lida_em` AS `lida_em`,`nd`.`oculta_em` AS `oculta_em`,`nd`.`versao` AS `versao`,`nd`.`entregue_em` AS `entregue_em`,`ne`.`id` AS `id`,`ne`.`chave_idempotencia` AS `chave_idempotencia`,`ne`.`tipo` AS `tipo`,`ne`.`titulo` AS `titulo`,`ne`.`mensagem` AS `mensagem`,`ne`.`caminho_destino` AS `caminho_destino`,`ne`.`solicitacao_id` AS `solicitacao_id`,`ne`.`viagem_id` AS `viagem_id`,`ne`.`multa_id` AS `multa_id`,`ne`.`despesa_id` AS `despesa_id`,`ne`.`chamado_id` AS `chamado_id`,`ne`.`criado_em` AS `criado_em` from (`notificacao_destinatarios` `nd` join `notificacao_eventos` `ne` on(`ne`.`id` = `nd`.`evento_id`));

-- Objeto: vw_permissoes_efetivas
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_permissoes_efetivas` AS select `va`.`vinculo_id` AS `vinculo_id`,`va`.`usuario_id` AS `usuario_id`,`va`.`perfil_id` AS `perfil_id`,`va`.`unidade_id` AS `unidade_id`,`p`.`modulo_codigo` AS `modulo_codigo`,`p`.`acao_codigo` AS `acao_codigo`,`p`.`alcance` AS `alcance`,`pp`.`delegavel` AS `delegavel`,case `p`.`alcance` when 'proprios' then 1 when 'unidade' then 2 else 3 end AS `nivel_alcance` from (((`vw_vinculos_ativos` `va` join `perfil_permissoes` `pp` on(`pp`.`perfil_id` = `va`.`perfil_id`)) join `permissoes` `p` on(`p`.`id` = `pp`.`permissao_id`)) join `modulos` `m` on(`m`.`codigo` = `p`.`modulo_codigo` and `m`.`ativo` = 1));

-- Objeto: vw_resumo_financeiro_veiculo
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_resumo_financeiro_veiculo` AS select `v`.`id` AS `veiculo_id`,`v`.`placa` AS `placa`,`v`.`unidade_id` AS `unidade_id`,coalesce((select sum(`d`.`valor`) from `despesas` `d` where `d`.`veiculo_id` = `v`.`id` and `d`.`situacao` <> 'cancelada'),0) AS `despesas_registradas`,coalesce((select sum(`p`.`valor`) from (`pagamentos_despesa` `p` join `despesas` `d` on(`d`.`id` = `p`.`despesa_id`)) where `d`.`veiculo_id` = `v`.`id`),0) AS `despesas_pagas`,coalesce((select sum(`m`.`valor`) from `multas` `m` where `m`.`veiculo_id` = `v`.`id` and `m`.`situacao` <> 'cancelada'),0) AS `multas_registradas`,coalesce((select sum(`p`.`valor`) from (`pagamentos_multa` `p` join `multas` `m` on(`m`.`id` = `p`.`multa_id`)) where `m`.`veiculo_id` = `v`.`id`),0) AS `multas_quitadas`,(select count(0) from `multas` `m` where `m`.`veiculo_id` = `v`.`id` and `m`.`situacao` in ('sem_responsavel','aguardando_comprovante','em_conferencia','contestada')) AS `multas_pendentes` from `veiculos` `v`;

-- Objeto: vw_solicitacoes_atuais
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_solicitacoes_atuais` AS select `s`.`id` AS `id`,`s`.`protocolo` AS `protocolo`,`s`.`solicitante_id` AS `solicitante_id`,`s`.`unidade_id` AS `unidade_id`,`s`.`situacao` AS `situacao`,`s`.`versao` AS `versao`,`s`.`criado_em` AS `criado_em`,`r`.`id` AS `revisao_id`,`r`.`numero` AS `numero`,`r`.`finalidade` AS `finalidade`,`r`.`origem` AS `origem`,`r`.`destino` AS `destino`,`r`.`saida_prevista` AS `saida_prevista`,`r`.`retorno_previsto` AS `retorno_previsto`,`r`.`quantidade_passageiros` AS `quantidade_passageiros`,`r`.`necessita_motorista` AS `necessita_motorista`,`r`.`veiculo_pretendido_id` AS `veiculo_pretendido_id`,`r`.`motorista_sugerido_id` AS `motorista_sugerido_id`,`r`.`trajeto_planejado` AS `trajeto_planejado`,`r`.`distancia_prevista_km` AS `distancia_prevista_km`,`r`.`enviado_em` AS `enviado_em`,`u`.`nome` AS `solicitante`,`un`.`nome` AS `unidade` from (((`solicitacoes` `s` join `solicitacao_revisoes` `r` on(`r`.`id` = `s`.`revisao_atual_id` and `r`.`solicitacao_id` = `s`.`id`)) join `usuarios` `u` on(`u`.`id` = `s`.`solicitante_id`)) join `unidades` `un` on(`un`.`id` = `s`.`unidade_id`));

-- Objeto: vw_trajetos_observados
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_trajetos_observados` AS select `p`.`id` AS `registro_id`,'rastreador' AS `fonte`,`p`.`veiculo_id` AS `veiculo_id`,`p`.`viagem_id` AS `viagem_id`,`p`.`capturado_em` AS `ocorrido_em`,`p`.`latitude` AS `latitude`,`p`.`longitude` AS `longitude`,`p`.`velocidade_kmh` AS `velocidade_kmh`,NULL AS `descricao` from `posicoes_rastreamento` `p` union all select `p`.`id` AS `registro_id`,'manual' AS `fonte`,`v`.`veiculo_id` AS `veiculo_id`,`p`.`viagem_id` AS `viagem_id`,`p`.`ocorrido_em` AS `ocorrido_em`,`p`.`latitude` AS `latitude`,`p`.`longitude` AS `longitude`,NULL AS `velocidade_kmh`,`p`.`descricao` AS `descricao` from (`posicoes_manuais` `p` join `viagens` `v` on(`v`.`id` = `p`.`viagem_id`));

-- Objeto: vw_ultima_posicao_rastreador
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_ultima_posicao_rastreador` AS select `p`.`id` AS `id`,`p`.`instalacao_id` AS `instalacao_id`,`p`.`veiculo_id` AS `veiculo_id`,`p`.`viagem_id` AS `viagem_id`,`p`.`identificador_evento` AS `identificador_evento`,`p`.`capturado_em` AS `capturado_em`,`p`.`recebido_em` AS `recebido_em`,`p`.`latitude` AS `latitude`,`p`.`longitude` AS `longitude`,`p`.`velocidade_kmh` AS `velocidade_kmh`,`p`.`precisao_metros` AS `precisao_metros`,`p`.`ignicao` AS `ignicao` from `posicoes_rastreamento` `p` where !exists(select 1 from `posicoes_rastreamento` `newer` where `newer`.`instalacao_id` = `p`.`instalacao_id` and (`newer`.`capturado_em` > `p`.`capturado_em` or `newer`.`capturado_em` = `p`.`capturado_em` and `newer`.`id` > `p`.`id`) limit 1);

-- Objeto: vw_viagens_detalhadas
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_viagens_detalhadas` AS select `v`.`id` AS `id`,`v`.`protocolo` AS `protocolo`,`v`.`revisao_id` AS `revisao_id`,`v`.`reserva_id` AS `reserva_id`,`v`.`veiculo_id` AS `veiculo_id`,`v`.`motorista_id` AS `motorista_id`,`v`.`situacao` AS `situacao`,`v`.`saida_real` AS `saida_real`,`v`.`retorno_real` AS `retorno_real`,`v`.`quilometragem_saida` AS `quilometragem_saida`,`v`.`quilometragem_retorno` AS `quilometragem_retorno`,`v`.`saida_registrada_por` AS `saida_registrada_por`,`v`.`retorno_registrado_por` AS `retorno_registrado_por`,`v`.`observacao_saida` AS `observacao_saida`,`v`.`observacao_retorno` AS `observacao_retorno`,`v`.`versao` AS `versao`,`v`.`criado_em` AS `criado_em`,`s`.`id` AS `solicitacao_id`,`s`.`solicitante_id` AS `solicitante_id`,`s`.`unidade_id` AS `unidade_id`,`r`.`saida_prevista` AS `saida_prevista`,`r`.`retorno_previsto` AS `retorno_previsto`,`r`.`finalidade` AS `finalidade`,`r`.`origem` AS `origem`,`r`.`destino` AS `destino`,`r`.`trajeto_planejado` AS `trajeto_planejado`,`ve`.`placa` AS `placa`,`ve`.`nome` AS `veiculo`,`u`.`nome` AS `motorista`,case when `v`.`quilometragem_retorno` is not null then `v`.`quilometragem_retorno` - `v`.`quilometragem_saida` else NULL end AS `distancia_real_km` from ((((`viagens` `v` join `solicitacao_revisoes` `r` on(`r`.`id` = `v`.`revisao_id`)) join `solicitacoes` `s` on(`s`.`id` = `r`.`solicitacao_id`)) join `veiculos` `ve` on(`ve`.`id` = `v`.`veiculo_id`)) join `usuarios` `u` on(`u`.`id` = `v`.`motorista_id`));

-- Objeto: vw_vinculos_ativos
CREATE ALGORITHM=UNDEFINED SQL SECURITY INVOKER VIEW `vw_vinculos_ativos` AS select `up`.`id` AS `vinculo_id`,`up`.`usuario_id` AS `usuario_id`,`up`.`perfil_id` AS `perfil_id`,`up`.`unidade_id` AS `unidade_id`,`up`.`vigente_desde` AS `vigente_desde`,`up`.`vigente_ate` AS `vigente_ate` from (((`usuario_perfis` `up` join `usuarios` `u` on(`u`.`id` = `up`.`usuario_id` and `u`.`ativo` = 1)) join `unidades` `un` on(`un`.`id` = `up`.`unidade_id` and `un`.`ativa` = 1)) join `perfis` `pf` on(`pf`.`id` = `up`.`perfil_id` and `pf`.`ativo` = 1)) where `up`.`ativo` = 1 and `up`.`vigente_desde` <= utc_timestamp(6) and (`up`.`vigente_ate` is null or `up`.`vigente_ate` > utc_timestamp(6));