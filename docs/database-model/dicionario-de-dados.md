# Dicionário de dados — banco remoto

Snapshot: 16/10/2026 22:42, horário de Manaus. Servidor: 11.8.9-MariaDB-log. Modelo até 1.0.5.

73 tabelas, 691 colunas, 178 FKs. Tipos físicos extraídos do MariaDB; sem dados operacionais.

PK = chave primária; FK = participante de chave estrangeira; UK = participante de índice único. UK pode ser composto. Um marcador UK em um campo não afirma unicidade individual.

Defaults são apresentados como metadados SQL, preservando aspas e expressões. A DDL completa de cada tabela está em definicoes-banco-remoto.sql.


## Organização e identidade


### unidades

Unidades e setores internos de um único órgão.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Organização e identidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | unidade_superior_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 3 | codigo | UK | varchar(30) | não | NULL/ausente |  |  |
| 4 | nome | · | varchar(150) | não | NULL/ausente |  |  |
| 5 | endereco | · | varchar(255) | sim | NULL |  |  |
| 6 | cidade | · | varchar(100) | sim | NULL |  |  |
| 7 | uf | · | char(2) | sim | NULL |  |  |
| 8 | ativa | · | tinyint(3) unsigned | não | 1 |  |  |
| 9 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_unidade_superior`: não único; (unidade_superior_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_unidade_codigo`: único; (codigo); BTREE.

Relacionamentos de saída:

- **R160 / fk_unidade_superior**: (unidade_superior_id) → `unidades` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_unidade_ativa`: ``ativa` in (0,1)`.

Triggers: `tg_unidade_au`, `tg_unidade_bu`.


### usuarios

Identidade institucional. Senha somente em hash gerado no backend.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Organização e identidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | unidade_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | identificador | UK | varchar(100) | não | NULL/ausente |  |  |
| 4 | nome | · | varchar(150) | não | NULL/ausente |  |  |
| 5 | email | UK | varchar(254) | sim | NULL |  |  |
| 6 | telefone | · | varchar(30) | sim | NULL |  |  |
| 7 | senha_hash | · | varchar(255) | não | NULL/ausente |  |  |
| 8 | ativo | · | tinyint(3) unsigned | não | 1 |  |  |
| 9 | deve_trocar_senha | · | tinyint(3) unsigned | não | 0 |  |  |
| 10 | senha_alterada_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 11 | versao | · | bigint(20) unsigned | não | 1 |  |  |
| 12 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 13 | atualizado_em | · | datetime(6) | não | current_timestamp(6) | on update current_timestamp(6) |  |
| 14 | estado_identidade | · | enum('legado','pendente','ativa') | não | 'legado' |  |  |
| 15 | email_verificado_em | · | datetime(6) | sim | NULL |  |  |
| 16 | email_pendente | · | varchar(254) | sim | NULL |  |  |
| 17 | versao_seguranca | · | bigint(20) unsigned | não | 1 |  |  |
| 18 | senha_temporaria_emitida_em | · | datetime(6) | sim | NULL |  |  |
| 19 | senha_temporaria_expira_em | · | datetime(6) | sim | NULL |  |  |
| 20 | senha_temporaria_usada_em | · | datetime(6) | sim | NULL |  |  |
| 21 | senha_temporaria_revogada_em | · | datetime(6) | sim | NULL |  |  |

Índices:

- `ix_usuario_unidade_ativo`: não único; (unidade_id, ativo); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_usuario_email`: único; (email); BTREE.
- `uq_usuario_identificador`: único; (identificador); BTREE.

Relacionamentos de saída:

- **R161 / fk_usuario_unidade**: (unidade_id) → `unidades` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_identidade_pendente`: ``estado_identidade` <> 'pendente' or `deve_trocar_senha` = 1`.
- `ck_identidade_versao`: ``versao_seguranca` > 0`.
- `ck_senha_temporaria_validade`: ``senha_temporaria_expira_em` is null or `senha_temporaria_emitida_em` is not null and `senha_temporaria_expira_em` > `senha_temporaria_emitida_em``.
- `ck_usuario_flags`: ``ativo` in (0,1) and `deve_trocar_senha` in (0,1)`.

Triggers: `tg_identidade_au`, `tg_identidade_bi`, `tg_identidade_bu`, `tg_usuario_au`, `tg_usuario_bd`, `tg_usuario_bi`, `tg_usuario_bu`.


### motoristas

Habilitação de usuários que podem efetivamente conduzir veículos.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Organização e identidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | usuario_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | numero_cnh | UK | varchar(20) | não | NULL/ausente |  |  |
| 3 | categoria_cnh | · | varchar(10) | não | NULL/ausente |  |  |
| 4 | categorias_autorizadas | · | varchar(5) | não | NULL/ausente |  |  |
| 5 | validade_cnh | · | date | não | NULL/ausente |  |  |
| 6 | ativo | · | tinyint(3) unsigned | não | 1 |  |  |
| 7 | cnh_arquivo_id | FK | bigint(20) unsigned | sim | NULL |  |  |

Índices:

- `fk_motorista_documento`: não único; (cnh_arquivo_id); BTREE.
- `PRIMARY`: único; (usuario_id); BTREE.
- `uq_motorista_cnh`: único; (numero_cnh); BTREE.

Relacionamentos de saída:

- **R078 / fk_motorista_documento**: (cnh_arquivo_id) → `arquivos` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R079 / fk_motorista_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.

Checks:

- `ck_motorista_ativo`: ``ativo` in (0,1)`.
- `ck_motorista_categoria`: ``categorias_autorizadas` regexp '^[ABCDE]{1,5}$'`.

### preferencias_usuario

Preferências compartilhadas por computador e celular.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Organização e identidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | usuario_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | tema | · | enum('claro','escuro','dispositivo') | não | 'dispositivo' |  |  |
| 3 | itens_por_pagina | · | smallint(5) unsigned | não | 10 |  |  |
| 4 | notificacoes_internas | · | tinyint(3) unsigned | não | 1 |  |  |
| 5 | atualizado_em | · | datetime(6) | não | current_timestamp(6) | on update current_timestamp(6) |  |

Índices:

- `PRIMARY`: único; (usuario_id); BTREE.

Relacionamentos de saída:

- **R126 / fk_preferencia_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.

Checks:

- `ck_preferencia_notificacao`: ``notificacoes_internas` in (0,1)`.
- `ck_preferencia_paginacao`: ``itens_por_pagina` in (10,20,50,100)`.

### configuracao_sistema

Uma única configuração do órgão; também serializa operações administrativas.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Organização e identidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | tinyint(3) unsigned | não | NULL/ausente |  |  |
| 2 | nome_sistema | · | varchar(120) | não | NULL/ausente |  |  |
| 3 | nome_orgao | · | varchar(150) | não | NULL/ausente |  |  |
| 4 | sigla_orgao | · | varchar(30) | sim | NULL |  |  |
| 5 | fuso_horario | · | varchar(64) | não | 'America/Porto_Velho' |  |  |
| 6 | moeda | · | char(3) | não | 'BRL' |  |  |
| 7 | sessao_inatividade_minutos | · | smallint(5) unsigned | não | 30 |  |  |
| 8 | limite_sem_comunicacao_minutos | · | smallint(5) unsigned | não | 15 |  |  |
| 9 | logo_arquivo_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 10 | bootstrap_concluido | · | tinyint(3) unsigned | não | 0 |  |  |
| 11 | atualizado_em | · | datetime(6) | não | current_timestamp(6) | on update current_timestamp(6) |  |

Índices:

- `fk_config_logo`: não único; (logo_arquivo_id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R027 / fk_config_logo**: (logo_arquivo_id) → `arquivos` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_config_bootstrap`: ``bootstrap_concluido` in (0,1)`.
- `ck_config_comunicacao`: ``limite_sem_comunicacao_minutos` > 0`.
- `ck_config_moeda`: ``moeda` = 'BRL'`.
- `ck_config_sessao`: ``sessao_inatividade_minutos` between 5 and 1440`.
- `ck_config_unica`: ``id` = 1`.

Triggers: `tg_config_bd`, `tg_config_bu`.


### versoes_modelo

Identifica a versão instalada do esquema.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Organização e identidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | versao | PK | varchar(30) | não | NULL/ausente |  |  |
| 2 | instalado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 3 | descricao | · | varchar(255) | não | NULL/ausente |  |  |

Índices:

- `PRIMARY`: único; (versao); BTREE.

## Perfis e permissões


### modulos

Catálogo dos módulos de negócio e de administração.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Perfis e permissões.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | codigo | PK | varchar(40) | não | NULL/ausente |  |  |
| 2 | nome | · | varchar(100) | não | NULL/ausente |  |  |
| 3 | ordem | · | smallint(5) unsigned | não | 0 |  |  |
| 4 | ativo | · | tinyint(3) unsigned | não | 1 |  |  |

Índices:

- `PRIMARY`: único; (codigo); BTREE.

Checks:

- `ck_modulo_ativo`: ``ativo` in (0,1)`.

Triggers: `tg_modulo_bu`.


### acoes

Ações verificadas pelo servidor, sem privilégio implícito por nome de perfil.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Perfis e permissões.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | codigo | PK | varchar(40) | não | NULL/ausente |  |  |
| 2 | nome | · | varchar(100) | não | NULL/ausente |  |  |

Índices:

- `PRIMARY`: único; (codigo); BTREE.

### modulo_acoes

Combinações de módulo e ação realmente suportadas pelo sistema.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Perfis e permissões.

Associação: N:N entre módulos e ações; chave composta da combinação permitida.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | modulo_codigo | PK/FK | varchar(40) | não | NULL/ausente |  |  |
| 2 | acao_codigo | PK/FK | varchar(40) | não | NULL/ausente |  |  |

Índices:

- `fk_ma_acao`: não único; (acao_codigo); BTREE.
- `PRIMARY`: único; (modulo_codigo, acao_codigo); BTREE.

Relacionamentos de saída:

- **R076 / fk_ma_acao**: (acao_codigo) → `acoes` (codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.
- **R077 / fk_ma_modulo**: (modulo_codigo) → `modulos` (codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.

Triggers: `tg_imutavel_modulo_acoes_bd`, `tg_imutavel_modulo_acoes_bu`.


### perfis

Quatro perfis iniciais e perfis personalizados.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Perfis e permissões.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | codigo | UK | varchar(60) | não | NULL/ausente |  |  |
| 3 | nome | · | varchar(100) | não | NULL/ausente |  |  |
| 4 | descricao | · | text | sim | NULL |  |  |
| 5 | ativo | · | tinyint(3) unsigned | não | 1 |  |  |
| 6 | perfil_origem_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 7 | criado_por | FK | bigint(20) unsigned | sim | NULL |  |  |
| 8 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 9 | atualizado_em | · | datetime(6) | não | current_timestamp(6) | on update current_timestamp(6) |  |

Índices:

- `fk_perfil_autor`: não único; (criado_por); BTREE.
- `fk_perfil_origem`: não único; (perfil_origem_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_perfil_codigo`: único; (codigo); BTREE.

Relacionamentos de saída:

- **R113 / fk_perfil_autor**: (criado_por) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R114 / fk_perfil_origem**: (perfil_origem_id) → `perfis` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_perfil_ativo`: ``ativo` in (0,1)`.

Triggers: `tg_perfil_au`, `tg_perfil_bd`, `tg_perfil_bu`.


### permissoes

Permissão formada por módulo, ação e alcance.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Perfis e permissões.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | modulo_codigo | FK/UK | varchar(40) | não | NULL/ausente |  |  |
| 3 | acao_codigo | FK/UK | varchar(40) | não | NULL/ausente |  |  |
| 4 | alcance | UK | enum('proprios','unidade','orgao') | não | NULL/ausente |  |  |

Índices:

- `fk_permissao_acao`: não único; (acao_codigo); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_permissao`: único; (modulo_codigo, acao_codigo, alcance); BTREE.

Relacionamentos de saída:

- **R115 / fk_permissao_acao**: (acao_codigo) → `acoes` (codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R116 / fk_permissao_combinacao**: (modulo_codigo, acao_codigo) → `modulo_acoes` (modulo_codigo, acao_codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R117 / fk_permissao_modulo**: (modulo_codigo) → `modulos` (codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Triggers: `tg_imutavel_permissoes_bd`, `tg_imutavel_permissoes_bu`.


### perfil_permissoes

Matriz de ações e alcances; marca concessões que podem ser delegadas.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Perfis e permissões.

Associação: N:N entre perfis e permissões; inclui autoria e delegabilidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | perfil_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | permissao_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | delegavel | · | tinyint(3) unsigned | não | 0 |  |  |
| 4 | concedido_por | FK | bigint(20) unsigned | sim | NULL |  |  |
| 5 | concedido_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_pp_autor`: não único; (concedido_por); BTREE.
- `fk_pp_permissao`: não único; (permissao_id); BTREE.
- `PRIMARY`: único; (perfil_id, permissao_id); BTREE.

Relacionamentos de saída:

- **R110 / fk_pp_autor**: (concedido_por) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R111 / fk_pp_perfil**: (perfil_id) → `perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.
- **R112 / fk_pp_permissao**: (permissao_id) → `permissoes` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.

Checks:

- `ck_pp_delegavel`: ``delegavel` in (0,1)`.

Triggers: `tg_pp_bd`, `tg_pp_bi`, `tg_pp_bu`, `tg_pp_controle_ad`, `tg_pp_controle_ai`.


### usuario_perfis

Vínculos independentes com início, fim opcional e unidade de alcance.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Perfis e permissões.

Associação: N:N entre usuários e perfis, com unidade de alcance, vigência e histórico; não há unicidade simples do par usuário/perfil.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | usuario_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | perfil_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | unidade_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | vigente_desde | · | datetime(6) | não | NULL/ausente |  |  |
| 6 | vigente_ate | · | datetime(6) | sim | NULL |  |  |
| 7 | ativo | · | tinyint(3) unsigned | não | 1 |  |  |
| 8 | concedido_por | FK | bigint(20) unsigned | sim | NULL |  |  |
| 9 | revogado_por | FK | bigint(20) unsigned | sim | NULL |  |  |
| 10 | motivo_revogacao | · | varchar(500) | sim | NULL |  |  |
| 11 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_up_concessor`: não único; (concedido_por); BTREE.
- `fk_up_revogador`: não único; (revogado_por); BTREE.
- `fk_up_unidade`: não único; (unidade_id); BTREE.
- `ix_vinculo_perfil`: não único; (perfil_id, ativo, vigente_ate); BTREE.
- `ix_vinculo_validade`: não único; (usuario_id, ativo, vigente_desde, vigente_ate); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_vinculo_identidade`: único; (id, usuario_id); BTREE.

Relacionamentos de saída:

- **R162 / fk_up_concessor**: (concedido_por) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R163 / fk_up_perfil**: (perfil_id) → `perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R164 / fk_up_revogador**: (revogado_por) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R165 / fk_up_unidade**: (unidade_id) → `unidades` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R166 / fk_up_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_vinculo_ativo`: ``ativo` in (0,1)`.
- `ck_vinculo_datas`: ``vigente_ate` is null or `vigente_ate` > `vigente_desde``.

Triggers: `tg_vinculo_ai`, `tg_vinculo_au`, `tg_vinculo_bd`, `tg_vinculo_bi`, `tg_vinculo_bu`.


### controle_vinculos

Materialização técnica mantida por triggers para locks de acesso e proteção administrativa.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Perfis e permissões.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | vinculo_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | usuario_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | perfil_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | unidade_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | vigente_desde | · | datetime(6) | não | NULL/ausente |  |  |
| 6 | vigente_ate | · | datetime(6) | sim | NULL |  |  |
| 7 | ativo_vinculo | · | tinyint(3) unsigned | não | NULL/ausente |  |  |
| 8 | ativo_usuario | · | tinyint(3) unsigned | não | NULL/ausente |  |  |
| 9 | ativo_perfil | · | tinyint(3) unsigned | não | NULL/ausente |  |  |
| 10 | ativo_unidade | · | tinyint(3) unsigned | não | NULL/ausente |  |  |
| 11 | pode_administrar | · | tinyint(3) unsigned | não | NULL/ausente |  |  |

Índices:

- `fk_cv_perfil`: não único; (perfil_id); BTREE.
- `fk_cv_unidade`: não único; (unidade_id); BTREE.
- `ix_cv_admin`: não único; (pode_administrar, ativo_vinculo, vigente_ate); BTREE.
- `ix_cv_overlap`: não único; (usuario_id, perfil_id, ativo_vinculo, vigente_desde, vigente_ate); BTREE.
- `PRIMARY`: único; (vinculo_id); BTREE.

Relacionamentos de saída:

- **R030 / fk_cv_perfil**: (perfil_id) → `perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R031 / fk_cv_unidade**: (unidade_id) → `unidades` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R032 / fk_cv_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R033 / fk_cv_vinculo**: (vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.

Checks:

- `ck_cv_flags`: ``ativo_vinculo` in (0,1) and `ativo_usuario` in (0,1) and `ativo_perfil` in (0,1) and `ativo_unidade` in (0,1) and `pode_administrar` in (0,1)`.

## Rotas e auditoria


### rotas_sistema

Rotas HTTP e de interface. Não são trajetos de veículos nem código executável.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Rotas e auditoria.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | chave | UK | varchar(80) | não | NULL/ausente |  |  |
| 3 | nome | · | varchar(120) | não | NULL/ausente |  |  |
| 4 | caminho | UK | varchar(255) | não | NULL/ausente |  |  |
| 5 | metodo_http | UK | enum('GET','POST','PUT','PATCH','DELETE') | não | 'GET' |  |  |
| 6 | modulo_codigo | FK | varchar(40) | não | NULL/ausente |  |  |
| 7 | descricao | · | text | sim | NULL |  |  |
| 8 | ativa | · | tinyint(3) unsigned | não | 1 |  |  |
| 9 | visivel_menu | · | tinyint(3) unsigned | não | 1 |  |  |
| 10 | implementada | · | tinyint(3) unsigned | não | 0 |  |  |
| 11 | protegida | · | tinyint(3) unsigned | não | 0 |  |  |
| 12 | ordem | · | smallint(5) unsigned | não | 0 |  |  |
| 13 | criado_por | FK | bigint(20) unsigned | sim | NULL |  |  |

Índices:

- `fk_rota_autor`: não único; (criado_por); BTREE.
- `fk_rota_modulo`: não único; (modulo_codigo); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_rota_chave`: único; (chave); BTREE.
- `uq_rota_metodo_caminho`: único; (metodo_http, caminho); BTREE.

Relacionamentos de saída:

- **R137 / fk_rota_autor**: (criado_por) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R138 / fk_rota_modulo**: (modulo_codigo) → `modulos` (codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_rota_caminho`: `left(`caminho`,1) = '/'`.
- `ck_rota_flags`: ``ativa` in (0,1) and `visivel_menu` in (0,1) and `implementada` in (0,1) and `protegida` in (0,1)`.

Triggers: `tg_preservar_rotas_sistema_bd`, `tg_rota_bu`.


### rota_permissoes

Todas as ações requeridas por uma rota, sem elevar o alcance concedido.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Rotas e auditoria.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | rota_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | modulo_codigo | PK/FK | varchar(40) | não | NULL/ausente |  |  |
| 3 | acao_codigo | PK/FK | varchar(40) | não | NULL/ausente |  |  |

Índices:

- `fk_rp_acao`: não único; (acao_codigo); BTREE.
- `fk_rp_combinacao`: não único; (modulo_codigo, acao_codigo); BTREE.
- `PRIMARY`: único; (rota_id, modulo_codigo, acao_codigo); BTREE.

Relacionamentos de saída:

- **R139 / fk_rp_acao**: (acao_codigo) → `acoes` (codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.
- **R140 / fk_rp_combinacao**: (modulo_codigo, acao_codigo) → `modulo_acoes` (modulo_codigo, acao_codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.
- **R141 / fk_rp_modulo**: (modulo_codigo) → `modulos` (codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.
- **R142 / fk_rp_rota**: (rota_id) → `rotas_sistema` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.

### auditoria

Log acrescentado sem edição. Não registrar senhas, tokens ou bytes de arquivos.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Rotas e auditoria.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | ator_usuario_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 3 | ator_vinculo_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 4 | sessao_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 5 | ator_nome_snapshot | · | varchar(150) | sim | NULL |  |  |
| 6 | perfil_nome_snapshot | · | varchar(100) | sim | NULL |  |  |
| 7 | perfil_codigo_snapshot | · | varchar(60) | sim | NULL |  |  |
| 8 | evento | · | varchar(80) | não | NULL/ausente |  |  |
| 9 | entidade | · | varchar(60) | sim | NULL |  |  |
| 10 | entidade_id | · | bigint(20) unsigned | sim | NULL |  |  |
| 11 | correlacao | · | char(36) | sim | NULL |  |  |
| 12 | descricao | · | varchar(1000) | sim | NULL |  |  |
| 13 | antes | · | longtext | sim | NULL |  |  |
| 14 | depois | · | longtext | sim | NULL |  |  |
| 15 | ip | · | varbinary(16) | sim | NULL |  |  |
| 16 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_auditoria_sessao`: não único; (sessao_id, ator_usuario_id); BTREE.
- `fk_auditoria_vinculo`: não único; (ator_vinculo_id, ator_usuario_id); BTREE.
- `ix_auditoria_ator`: não único; (ator_usuario_id, criado_em); BTREE.
- `ix_auditoria_data`: não único; (criado_em, id); BTREE.
- `ix_auditoria_entidade`: não único; (entidade, entidade_id, criado_em); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R015 / fk_auditoria_sessao**: (sessao_id, ator_usuario_id) → `sessoes` (id, usuario_id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R016 / fk_auditoria_usuario**: (ator_usuario_id) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R017 / fk_auditoria_vinculo**: (ator_vinculo_id, ator_usuario_id) → `usuario_perfis` (id, usuario_id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `antes`: `json_valid(`antes`)`.
- `ck_auditoria_contexto`: ``ator_vinculo_id` is null and `sessao_id` is null or `ator_usuario_id` is not null`.
- `depois`: `json_valid(`depois`)`.

Triggers: `tg_auditoria_bi`, `tg_imutavel_auditoria_bd`, `tg_imutavel_auditoria_bu`.


## Sessões e recuperação


### sessoes

Sessões com token somente em hash; atividade, expiração e encerramento separados.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Sessões e recuperação.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | usuario_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | vinculo_ativo_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 4 | token_hash | UK | binary(32) | não | NULL/ausente |  |  |
| 5 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 6 | ultima_atividade_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 7 | expira_em | · | datetime(6) | não | NULL/ausente |  |  |
| 8 | encerrada_em | · | datetime(6) | sim | NULL |  |  |
| 9 | motivo_encerramento | · | enum('logout','inatividade','prazo','revogacao','troca_senha') | sim | NULL |  |  |
| 10 | ip | · | varbinary(16) | sim | NULL |  |  |
| 11 | agente_usuario | · | varchar(500) | sim | NULL |  |  |
| 12 | autenticada_em | · | datetime(6) | sim | NULL |  |  |
| 13 | reautenticar_ate | · | datetime(6) | sim | NULL |  |  |
| 14 | mfa_verificada_em | · | datetime(6) | sim | NULL |  |  |
| 15 | mfa_vinculo_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 16 | mfa_versao | · | bigint(20) unsigned | sim | NULL |  |  |
| 17 | mfa_http_hash | · | binary(32) | sim | NULL |  |  |

Índices:

- `fk_sessao_mfa_vinculo`: não único; (mfa_vinculo_id, usuario_id); BTREE.
- `fk_sessao_vinculo`: não único; (vinculo_ativo_id, usuario_id); BTREE.
- `ix_sessao_ativa`: não único; (usuario_id, encerrada_em, expira_em); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_sessao_identidade`: único; (id, usuario_id); BTREE.
- `uq_sessao_token`: único; (token_hash); BTREE.

Relacionamentos de saída:

- **R143 / fk_sessao_mfa_vinculo**: (mfa_vinculo_id, usuario_id) → `usuario_perfis` (id, usuario_id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R144 / fk_sessao_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R145 / fk_sessao_vinculo**: (vinculo_ativo_id, usuario_id) → `usuario_perfis` (id, usuario_id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_sessao_datas`: ``expira_em` > `criado_em` and `ultima_atividade_em` >= `criado_em``.
- `ck_sessao_fim`: ``encerrada_em` is null and `motivo_encerramento` is null or `encerrada_em` is not null and `motivo_encerramento` is not null`.
- `ck_sessao_reautenticacao`: ``reautenticar_ate` is null or `autenticada_em` is not null and `reautenticar_ate` > `autenticada_em``.

Triggers: `bi_sessoes_reautenticacao`.


### recuperacoes_senha

Tokens de uso único com validade; envio e consumo pertencem ao backend.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Sessões e recuperação.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | usuario_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | token_hash | UK | binary(32) | não | NULL/ausente |  |  |
| 4 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 5 | expira_em | · | datetime(6) | não | NULL/ausente |  |  |
| 6 | usado_em | · | datetime(6) | sim | NULL |  |  |
| 7 | invalidado_em | · | datetime(6) | sim | NULL |  |  |
| 8 | email_destino | · | varchar(254) | sim | NULL |  |  |
| 9 | versao_seguranca | · | bigint(20) unsigned | sim | NULL |  |  |

Índices:

- `ix_recuperacao_usuario_versao`: não único; (usuario_id, versao_seguranca, invalidado_em, usado_em); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_recuperacao_token`: único; (token_hash); BTREE.

Relacionamentos de saída:

- **R129 / fk_recuperacao_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_recuperacao_validade`: ``expira_em` > `criado_em``.

### confirmacoes_identidade



Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Sessões e recuperação.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | usuario_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | vinculo_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 4 | finalidade | · | enum('ativacao','email') | não | NULL/ausente |  |  |
| 5 | email_destino | · | varchar(254) | não | NULL/ausente |  |  |
| 6 | versao_seguranca | · | bigint(20) unsigned | não | NULL/ausente |  |  |
| 7 | token_hash | UK | binary(32) | não | NULL/ausente |  |  |
| 8 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 9 | expira_em | · | datetime(6) | não | NULL/ausente |  |  |
| 10 | usado_em | · | datetime(6) | sim | NULL |  |  |
| 11 | invalidado_em | · | datetime(6) | sim | NULL |  |  |

Índices:

- `fk_confirmacao_vinculo`: não único; (vinculo_id, usuario_id); BTREE.
- `ix_confirmacao_usuario_versao`: não único; (usuario_id, versao_seguranca, finalidade, invalidado_em, usado_em); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_confirmacao_token`: único; (token_hash); BTREE.

Relacionamentos de saída:

- **R028 / fk_confirmacao_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R029 / fk_confirmacao_vinculo**: (vinculo_id, usuario_id) → `usuario_perfis` (id, usuario_id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_confirmacao_finalidade`: ``finalidade` <> 'ativacao' or `vinculo_id` is not null`.
- `ck_confirmacao_validade`: ``expira_em` > `criado_em` and `versao_seguranca` > 0`.

### email_outbox

Durable intent; temporary secret encrypted with application key, erased at terminal state/expiry.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Sessões e recuperação.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | evento_chave | UK | char(36) | não | NULL/ausente |  |  |
| 3 | usuario_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | finalidade | · | enum('ativacao','email','recuperacao','aviso') | não | NULL/ausente |  |  |
| 5 | versao_seguranca | · | bigint(20) unsigned | não | NULL/ausente |  |  |
| 6 | destinatario_cifrado | · | text | sim | NULL |  |  |
| 7 | token_cifrado | · | text | sim | NULL |  |  |
| 8 | recuperacao_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 9 | confirmacao_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 10 | aviso_codigo | · | varchar(80) | sim | NULL |  |  |
| 11 | estado | · | enum('pendente','enfileirado','enviando','aceito','cancelado','falhou') | não | 'pendente' |  |  |
| 12 | tentativas | · | smallint(5) unsigned | não | 0 |  |  |
| 13 | proxima_tentativa_em | · | datetime(6) | não | NULL/ausente |  |  |
| 14 | expira_em | · | datetime(6) | não | NULL/ausente |  |  |
| 15 | lease_id | · | char(36) | sim | NULL |  |  |
| 16 | lease_ate | · | datetime(6) | sim | NULL |  |  |
| 17 | aceito_smtp_em | · | datetime(6) | sim | NULL |  |  |
| 18 | provedor_id | · | varchar(150) | sim | NULL |  |  |
| 19 | codigo_erro | · | varchar(60) | sim | NULL |  |  |
| 20 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_outbox_confirmacao`: não único; (confirmacao_id); BTREE.
- `fk_outbox_recuperacao`: não único; (recuperacao_id); BTREE.
- `ix_outbox_identidade`: não único; (usuario_id, versao_seguranca, estado); BTREE.
- `ix_outbox_relay`: não único; (estado, proxima_tentativa_em, lease_ate); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_outbox_evento`: único; (evento_chave); BTREE.

Relacionamentos de saída:

- **R048 / fk_outbox_confirmacao**: (confirmacao_id) → `confirmacoes_identidade` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R049 / fk_outbox_recuperacao**: (recuperacao_id) → `recuperacoes_senha` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R050 / fk_outbox_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_outbox_pedido`: ``finalidade` = 'recuperacao' and `recuperacao_id` is not null and `confirmacao_id` is null or `finalidade` in ('ativacao','email') and `confirmacao_id` is not null and `recuperacao_id` is null or `finalidade` = 'aviso' and `confirmacao_id` is null and `recuperacao_id` is null`.
- `ck_outbox_segredo`: ``estado` in ('aceito','cancelado','falhou') or `destinatario_cifrado` is not null and (`finalidade` = 'aviso' or `token_cifrado` is not null)`.
- `ck_outbox_validade`: ``expira_em` > `criado_em` and `versao_seguranca` > 0`.

## Autenticação multifator


### credenciais_mfa



Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Autenticação multifator.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | usuario_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | credential_id | UK | varchar(1366) | não | NULL/ausente |  |  |
| 4 | registro | · | longtext | não | NULL/ausente |  |  |
| 5 | criada_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 6 | usada_em | · | datetime(6) | sim | NULL |  |  |
| 7 | revogada_em | · | datetime(6) | sim | NULL |  |  |

Índices:

- `ix_credenciais_usuario`: não único; (usuario_id, revogada_em); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_credencial_mfa`: único; (credential_id); BTREE.

Relacionamentos de saída:

- **R034 / fk_credencial_mfa_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `registro`: `json_valid(`registro`)`.

### desafios_mfa



Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Autenticação multifator.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | usuario_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | sessao_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | versao_seguranca | · | bigint(20) unsigned | não | NULL/ausente |  |  |
| 6 | http_hash | · | binary(32) | não | NULL/ausente |  |  |
| 7 | finalidade | · | enum('registration','assertion') | não | NULL/ausente |  |  |
| 8 | opcoes | · | longtext | não | NULL/ausente |  |  |
| 9 | expira_em | · | datetime(6) | não | NULL/ausente |  |  |
| 10 | usado_em | · | datetime(6) | sim | NULL |  |  |
| 11 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_desafio_mfa_sessao`: não único; (sessao_id, usuario_id); BTREE.
- `fk_desafio_mfa_usuario`: não único; (usuario_id); BTREE.
- `fk_desafio_mfa_vinculo`: não único; (vinculo_id, usuario_id); BTREE.
- `ix_desafio_sessao`: não único; (sessao_id, usado_em); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R035 / fk_desafio_mfa_sessao**: (sessao_id, usuario_id) → `sessoes` (id, usuario_id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R036 / fk_desafio_mfa_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R037 / fk_desafio_mfa_vinculo**: (vinculo_id, usuario_id) → `usuario_perfis` (id, usuario_id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_desafio_mfa_prazo`: ``expira_em` > `criado_em``.
- `opcoes`: `json_valid(`opcoes`)`.

### recuperacoes_mfa



Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Autenticação multifator.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | usuario_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | solicitada_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | versao_seguranca | · | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | justificativa | · | varchar(500) | não | NULL/ausente |  |  |
| 6 | criada_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 7 | expira_em | · | datetime(6) | não | NULL/ausente |  |  |
| 8 | concluida_em | · | datetime(6) | sim | NULL |  |  |

Índices:

- `fk_recuperacao_mfa_solicitante`: não único; (solicitada_por); BTREE.
- `fk_recuperacao_mfa_usuario`: não único; (usuario_id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R127 / fk_recuperacao_mfa_solicitante**: (solicitada_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R128 / fk_recuperacao_mfa_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_recuperacao_mfa_pessoas`: ``usuario_id` <> `solicitada_por``.
- `ck_recuperacao_mfa_prazo`: ``expira_em` > `criada_em``.

### aprovacoes_mfa



Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Autenticação multifator.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | recuperacao_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | aprovada_por | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | aprovada_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_aprovacao_mfa_usuario`: não único; (aprovada_por); BTREE.
- `fk_aprovacao_mfa_vinculo`: não único; (vinculo_id, aprovada_por); BTREE.
- `PRIMARY`: único; (recuperacao_id, aprovada_por); BTREE.

Relacionamentos de saída:

- **R011 / fk_aprovacao_mfa_pedido**: (recuperacao_id) → `recuperacoes_mfa` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.
- **R012 / fk_aprovacao_mfa_usuario**: (aprovada_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.
- **R013 / fk_aprovacao_mfa_vinculo**: (vinculo_id, aprovada_por) → `usuario_perfis` (id, usuario_id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Triggers: `bi_aprovacoes_mfa_independentes`.


## Frota e documentos


### categorias_veiculo

Categorias de veículos e categoria mínima de habilitação.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Frota e documentos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | smallint(5) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | nome | UK | varchar(60) | não | NULL/ausente |  |  |
| 3 | categoria_cnh_requerida | · | char(1) | não | 'B' |  |  |
| 4 | ativa | · | tinyint(3) unsigned | não | 1 |  |  |

Índices:

- `PRIMARY`: único; (id); BTREE.
- `uq_categoria_veiculo_nome`: único; (nome); BTREE.

Checks:

- `ck_categoria_cnh`: ``categoria_cnh_requerida` in ('A','B','C','D','E')`.
- `ck_categoria_veiculo_ativa`: ``ativa` in (0,1)`.

### veiculos

Cadastro da frota. Em viagem e manutenção são estados derivados da agenda.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Frota e documentos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | unidade_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | categoria_id | FK | smallint(5) unsigned | não | NULL/ausente |  |  |
| 4 | nome | · | varchar(120) | não | NULL/ausente |  |  |
| 5 | placa | UK | varchar(10) | não | NULL/ausente |  |  |
| 6 | renavam | UK | varchar(20) | sim | NULL |  |  |
| 7 | chassi | UK | varchar(30) | sim | NULL |  |  |
| 8 | marca | · | varchar(80) | sim | NULL |  |  |
| 9 | modelo | · | varchar(80) | sim | NULL |  |  |
| 10 | ano_fabricacao | · | smallint(5) unsigned | sim | NULL |  |  |
| 11 | ano_modelo | · | smallint(5) unsigned | sim | NULL |  |  |
| 12 | capacidade | · | smallint(5) unsigned | não | NULL/ausente |  |  |
| 13 | quilometragem_atual | · | decimal(12,1) | não | 0.0 |  |  |
| 14 | situacao_cadastro | · | enum('ativo','inativo','baixado') | não | 'ativo' |  |  |
| 15 | observacoes | · | text | sim | NULL |  |  |
| 16 | criado_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 17 | versao | · | bigint(20) unsigned | não | 1 |  |  |
| 18 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 19 | atualizado_em | · | datetime(6) | não | current_timestamp(6) | on update current_timestamp(6) |  |

Índices:

- `fk_veiculo_autor`: não único; (criado_por); BTREE.
- `fk_veiculo_categoria`: não único; (categoria_id); BTREE.
- `ix_veiculo_unidade_status`: não único; (unidade_id, situacao_cadastro); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_veiculo_chassi`: único; (chassi); BTREE.
- `uq_veiculo_placa`: único; (placa); BTREE.
- `uq_veiculo_renavam`: único; (renavam); BTREE.

Relacionamentos de saída:

- **R167 / fk_veiculo_autor**: (criado_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R168 / fk_veiculo_categoria**: (categoria_id) → `categorias_veiculo` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R169 / fk_veiculo_unidade**: (unidade_id) → `unidades` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_veiculo_capacidade`: ``capacidade` between 1 and 100`.
- `ck_veiculo_km`: ``quilometragem_atual` >= 0`.

Triggers: `tg_preservar_veiculos_bd`.


### documentos_veiculo

Documentos e suas versões; vencimento independente do arquivo.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Frota e documentos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | veiculo_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | arquivo_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | tipo | · | varchar(60) | não | NULL/ausente |  |  |
| 5 | numero | · | varchar(80) | sim | NULL |  |  |
| 6 | exercicio | · | smallint(5) unsigned | sim | NULL |  |  |
| 7 | emitido_em | · | date | sim | NULL |  |  |
| 8 | vence_em | · | date | sim | NULL |  |  |
| 9 | substitui_documento_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 10 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_dv_anterior`: não único; (substitui_documento_id); BTREE.
- `fk_dv_arquivo`: não único; (arquivo_id); BTREE.
- `ix_documento_vencimento`: não único; (vence_em, veiculo_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_documento_veiculo_arquivo`: único; (veiculo_id, arquivo_id); BTREE.

Relacionamentos de saída:

- **R045 / fk_dv_anterior**: (substitui_documento_id) → `documentos_veiculo` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R046 / fk_dv_arquivo**: (arquivo_id) → `arquivos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R047 / fk_dv_veiculo**: (veiculo_id) → `veiculos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_dv_datas`: ``emitido_em` is null or `vence_em` is null or `vence_em` >= `emitido_em``.

Triggers: `tg_imutavel_documentos_veiculo_bd`, `tg_imutavel_documentos_veiculo_bu`.


### rastreadores

Dispositivo e identificação do provedor, sem credenciais da integração.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Frota e documentos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | identificador_externo | UK | varchar(100) | não | NULL/ausente |  |  |
| 3 | provedor | UK | varchar(100) | não | NULL/ausente |  |  |
| 4 | tipo | · | enum('gps','telematica','outro') | não | 'gps' |  |  |
| 5 | modelo | · | varchar(100) | sim | NULL |  |  |
| 6 | ativo | · | tinyint(3) unsigned | não | 1 |  |  |
| 7 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `PRIMARY`: único; (id); BTREE.
- `uq_rastreador_provedor`: único; (provedor, identificador_externo); BTREE.

Checks:

- `ck_rastreador_ativo`: ``ativo` in (0,1)`.

## Solicitações e revisões


### solicitacoes

Protocolo persistente da solicitação; aponta para a revisão em análise.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Solicitações e revisões.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/FK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | protocolo | UK | varchar(40) | não | NULL/ausente |  |  |
| 3 | solicitante_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | unidade_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | revisao_atual_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 6 | situacao | · | enum('rascunho','aguardando_analise','ajustes_solicitados','aprovada','negada','cancelada') | não | 'rascunho' |  |  |
| 7 | versao | · | bigint(20) unsigned | não | 1 |  |  |
| 8 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 9 | atualizado_em | · | datetime(6) | não | current_timestamp(6) | on update current_timestamp(6) |  |

Índices:

- `fk_solicitacao_revisao_atual`: não único; (id, revisao_atual_id); BTREE.
- `ix_solicitacao_proprios`: não único; (solicitante_id, situacao, criado_em, id); BTREE.
- `ix_solicitacao_unidade`: não único; (unidade_id, situacao, criado_em, id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_solicitacao_protocolo`: único; (protocolo); BTREE.

Relacionamentos de saída:

- **R157 / fk_solicitacao_autor**: (solicitante_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R158 / fk_solicitacao_revisao_atual**: (id, revisao_atual_id) → `solicitacao_revisoes` (solicitacao_id, id); pai por filho **0..1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R159 / fk_solicitacao_unidade**: (unidade_id) → `unidades` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_solicitacao_revisao`: ``situacao` = 'rascunho' or `revisao_atual_id` is not null`.

Triggers: `tg_preservar_solicitacoes_bd`, `tg_solicitacao_bu`.


### solicitacao_revisoes

Uma versão do formulário de quatro etapas; imutável depois de enviada.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Solicitações e revisões.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | solicitacao_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | numero | UK | int(10) unsigned | não | NULL/ausente |  |  |
| 4 | finalidade | · | text | sim | NULL |  |  |
| 5 | origem | · | varchar(255) | sim | NULL |  |  |
| 6 | destino | · | varchar(255) | sim | NULL |  |  |
| 7 | origem_latitude | · | decimal(10,7) | sim | NULL |  |  |
| 8 | origem_longitude | · | decimal(10,7) | sim | NULL |  |  |
| 9 | destino_latitude | · | decimal(10,7) | sim | NULL |  |  |
| 10 | destino_longitude | · | decimal(10,7) | sim | NULL |  |  |
| 11 | saida_prevista | · | datetime(6) | sim | NULL |  |  |
| 12 | retorno_previsto | · | datetime(6) | sim | NULL |  |  |
| 13 | quantidade_passageiros | · | smallint(5) unsigned | sim | NULL |  |  |
| 14 | necessita_motorista | · | tinyint(3) unsigned | não | 1 |  |  |
| 15 | motorista_sugerido_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 16 | veiculo_pretendido_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 17 | trajeto_planejado | · | text | sim | NULL |  |  |
| 18 | polilinha_planejada | · | longtext | sim | NULL |  |  |
| 19 | distancia_prevista_km | · | decimal(12,3) | sim | NULL |  |  |
| 20 | observacoes | · | text | sim | NULL |  |  |
| 21 | etapa_atual | · | tinyint(3) unsigned | não | 1 |  |  |
| 22 | criado_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 23 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 24 | enviado_em | · | datetime(6) | sim | NULL |  |  |

Índices:

- `fk_revisao_autor`: não único; (criado_por); BTREE.
- `fk_revisao_motorista`: não único; (motorista_sugerido_id); BTREE.
- `fk_revisao_veiculo`: não único; (veiculo_pretendido_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_revisao_numero`: único; (solicitacao_id, numero); BTREE.
- `uq_revisao_solicitacao`: único; (solicitacao_id, id); BTREE.

Relacionamentos de saída:

- **R153 / fk_revisao_autor**: (criado_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R154 / fk_revisao_motorista**: (motorista_sugerido_id) → `motoristas` (usuario_id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R155 / fk_revisao_solicitacao**: (solicitacao_id) → `solicitacoes` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R156 / fk_revisao_veiculo**: (veiculo_pretendido_id) → `veiculos` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_revisao_distancia`: ``distancia_prevista_km` is null or `distancia_prevista_km` >= 0`.
- `ck_revisao_flags`: ``necessita_motorista` in (0,1) and `etapa_atual` between 1 and 4 and `numero` > 0`.
- `ck_revisao_latitude`: `(`origem_latitude` is null or `origem_latitude` between -90 and 90) and (`destino_latitude` is null or `destino_latitude` between -90 and 90)`.
- `ck_revisao_longitude`: `(`origem_longitude` is null or `origem_longitude` between -180 and 180) and (`destino_longitude` is null or `destino_longitude` between -180 and 180)`.
- `ck_revisao_periodo`: ``saida_prevista` is null or `retorno_previsto` is null or `retorno_previsto` > `saida_prevista``.

Triggers: `tg_revisao_bd`, `tg_revisao_bi`, `tg_revisao_bu`.


### solicitacao_paradas

Paradas ordenadas do percurso de uma revisão específica.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Solicitações e revisões.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | revisao_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | ordem | UK | smallint(5) unsigned | não | NULL/ausente |  |  |
| 4 | descricao | · | varchar(255) | não | NULL/ausente |  |  |
| 5 | latitude | · | decimal(10,7) | sim | NULL |  |  |
| 6 | longitude | · | decimal(10,7) | sim | NULL |  |  |

Índices:

- `PRIMARY`: único; (id); BTREE.
- `uq_parada_ordem`: único; (revisao_id, ordem); BTREE.

Relacionamentos de saída:

- **R150 / fk_parada_revisao**: (revisao_id) → `solicitacao_revisoes` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_parada_coordenadas`: ``latitude` is null and `longitude` is null or `latitude` is not null and `longitude` is not null and `latitude` between -90 and 90 and `longitude` between -180 and 180`.

Triggers: `tg_solicitacao_paradas_bd`, `tg_solicitacao_paradas_bi`, `tg_solicitacao_paradas_bu`.


### solicitacao_passageiros

Lista opcional de passageiros. A contagem informada não inclui o motorista.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Solicitações e revisões.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | revisao_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | usuario_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 4 | nome | · | varchar(150) | não | NULL/ausente |  |  |
| 5 | contato | · | varchar(100) | sim | NULL |  |  |

Índices:

- `fk_passageiro_revisao`: não único; (revisao_id); BTREE.
- `fk_passageiro_usuario`: não único; (usuario_id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R151 / fk_passageiro_revisao**: (revisao_id) → `solicitacao_revisoes` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R152 / fk_passageiro_usuario**: (usuario_id) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Triggers: `tg_solicitacao_passageiros_bd`, `tg_solicitacao_passageiros_bi`, `tg_solicitacao_passageiros_bu`.


### solicitacao_eventos

Histórico de envio, ajustes, decisão, revisão e cancelamento.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Solicitações e revisões.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | solicitacao_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | revisao_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | ator_vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | tipo | · | enum('criada','enviada','ajustes_solicitados','aprovada','negada','revisao_aberta','cancelada') | não | NULL/ausente |  |  |
| 6 | situacao_anterior | · | varchar(40) | sim | NULL |  |  |
| 7 | situacao_nova | · | varchar(40) | não | NULL/ausente |  |  |
| 8 | motivo | · | text | sim | NULL |  |  |
| 9 | veiculo_confirmado_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 10 | motorista_confirmado_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 11 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_se_motorista`: não único; (motorista_confirmado_id); BTREE.
- `fk_se_revisao`: não único; (solicitacao_id, revisao_id); BTREE.
- `fk_se_veiculo`: não único; (veiculo_confirmado_id); BTREE.
- `fk_se_vinculo`: não único; (ator_vinculo_id); BTREE.
- `ix_se_historico`: não único; (solicitacao_id, criado_em, id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R146 / fk_se_motorista**: (motorista_confirmado_id) → `motoristas` (usuario_id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R147 / fk_se_revisao**: (solicitacao_id, revisao_id) → `solicitacao_revisoes` (solicitacao_id, id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R148 / fk_se_veiculo**: (veiculo_confirmado_id) → `veiculos` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R149 / fk_se_vinculo**: (ator_vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Triggers: `tg_imutavel_solicitacao_eventos_bd`, `tg_imutavel_solicitacao_eventos_bu`.


## Reservas e agenda


### reservas

Agenda única de viagens e bloqueios. Triggers serializam a reserva por veículo e motorista.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Reservas e agenda.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | veiculo_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | motorista_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 4 | revisao_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 5 | tipo | · | enum('viagem','manutencao','indisponibilidade') | não | NULL/ausente |  |  |
| 6 | inicio | · | datetime(6) | não | NULL/ausente |  |  |
| 7 | fim | · | datetime(6) | não | NULL/ausente |  |  |
| 8 | situacao | · | enum('ativa','liberada','cancelada') | não | 'ativa' |  |  |
| 9 | descricao | · | varchar(500) | sim | NULL |  |  |
| 10 | criado_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 11 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 12 | liberada_em | · | datetime(6) | sim | NULL |  |  |

Índices:

- `fk_reserva_autor`: não único; (criado_por); BTREE.
- `ix_reserva_motorista_periodo`: não único; (motorista_id, situacao, inicio, fim); BTREE.
- `ix_reserva_veiculo_periodo`: não único; (veiculo_id, situacao, inicio, fim); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_reserva_revisao`: único; (revisao_id); BTREE.
- `uq_reserva_veiculo`: único; (id, veiculo_id); BTREE.
- `uq_reserva_viagem`: único; (id, veiculo_id, motorista_id, revisao_id); BTREE.

Relacionamentos de saída:

- **R133 / fk_reserva_autor**: (criado_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R134 / fk_reserva_motorista**: (motorista_id) → `motoristas` (usuario_id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R135 / fk_reserva_revisao**: (revisao_id) → `solicitacao_revisoes` (id); pai por filho **0..1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R136 / fk_reserva_veiculo**: (veiculo_id) → `veiculos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_reserva_periodo`: ``fim` > `inicio``.
- `ck_reserva_tipo`: ``tipo` = 'viagem' and `revisao_id` is not null and `motorista_id` is not null or `tipo` in ('manutencao','indisponibilidade') and `revisao_id` is null and `motorista_id` is null`.

Triggers: `tg_reserva_ai`, `tg_reserva_au`, `tg_reserva_bd`, `tg_reserva_bi`, `tg_reserva_bu`.


### ocupacoes_agenda

Espelho técnico apenas das reservas ativas, mantido por triggers para leituras bloqueantes.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Reservas e agenda.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | reserva_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | veiculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | motorista_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 4 | viagem_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 5 | em_andamento | · | tinyint(3) unsigned | não | 0 |  |  |
| 6 | inicio | · | datetime(6) | não | NULL/ausente |  |  |
| 7 | fim | · | datetime(6) | não | NULL/ausente |  |  |

Índices:

- `fk_oa_viagem`: não único; (viagem_id); BTREE.
- `ix_oa_motorista`: não único; (motorista_id, inicio, fim); BTREE.
- `ix_oa_veiculo`: não único; (veiculo_id, inicio, fim); BTREE.
- `PRIMARY`: único; (reserva_id); BTREE.

Relacionamentos de saída:

- **R102 / fk_oa_motorista**: (motorista_id) → `motoristas` (usuario_id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R103 / fk_oa_reserva**: (reserva_id) → `reservas` (id); pai por filho **1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.
- **R104 / fk_oa_veiculo**: (veiculo_id) → `veiculos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R105 / fk_oa_viagem**: (viagem_id) → `viagens` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_oa_em_andamento`: ``em_andamento` in (0,1)`.
- `ck_oa_periodo`: ``fim` > `inicio``.

## Viagens e vistorias


### viagens

Aprovação gera viagem programada; saída e retorno são registros distintos.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Viagens e vistorias.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | protocolo | UK | varchar(40) | não | NULL/ausente |  |  |
| 3 | revisao_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | reserva_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | veiculo_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 6 | motorista_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 7 | situacao | · | enum('programada','em_andamento','concluida','cancelada') | não | 'programada' |  |  |
| 8 | saida_real | · | datetime(6) | sim | NULL |  |  |
| 9 | retorno_real | · | datetime(6) | sim | NULL |  |  |
| 10 | quilometragem_saida | · | decimal(12,1) | sim | NULL |  |  |
| 11 | quilometragem_retorno | · | decimal(12,1) | sim | NULL |  |  |
| 12 | saida_registrada_por | FK | bigint(20) unsigned | sim | NULL |  |  |
| 13 | retorno_registrado_por | FK | bigint(20) unsigned | sim | NULL |  |  |
| 14 | observacao_saida | · | text | sim | NULL |  |  |
| 15 | observacao_retorno | · | text | sim | NULL |  |  |
| 16 | versao | · | bigint(20) unsigned | não | 1 |  |  |
| 17 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_viagem_reserva`: não único; (reserva_id, veiculo_id, motorista_id, revisao_id); BTREE.
- `fk_viagem_retorno_autor`: não único; (retorno_registrado_por); BTREE.
- `fk_viagem_saida_autor`: não único; (saida_registrada_por); BTREE.
- `ix_viagem_historico_motorista`: não único; (motorista_id, situacao, saida_real, retorno_real); BTREE.
- `ix_viagem_historico_veiculo`: não único; (veiculo_id, situacao, saida_real, retorno_real); BTREE.
- `ix_viagem_situacao`: não único; (situacao, saida_real, id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_viagem_protocolo`: único; (protocolo); BTREE.
- `uq_viagem_reserva`: único; (reserva_id); BTREE.
- `uq_viagem_revisao`: único; (revisao_id); BTREE.
- `uq_viagem_veiculo`: único; (id, veiculo_id); BTREE.
- `uq_viagem_veiculo_motorista`: único; (id, veiculo_id, motorista_id); BTREE.

Relacionamentos de saída:

- **R176 / fk_viagem_reserva**: (reserva_id, veiculo_id, motorista_id, revisao_id) → `reservas` (id, veiculo_id, motorista_id, revisao_id); pai por filho **1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R177 / fk_viagem_retorno_autor**: (retorno_registrado_por) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R178 / fk_viagem_saida_autor**: (saida_registrada_por) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_viagem_estado`: ``situacao` in ('programada','cancelada') and `saida_real` is null and `retorno_real` is null and `quilometragem_saida` is null and `quilometragem_retorno` is null or `situacao` = 'em_andamento' and `saida_real` is not null and `quilometragem_saida` is not null and `saida_registrada_por` is not null and `retorno_real` is null and `quilometragem_retorno` is null or `situacao` = 'concluida' and `saida_real` is not null and `retorno_real` is not null and `quilometragem_saida` is not null and `quilometragem_retorno` is not null and `saida_registrada_por` is not null and `retorno_registrado_por` is not null`.
- `ck_viagem_km`: `(`quilometragem_saida` is null or `quilometragem_saida` >= 0) and (`quilometragem_retorno` is null or `quilometragem_retorno` >= `quilometragem_saida`)`.
- `ck_viagem_periodo`: ``retorno_real` is null or `saida_real` is not null and `retorno_real` > `saida_real``.

Triggers: `tg_preservar_viagens_bd`, `tg_viagem_ai`, `tg_viagem_au`, `tg_viagem_bi`, `tg_viagem_bu`.


### checklist_itens

Itens padronizados das vistorias de saída e retorno.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Viagens e vistorias.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | smallint(5) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | codigo | UK | varchar(40) | não | NULL/ausente |  |  |
| 3 | descricao | · | varchar(150) | não | NULL/ausente |  |  |
| 4 | obrigatorio | · | tinyint(3) unsigned | não | 1 |  |  |
| 5 | ativo | · | tinyint(3) unsigned | não | 1 |  |  |
| 6 | ordem | · | smallint(5) unsigned | não | 0 |  |  |

Índices:

- `PRIMARY`: único; (id); BTREE.
- `uq_checklist_codigo`: único; (codigo); BTREE.

Checks:

- `ck_checklist_flags`: ``obrigatorio` in (0,1) and `ativo` in (0,1)`.

### viagem_checklists

Cabeçalho de vistoria, preservado quando finalizado.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Viagens e vistorias.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | viagem_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | tipo | UK | enum('saida','retorno') | não | NULL/ausente |  |  |
| 4 | preenchido_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 6 | finalizado_em | · | datetime(6) | sim | NULL |  |  |

Índices:

- `fk_vc_autor`: não único; (preenchido_por); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_vc_tipo`: único; (viagem_id, tipo); BTREE.

Relacionamentos de saída:

- **R170 / fk_vc_autor**: (preenchido_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R171 / fk_vc_viagem**: (viagem_id) → `viagens` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Triggers: `tg_preservar_viagem_checklists_bd`, `tg_vcheck_bu`.


### viagem_checklist_respostas

Resultado por item; problema exige observação.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Viagens e vistorias.

Associação: N:N entre checklists de viagem e itens do checklist; contém a resposta da vistoria.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | checklist_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | item_id | PK/FK | smallint(5) unsigned | não | NULL/ausente |  |  |
| 3 | resultado | · | enum('ok','problema','nao_aplicavel') | não | NULL/ausente |  |  |
| 4 | observacao | · | varchar(1000) | sim | NULL |  |  |
| 5 | descricao_item | · | varchar(150) | não | '' |  |  |
| 6 | obrigatorio_snapshot | · | tinyint(3) unsigned | não | 1 |  |  |

Índices:

- `fk_vcr_item`: não único; (item_id); BTREE.
- `PRIMARY`: único; (checklist_id, item_id); BTREE.

Relacionamentos de saída:

- **R172 / fk_vcr_checklist**: (checklist_id) → `viagem_checklists` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.
- **R173 / fk_vcr_item**: (item_id) → `checklist_itens` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.

Checks:

- `ck_vcr_obrigatorio`: ``obrigatorio_snapshot` in (0,1)`.
- `ck_vcr_observacao`: ``resultado` <> 'problema' or `observacao` is not null and char_length(trim(`observacao`)) > 0`.

Triggers: `tg_vresposta_bd`, `tg_vresposta_bi`, `tg_vresposta_bu`.


### viagem_ocorrencias

Ocorrências e justificativas de desvio, separadas de telemetria e multas.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Viagens e vistorias.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | viagem_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | registrado_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | tipo | · | enum('geral','desvio_trajeto','avaria','acidente','atraso') | não | 'geral' |  |  |
| 5 | ocorrido_em | · | datetime(6) | não | NULL/ausente |  |  |
| 6 | descricao | · | text | não | NULL/ausente |  |  |
| 7 | latitude | · | decimal(10,7) | sim | NULL |  |  |
| 8 | longitude | · | decimal(10,7) | sim | NULL |  |  |
| 9 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_ocorrencia_autor`: não único; (registrado_por); BTREE.
- `ix_ocorrencia_viagem`: não único; (viagem_id, ocorrido_em); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R174 / fk_ocorrencia_autor**: (registrado_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R175 / fk_ocorrencia_viagem**: (viagem_id) → `viagens` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_ocorrencia_coordenadas`: ``latitude` is null and `longitude` is null or `latitude` is not null and `longitude` is not null and `latitude` between -90 and 90 and `longitude` between -180 and 180`.

Triggers: `tg_imutavel_viagem_ocorrencias_bd`, `tg_imutavel_viagem_ocorrencias_bu`.


## Monitoramento e posições


### posicoes_rastreamento

Pontos realmente recebidos do dispositivo instalado; nunca simular caminho executado.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Monitoramento e posições.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | instalacao_id | UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | veiculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | viagem_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 5 | identificador_evento | UK | varchar(120) | não | NULL/ausente |  |  |
| 6 | capturado_em | · | datetime(6) | não | NULL/ausente |  |  |
| 7 | recebido_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 8 | latitude | · | decimal(10,7) | não | NULL/ausente |  |  |
| 9 | longitude | · | decimal(10,7) | não | NULL/ausente |  |  |
| 10 | velocidade_kmh | · | decimal(7,2) | sim | NULL |  |  |
| 11 | precisao_metros | · | decimal(9,2) | sim | NULL |  |  |
| 12 | ignicao | · | tinyint(3) unsigned | sim | NULL |  |  |

Índices:

- `fk_posicao_instalacao`: não único; (instalacao_id, veiculo_id); BTREE.
- `fk_posicao_viagem`: não único; (viagem_id, veiculo_id); BTREE.
- `ix_posicao_veiculo`: não único; (veiculo_id, capturado_em, id); BTREE.
- `ix_posicao_viagem`: não único; (viagem_id, capturado_em, id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_posicao_evento`: único; (instalacao_id, identificador_evento); BTREE.

Relacionamentos de saída:

- **R125 / fk_posicao_viagem**: (viagem_id, veiculo_id) → `viagens` (id, veiculo_id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_posicao_coordenadas`: ``latitude` between -90 and 90 and `longitude` between -180 and 180`.
- `ck_posicao_medidas`: `(`velocidade_kmh` is null or `velocidade_kmh` >= 0) and (`precisao_metros` is null or `precisao_metros` >= 0) and (`ignicao` is null or `ignicao` in (0,1))`.

Triggers: `tg_imutavel_posicoes_rastreamento_bd`, `tg_imutavel_posicoes_rastreamento_bu`, `tg_posicao_sem_instalacao_bi`.


**Divergência:** instalacao_id não possui FK neste remoto. O vínculo de instalação não é desenhado. A trigger tg_posicao_sem_instalacao_bi verifica data futura e, quando há viagem, o período real da viagem; não valida a existência de instalação.


### posicoes_manuais

Posição declarada por usuário; identificada como manual, não como GPS.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Monitoramento e posições.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | viagem_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | registrado_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | registrado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 5 | ocorrido_em | · | datetime(6) | não | NULL/ausente |  |  |
| 6 | latitude | · | decimal(10,7) | não | NULL/ausente |  |  |
| 7 | longitude | · | decimal(10,7) | não | NULL/ausente |  |  |
| 8 | descricao | · | varchar(1000) | não | NULL/ausente |  |  |

Índices:

- `fk_pm_autor`: não único; (registrado_por); BTREE.
- `fk_pm_viagem`: não único; (viagem_id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R123 / fk_pm_autor**: (registrado_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R124 / fk_pm_viagem**: (viagem_id) → `viagens` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_pm_coordenadas`: ``latitude` between -90 and 90 and `longitude` between -180 and 180`.

Triggers: `tg_imutavel_posicoes_manuais_bd`, `tg_imutavel_posicoes_manuais_bu`.


## Despesas e pagamentos


### categorias_despesa

Categorias de custo separadas dos registros de multa.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Despesas e pagamentos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | smallint(5) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | codigo | UK | varchar(40) | não | NULL/ausente |  |  |
| 3 | nome | · | varchar(80) | não | NULL/ausente |  |  |
| 4 | ativa | · | tinyint(3) unsigned | não | 1 |  |  |

Índices:

- `PRIMARY`: único; (id); BTREE.
- `uq_categoria_despesa_codigo`: único; (codigo); BTREE.

Checks:

- `ck_categoria_despesa_ativa`: ``ativa` in (0,1)`.

### fornecedores

Fornecedor opcional para notas fiscais e ordens de serviço.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Despesas e pagamentos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | nome | · | varchar(150) | não | NULL/ausente |  |  |
| 3 | cpf_cnpj | UK | varchar(14) | sim | NULL |  |  |
| 4 | email | · | varchar(254) | sim | NULL |  |  |
| 5 | telefone | · | varchar(30) | sim | NULL |  |  |
| 6 | ativo | · | tinyint(3) unsigned | não | 1 |  |  |

Índices:

- `PRIMARY`: único; (id); BTREE.
- `uq_fornecedor_documento`: único; (cpf_cnpj); BTREE.

Checks:

- `ck_fornecedor_ativo`: ``ativo` in (0,1)`.

### despesas

Lançamento financeiro do veículo, em BRL com DECIMAL.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Despesas e pagamentos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | protocolo | UK | varchar(40) | não | NULL/ausente |  |  |
| 3 | veiculo_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | unidade_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | categoria_id | FK | smallint(5) unsigned | não | NULL/ausente |  |  |
| 6 | fornecedor_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 7 | data_despesa | · | date | não | NULL/ausente |  |  |
| 8 | valor | · | decimal(13,2) | não | NULL/ausente |  |  |
| 9 | descricao | · | text | não | NULL/ausente |  |  |
| 10 | numero_documento | · | varchar(100) | sim | NULL |  |  |
| 11 | situacao | · | enum('registrada','em_conferencia','aprovada','paga','cancelada') | não | 'registrada' |  |  |
| 12 | criado_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 13 | versao | · | bigint(20) unsigned | não | 1 |  |  |
| 14 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 15 | atualizado_em | · | datetime(6) | não | current_timestamp(6) | on update current_timestamp(6) |  |

Índices:

- `fk_despesa_autor`: não único; (criado_por); BTREE.
- `fk_despesa_categoria`: não único; (categoria_id); BTREE.
- `fk_despesa_fornecedor`: não único; (fornecedor_id); BTREE.
- `ix_despesa_relatorio`: não único; (unidade_id, data_despesa, categoria_id, situacao); BTREE.
- `ix_despesa_veiculo_data`: não único; (veiculo_id, data_despesa); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_despesa_protocolo`: único; (protocolo); BTREE.
- `uq_despesa_veiculo`: único; (id, veiculo_id); BTREE.

Relacionamentos de saída:

- **R038 / fk_despesa_autor**: (criado_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R039 / fk_despesa_categoria**: (categoria_id) → `categorias_despesa` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R040 / fk_despesa_fornecedor**: (fornecedor_id) → `fornecedores` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R041 / fk_despesa_unidade**: (unidade_id) → `unidades` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R042 / fk_despesa_veiculo**: (veiculo_id) → `veiculos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_despesa_valor`: ``valor` > 0`.

Triggers: `tg_despesa_bu`, `tg_preservar_despesas_bd`.


### despesa_eventos

Histórico da conferência e decisões financeiras de despesas.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Despesas e pagamentos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | despesa_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | ator_vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | tipo | · | varchar(40) | não | NULL/ausente |  |  |
| 5 | situacao_anterior | · | varchar(40) | sim | NULL |  |  |
| 6 | situacao_nova | · | varchar(40) | não | NULL/ausente |  |  |
| 7 | motivo | · | text | sim | NULL |  |  |
| 8 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_de_despesa`: não único; (despesa_id); BTREE.
- `fk_de_vinculo`: não único; (ator_vinculo_id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R043 / fk_de_despesa**: (despesa_id) → `despesas` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R044 / fk_de_vinculo**: (ator_vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Triggers: `tg_imutavel_despesa_eventos_bd`, `tg_imutavel_despesa_eventos_bu`.


### pagamentos_despesa

Pagamento confirmado separado da nota e do estado de conferência.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Despesas e pagamentos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | despesa_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | valor | · | decimal(13,2) | não | NULL/ausente |  |  |
| 4 | pago_em | · | datetime(6) | não | NULL/ausente |  |  |
| 5 | comprovante_arquivo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 6 | confirmado_por_vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 7 | confirmado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_pd_arquivo`: não único; (comprovante_arquivo_id); BTREE.
- `fk_pd_vinculo`: não único; (confirmado_por_vinculo_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_pagamento_despesa`: único; (despesa_id); BTREE.

Relacionamentos de saída:

- **R106 / fk_pd_arquivo**: (comprovante_arquivo_id) → `arquivos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R107 / fk_pd_despesa**: (despesa_id) → `despesas` (id); pai por filho **1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R108 / fk_pd_vinculo**: (confirmado_por_vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_pd_valor`: ``valor` > 0`.

Triggers: `tg_imutavel_pagamentos_despesa_bd`, `tg_imutavel_pagamentos_despesa_bu`, `tg_pagamento_despesa_bi`.


### abastecimentos

Detalhes físicos de uma despesa de abastecimento.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Despesas e pagamentos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | despesa_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | veiculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | combustivel | · | enum('gasolina','etanol','diesel','gnv','eletricidade','outro') | não | NULL/ausente |  |  |
| 4 | quantidade | · | decimal(12,3) | não | NULL/ausente |  |  |
| 5 | unidade_medida | · | enum('litro','m3','kwh') | não | 'litro' |  |  |
| 6 | preco_unitario | · | decimal(12,4) | não | NULL/ausente |  |  |
| 7 | quilometragem | · | decimal(12,1) | não | NULL/ausente |  |  |
| 8 | tanque_completo | · | tinyint(3) unsigned | não | 0 |  |  |

Índices:

- `fk_abastecimento_despesa`: não único; (despesa_id, veiculo_id); BTREE.
- `PRIMARY`: único; (despesa_id); BTREE.

Relacionamentos de saída:

- **R001 / fk_abastecimento_despesa**: (despesa_id, veiculo_id) → `despesas` (id, veiculo_id); pai por filho **1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_abastecimento_medida`: ``combustivel` in ('gasolina','etanol','diesel') and `unidade_medida` = 'litro' or `combustivel` = 'gnv' and `unidade_medida` = 'm3' or `combustivel` = 'eletricidade' and `unidade_medida` = 'kwh' or `combustivel` = 'outro'`.
- `ck_abastecimento_tanque`: ``tanque_completo` in (0,1)`.
- `ck_abastecimento_valores`: ``quantidade` > 0 and `preco_unitario` > 0 and `quilometragem` >= 0`.

Triggers: `tg_abastecimento_bi`.


## Manutenção e pneus


### manutencoes

Ordem de manutenção com bloqueio da agenda e despesa opcional.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Manutenção e pneus.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | protocolo | UK | varchar(40) | não | NULL/ausente |  |  |
| 3 | veiculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | despesa_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 5 | reserva_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 6 | fornecedor_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 7 | tipo | · | enum('preventiva','corretiva','vistoria') | não | NULL/ausente |  |  |
| 8 | situacao | · | enum('planejada','em_execucao','concluida','cancelada') | não | 'planejada' |  |  |
| 9 | descricao | · | text | não | NULL/ausente |  |  |
| 10 | inicio_previsto | · | datetime(6) | não | NULL/ausente |  |  |
| 11 | fim_previsto | · | datetime(6) | não | NULL/ausente |  |  |
| 12 | inicio_real | · | datetime(6) | sim | NULL |  |  |
| 13 | fim_real | · | datetime(6) | sim | NULL |  |  |
| 14 | quilometragem | · | decimal(12,1) | sim | NULL |  |  |
| 15 | proxima_revisao_km | · | decimal(12,1) | sim | NULL |  |  |
| 16 | proxima_revisao_data | · | date | sim | NULL |  |  |
| 17 | criado_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 18 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_manutencao_autor`: não único; (criado_por); BTREE.
- `fk_manutencao_despesa`: não único; (despesa_id, veiculo_id); BTREE.
- `fk_manutencao_fornecedor`: não único; (fornecedor_id); BTREE.
- `fk_manutencao_reserva`: não único; (reserva_id, veiculo_id); BTREE.
- `ix_manutencao_veiculo`: não único; (veiculo_id, situacao, inicio_previsto); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_manutencao_despesa`: único; (despesa_id); BTREE.
- `uq_manutencao_protocolo`: único; (protocolo); BTREE.
- `uq_manutencao_reserva`: único; (reserva_id); BTREE.

Relacionamentos de saída:

- **R071 / fk_manutencao_autor**: (criado_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R072 / fk_manutencao_despesa**: (despesa_id, veiculo_id) → `despesas` (id, veiculo_id); pai por filho **0..1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R073 / fk_manutencao_fornecedor**: (fornecedor_id) → `fornecedores` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R074 / fk_manutencao_reserva**: (reserva_id, veiculo_id) → `reservas` (id, veiculo_id); pai por filho **0..1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R075 / fk_manutencao_veiculo**: (veiculo_id) → `veiculos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_manutencao_km`: `(`quilometragem` is null or `quilometragem` >= 0) and (`proxima_revisao_km` is null or `proxima_revisao_km` >= 0)`.
- `ck_manutencao_periodo`: ``fim_previsto` > `inicio_previsto` and (`fim_real` is null or `inicio_real` is not null and `fim_real` >= `inicio_real`)`.

Triggers: `tg_manutencao_bi`, `tg_preservar_manutencoes_bd`.


### manutencao_itens

Peças e serviços da ordem de manutenção.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Manutenção e pneus.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | manutencao_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | tipo | · | enum('peca','servico') | não | NULL/ausente |  |  |
| 4 | descricao | · | varchar(255) | não | NULL/ausente |  |  |
| 5 | quantidade | · | decimal(12,3) | não | 1.000 |  |  |
| 6 | valor_unitario | · | decimal(13,2) | não | NULL/ausente |  |  |

Índices:

- `fk_mi_manutencao`: não único; (manutencao_id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R070 / fk_mi_manutencao**: (manutencao_id) → `manutencoes` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_mi_valores`: ``quantidade` > 0 and `valor_unitario` >= 0`.

### pneus

Ativos identificáveis, com aquisição e descarte.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Manutenção e pneus.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | codigo | UK | varchar(60) | não | NULL/ausente |  |  |
| 3 | numero_serie | UK | varchar(100) | sim | NULL |  |  |
| 4 | marca | · | varchar(80) | sim | NULL |  |  |
| 5 | modelo | · | varchar(80) | sim | NULL |  |  |
| 6 | medida | · | varchar(40) | não | NULL/ausente |  |  |
| 7 | adquirido_em | · | date | sim | NULL |  |  |
| 8 | despesa_aquisicao_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 9 | situacao | · | enum('estoque','instalado','descartado') | não | 'estoque' |  |  |
| 10 | descartado_em | · | datetime(6) | sim | NULL |  |  |
| 11 | motivo_descarte | · | varchar(500) | sim | NULL |  |  |

Índices:

- `fk_pneu_despesa`: não único; (despesa_aquisicao_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_pneu_codigo`: único; (codigo); BTREE.
- `uq_pneu_serie`: único; (numero_serie); BTREE.

Relacionamentos de saída:

- **R118 / fk_pneu_despesa**: (despesa_aquisicao_id) → `despesas` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_pneu_descarte`: ``situacao` = 'descartado' and `descartado_em` is not null and `motivo_descarte` is not null or `situacao` <> 'descartado' and `descartado_em` is null`.

Triggers: `tg_pneu_bu`, `tg_preservar_pneus_bd`.


### pneu_instalacoes

Histórico de montagem e rodízio; posição e pneu só têm uma instalação aberta.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Manutenção e pneus.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | pneu_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | veiculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | posicao | · | varchar(40) | não | NULL/ausente |  |  |
| 5 | instalado_em | · | datetime(6) | não | NULL/ausente |  |  |
| 6 | removido_em | · | datetime(6) | sim | NULL |  |  |
| 7 | quilometragem_instalacao | · | decimal(12,1) | não | NULL/ausente |  |  |
| 8 | quilometragem_remocao | · | decimal(12,1) | sim | NULL |  |  |
| 9 | motivo_remocao | · | varchar(500) | sim | NULL |  |  |
| 10 | registrado_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 11 | manutencao_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 12 | pneu_aberto | UK | bigint(20) unsigned | sim | NULL | STORED GENERATED; case when `removido_em` is null then `pneu_id` else NULL end |  |
| 13 | veiculo_aberto | UK | bigint(20) unsigned | sim | NULL | STORED GENERATED; case when `removido_em` is null then `veiculo_id` else NULL end |  |
| 14 | posicao_aberta | UK | varchar(40) | sim | NULL | STORED GENERATED; case when `removido_em` is null then `posicao` else NULL end |  |

Índices:

- `fk_pi_autor`: não único; (registrado_por); BTREE.
- `fk_pi_manutencao`: não único; (manutencao_id); BTREE.
- `fk_pi_pneu`: não único; (pneu_id); BTREE.
- `fk_pi_veiculo`: não único; (veiculo_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_pneu_instalado`: único; (pneu_aberto); BTREE.
- `uq_pneu_posicao`: único; (veiculo_aberto, posicao_aberta); BTREE.

Relacionamentos de saída:

- **R119 / fk_pi_autor**: (registrado_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R120 / fk_pi_manutencao**: (manutencao_id) → `manutencoes` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R121 / fk_pi_pneu**: (pneu_id) → `pneus` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R122 / fk_pi_veiculo**: (veiculo_id) → `veiculos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_pi_km`: ``quilometragem_instalacao` >= 0 and (`quilometragem_remocao` is null or `quilometragem_remocao` >= `quilometragem_instalacao`)`.
- `ck_pi_periodo`: ``removido_em` is null or `removido_em` >= `instalado_em``.
- `ck_pi_remocao`: ``removido_em` is null and `quilometragem_remocao` is null or `removido_em` is not null and `quilometragem_remocao` is not null and `motivo_remocao` is not null`.

Triggers: `tg_pneu_instalacao_ai`, `tg_pneu_instalacao_au`, `tg_pneu_instalacao_bi`, `tg_pneu_instalacao_bu`, `tg_preservar_pneu_instalacoes_bd`.


## Multas e responsabilidade


### multas

Autuação do veículo. Não atribui responsabilidade automaticamente ao solicitante.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Multas e responsabilidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/FK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | protocolo | UK | varchar(40) | não | NULL/ausente |  |  |
| 3 | veiculo_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | unidade_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | numero_auto | UK | varchar(100) | sim | NULL |  |  |
| 6 | orgao_autuador | UK | varchar(150) | sim | NULL |  |  |
| 7 | ocorrido_em | · | datetime(6) | não | NULL/ausente |  |  |
| 8 | precisao_ocorrencia | · | enum('dia','instante') | não | 'instante' |  |  |
| 9 | fim_dia_ocorrencia_utc | · | datetime(6) | sim | NULL |  |  |
| 10 | data_vencimento | · | date | sim | NULL |  |  |
| 11 | valor | · | decimal(13,2) | não | NULL/ausente |  |  |
| 12 | descricao | · | text | não | NULL/ausente |  |  |
| 13 | situacao | · | enum('sem_responsavel','aguardando_comprovante','em_conferencia','quitada','contestada','cancelada') | não | 'sem_responsavel' |  |  |
| 14 | responsabilidade_atual_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 15 | criado_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 16 | versao | · | bigint(20) unsigned | não | 1 |  |  |
| 17 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_multa_autor`: não único; (criado_por); BTREE.
- `fk_multa_responsabilidade_atual`: não único; (id, responsabilidade_atual_id); BTREE.
- `fk_multa_veiculo`: não único; (veiculo_id); BTREE.
- `ix_multa_unidade_status`: não único; (unidade_id, situacao, ocorrido_em); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_multa_auto`: único; (orgao_autuador, numero_auto); BTREE.
- `uq_multa_protocolo`: único; (protocolo); BTREE.
- `uq_multa_veiculo`: único; (id, veiculo_id); BTREE.

Relacionamentos de saída:

- **R080 / fk_multa_autor**: (criado_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R081 / fk_multa_responsabilidade_atual**: (id, responsabilidade_atual_id) → `multa_responsabilidades` (multa_id, id); pai por filho **0..1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R082 / fk_multa_unidade**: (unidade_id) → `unidades` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R083 / fk_multa_veiculo**: (veiculo_id) → `veiculos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_multa_precisao`: ``precisao_ocorrencia` = 'instante' and `fim_dia_ocorrencia_utc` is null or `precisao_ocorrencia` = 'dia' and `fim_dia_ocorrencia_utc` is not null and `fim_dia_ocorrencia_utc` > `ocorrido_em``.
- `ck_multa_responsavel`: ``situacao` <> 'sem_responsavel' or `responsabilidade_atual_id` is null`.
- `ck_multa_valor`: ``valor` > 0`.

Triggers: `tg_multa_bu`, `tg_preservar_multas_bd`.


### multa_responsabilidades

Versões da apuração administrativa, com veículo e condutor da viagem comprovados.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Multas e responsabilidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | multa_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | veiculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | viagem_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | motorista_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 6 | responsavel_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 7 | numero | UK | int(10) unsigned | não | NULL/ausente |  |  |
| 8 | confirmado_por_vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 9 | confirmado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 10 | justificativa | · | text | não | NULL/ausente |  |  |

Índices:

- `fk_mr_confirmador`: não único; (confirmado_por_vinculo_id); BTREE.
- `fk_mr_multa`: não único; (multa_id, veiculo_id); BTREE.
- `fk_mr_responsavel`: não único; (responsavel_id); BTREE.
- `fk_mr_viagem`: não único; (viagem_id, veiculo_id, motorista_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_mr_multa`: único; (multa_id, id); BTREE.
- `uq_mr_versao`: único; (multa_id, numero); BTREE.

Relacionamentos de saída:

- **R091 / fk_mr_confirmador**: (confirmado_por_vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R092 / fk_mr_multa**: (multa_id, veiculo_id) → `multas` (id, veiculo_id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R093 / fk_mr_responsavel**: (responsavel_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R094 / fk_mr_viagem**: (viagem_id, veiculo_id, motorista_id) → `viagens` (id, veiculo_id, motorista_id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_mr_numero`: ``numero` > 0`.

Triggers: `tg_imutavel_multa_responsabilidades_bd`, `tg_imutavel_multa_responsabilidades_bu`, `tg_responsabilidade_bi`.


### multa_eventos

Histórico imutável de apuração, contestação, cancelamento e comprovantes.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Multas e responsabilidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | multa_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | ator_vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | tipo | · | varchar(40) | não | NULL/ausente |  |  |
| 5 | situacao_anterior | · | varchar(40) | sim | NULL |  |  |
| 6 | situacao_nova | · | varchar(40) | não | NULL/ausente |  |  |
| 7 | motivo | · | text | sim | NULL |  |  |
| 8 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_me_vinculo`: não único; (ator_vinculo_id); BTREE.
- `ix_me_historico`: não único; (multa_id, criado_em, id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R089 / fk_me_multa**: (multa_id) → `multas` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R090 / fk_me_vinculo**: (ator_vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Triggers: `tg_imutavel_multa_eventos_bd`, `tg_imutavel_multa_eventos_bu`.


### multa_comprovantes

Cada envio é uma versão imutável. Enviar arquivo não significa quitar.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Multas e responsabilidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | multa_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | numero | UK | int(10) unsigned | não | NULL/ausente |  |  |
| 4 | responsabilidade_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | arquivo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 6 | enviado_por_vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 7 | valor_declarado | · | decimal(13,2) | não | NULL/ausente |  |  |
| 8 | pagamento_declarado_em | · | datetime(6) | não | NULL/ausente |  |  |
| 9 | observacao | · | text | sim | NULL |  |  |
| 10 | enviado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_mc_arquivo`: não único; (arquivo_id); BTREE.
- `fk_mc_responsabilidade`: não único; (multa_id, responsabilidade_id); BTREE.
- `fk_mc_vinculo`: não único; (enviado_por_vinculo_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_mc_multa`: único; (multa_id, id); BTREE.
- `uq_mc_numero`: único; (multa_id, numero); BTREE.

Relacionamentos de saída:

- **R084 / fk_mc_arquivo**: (arquivo_id) → `arquivos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R085 / fk_mc_responsabilidade**: (multa_id, responsabilidade_id) → `multa_responsabilidades` (multa_id, id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R086 / fk_mc_vinculo**: (enviado_por_vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_mc_valor`: ``valor_declarado` > 0 and `numero` > 0`.

Triggers: `tg_comprovante_bi`, `tg_imutavel_multa_comprovantes_bd`, `tg_imutavel_multa_comprovantes_bu`.


### multa_conferencias

Decisão financeira de uma versão do comprovante; uma conferência por envio.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Multas e responsabilidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | multa_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | comprovante_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | conferido_por_vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | resultado | · | enum('aceito','correcao_solicitada') | não | NULL/ausente |  |  |
| 6 | motivo | · | text | sim | NULL |  |  |
| 7 | valor_confirmado | · | decimal(13,2) | sim | NULL |  |  |
| 8 | pagamento_confirmado_em | · | datetime(6) | sim | NULL |  |  |
| 9 | conferido_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_mcf_comprovante`: não único; (multa_id, comprovante_id); BTREE.
- `fk_mcf_vinculo`: não único; (conferido_por_vinculo_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_mcf_comprovante`: único; (comprovante_id); BTREE.
- `uq_mcf_multa`: único; (multa_id, id); BTREE.

Relacionamentos de saída:

- **R087 / fk_mcf_comprovante**: (multa_id, comprovante_id) → `multa_comprovantes` (multa_id, id); pai por filho **1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R088 / fk_mcf_vinculo**: (conferido_por_vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_mcf_resultado`: ``resultado` = 'aceito' and `valor_confirmado` is not null and `valor_confirmado` > 0 and `pagamento_confirmado_em` is not null or `resultado` = 'correcao_solicitada' and `motivo` is not null and char_length(trim(`motivo`)) > 0 and `valor_confirmado` is null and `pagamento_confirmado_em` is null`.

Triggers: `tg_conferencia_bi`, `tg_imutavel_multa_conferencias_bd`, `tg_imutavel_multa_conferencias_bu`.


### pagamentos_multa

Quitação única, criada somente após aceite financeiro.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Multas e responsabilidade.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | multa_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | conferencia_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | valor | · | decimal(13,2) | não | NULL/ausente |  |  |
| 5 | pago_em | · | datetime(6) | não | NULL/ausente |  |  |
| 6 | registrado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_pagamento_multa_conferencia`: não único; (multa_id, conferencia_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_pagamento_conferencia`: único; (conferencia_id); BTREE.
- `uq_pagamento_multa`: único; (multa_id); BTREE.

Relacionamentos de saída:

- **R109 / fk_pagamento_multa_conferencia**: (multa_id, conferencia_id) → `multa_conferencias` (multa_id, id); pai por filho **1**; filhos por pai **0..1**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_pagamento_multa_valor`: ``valor` > 0`.

Triggers: `tg_imutavel_pagamentos_multa_bd`, `tg_imutavel_pagamentos_multa_bu`, `tg_pagamento_multa_bi`.


## Chamados e comunicação


### categorias_chamado

Categorias de atendimento interno.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Chamados e comunicação.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | smallint(5) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | nome | UK | varchar(80) | não | NULL/ausente |  |  |
| 3 | ativa | · | tinyint(3) unsigned | não | 1 |  |  |

Índices:

- `PRIMARY`: único; (id); BTREE.
- `uq_categoria_chamado_nome`: único; (nome); BTREE.

Checks:

- `ck_categoria_chamado_ativa`: ``ativa` in (0,1)`.

### chamados

Solicitante vê os próprios chamados; filas dependem de permissão.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Chamados e comunicação.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | protocolo | UK | varchar(40) | não | NULL/ausente |  |  |
| 3 | solicitante_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | unidade_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 5 | perfil_contexto_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 6 | categoria_id | FK | smallint(5) unsigned | não | NULL/ausente |  |  |
| 7 | assunto | · | varchar(200) | não | NULL/ausente |  |  |
| 8 | descricao | · | text | não | NULL/ausente |  |  |
| 9 | pagina_contexto | · | varchar(255) | sim | NULL |  |  |
| 10 | situacao | · | enum('aberto','em_atendimento','aguardando_solicitante','resolvido') | não | 'aberto' |  |  |
| 11 | atribuido_a | FK | bigint(20) unsigned | sim | NULL |  |  |
| 12 | resolvido_em | · | datetime(6) | sim | NULL |  |  |
| 13 | versao | · | bigint(20) unsigned | não | 1 |  |  |
| 14 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 15 | atualizado_em | · | datetime(6) | não | current_timestamp(6) | on update current_timestamp(6) |  |

Índices:

- `fk_chamado_atendente`: não único; (atribuido_a); BTREE.
- `fk_chamado_categoria`: não único; (categoria_id); BTREE.
- `fk_chamado_perfil`: não único; (perfil_contexto_id); BTREE.
- `ix_chamado_fila`: não único; (unidade_id, situacao, atribuido_a, criado_em); BTREE.
- `ix_chamado_proprios`: não único; (solicitante_id, situacao, criado_em); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_chamado_protocolo`: único; (protocolo); BTREE.

Relacionamentos de saída:

- **R018 / fk_chamado_atendente**: (atribuido_a) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R019 / fk_chamado_categoria**: (categoria_id) → `categorias_chamado` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R020 / fk_chamado_perfil**: (perfil_contexto_id) → `perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R021 / fk_chamado_solicitante**: (solicitante_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R022 / fk_chamado_unidade**: (unidade_id) → `unidades` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_chamado_resolvido`: ``situacao` = 'resolvido' and `resolvido_em` is not null or `situacao` <> 'resolvido' and `resolvido_em` is null`.

Triggers: `tg_preservar_chamados_bd`.


### chamado_mensagens

Conversa do chamado; mensagens internas não são exibidas ao solicitante.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Chamados e comunicação.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | chamado_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | autor_vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | mensagem | · | text | não | NULL/ausente |  |  |
| 5 | interna | · | tinyint(3) unsigned | não | 0 |  |  |
| 6 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_cm_autor`: não único; (autor_vinculo_id); BTREE.
- `ix_cm_conversa`: não único; (chamado_id, criado_em, id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R025 / fk_cm_autor**: (autor_vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R026 / fk_cm_chamado**: (chamado_id) → `chamados` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_cm_interna`: ``interna` in (0,1)`.

Triggers: `tg_imutavel_chamado_mensagens_bd`, `tg_imutavel_chamado_mensagens_bu`.


### chamado_eventos

Abertura, atribuição, mudança de estado e resolução.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Chamados e comunicação.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | chamado_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | ator_vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | tipo | · | varchar(40) | não | NULL/ausente |  |  |
| 5 | situacao_anterior | · | varchar(40) | sim | NULL |  |  |
| 6 | situacao_nova | · | varchar(40) | não | NULL/ausente |  |  |
| 7 | motivo | · | text | sim | NULL |  |  |
| 8 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_ce_chamado`: não único; (chamado_id); BTREE.
- `fk_ce_vinculo`: não único; (ator_vinculo_id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R023 / fk_ce_chamado**: (chamado_id) → `chamados` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R024 / fk_ce_vinculo**: (ator_vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Triggers: `tg_imutavel_chamado_eventos_bd`, `tg_imutavel_chamado_eventos_bu`.


### notificacao_eventos

Evento único de negócio; destinatários mantêm seus próprios estados de leitura.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Chamados e comunicação.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | chave_idempotencia | UK | varchar(160) | não | NULL/ausente |  |  |
| 3 | tipo | · | varchar(60) | não | NULL/ausente |  |  |
| 4 | titulo | · | varchar(160) | não | NULL/ausente |  |  |
| 5 | mensagem | · | varchar(1000) | não | NULL/ausente |  |  |
| 6 | caminho_destino | · | varchar(255) | sim | NULL |  |  |
| 7 | solicitacao_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 8 | viagem_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 9 | multa_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 10 | despesa_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 11 | chamado_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 12 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_ne_chamado`: não único; (chamado_id); BTREE.
- `fk_ne_despesa`: não único; (despesa_id); BTREE.
- `fk_ne_multa`: não único; (multa_id); BTREE.
- `fk_ne_solicitacao`: não único; (solicitacao_id); BTREE.
- `fk_ne_viagem`: não único; (viagem_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_notificacao_evento`: único; (chave_idempotencia); BTREE.

Relacionamentos de saída:

- **R097 / fk_ne_chamado**: (chamado_id) → `chamados` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R098 / fk_ne_despesa**: (despesa_id) → `despesas` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R099 / fk_ne_multa**: (multa_id) → `multas` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R100 / fk_ne_solicitacao**: (solicitacao_id) → `solicitacoes` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R101 / fk_ne_viagem**: (viagem_id) → `viagens` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_ne_alvo`: `(`solicitacao_id` is not null) + (`viagem_id` is not null) + (`multa_id` is not null) + (`despesa_id` is not null) + (`chamado_id` is not null) <= 1`.

Triggers: `tg_imutavel_notificacao_eventos_bd`, `tg_imutavel_notificacao_eventos_bu`.


### notificacao_destinatarios

Leitura, ocultação e versão por usuário; sem apagar o evento.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Chamados e comunicação.

Associação: N:N entre eventos de notificação e usuários destinatários.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | evento_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | usuario_id | PK/FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | lida_em | · | datetime(6) | sim | NULL |  |  |
| 4 | oculta_em | · | datetime(6) | sim | NULL |  |  |
| 5 | versao | · | bigint(20) unsigned | não | 1 |  |  |
| 6 | entregue_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `ix_nd_usuario`: não único; (usuario_id, oculta_em, lida_em, entregue_em); BTREE.
- `PRIMARY`: único; (evento_id, usuario_id); BTREE.

Relacionamentos de saída:

- **R095 / fk_nd_evento**: (evento_id) → `notificacao_eventos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.
- **R096 / fk_nd_usuario**: (usuario_id) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; identificadora.

## Arquivos e anexos


### arquivos

Metadados de documentos no armazenamento privado; não guarda bytes no banco.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Arquivos e anexos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | enviado_por | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | chave_armazenamento | UK | varchar(255) | não | NULL/ausente |  |  |
| 4 | nome_original | · | varchar(255) | não | NULL/ausente |  |  |
| 5 | tipo_mime | · | varchar(120) | não | NULL/ausente |  |  |
| 6 | tamanho_bytes | · | bigint(20) unsigned | não | NULL/ausente |  |  |
| 7 | sha256 | · | binary(32) | não | NULL/ausente |  |  |
| 8 | situacao | · | enum('pendente','disponivel','bloqueado','arquivado') | não | 'pendente' |  |  |
| 9 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_arquivo_autor`: não único; (enviado_por); BTREE.
- `ix_arquivo_hash`: não único; (sha256); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_arquivo_chave`: único; (chave_armazenamento); BTREE.

Relacionamentos de saída:

- **R014 / fk_arquivo_autor**: (enviado_por) → `usuarios` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_arquivo_tamanho`: ``tamanho_bytes` > 0`.

Triggers: `tg_arquivo_bu`, `tg_preservar_arquivos_bd`.


### anexos

Vínculos tipados de arquivo. Exatamente um destino por anexo, todos com FK.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Arquivos e anexos.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | arquivo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | revisao_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 4 | ocorrencia_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 5 | checklist_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 6 | despesa_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 7 | manutencao_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 8 | multa_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 9 | chamado_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 10 | mensagem_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 11 | descricao | · | varchar(255) | sim | NULL |  |  |
| 12 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |

Índices:

- `fk_anexo_arquivo`: não único; (arquivo_id); BTREE.
- `fk_anexo_chamado`: não único; (chamado_id); BTREE.
- `fk_anexo_checklist`: não único; (checklist_id); BTREE.
- `fk_anexo_despesa`: não único; (despesa_id); BTREE.
- `fk_anexo_manutencao`: não único; (manutencao_id); BTREE.
- `fk_anexo_mensagem`: não único; (mensagem_id); BTREE.
- `fk_anexo_multa`: não único; (multa_id); BTREE.
- `fk_anexo_ocorrencia`: não único; (ocorrencia_id); BTREE.
- `fk_anexo_revisao`: não único; (revisao_id); BTREE.
- `PRIMARY`: único; (id); BTREE.

Relacionamentos de saída:

- **R002 / fk_anexo_arquivo**: (arquivo_id) → `arquivos` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R003 / fk_anexo_chamado**: (chamado_id) → `chamados` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R004 / fk_anexo_checklist**: (checklist_id) → `viagem_checklists` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R005 / fk_anexo_despesa**: (despesa_id) → `despesas` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R006 / fk_anexo_manutencao**: (manutencao_id) → `manutencoes` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R007 / fk_anexo_mensagem**: (mensagem_id) → `chamado_mensagens` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R008 / fk_anexo_multa**: (multa_id) → `multas` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R009 / fk_anexo_ocorrencia**: (ocorrencia_id) → `viagem_ocorrencias` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R010 / fk_anexo_revisao**: (revisao_id) → `solicitacao_revisoes` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_anexo_alvo`: `(`revisao_id` is not null) + (`ocorrencia_id` is not null) + (`checklist_id` is not null) + (`despesa_id` is not null) + (`manutencao_id` is not null) + (`multa_id` is not null) + (`chamado_id` is not null) + (`mensagem_id` is not null) = 1`.

## Relatórios e exportações


### relatorio_campos

Lista autorizada de campos; campos sensíveis têm ação adicional obrigatória.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Relatórios e exportações.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | smallint(5) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | modulo_codigo | FK/UK | varchar(40) | não | NULL/ausente |  |  |
| 3 | chave | UK | varchar(80) | não | NULL/ausente |  |  |
| 4 | rotulo | · | varchar(150) | não | NULL/ausente |  |  |
| 5 | tipo | · | enum('texto','numero','moeda','data','data_hora','situacao') | não | NULL/ausente |  |  |
| 6 | acao_adicional | FK | varchar(40) | sim | NULL |  |  |
| 7 | ordem | · | smallint(5) unsigned | não | 0 |  |  |
| 8 | ativo | · | tinyint(3) unsigned | não | 1 |  |  |

Índices:

- `fk_rc_acao`: não único; (acao_adicional); BTREE.
- `fk_rc_acao_modulo`: não único; (modulo_codigo, acao_adicional); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_rc_campo`: único; (modulo_codigo, chave); BTREE.
- `uq_rc_modulo`: único; (id, modulo_codigo); BTREE.

Relacionamentos de saída:

- **R130 / fk_rc_acao**: (acao_adicional) → `acoes` (codigo); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R131 / fk_rc_acao_modulo**: (modulo_codigo, acao_adicional) → `modulo_acoes` (modulo_codigo, acao_codigo); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R132 / fk_rc_modulo**: (modulo_codigo) → `modulos` (codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_rc_ativo`: ``ativo` in (0,1)`.

### exportacoes

Pedido e snapshot da seleção; gerar arquivo exige revalidar permissão no servidor.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Relatórios e exportações.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK/UK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | solicitado_por_vinculo_id | FK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | modulo_codigo | FK/UK | varchar(40) | não | NULL/ausente |  |  |
| 4 | formato | · | enum('md','csv','xlsx','pdf') | não | NULL/ausente |  |  |
| 5 | situacao | · | enum('previa','fila','processando','concluida','falhou','expirada','cancelada') | não | 'previa' |  |  |
| 6 | periodo_inicio | · | date | sim | NULL |  |  |
| 7 | periodo_fim | · | date | sim | NULL |  |  |
| 8 | periodo_inicio_utc | · | datetime(6) | sim | NULL |  |  |
| 9 | periodo_fim_utc | · | datetime(6) | sim | NULL |  |  |
| 10 | fuso_apresentacao | · | varchar(64) | não | 'America/Porto_Velho' |  |  |
| 11 | filtros | · | longtext | não | NULL/ausente |  |  |
| 12 | alcance_aplicado | · | enum('proprios','unidade','orgao') | não | NULL/ausente |  |  |
| 13 | unidade_aplicada_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 14 | total_registros | · | bigint(20) unsigned | não | 0 |  |  |
| 15 | arquivo_id | FK | bigint(20) unsigned | sim | NULL |  |  |
| 16 | erro_codigo | · | varchar(60) | sim | NULL |  |  |
| 17 | criado_em | · | datetime(6) | não | current_timestamp(6) |  |  |
| 18 | iniciado_em | · | datetime(6) | sim | NULL |  |  |
| 19 | concluido_em | · | datetime(6) | sim | NULL |  |  |
| 20 | expira_em | · | datetime(6) | sim | NULL |  |  |

Índices:

- `fk_exportacao_arquivo`: não único; (arquivo_id); BTREE.
- `fk_exportacao_modulo`: não único; (modulo_codigo); BTREE.
- `fk_exportacao_unidade`: não único; (unidade_aplicada_id); BTREE.
- `fk_exportacao_vinculo`: não único; (solicitado_por_vinculo_id); BTREE.
- `ix_exportacao_fila`: não único; (situacao, criado_em); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_exportacao_modulo`: único; (id, modulo_codigo); BTREE.

Relacionamentos de saída:

- **R066 / fk_exportacao_arquivo**: (arquivo_id) → `arquivos` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R067 / fk_exportacao_modulo**: (modulo_codigo) → `modulos` (codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R068 / fk_exportacao_unidade**: (unidade_aplicada_id) → `unidades` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R069 / fk_exportacao_vinculo**: (solicitado_por_vinculo_id) → `usuario_perfis` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_exportacao_final`: ``situacao` <> 'concluida' or `arquivo_id` is not null and `concluido_em` is not null`.
- `ck_exportacao_janela_utc`: ``periodo_inicio_utc` is null or `periodo_fim_utc` is null or `periodo_fim_utc` > `periodo_inicio_utc``.
- `ck_exportacao_periodo`: ``periodo_inicio` is null or `periodo_fim` is null or `periodo_fim` >= `periodo_inicio``.
- `filtros`: `json_valid(`filtros`)`.

### exportacao_campos

Ordem das colunas selecionadas e pertencimento ao mesmo módulo do relatório.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Relatórios e exportações.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | exportacao_id | PK/FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 2 | campo_id | PK/FK | smallint(5) unsigned | não | NULL/ausente |  |  |
| 3 | modulo_codigo | FK | varchar(40) | não | NULL/ausente |  |  |
| 4 | ordem | UK | smallint(5) unsigned | não | NULL/ausente |  |  |

Índices:

- `fk_ec_campo`: não único; (campo_id, modulo_codigo); BTREE.
- `fk_ec_exportacao`: não único; (exportacao_id, modulo_codigo); BTREE.
- `PRIMARY`: único; (exportacao_id, campo_id); BTREE.
- `uq_ec_ordem`: único; (exportacao_id, ordem); BTREE.

Relacionamentos de saída:

- **R051 / fk_ec_campo**: (campo_id, modulo_codigo) → `relatorio_campos` (id, modulo_codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R052 / fk_ec_exportacao**: (exportacao_id, modulo_codigo) → `exportacoes` (id, modulo_codigo); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Triggers: `tg_exportacao_campos_bi`, `tg_imutavel_exportacao_campos_bd`, `tg_imutavel_exportacao_campos_bu`.


### exportacao_registros

Seleção reproduzível de registros com FKs reais e snapshot apenas dos campos permitidos.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Relatórios e exportações.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | exportacao_id | FK/UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 3 | ordem | UK | bigint(20) unsigned | não | NULL/ausente |  |  |
| 4 | solicitacao_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 5 | viagem_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 6 | veiculo_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 7 | despesa_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 8 | multa_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 9 | chamado_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 10 | usuario_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 11 | perfil_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 12 | rota_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 13 | auditoria_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 14 | posicao_rastreamento_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 15 | posicao_manual_id | FK/UK | bigint(20) unsigned | sim | NULL |  |  |
| 16 | snapshot | · | longtext | não | NULL/ausente |  |  |

Índices:

- `fk_er_auditoria`: não único; (auditoria_id); BTREE.
- `fk_er_chamado`: não único; (chamado_id); BTREE.
- `fk_er_despesa`: não único; (despesa_id); BTREE.
- `fk_er_multa`: não único; (multa_id); BTREE.
- `fk_er_perfil`: não único; (perfil_id); BTREE.
- `fk_er_posicao`: não único; (posicao_rastreamento_id); BTREE.
- `fk_er_posicao_manual`: não único; (posicao_manual_id); BTREE.
- `fk_er_rota`: não único; (rota_id); BTREE.
- `fk_er_solicitacao`: não único; (solicitacao_id); BTREE.
- `fk_er_usuario`: não único; (usuario_id); BTREE.
- `fk_er_veiculo`: não único; (veiculo_id); BTREE.
- `fk_er_viagem`: não único; (viagem_id); BTREE.
- `PRIMARY`: único; (id); BTREE.
- `uq_er_auditoria`: único; (exportacao_id, auditoria_id); BTREE.
- `uq_er_chamado`: único; (exportacao_id, chamado_id); BTREE.
- `uq_er_despesa`: único; (exportacao_id, despesa_id); BTREE.
- `uq_er_multa`: único; (exportacao_id, multa_id); BTREE.
- `uq_er_ordem`: único; (exportacao_id, ordem); BTREE.
- `uq_er_perfil`: único; (exportacao_id, perfil_id); BTREE.
- `uq_er_posicao`: único; (exportacao_id, posicao_rastreamento_id); BTREE.
- `uq_er_posicao_manual`: único; (exportacao_id, posicao_manual_id); BTREE.
- `uq_er_rota`: único; (exportacao_id, rota_id); BTREE.
- `uq_er_solicitacao`: único; (exportacao_id, solicitacao_id); BTREE.
- `uq_er_usuario`: único; (exportacao_id, usuario_id); BTREE.
- `uq_er_veiculo`: único; (exportacao_id, veiculo_id); BTREE.
- `uq_er_viagem`: único; (exportacao_id, viagem_id); BTREE.

Relacionamentos de saída:

- **R053 / fk_er_auditoria**: (auditoria_id) → `auditoria` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R054 / fk_er_chamado**: (chamado_id) → `chamados` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R055 / fk_er_despesa**: (despesa_id) → `despesas` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R056 / fk_er_exportacao**: (exportacao_id) → `exportacoes` (id); pai por filho **1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R057 / fk_er_multa**: (multa_id) → `multas` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R058 / fk_er_perfil**: (perfil_id) → `perfis` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R059 / fk_er_posicao**: (posicao_rastreamento_id) → `posicoes_rastreamento` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R060 / fk_er_posicao_manual**: (posicao_manual_id) → `posicoes_manuais` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R061 / fk_er_rota**: (rota_id) → `rotas_sistema` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R062 / fk_er_solicitacao**: (solicitacao_id) → `solicitacoes` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R063 / fk_er_usuario**: (usuario_id) → `usuarios` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R064 / fk_er_veiculo**: (veiculo_id) → `veiculos` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.
- **R065 / fk_er_viagem**: (viagem_id) → `viagens` (id); pai por filho **0..1**; filhos por pai **0..N**; DELETE RESTRICT; UPDATE RESTRICT; não identificadora.

Checks:

- `ck_er_alvo`: `(`solicitacao_id` is not null) + (`viagem_id` is not null) + (`veiculo_id` is not null) + (`despesa_id` is not null) + (`multa_id` is not null) + (`chamado_id` is not null) + (`usuario_id` is not null) + (`perfil_id` is not null) + (`rota_id` is not null) + (`auditoria_id` is not null) + (`posicao_rastreamento_id` is not null) + (`posicao_manual_id` is not null) = 1`.
- `snapshot`: `json_valid(`snapshot`)`.

Triggers: `tg_exportacao_registros_bi`, `tg_imutavel_exportacao_registros_bd`, `tg_imutavel_exportacao_registros_bu`.


## Filas técnicas


### jobs

Fila durável para tarefas assíncronas; payloads sensíveis devem estar cifrados.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Filas técnicas.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | queue | · | varchar(255) | não | NULL/ausente |  |  |
| 3 | payload | · | longtext | não | NULL/ausente |  |  |
| 4 | attempts | · | tinyint(3) unsigned | não | NULL/ausente |  |  |
| 5 | reserved_at | · | int(10) unsigned | sim | NULL |  |  |
| 6 | available_at | · | int(10) unsigned | não | NULL/ausente |  |  |
| 7 | created_at | · | int(10) unsigned | não | NULL/ausente |  |  |

Índices:

- `ix_jobs_queue`: não único; (queue); BTREE.
- `PRIMARY`: único; (id); BTREE.

### failed_jobs

Falhas da fila Laravel; payload de recuperação contém somente identificador cifrado.

Engine: InnoDB; collation: utf8mb4_unicode_ci. Página draw.io: Filas técnicas.

| Ordem | Campo | Chave | Tipo físico | Nulo | Default | Extra / expressão | Comentário |
|---:|---|---|---|---|---|---|---|
| 1 | id | PK | bigint(20) unsigned | não | NULL/ausente | auto_increment |  |
| 2 | uuid | UK | varchar(255) | não | NULL/ausente |  |  |
| 3 | connection | · | text | não | NULL/ausente |  |  |
| 4 | queue | · | text | não | NULL/ausente |  |  |
| 5 | payload | · | longtext | não | NULL/ausente |  |  |
| 6 | exception | · | longtext | não | NULL/ausente |  |  |
| 7 | failed_at | · | timestamp | não | current_timestamp() |  |  |

Índices:

- `PRIMARY`: único; (id); BTREE.
- `uuid`: único; (uuid); BTREE.