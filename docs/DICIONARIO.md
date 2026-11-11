# Dicionário do banco remoto

O dicionário abrange tabelas e visões do banco. Os tipos e os valores padrão correspondem aos metadados do servidor consultado. Os indicadores PRI, UNI e MUL descrevem propriedades das colunas, mas não detalham integralmente os índices compostos.

## abastecimentos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo           | Tipo                                                            | Aceita NULL | Default | Chave | Comentário |
| --------------- | --------------------------------------------------------------- | ----------- | ------- | ----- | ---------- |
| despesa_id      | bigint(20) unsigned                                             | NO          | NULL    | PRI   |            |
| veiculo_id      | bigint(20) unsigned                                             | NO          | NULL    |       |            |
| combustivel     | enum('gasolina','etanol','diesel','gnv','eletricidade','outro') | NO          | NULL    |       |            |
| quantidade      | decimal(12,3)                                                   | NO          | NULL    |       |            |
| unidade_medida  | enum('litro','m3','kwh')                                        | NO          | 'litro' |       |            |
| preco_unitario  | decimal(12,4)                                                   | NO          | NULL    |       |            |
| quilometragem   | decimal(12,1)                                                   | NO          | NULL    |       |            |
| tanque_completo | tinyint(3) unsigned                                             | NO          | 0       |       |            |

Chaves estrangeiras desta tabela:

- `despesa_id` → `despesas.id` (fk_abastecimento_despesa).
- `veiculo_id` → `despesas.veiculo_id` (fk_abastecimento_despesa).

## acoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo  | Tipo         | Aceita NULL | Default | Chave | Comentário |
| ------ | ------------ | ----------- | ------- | ----- | ---------- |
| codigo | varchar(40)  | NO          | NULL    | PRI   |            |
| nome   | varchar(100) | NO          | NULL    |       |            |

## anexos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo         | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id            | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| arquivo_id    | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| revisao_id    | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| ocorrencia_id | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| checklist_id  | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| despesa_id    | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| manutencao_id | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| multa_id      | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| chamado_id    | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| mensagem_id   | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| descricao     | varchar(255)        | YES         | NULL                 |       |            |
| criado_em     | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `arquivo_id` → `arquivos.id` (fk_anexo_arquivo).
- `chamado_id` → `chamados.id` (fk_anexo_chamado).
- `checklist_id` → `viagem_checklists.id` (fk_anexo_checklist).
- `despesa_id` → `despesas.id` (fk_anexo_despesa).
- `manutencao_id` → `manutencoes.id` (fk_anexo_manutencao).
- `mensagem_id` → `chamado_mensagens.id` (fk_anexo_mensagem).
- `multa_id` → `multas.id` (fk_anexo_multa).
- `ocorrencia_id` → `viagem_ocorrencias.id` (fk_anexo_ocorrencia).
- `revisao_id` → `solicitacao_revisoes.id` (fk_anexo_revisao).

## aprovacoes_mfa

Tipo: BASE TABLE. Motor: InnoDB.

| Campo          | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| -------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| recuperacao_id | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| aprovada_por   | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| vinculo_id     | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| aprovada_em    | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `recuperacao_id` → `recuperacoes_mfa.id` (fk_aprovacao_mfa_pedido).
- `aprovada_por` → `usuarios.id` (fk_aprovacao_mfa_usuario).
- `vinculo_id` → `usuario_perfis.id` (fk_aprovacao_mfa_vinculo).
- `aprovada_por` → `usuario_perfis.usuario_id` (fk_aprovacao_mfa_vinculo).

## arquivos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo               | Tipo                                                  | Aceita NULL | Default              | Chave | Comentário |
| ------------------- | ----------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                  | bigint(20) unsigned                                   | NO          | NULL                 | PRI   |            |
| enviado_por         | bigint(20) unsigned                                   | NO          | NULL                 | MUL   |            |
| chave_armazenamento | varchar(255)                                          | NO          | NULL                 | UNI   |            |
| nome_original       | varchar(255)                                          | NO          | NULL                 |       |            |
| tipo_mime           | varchar(120)                                          | NO          | NULL                 |       |            |
| tamanho_bytes       | bigint(20) unsigned                                   | NO          | NULL                 |       |            |
| sha256              | binary(32)                                            | NO          | NULL                 | MUL   |            |
| situacao            | enum('pendente','disponivel','bloqueado','arquivado') | NO          | 'pendente'           |       |            |
| criado_em           | datetime(6)                                           | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `enviado_por` → `usuarios.id` (fk_arquivo_autor).

## auditoria

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                  | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ---------------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                     | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| ator_usuario_id        | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| ator_vinculo_id        | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| sessao_id              | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| ator_nome_snapshot     | varchar(150)        | YES         | NULL                 |       |            |
| perfil_nome_snapshot   | varchar(100)        | YES         | NULL                 |       |            |
| perfil_codigo_snapshot | varchar(60)         | YES         | NULL                 |       |            |
| evento                 | varchar(80)         | NO          | NULL                 |       |            |
| entidade               | varchar(60)         | YES         | NULL                 | MUL   |            |
| entidade_id            | bigint(20) unsigned | YES         | NULL                 |       |            |
| correlacao             | char(36)            | YES         | NULL                 |       |            |
| descricao              | varchar(1000)       | YES         | NULL                 |       |            |
| antes                  | longtext            | YES         | NULL                 |       |            |
| depois                 | longtext            | YES         | NULL                 |       |            |
| ip                     | varbinary(16)       | YES         | NULL                 |       |            |
| criado_em              | datetime(6)         | NO          | current_timestamp(6) | MUL   |            |

Chaves estrangeiras desta tabela:

- `sessao_id` → `sessoes.id` (fk_auditoria_sessao).
- `ator_usuario_id` → `sessoes.usuario_id` (fk_auditoria_sessao).
- `ator_usuario_id` → `usuarios.id` (fk_auditoria_usuario).
- `ator_vinculo_id` → `usuario_perfis.id` (fk_auditoria_vinculo).
- `ator_usuario_id` → `usuario_perfis.usuario_id` (fk_auditoria_vinculo).

## cache

Tipo: BASE TABLE. Motor: InnoDB.

| Campo      | Tipo         | Aceita NULL | Default | Chave | Comentário |
| ---------- | ------------ | ----------- | ------- | ----- | ---------- |
| key        | varchar(255) | NO          | NULL    | PRI   |            |
| value      | mediumtext   | NO          | NULL    |       |            |
| expiration | int(11)      | NO          | NULL    | MUL   |            |

## cache_locks

Tipo: BASE TABLE. Motor: InnoDB.

| Campo      | Tipo         | Aceita NULL | Default | Chave | Comentário |
| ---------- | ------------ | ----------- | ------- | ----- | ---------- |
| key        | varchar(255) | NO          | NULL    | PRI   |            |
| owner      | varchar(255) | NO          | NULL    |       |            |
| expiration | int(11)      | NO          | NULL    | MUL   |            |

## categorias_chamado

Tipo: BASE TABLE. Motor: InnoDB.

| Campo | Tipo                 | Aceita NULL | Default | Chave | Comentário |
| ----- | -------------------- | ----------- | ------- | ----- | ---------- |
| id    | smallint(5) unsigned | NO          | NULL    | PRI   |            |
| nome  | varchar(80)          | NO          | NULL    | UNI   |            |
| ativa | tinyint(3) unsigned  | NO          | 1       |       |            |

## categorias_despesa

Tipo: BASE TABLE. Motor: InnoDB.

| Campo  | Tipo                 | Aceita NULL | Default | Chave | Comentário |
| ------ | -------------------- | ----------- | ------- | ----- | ---------- |
| id     | smallint(5) unsigned | NO          | NULL    | PRI   |            |
| codigo | varchar(40)          | NO          | NULL    | UNI   |            |
| nome   | varchar(80)          | NO          | NULL    |       |            |
| ativa  | tinyint(3) unsigned  | NO          | 1       |       |            |

## categorias_veiculo

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                   | Tipo                 | Aceita NULL | Default | Chave | Comentário |
| ----------------------- | -------------------- | ----------- | ------- | ----- | ---------- |
| id                      | smallint(5) unsigned | NO          | NULL    | PRI   |            |
| nome                    | varchar(60)          | NO          | NULL    | UNI   |            |
| categoria_cnh_requerida | char(1)              | NO          | 'B'     |       |            |
| ativa                   | tinyint(3) unsigned  | NO          | 1       |       |            |

## chamado_eventos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo             | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ----------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| chamado_id        | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| ator_vinculo_id   | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| tipo              | varchar(40)         | NO          | NULL                 |       |            |
| situacao_anterior | varchar(40)         | YES         | NULL                 |       |            |
| situacao_nova     | varchar(40)         | NO          | NULL                 |       |            |
| motivo            | text                | YES         | NULL                 |       |            |
| criado_em         | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `chamado_id` → `chamados.id` (fk_ce_chamado).
- `ator_vinculo_id` → `usuario_perfis.id` (fk_ce_vinculo).

## chamado_mensagens

Tipo: BASE TABLE. Motor: InnoDB.

| Campo            | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ---------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id               | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| chamado_id       | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| autor_vinculo_id | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| mensagem         | text                | NO          | NULL                 |       |            |
| interna          | tinyint(3) unsigned | NO          | 0                    |       |            |
| criado_em        | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `autor_vinculo_id` → `usuario_perfis.id` (fk_cm_autor).
- `chamado_id` → `chamados.id` (fk_cm_chamado).

## chamados

Tipo: BASE TABLE. Motor: InnoDB.

| Campo              | Tipo                                                                 | Aceita NULL | Default              | Chave | Comentário |
| ------------------ | -------------------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                 | bigint(20) unsigned                                                  | NO          | NULL                 | PRI   |            |
| protocolo          | varchar(40)                                                          | NO          | NULL                 | UNI   |            |
| solicitante_id     | bigint(20) unsigned                                                  | NO          | NULL                 | MUL   |            |
| unidade_id         | bigint(20) unsigned                                                  | NO          | NULL                 | MUL   |            |
| perfil_contexto_id | bigint(20) unsigned                                                  | NO          | NULL                 | MUL   |            |
| categoria_id       | smallint(5) unsigned                                                 | NO          | NULL                 | MUL   |            |
| assunto            | varchar(200)                                                         | NO          | NULL                 |       |            |
| descricao          | text                                                                 | NO          | NULL                 |       |            |
| pagina_contexto    | varchar(255)                                                         | YES         | NULL                 |       |            |
| situacao           | enum('aberto','em_atendimento','aguardando_solicitante','resolvido') | NO          | 'aberto'             |       |            |
| atribuido_a        | bigint(20) unsigned                                                  | YES         | NULL                 | MUL   |            |
| resolvido_em       | datetime(6)                                                          | YES         | NULL                 |       |            |
| versao             | bigint(20) unsigned                                                  | NO          | 1                    |       |            |
| criado_em          | datetime(6)                                                          | NO          | current_timestamp(6) |       |            |
| atualizado_em      | datetime(6)                                                          | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `atribuido_a` → `usuarios.id` (fk_chamado_atendente).
- `categoria_id` → `categorias_chamado.id` (fk_chamado_categoria).
- `perfil_contexto_id` → `perfis.id` (fk_chamado_perfil).
- `solicitante_id` → `usuarios.id` (fk_chamado_solicitante).
- `unidade_id` → `unidades.id` (fk_chamado_unidade).

## checklist_itens

Tipo: BASE TABLE. Motor: InnoDB.

| Campo       | Tipo                 | Aceita NULL | Default | Chave | Comentário |
| ----------- | -------------------- | ----------- | ------- | ----- | ---------- |
| id          | smallint(5) unsigned | NO          | NULL    | PRI   |            |
| codigo      | varchar(40)          | NO          | NULL    | UNI   |            |
| descricao   | varchar(150)         | NO          | NULL    |       |            |
| obrigatorio | tinyint(3) unsigned  | NO          | 1       |       |            |
| ativo       | tinyint(3) unsigned  | NO          | 1       |       |            |
| ordem       | smallint(5) unsigned | NO          | 0       |       |            |

## configuracao_sistema

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                          | Tipo                 | Aceita NULL | Default               | Chave | Comentário |
| ------------------------------ | -------------------- | ----------- | --------------------- | ----- | ---------- |
| id                             | tinyint(3) unsigned  | NO          | NULL                  | PRI   |            |
| nome_sistema                   | varchar(120)         | NO          | NULL                  |       |            |
| nome_orgao                     | varchar(150)         | NO          | NULL                  |       |            |
| sigla_orgao                    | varchar(30)          | YES         | NULL                  |       |            |
| fuso_horario                   | varchar(64)          | NO          | 'America/Porto_Velho' |       |            |
| moeda                          | char(3)              | NO          | 'BRL'                 |       |            |
| sessao_inatividade_minutos     | smallint(5) unsigned | NO          | 30                    |       |            |
| limite_sem_comunicacao_minutos | smallint(5) unsigned | NO          | 15                    |       |            |
| logo_arquivo_id                | bigint(20) unsigned  | YES         | NULL                  | MUL   |            |
| bootstrap_concluido            | tinyint(3) unsigned  | NO          | 0                     |       |            |
| atualizado_em                  | datetime(6)          | NO          | current_timestamp(6)  |       |            |

Chaves estrangeiras desta tabela:

- `logo_arquivo_id` → `arquivos.id` (fk_config_logo).

## confirmacoes_identidade

Tipo: BASE TABLE. Motor: InnoDB.

| Campo            | Tipo                     | Aceita NULL | Default              | Chave | Comentário |
| ---------------- | ------------------------ | ----------- | -------------------- | ----- | ---------- |
| id               | bigint(20) unsigned      | NO          | NULL                 | PRI   |            |
| usuario_id       | bigint(20) unsigned      | NO          | NULL                 | MUL   |            |
| vinculo_id       | bigint(20) unsigned      | YES         | NULL                 | MUL   |            |
| finalidade       | enum('ativacao','email') | NO          | NULL                 |       |            |
| email_destino    | varchar(254)             | NO          | NULL                 |       |            |
| versao_seguranca | bigint(20) unsigned      | NO          | NULL                 |       |            |
| token_hash       | binary(32)               | NO          | NULL                 | UNI   |            |
| criado_em        | datetime(6)              | NO          | current_timestamp(6) |       |            |
| expira_em        | datetime(6)              | NO          | NULL                 |       |            |
| usado_em         | datetime(6)              | YES         | NULL                 |       |            |
| invalidado_em    | datetime(6)              | YES         | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `usuario_id` → `usuarios.id` (fk_confirmacao_usuario).
- `vinculo_id` → `usuario_perfis.id` (fk_confirmacao_vinculo).
- `usuario_id` → `usuario_perfis.usuario_id` (fk_confirmacao_vinculo).

## controle_vinculos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo            | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ---------------- | ------------------- | ----------- | ------- | ----- | ---------- |
| vinculo_id       | bigint(20) unsigned | NO          | NULL    | PRI   |            |
| usuario_id       | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| perfil_id        | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| unidade_id       | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| vigente_desde    | datetime(6)         | NO          | NULL    |       |            |
| vigente_ate      | datetime(6)         | YES         | NULL    |       |            |
| ativo_vinculo    | tinyint(3) unsigned | NO          | NULL    |       |            |
| ativo_usuario    | tinyint(3) unsigned | NO          | NULL    |       |            |
| ativo_perfil     | tinyint(3) unsigned | NO          | NULL    |       |            |
| ativo_unidade    | tinyint(3) unsigned | NO          | NULL    |       |            |
| pode_administrar | tinyint(3) unsigned | NO          | NULL    | MUL   |            |

Chaves estrangeiras desta tabela:

- `perfil_id` → `perfis.id` (fk_cv_perfil).
- `unidade_id` → `unidades.id` (fk_cv_unidade).
- `usuario_id` → `usuarios.id` (fk_cv_usuario).
- `vinculo_id` → `usuario_perfis.id` (fk_cv_vinculo).

## credenciais_mfa

Tipo: BASE TABLE. Motor: InnoDB.

| Campo         | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id            | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| usuario_id    | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| credential_id | varchar(1366)       | NO          | NULL                 | UNI   |            |
| registro      | longtext            | NO          | NULL                 |       |            |
| criada_em     | datetime(6)         | NO          | current_timestamp(6) |       |            |
| usada_em      | datetime(6)         | YES         | NULL                 |       |            |
| revogada_em   | datetime(6)         | YES         | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `usuario_id` → `usuarios.id` (fk_credencial_mfa_usuario).

## desafios_mfa

Tipo: BASE TABLE. Motor: InnoDB.

| Campo            | Tipo                             | Aceita NULL | Default              | Chave | Comentário |
| ---------------- | -------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id               | bigint(20) unsigned              | NO          | NULL                 | PRI   |            |
| usuario_id       | bigint(20) unsigned              | NO          | NULL                 | MUL   |            |
| sessao_id        | bigint(20) unsigned              | NO          | NULL                 | MUL   |            |
| vinculo_id       | bigint(20) unsigned              | NO          | NULL                 | MUL   |            |
| versao_seguranca | bigint(20) unsigned              | NO          | NULL                 |       |            |
| http_hash        | binary(32)                       | NO          | NULL                 |       |            |
| finalidade       | enum('registration','assertion') | NO          | NULL                 |       |            |
| opcoes           | longtext                         | NO          | NULL                 |       |            |
| expira_em        | datetime(6)                      | NO          | NULL                 |       |            |
| usado_em         | datetime(6)                      | YES         | NULL                 |       |            |
| criado_em        | datetime(6)                      | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `sessao_id` → `sessoes.id` (fk_desafio_mfa_sessao).
- `usuario_id` → `sessoes.usuario_id` (fk_desafio_mfa_sessao).
- `usuario_id` → `usuarios.id` (fk_desafio_mfa_usuario).
- `vinculo_id` → `usuario_perfis.id` (fk_desafio_mfa_vinculo).
- `usuario_id` → `usuario_perfis.usuario_id` (fk_desafio_mfa_vinculo).

## despesa_eventos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo             | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ----------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| despesa_id        | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| ator_vinculo_id   | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| tipo              | varchar(40)         | NO          | NULL                 |       |            |
| situacao_anterior | varchar(40)         | YES         | NULL                 |       |            |
| situacao_nova     | varchar(40)         | NO          | NULL                 |       |            |
| motivo            | text                | YES         | NULL                 |       |            |
| criado_em         | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `despesa_id` → `despesas.id` (fk_de_despesa).
- `ator_vinculo_id` → `usuario_perfis.id` (fk_de_vinculo).

## despesas

Tipo: BASE TABLE. Motor: InnoDB.

| Campo            | Tipo                                                              | Aceita NULL | Default              | Chave | Comentário |
| ---------------- | ----------------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id               | bigint(20) unsigned                                               | NO          | NULL                 | PRI   |            |
| protocolo        | varchar(40)                                                       | NO          | NULL                 | UNI   |            |
| veiculo_id       | bigint(20) unsigned                                               | NO          | NULL                 | MUL   |            |
| unidade_id       | bigint(20) unsigned                                               | NO          | NULL                 | MUL   |            |
| categoria_id     | smallint(5) unsigned                                              | NO          | NULL                 | MUL   |            |
| fornecedor_id    | bigint(20) unsigned                                               | YES         | NULL                 | MUL   |            |
| data_despesa     | date                                                              | NO          | NULL                 |       |            |
| valor            | decimal(13,2)                                                     | NO          | NULL                 |       |            |
| descricao        | text                                                              | NO          | NULL                 |       |            |
| numero_documento | varchar(100)                                                      | YES         | NULL                 |       |            |
| situacao         | enum('registrada','em_conferencia','aprovada','paga','cancelada') | NO          | 'registrada'         |       |            |
| criado_por       | bigint(20) unsigned                                               | NO          | NULL                 | MUL   |            |
| versao           | bigint(20) unsigned                                               | NO          | 1                    |       |            |
| criado_em        | datetime(6)                                                       | NO          | current_timestamp(6) |       |            |
| atualizado_em    | datetime(6)                                                       | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `criado_por` → `usuarios.id` (fk_despesa_autor).
- `categoria_id` → `categorias_despesa.id` (fk_despesa_categoria).
- `fornecedor_id` → `fornecedores.id` (fk_despesa_fornecedor).
- `unidade_id` → `unidades.id` (fk_despesa_unidade).
- `veiculo_id` → `veiculos.id` (fk_despesa_veiculo).

## documentos_veiculo

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                  | Tipo                 | Aceita NULL | Default              | Chave | Comentário |
| ---------------------- | -------------------- | ----------- | -------------------- | ----- | ---------- |
| id                     | bigint(20) unsigned  | NO          | NULL                 | PRI   |            |
| veiculo_id             | bigint(20) unsigned  | NO          | NULL                 | MUL   |            |
| arquivo_id             | bigint(20) unsigned  | NO          | NULL                 | MUL   |            |
| tipo                   | varchar(60)          | NO          | NULL                 |       |            |
| numero                 | varchar(80)          | YES         | NULL                 |       |            |
| exercicio              | smallint(5) unsigned | YES         | NULL                 |       |            |
| emitido_em             | date                 | YES         | NULL                 |       |            |
| vence_em               | date                 | YES         | NULL                 | MUL   |            |
| substitui_documento_id | bigint(20) unsigned  | YES         | NULL                 | MUL   |            |
| criado_em              | datetime(6)          | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `substitui_documento_id` → `documentos_veiculo.id` (fk_dv_anterior).
- `arquivo_id` → `arquivos.id` (fk_dv_arquivo).
- `veiculo_id` → `veiculos.id` (fk_dv_veiculo).

## email_outbox

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                | Tipo                                                                    | Aceita NULL | Default              | Chave | Comentário |
| -------------------- | ----------------------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                   | bigint(20) unsigned                                                     | NO          | NULL                 | PRI   |            |
| evento_chave         | char(36)                                                                | NO          | NULL                 | UNI   |            |
| usuario_id           | bigint(20) unsigned                                                     | NO          | NULL                 | MUL   |            |
| finalidade           | enum('ativacao','email','recuperacao','aviso')                          | NO          | NULL                 |       |            |
| versao_seguranca     | bigint(20) unsigned                                                     | NO          | NULL                 |       |            |
| destinatario_cifrado | text                                                                    | YES         | NULL                 |       |            |
| token_cifrado        | text                                                                    | YES         | NULL                 |       |            |
| recuperacao_id       | bigint(20) unsigned                                                     | YES         | NULL                 | MUL   |            |
| confirmacao_id       | bigint(20) unsigned                                                     | YES         | NULL                 | MUL   |            |
| aviso_codigo         | varchar(80)                                                             | YES         | NULL                 |       |            |
| estado               | enum('pendente','enfileirado','enviando','aceito','cancelado','falhou') | NO          | 'pendente'           | MUL   |            |
| tentativas           | smallint(5) unsigned                                                    | NO          | 0                    |       |            |
| proxima_tentativa_em | datetime(6)                                                             | NO          | NULL                 |       |            |
| expira_em            | datetime(6)                                                             | NO          | NULL                 |       |            |
| lease_id             | char(36)                                                                | YES         | NULL                 |       |            |
| lease_ate            | datetime(6)                                                             | YES         | NULL                 |       |            |
| aceito_smtp_em       | datetime(6)                                                             | YES         | NULL                 |       |            |
| provedor_id          | varchar(150)                                                            | YES         | NULL                 |       |            |
| codigo_erro          | varchar(60)                                                             | YES         | NULL                 |       |            |
| criado_em            | datetime(6)                                                             | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `confirmacao_id` → `confirmacoes_identidade.id` (fk_outbox_confirmacao).
- `recuperacao_id` → `recuperacoes_senha.id` (fk_outbox_recuperacao).
- `usuario_id` → `usuarios.id` (fk_outbox_usuario).

## exportacao_campos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo         | Tipo                 | Aceita NULL | Default | Chave | Comentário |
| ------------- | -------------------- | ----------- | ------- | ----- | ---------- |
| exportacao_id | bigint(20) unsigned  | NO          | NULL    | PRI   |            |
| campo_id      | smallint(5) unsigned | NO          | NULL    | PRI   |            |
| modulo_codigo | varchar(40)          | NO          | NULL    |       |            |
| ordem         | smallint(5) unsigned | NO          | NULL    |       |            |

Chaves estrangeiras desta tabela:

- `campo_id` → `relatorio_campos.id` (fk_ec_campo).
- `modulo_codigo` → `relatorio_campos.modulo_codigo` (fk_ec_campo).
- `exportacao_id` → `exportacoes.id` (fk_ec_exportacao).
- `modulo_codigo` → `exportacoes.modulo_codigo` (fk_ec_exportacao).

## exportacao_registros

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                   | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ----------------------- | ------------------- | ----------- | ------- | ----- | ---------- |
| id                      | bigint(20) unsigned | NO          | NULL    | PRI   |            |
| exportacao_id           | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| ordem                   | bigint(20) unsigned | NO          | NULL    |       |            |
| solicitacao_id          | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| viagem_id               | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| veiculo_id              | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| despesa_id              | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| multa_id                | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| chamado_id              | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| usuario_id              | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| perfil_id               | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| rota_id                 | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| auditoria_id            | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| posicao_rastreamento_id | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| posicao_manual_id       | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| snapshot                | longtext            | NO          | NULL    |       |            |

Chaves estrangeiras desta tabela:

- `auditoria_id` → `auditoria.id` (fk_er_auditoria).
- `chamado_id` → `chamados.id` (fk_er_chamado).
- `despesa_id` → `despesas.id` (fk_er_despesa).
- `exportacao_id` → `exportacoes.id` (fk_er_exportacao).
- `multa_id` → `multas.id` (fk_er_multa).
- `perfil_id` → `perfis.id` (fk_er_perfil).
- `posicao_rastreamento_id` → `posicoes_rastreamento.id` (fk_er_posicao).
- `posicao_manual_id` → `posicoes_manuais.id` (fk_er_posicao_manual).
- `rota_id` → `rotas_sistema.id` (fk_er_rota).
- `solicitacao_id` → `solicitacoes.id` (fk_er_solicitacao).
- `usuario_id` → `usuarios.id` (fk_er_usuario).
- `veiculo_id` → `veiculos.id` (fk_er_veiculo).
- `viagem_id` → `viagens.id` (fk_er_viagem).

## exportacoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                     | Tipo                                                                            | Aceita NULL | Default               | Chave | Comentário |
| ------------------------- | ------------------------------------------------------------------------------- | ----------- | --------------------- | ----- | ---------- |
| id                        | bigint(20) unsigned                                                             | NO          | NULL                  | PRI   |            |
| solicitado_por_vinculo_id | bigint(20) unsigned                                                             | NO          | NULL                  | MUL   |            |
| modulo_codigo             | varchar(40)                                                                     | NO          | NULL                  | MUL   |            |
| formato                   | enum('md','csv','xlsx','pdf')                                                   | NO          | NULL                  |       |            |
| situacao                  | enum('previa','fila','processando','concluida','falhou','expirada','cancelada') | NO          | 'previa'              | MUL   |            |
| periodo_inicio            | date                                                                            | YES         | NULL                  |       |            |
| periodo_fim               | date                                                                            | YES         | NULL                  |       |            |
| periodo_inicio_utc        | datetime(6)                                                                     | YES         | NULL                  |       |            |
| periodo_fim_utc           | datetime(6)                                                                     | YES         | NULL                  |       |            |
| fuso_apresentacao         | varchar(64)                                                                     | NO          | 'America/Porto_Velho' |       |            |
| filtros                   | longtext                                                                        | NO          | NULL                  |       |            |
| alcance_aplicado          | enum('proprios','unidade','orgao')                                              | NO          | NULL                  |       |            |
| unidade_aplicada_id       | bigint(20) unsigned                                                             | YES         | NULL                  | MUL   |            |
| total_registros           | bigint(20) unsigned                                                             | NO          | 0                     |       |            |
| arquivo_id                | bigint(20) unsigned                                                             | YES         | NULL                  | MUL   |            |
| erro_codigo               | varchar(60)                                                                     | YES         | NULL                  |       |            |
| criado_em                 | datetime(6)                                                                     | NO          | current_timestamp(6)  |       |            |
| iniciado_em               | datetime(6)                                                                     | YES         | NULL                  |       |            |
| concluido_em              | datetime(6)                                                                     | YES         | NULL                  |       |            |
| expira_em                 | datetime(6)                                                                     | YES         | NULL                  |       |            |

Chaves estrangeiras desta tabela:

- `arquivo_id` → `arquivos.id` (fk_exportacao_arquivo).
- `modulo_codigo` → `modulos.codigo` (fk_exportacao_modulo).
- `unidade_aplicada_id` → `unidades.id` (fk_exportacao_unidade).
- `solicitado_por_vinculo_id` → `usuario_perfis.id` (fk_exportacao_vinculo).

## failed_jobs

Tipo: BASE TABLE. Motor: InnoDB.

| Campo      | Tipo                | Aceita NULL | Default             | Chave | Comentário |
| ---------- | ------------------- | ----------- | ------------------- | ----- | ---------- |
| id         | bigint(20) unsigned | NO          | NULL                | PRI   |            |
| uuid       | varchar(255)        | NO          | NULL                | UNI   |            |
| connection | text                | NO          | NULL                |       |            |
| queue      | text                | NO          | NULL                |       |            |
| payload    | longtext            | NO          | NULL                |       |            |
| exception  | longtext            | NO          | NULL                |       |            |
| failed_at  | timestamp           | NO          | current_timestamp() |       |            |

## fornecedores

Tipo: BASE TABLE. Motor: InnoDB.

| Campo    | Tipo                | Aceita NULL | Default | Chave | Comentário |
| -------- | ------------------- | ----------- | ------- | ----- | ---------- |
| id       | bigint(20) unsigned | NO          | NULL    | PRI   |            |
| nome     | varchar(150)        | NO          | NULL    |       |            |
| cpf_cnpj | varchar(14)         | YES         | NULL    | UNI   |            |
| email    | varchar(254)        | YES         | NULL    |       |            |
| telefone | varchar(30)         | YES         | NULL    |       |            |
| ativo    | tinyint(3) unsigned | NO          | 1       |       |            |

## jobs

Tipo: BASE TABLE. Motor: InnoDB.

| Campo        | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ------------ | ------------------- | ----------- | ------- | ----- | ---------- |
| id           | bigint(20) unsigned | NO          | NULL    | PRI   |            |
| queue        | varchar(255)        | NO          | NULL    | MUL   |            |
| payload      | longtext            | NO          | NULL    |       |            |
| attempts     | tinyint(3) unsigned | NO          | NULL    |       |            |
| reserved_at  | int(10) unsigned    | YES         | NULL    |       |            |
| available_at | int(10) unsigned    | NO          | NULL    |       |            |
| created_at   | int(10) unsigned    | NO          | NULL    |       |            |

## manutencao_itens

Tipo: BASE TABLE. Motor: InnoDB.

| Campo          | Tipo                   | Aceita NULL | Default | Chave | Comentário |
| -------------- | ---------------------- | ----------- | ------- | ----- | ---------- |
| id             | bigint(20) unsigned    | NO          | NULL    | PRI   |            |
| manutencao_id  | bigint(20) unsigned    | NO          | NULL    | MUL   |            |
| tipo           | enum('peca','servico') | NO          | NULL    |       |            |
| descricao      | varchar(255)           | NO          | NULL    |       |            |
| quantidade     | decimal(12,3)          | NO          | 1.000   |       |            |
| valor_unitario | decimal(13,2)          | NO          | NULL    |       |            |

Chaves estrangeiras desta tabela:

- `manutencao_id` → `manutencoes.id` (fk_mi_manutencao).

## manutencoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                | Tipo                                                    | Aceita NULL | Default              | Chave | Comentário |
| -------------------- | ------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                   | bigint(20) unsigned                                     | NO          | NULL                 | PRI   |            |
| protocolo            | varchar(40)                                             | NO          | NULL                 | UNI   |            |
| veiculo_id           | bigint(20) unsigned                                     | NO          | NULL                 | MUL   |            |
| despesa_id           | bigint(20) unsigned                                     | YES         | NULL                 | UNI   |            |
| reserva_id           | bigint(20) unsigned                                     | YES         | NULL                 | UNI   |            |
| fornecedor_id        | bigint(20) unsigned                                     | YES         | NULL                 | MUL   |            |
| tipo                 | enum('preventiva','corretiva','vistoria')               | NO          | NULL                 |       |            |
| situacao             | enum('planejada','em_execucao','concluida','cancelada') | NO          | 'planejada'          |       |            |
| descricao            | text                                                    | NO          | NULL                 |       |            |
| inicio_previsto      | datetime(6)                                             | NO          | NULL                 |       |            |
| fim_previsto         | datetime(6)                                             | NO          | NULL                 |       |            |
| inicio_real          | datetime(6)                                             | YES         | NULL                 |       |            |
| fim_real             | datetime(6)                                             | YES         | NULL                 |       |            |
| quilometragem        | decimal(12,1)                                           | YES         | NULL                 |       |            |
| proxima_revisao_km   | decimal(12,1)                                           | YES         | NULL                 |       |            |
| proxima_revisao_data | date                                                    | YES         | NULL                 |       |            |
| criado_por           | bigint(20) unsigned                                     | NO          | NULL                 | MUL   |            |
| criado_em            | datetime(6)                                             | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `criado_por` → `usuarios.id` (fk_manutencao_autor).
- `despesa_id` → `despesas.id` (fk_manutencao_despesa).
- `veiculo_id` → `despesas.veiculo_id` (fk_manutencao_despesa).
- `fornecedor_id` → `fornecedores.id` (fk_manutencao_fornecedor).
- `reserva_id` → `reservas.id` (fk_manutencao_reserva).
- `veiculo_id` → `reservas.veiculo_id` (fk_manutencao_reserva).
- `veiculo_id` → `veiculos.id` (fk_manutencao_veiculo).

## modulo_acoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo         | Tipo        | Aceita NULL | Default | Chave | Comentário |
| ------------- | ----------- | ----------- | ------- | ----- | ---------- |
| modulo_codigo | varchar(40) | NO          | NULL    | PRI   |            |
| acao_codigo   | varchar(40) | NO          | NULL    | PRI   |            |

Chaves estrangeiras desta tabela:

- `acao_codigo` → `acoes.codigo` (fk_ma_acao).
- `modulo_codigo` → `modulos.codigo` (fk_ma_modulo).

## modulos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo  | Tipo                 | Aceita NULL | Default | Chave | Comentário |
| ------ | -------------------- | ----------- | ------- | ----- | ---------- |
| codigo | varchar(40)          | NO          | NULL    | PRI   |            |
| nome   | varchar(100)         | NO          | NULL    |       |            |
| ordem  | smallint(5) unsigned | NO          | 0       |       |            |
| ativo  | tinyint(3) unsigned  | NO          | 1       |       |            |

## motoristas

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                  | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ---------------------- | ------------------- | ----------- | ------- | ----- | ---------- |
| usuario_id             | bigint(20) unsigned | NO          | NULL    | PRI   |            |
| numero_cnh             | varchar(20)         | NO          | NULL    | UNI   |            |
| categoria_cnh          | varchar(10)         | NO          | NULL    |       |            |
| categorias_autorizadas | varchar(5)          | NO          | NULL    |       |            |
| validade_cnh           | date                | NO          | NULL    |       |            |
| ativo                  | tinyint(3) unsigned | NO          | 1       |       |            |
| cnh_arquivo_id         | bigint(20) unsigned | YES         | NULL    | MUL   |            |

Chaves estrangeiras desta tabela:

- `cnh_arquivo_id` → `arquivos.id` (fk_motorista_documento).
- `usuario_id` → `usuarios.id` (fk_motorista_usuario).

## multa_comprovantes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                  | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ---------------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                     | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| multa_id               | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| numero                 | int(10) unsigned    | NO          | NULL                 |       |            |
| responsabilidade_id    | bigint(20) unsigned | NO          | NULL                 |       |            |
| arquivo_id             | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| enviado_por_vinculo_id | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| valor_declarado        | decimal(13,2)       | NO          | NULL                 |       |            |
| pagamento_declarado_em | datetime(6)         | NO          | NULL                 |       |            |
| observacao             | text                | YES         | NULL                 |       |            |
| enviado_em             | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `arquivo_id` → `arquivos.id` (fk_mc_arquivo).
- `multa_id` → `multa_responsabilidades.multa_id` (fk_mc_responsabilidade).
- `responsabilidade_id` → `multa_responsabilidades.id` (fk_mc_responsabilidade).
- `enviado_por_vinculo_id` → `usuario_perfis.id` (fk_mc_vinculo).

## multa_conferencias

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                    | Tipo                                 | Aceita NULL | Default              | Chave | Comentário |
| ------------------------ | ------------------------------------ | ----------- | -------------------- | ----- | ---------- |
| id                       | bigint(20) unsigned                  | NO          | NULL                 | PRI   |            |
| multa_id                 | bigint(20) unsigned                  | NO          | NULL                 | MUL   |            |
| comprovante_id           | bigint(20) unsigned                  | NO          | NULL                 | UNI   |            |
| conferido_por_vinculo_id | bigint(20) unsigned                  | NO          | NULL                 | MUL   |            |
| resultado                | enum('aceito','correcao_solicitada') | NO          | NULL                 |       |            |
| motivo                   | text                                 | YES         | NULL                 |       |            |
| valor_confirmado         | decimal(13,2)                        | YES         | NULL                 |       |            |
| pagamento_confirmado_em  | datetime(6)                          | YES         | NULL                 |       |            |
| conferido_em             | datetime(6)                          | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `multa_id` → `multa_comprovantes.multa_id` (fk_mcf_comprovante).
- `comprovante_id` → `multa_comprovantes.id` (fk_mcf_comprovante).
- `conferido_por_vinculo_id` → `usuario_perfis.id` (fk_mcf_vinculo).

## multa_eventos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo             | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ----------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| multa_id          | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| ator_vinculo_id   | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| tipo              | varchar(40)         | NO          | NULL                 |       |            |
| situacao_anterior | varchar(40)         | YES         | NULL                 |       |            |
| situacao_nova     | varchar(40)         | NO          | NULL                 |       |            |
| motivo            | text                | YES         | NULL                 |       |            |
| criado_em         | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `multa_id` → `multas.id` (fk_me_multa).
- `ator_vinculo_id` → `usuario_perfis.id` (fk_me_vinculo).

## multa_responsabilidades

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                     | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ------------------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                        | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| multa_id                  | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| veiculo_id                | bigint(20) unsigned | NO          | NULL                 |       |            |
| viagem_id                 | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| motorista_id              | bigint(20) unsigned | NO          | NULL                 |       |            |
| responsavel_id            | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| numero                    | int(10) unsigned    | NO          | NULL                 |       |            |
| confirmado_por_vinculo_id | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| confirmado_em             | datetime(6)         | NO          | current_timestamp(6) |       |            |
| justificativa             | text                | NO          | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `confirmado_por_vinculo_id` → `usuario_perfis.id` (fk_mr_confirmador).
- `multa_id` → `multas.id` (fk_mr_multa).
- `veiculo_id` → `multas.veiculo_id` (fk_mr_multa).
- `responsavel_id` → `usuarios.id` (fk_mr_responsavel).
- `viagem_id` → `viagens.id` (fk_mr_viagem).
- `veiculo_id` → `viagens.veiculo_id` (fk_mr_viagem).
- `motorista_id` → `viagens.motorista_id` (fk_mr_viagem).

## multas

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                     | Tipo                                                                                                 | Aceita NULL | Default              | Chave | Comentário |
| ------------------------- | ---------------------------------------------------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                        | bigint(20) unsigned                                                                                  | NO          | NULL                 | PRI   |            |
| protocolo                 | varchar(40)                                                                                          | NO          | NULL                 | UNI   |            |
| veiculo_id                | bigint(20) unsigned                                                                                  | NO          | NULL                 | MUL   |            |
| unidade_id                | bigint(20) unsigned                                                                                  | NO          | NULL                 | MUL   |            |
| numero_auto               | varchar(100)                                                                                         | YES         | NULL                 |       |            |
| orgao_autuador            | varchar(150)                                                                                         | YES         | NULL                 | MUL   |            |
| ocorrido_em               | datetime(6)                                                                                          | NO          | NULL                 |       |            |
| precisao_ocorrencia       | enum('dia','instante')                                                                               | NO          | 'instante'           |       |            |
| fim_dia_ocorrencia_utc    | datetime(6)                                                                                          | YES         | NULL                 |       |            |
| data_vencimento           | date                                                                                                 | YES         | NULL                 |       |            |
| valor                     | decimal(13,2)                                                                                        | NO          | NULL                 |       |            |
| descricao                 | text                                                                                                 | NO          | NULL                 |       |            |
| situacao                  | enum('sem_responsavel','aguardando_comprovante','em_conferencia','quitada','contestada','cancelada') | NO          | 'sem_responsavel'    |       |            |
| responsabilidade_atual_id | bigint(20) unsigned                                                                                  | YES         | NULL                 |       |            |
| criado_por                | bigint(20) unsigned                                                                                  | NO          | NULL                 | MUL   |            |
| versao                    | bigint(20) unsigned                                                                                  | NO          | 1                    |       |            |
| criado_em                 | datetime(6)                                                                                          | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `criado_por` → `usuarios.id` (fk_multa_autor).
- `id` → `multa_responsabilidades.multa_id` (fk_multa_responsabilidade_atual).
- `responsabilidade_atual_id` → `multa_responsabilidades.id` (fk_multa_responsabilidade_atual).
- `unidade_id` → `unidades.id` (fk_multa_unidade).
- `veiculo_id` → `veiculos.id` (fk_multa_veiculo).

## notificacao_destinatarios

Tipo: BASE TABLE. Motor: InnoDB.

| Campo       | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ----------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| evento_id   | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| usuario_id  | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| lida_em     | datetime(6)         | YES         | NULL                 |       |            |
| oculta_em   | datetime(6)         | YES         | NULL                 |       |            |
| versao      | bigint(20) unsigned | NO          | 1                    |       |            |
| entregue_em | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `evento_id` → `notificacao_eventos.id` (fk_nd_evento).
- `usuario_id` → `usuarios.id` (fk_nd_usuario).

## notificacao_eventos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo              | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ------------------ | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                 | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| chave_idempotencia | varchar(160)        | NO          | NULL                 | UNI   |            |
| tipo               | varchar(60)         | NO          | NULL                 |       |            |
| titulo             | varchar(160)        | NO          | NULL                 |       |            |
| mensagem           | varchar(1000)       | NO          | NULL                 |       |            |
| caminho_destino    | varchar(255)        | YES         | NULL                 |       |            |
| solicitacao_id     | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| viagem_id          | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| multa_id           | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| despesa_id         | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| chamado_id         | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| criado_em          | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `chamado_id` → `chamados.id` (fk_ne_chamado).
- `despesa_id` → `despesas.id` (fk_ne_despesa).
- `multa_id` → `multas.id` (fk_ne_multa).
- `solicitacao_id` → `solicitacoes.id` (fk_ne_solicitacao).
- `viagem_id` → `viagens.id` (fk_ne_viagem).

## ocupacoes_agenda

Tipo: BASE TABLE. Motor: InnoDB.

| Campo        | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ------------ | ------------------- | ----------- | ------- | ----- | ---------- |
| reserva_id   | bigint(20) unsigned | NO          | NULL    | PRI   |            |
| veiculo_id   | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| motorista_id | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| viagem_id    | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| em_andamento | tinyint(3) unsigned | NO          | 0       |       |            |
| inicio       | datetime(6)         | NO          | NULL    |       |            |
| fim          | datetime(6)         | NO          | NULL    |       |            |

Chaves estrangeiras desta tabela:

- `motorista_id` → `motoristas.usuario_id` (fk_oa_motorista).
- `reserva_id` → `reservas.id` (fk_oa_reserva).
- `veiculo_id` → `veiculos.id` (fk_oa_veiculo).
- `viagem_id` → `viagens.id` (fk_oa_viagem).

## pagamentos_despesa

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                     | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ------------------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                        | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| despesa_id                | bigint(20) unsigned | NO          | NULL                 | UNI   |            |
| valor                     | decimal(13,2)       | NO          | NULL                 |       |            |
| pago_em                   | datetime(6)         | NO          | NULL                 |       |            |
| comprovante_arquivo_id    | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| confirmado_por_vinculo_id | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| confirmado_em             | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `comprovante_arquivo_id` → `arquivos.id` (fk_pd_arquivo).
- `despesa_id` → `despesas.id` (fk_pd_despesa).
- `confirmado_por_vinculo_id` → `usuario_perfis.id` (fk_pd_vinculo).

## pagamentos_multa

Tipo: BASE TABLE. Motor: InnoDB.

| Campo          | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| -------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id             | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| multa_id       | bigint(20) unsigned | NO          | NULL                 | UNI   |            |
| conferencia_id | bigint(20) unsigned | NO          | NULL                 | UNI   |            |
| valor          | decimal(13,2)       | NO          | NULL                 |       |            |
| pago_em        | datetime(6)         | NO          | NULL                 |       |            |
| registrado_em  | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `multa_id` → `multa_conferencias.multa_id` (fk_pagamento_multa_conferencia).
- `conferencia_id` → `multa_conferencias.id` (fk_pagamento_multa_conferencia).

## perfil_permissoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo         | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| perfil_id     | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| permissao_id  | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| delegavel     | tinyint(3) unsigned | NO          | 0                    |       |            |
| concedido_por | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| concedido_em  | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `concedido_por` → `usuarios.id` (fk_pp_autor).
- `perfil_id` → `perfis.id` (fk_pp_perfil).
- `permissao_id` → `permissoes.id` (fk_pp_permissao).

## perfis

Tipo: BASE TABLE. Motor: InnoDB.

| Campo            | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ---------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id               | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| codigo           | varchar(60)         | NO          | NULL                 | UNI   |            |
| nome             | varchar(100)        | NO          | NULL                 |       |            |
| descricao        | text                | YES         | NULL                 |       |            |
| ativo            | tinyint(3) unsigned | NO          | 1                    |       |            |
| perfil_origem_id | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| criado_por       | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| criado_em        | datetime(6)         | NO          | current_timestamp(6) |       |            |
| atualizado_em    | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `criado_por` → `usuarios.id` (fk_perfil_autor).
- `perfil_origem_id` → `perfis.id` (fk_perfil_origem).

## permissoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo         | Tipo                               | Aceita NULL | Default | Chave | Comentário |
| ------------- | ---------------------------------- | ----------- | ------- | ----- | ---------- |
| id            | bigint(20) unsigned                | NO          | NULL    | PRI   |            |
| modulo_codigo | varchar(40)                        | NO          | NULL    | MUL   |            |
| acao_codigo   | varchar(40)                        | NO          | NULL    | MUL   |            |
| alcance       | enum('proprios','unidade','orgao') | NO          | NULL    |       |            |

Chaves estrangeiras desta tabela:

- `acao_codigo` → `acoes.codigo` (fk_permissao_acao).
- `modulo_codigo` → `modulo_acoes.modulo_codigo` (fk_permissao_combinacao).
- `acao_codigo` → `modulo_acoes.acao_codigo` (fk_permissao_combinacao).
- `modulo_codigo` → `modulos.codigo` (fk_permissao_modulo).

## pneu_instalacoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                    | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ------------------------ | ------------------- | ----------- | ------- | ----- | ---------- |
| id                       | bigint(20) unsigned | NO          | NULL    | PRI   |            |
| pneu_id                  | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| veiculo_id               | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| posicao                  | varchar(40)         | NO          | NULL    |       |            |
| instalado_em             | datetime(6)         | NO          | NULL    |       |            |
| removido_em              | datetime(6)         | YES         | NULL    |       |            |
| quilometragem_instalacao | decimal(12,1)       | NO          | NULL    |       |            |
| quilometragem_remocao    | decimal(12,1)       | YES         | NULL    |       |            |
| motivo_remocao           | varchar(500)        | YES         | NULL    |       |            |
| registrado_por           | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| manutencao_id            | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| pneu_aberto              | bigint(20) unsigned | YES         | NULL    | UNI   |            |
| veiculo_aberto           | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| posicao_aberta           | varchar(40)         | YES         | NULL    |       |            |

Chaves estrangeiras desta tabela:

- `registrado_por` → `usuarios.id` (fk_pi_autor).
- `manutencao_id` → `manutencoes.id` (fk_pi_manutencao).
- `pneu_id` → `pneus.id` (fk_pi_pneu).
- `veiculo_id` → `veiculos.id` (fk_pi_veiculo).

## pneus

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                | Tipo                                     | Aceita NULL | Default   | Chave | Comentário |
| -------------------- | ---------------------------------------- | ----------- | --------- | ----- | ---------- |
| id                   | bigint(20) unsigned                      | NO          | NULL      | PRI   |            |
| codigo               | varchar(60)                              | NO          | NULL      | UNI   |            |
| numero_serie         | varchar(100)                             | YES         | NULL      | UNI   |            |
| marca                | varchar(80)                              | YES         | NULL      |       |            |
| modelo               | varchar(80)                              | YES         | NULL      |       |            |
| medida               | varchar(40)                              | NO          | NULL      |       |            |
| adquirido_em         | date                                     | YES         | NULL      |       |            |
| despesa_aquisicao_id | bigint(20) unsigned                      | YES         | NULL      | MUL   |            |
| situacao             | enum('estoque','instalado','descartado') | NO          | 'estoque' |       |            |
| descartado_em        | datetime(6)                              | YES         | NULL      |       |            |
| motivo_descarte      | varchar(500)                             | YES         | NULL      |       |            |

Chaves estrangeiras desta tabela:

- `despesa_aquisicao_id` → `despesas.id` (fk_pneu_despesa).

## posicoes_manuais

Tipo: BASE TABLE. Motor: InnoDB.

| Campo          | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| -------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id             | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| viagem_id      | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| registrado_por | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| registrado_em  | datetime(6)         | NO          | current_timestamp(6) |       |            |
| ocorrido_em    | datetime(6)         | NO          | NULL                 |       |            |
| latitude       | decimal(10,7)       | NO          | NULL                 |       |            |
| longitude      | decimal(10,7)       | NO          | NULL                 |       |            |
| descricao      | varchar(1000)       | NO          | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `registrado_por` → `usuarios.id` (fk_pm_autor).
- `viagem_id` → `viagens.id` (fk_pm_viagem).

## posicoes_rastreamento

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| -------------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                   | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| instalacao_id        | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| veiculo_id           | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| viagem_id            | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| identificador_evento | varchar(120)        | NO          | NULL                 |       |            |
| capturado_em         | datetime(6)         | NO          | NULL                 |       |            |
| recebido_em          | datetime(6)         | NO          | current_timestamp(6) |       |            |
| latitude             | decimal(10,7)       | NO          | NULL                 |       |            |
| longitude            | decimal(10,7)       | NO          | NULL                 |       |            |
| velocidade_kmh       | decimal(7,2)        | YES         | NULL                 |       |            |
| precisao_metros      | decimal(9,2)        | YES         | NULL                 |       |            |
| ignicao              | tinyint(3) unsigned | YES         | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `instalacao_id` → `veiculo_rastreadores.id` (fk_posicao_instalacao).
- `veiculo_id` → `veiculo_rastreadores.veiculo_id` (fk_posicao_instalacao).
- `viagem_id` → `viagens.id` (fk_posicao_viagem).
- `veiculo_id` → `viagens.veiculo_id` (fk_posicao_viagem).

## preferencias_usuario

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                 | Tipo                                 | Aceita NULL | Default              | Chave | Comentário |
| --------------------- | ------------------------------------ | ----------- | -------------------- | ----- | ---------- |
| usuario_id            | bigint(20) unsigned                  | NO          | NULL                 | PRI   |            |
| tema                  | enum('claro','escuro','dispositivo') | NO          | 'dispositivo'        |       |            |
| itens_por_pagina      | smallint(5) unsigned                 | NO          | 10                   |       |            |
| notificacoes_internas | tinyint(3) unsigned                  | NO          | 1                    |       |            |
| atualizado_em         | datetime(6)                          | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `usuario_id` → `usuarios.id` (fk_preferencia_usuario).

## rastreadores

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                 | Tipo                             | Aceita NULL | Default              | Chave | Comentário |
| --------------------- | -------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                    | bigint(20) unsigned              | NO          | NULL                 | PRI   |            |
| identificador_externo | varchar(100)                     | NO          | NULL                 |       |            |
| provedor              | varchar(100)                     | NO          | NULL                 | MUL   |            |
| tipo                  | enum('gps','telematica','outro') | NO          | 'gps'                |       |            |
| modelo                | varchar(100)                     | YES         | NULL                 |       |            |
| ativo                 | tinyint(3) unsigned              | NO          | 1                    |       |            |
| criado_em             | datetime(6)                      | NO          | current_timestamp(6) |       |            |

## recuperacoes_mfa

Tipo: BASE TABLE. Motor: InnoDB.

| Campo            | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ---------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id               | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| usuario_id       | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| solicitada_por   | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| versao_seguranca | bigint(20) unsigned | NO          | NULL                 |       |            |
| justificativa    | varchar(500)        | NO          | NULL                 |       |            |
| criada_em        | datetime(6)         | NO          | current_timestamp(6) |       |            |
| expira_em        | datetime(6)         | NO          | NULL                 |       |            |
| concluida_em     | datetime(6)         | YES         | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `solicitada_por` → `usuarios.id` (fk_recuperacao_mfa_solicitante).
- `usuario_id` → `usuarios.id` (fk_recuperacao_mfa_usuario).

## recuperacoes_senha

Tipo: BASE TABLE. Motor: InnoDB.

| Campo            | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ---------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id               | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| usuario_id       | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| token_hash       | binary(32)          | NO          | NULL                 | UNI   |            |
| criado_em        | datetime(6)         | NO          | current_timestamp(6) |       |            |
| expira_em        | datetime(6)         | NO          | NULL                 |       |            |
| usado_em         | datetime(6)         | YES         | NULL                 |       |            |
| invalidado_em    | datetime(6)         | YES         | NULL                 |       |            |
| email_destino    | varchar(254)        | YES         | NULL                 |       |            |
| versao_seguranca | bigint(20) unsigned | YES         | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `usuario_id` → `usuarios.id` (fk_recuperacao_usuario).

## relatorio_campos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo          | Tipo                                                         | Aceita NULL | Default | Chave | Comentário |
| -------------- | ------------------------------------------------------------ | ----------- | ------- | ----- | ---------- |
| id             | smallint(5) unsigned                                         | NO          | NULL    | PRI   |            |
| modulo_codigo  | varchar(40)                                                  | NO          | NULL    | MUL   |            |
| chave          | varchar(80)                                                  | NO          | NULL    |       |            |
| rotulo         | varchar(150)                                                 | NO          | NULL    |       |            |
| tipo           | enum('texto','numero','moeda','data','data_hora','situacao') | NO          | NULL    |       |            |
| acao_adicional | varchar(40)                                                  | YES         | NULL    | MUL   |            |
| ordem          | smallint(5) unsigned                                         | NO          | 0       |       |            |
| ativo          | tinyint(3) unsigned                                          | NO          | 1       |       |            |

Chaves estrangeiras desta tabela:

- `acao_adicional` → `acoes.codigo` (fk_rc_acao).
- `modulo_codigo` → `modulo_acoes.modulo_codigo` (fk_rc_acao_modulo).
- `acao_adicional` → `modulo_acoes.acao_codigo` (fk_rc_acao_modulo).
- `modulo_codigo` → `modulos.codigo` (fk_rc_modulo).

## reservas

Tipo: BASE TABLE. Motor: InnoDB.

| Campo        | Tipo                                            | Aceita NULL | Default              | Chave | Comentário |
| ------------ | ----------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id           | bigint(20) unsigned                             | NO          | NULL                 | PRI   |            |
| veiculo_id   | bigint(20) unsigned                             | NO          | NULL                 | MUL   |            |
| motorista_id | bigint(20) unsigned                             | YES         | NULL                 | MUL   |            |
| revisao_id   | bigint(20) unsigned                             | YES         | NULL                 | UNI   |            |
| tipo         | enum('viagem','manutencao','indisponibilidade') | NO          | NULL                 |       |            |
| inicio       | datetime(6)                                     | NO          | NULL                 |       |            |
| fim          | datetime(6)                                     | NO          | NULL                 |       |            |
| situacao     | enum('ativa','liberada','cancelada')            | NO          | 'ativa'              |       |            |
| descricao    | varchar(500)                                    | YES         | NULL                 |       |            |
| criado_por   | bigint(20) unsigned                             | NO          | NULL                 | MUL   |            |
| criado_em    | datetime(6)                                     | NO          | current_timestamp(6) |       |            |
| liberada_em  | datetime(6)                                     | YES         | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `criado_por` → `usuarios.id` (fk_reserva_autor).
- `motorista_id` → `motoristas.usuario_id` (fk_reserva_motorista).
- `revisao_id` → `solicitacao_revisoes.id` (fk_reserva_revisao).
- `veiculo_id` → `veiculos.id` (fk_reserva_veiculo).

## rota_permissoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo         | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ------------- | ------------------- | ----------- | ------- | ----- | ---------- |
| rota_id       | bigint(20) unsigned | NO          | NULL    | PRI   |            |
| modulo_codigo | varchar(40)         | NO          | NULL    | PRI   |            |
| acao_codigo   | varchar(40)         | NO          | NULL    | PRI   |            |

Chaves estrangeiras desta tabela:

- `acao_codigo` → `acoes.codigo` (fk_rp_acao).
- `modulo_codigo` → `modulo_acoes.modulo_codigo` (fk_rp_combinacao).
- `acao_codigo` → `modulo_acoes.acao_codigo` (fk_rp_combinacao).
- `modulo_codigo` → `modulos.codigo` (fk_rp_modulo).
- `rota_id` → `rotas_sistema.id` (fk_rp_rota).

## rotas_sistema

Tipo: BASE TABLE. Motor: InnoDB.

| Campo         | Tipo                                      | Aceita NULL | Default | Chave | Comentário |
| ------------- | ----------------------------------------- | ----------- | ------- | ----- | ---------- |
| id            | bigint(20) unsigned                       | NO          | NULL    | PRI   |            |
| chave         | varchar(80)                               | NO          | NULL    | UNI   |            |
| nome          | varchar(120)                              | NO          | NULL    |       |            |
| caminho       | varchar(255)                              | NO          | NULL    |       |            |
| metodo_http   | enum('GET','POST','PUT','PATCH','DELETE') | NO          | 'GET'   | MUL   |            |
| modulo_codigo | varchar(40)                               | NO          | NULL    | MUL   |            |
| descricao     | text                                      | YES         | NULL    |       |            |
| ativa         | tinyint(3) unsigned                       | NO          | 1       |       |            |
| visivel_menu  | tinyint(3) unsigned                       | NO          | 1       |       |            |
| implementada  | tinyint(3) unsigned                       | NO          | 0       |       |            |
| protegida     | tinyint(3) unsigned                       | NO          | 0       |       |            |
| ordem         | smallint(5) unsigned                      | NO          | 0       |       |            |
| criado_por    | bigint(20) unsigned                       | YES         | NULL    | MUL   |            |

Chaves estrangeiras desta tabela:

- `criado_por` → `usuarios.id` (fk_rota_autor).
- `modulo_codigo` → `modulos.codigo` (fk_rota_modulo).

## sessoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo               | Tipo                                                           | Aceita NULL | Default              | Chave | Comentário |
| ------------------- | -------------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                  | bigint(20) unsigned                                            | NO          | NULL                 | PRI   |            |
| usuario_id          | bigint(20) unsigned                                            | NO          | NULL                 | MUL   |            |
| vinculo_ativo_id    | bigint(20) unsigned                                            | YES         | NULL                 | MUL   |            |
| token_hash          | binary(32)                                                     | NO          | NULL                 | UNI   |            |
| criado_em           | datetime(6)                                                    | NO          | current_timestamp(6) |       |            |
| ultima_atividade_em | datetime(6)                                                    | NO          | current_timestamp(6) |       |            |
| expira_em           | datetime(6)                                                    | NO          | NULL                 |       |            |
| encerrada_em        | datetime(6)                                                    | YES         | NULL                 |       |            |
| motivo_encerramento | enum('logout','inatividade','prazo','revogacao','troca_senha') | YES         | NULL                 |       |            |
| ip                  | varbinary(16)                                                  | YES         | NULL                 |       |            |
| agente_usuario      | varchar(500)                                                   | YES         | NULL                 |       |            |
| autenticada_em      | datetime(6)                                                    | YES         | NULL                 |       |            |
| reautenticar_ate    | datetime(6)                                                    | YES         | NULL                 |       |            |
| mfa_verificada_em   | datetime(6)                                                    | YES         | NULL                 |       |            |
| mfa_vinculo_id      | bigint(20) unsigned                                            | YES         | NULL                 | MUL   |            |
| mfa_versao          | bigint(20) unsigned                                            | YES         | NULL                 |       |            |
| mfa_http_hash       | binary(32)                                                     | YES         | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `mfa_vinculo_id` → `usuario_perfis.id` (fk_sessao_mfa_vinculo).
- `usuario_id` → `usuario_perfis.usuario_id` (fk_sessao_mfa_vinculo).
- `usuario_id` → `usuarios.id` (fk_sessao_usuario).
- `vinculo_ativo_id` → `usuario_perfis.id` (fk_sessao_vinculo).
- `usuario_id` → `usuario_perfis.usuario_id` (fk_sessao_vinculo).

## solicitacao_eventos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                   | Tipo                                                                                            | Aceita NULL | Default              | Chave | Comentário |
| ----------------------- | ----------------------------------------------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                      | bigint(20) unsigned                                                                             | NO          | NULL                 | PRI   |            |
| solicitacao_id          | bigint(20) unsigned                                                                             | NO          | NULL                 | MUL   |            |
| revisao_id              | bigint(20) unsigned                                                                             | NO          | NULL                 |       |            |
| ator_vinculo_id         | bigint(20) unsigned                                                                             | NO          | NULL                 | MUL   |            |
| tipo                    | enum('criada','enviada','ajustes_solicitados','aprovada','negada','revisao_aberta','cancelada') | NO          | NULL                 |       |            |
| situacao_anterior       | varchar(40)                                                                                     | YES         | NULL                 |       |            |
| situacao_nova           | varchar(40)                                                                                     | NO          | NULL                 |       |            |
| motivo                  | text                                                                                            | YES         | NULL                 |       |            |
| veiculo_confirmado_id   | bigint(20) unsigned                                                                             | YES         | NULL                 | MUL   |            |
| motorista_confirmado_id | bigint(20) unsigned                                                                             | YES         | NULL                 | MUL   |            |
| criado_em               | datetime(6)                                                                                     | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `motorista_confirmado_id` → `motoristas.usuario_id` (fk_se_motorista).
- `solicitacao_id` → `solicitacao_revisoes.solicitacao_id` (fk_se_revisao).
- `revisao_id` → `solicitacao_revisoes.id` (fk_se_revisao).
- `veiculo_confirmado_id` → `veiculos.id` (fk_se_veiculo).
- `ator_vinculo_id` → `usuario_perfis.id` (fk_se_vinculo).

## solicitacao_paradas

Tipo: BASE TABLE. Motor: InnoDB.

| Campo      | Tipo                 | Aceita NULL | Default | Chave | Comentário |
| ---------- | -------------------- | ----------- | ------- | ----- | ---------- |
| id         | bigint(20) unsigned  | NO          | NULL    | PRI   |            |
| revisao_id | bigint(20) unsigned  | NO          | NULL    | MUL   |            |
| ordem      | smallint(5) unsigned | NO          | NULL    |       |            |
| descricao  | varchar(255)         | NO          | NULL    |       |            |
| latitude   | decimal(10,7)        | YES         | NULL    |       |            |
| longitude  | decimal(10,7)        | YES         | NULL    |       |            |

Chaves estrangeiras desta tabela:

- `revisao_id` → `solicitacao_revisoes.id` (fk_parada_revisao).

## solicitacao_passageiros

Tipo: BASE TABLE. Motor: InnoDB.

| Campo      | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ---------- | ------------------- | ----------- | ------- | ----- | ---------- |
| id         | bigint(20) unsigned | NO          | NULL    | PRI   |            |
| revisao_id | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| usuario_id | bigint(20) unsigned | YES         | NULL    | MUL   |            |
| nome       | varchar(150)        | NO          | NULL    |       |            |
| contato    | varchar(100)        | YES         | NULL    |       |            |

Chaves estrangeiras desta tabela:

- `revisao_id` → `solicitacao_revisoes.id` (fk_passageiro_revisao).
- `usuario_id` → `usuarios.id` (fk_passageiro_usuario).

## solicitacao_revisoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                  | Tipo                 | Aceita NULL | Default              | Chave | Comentário |
| ---------------------- | -------------------- | ----------- | -------------------- | ----- | ---------- |
| id                     | bigint(20) unsigned  | NO          | NULL                 | PRI   |            |
| solicitacao_id         | bigint(20) unsigned  | NO          | NULL                 | MUL   |            |
| numero                 | int(10) unsigned     | NO          | NULL                 |       |            |
| finalidade             | text                 | YES         | NULL                 |       |            |
| origem                 | varchar(255)         | YES         | NULL                 |       |            |
| destino                | varchar(255)         | YES         | NULL                 |       |            |
| origem_latitude        | decimal(10,7)        | YES         | NULL                 |       |            |
| origem_longitude       | decimal(10,7)        | YES         | NULL                 |       |            |
| destino_latitude       | decimal(10,7)        | YES         | NULL                 |       |            |
| destino_longitude      | decimal(10,7)        | YES         | NULL                 |       |            |
| saida_prevista         | datetime(6)          | YES         | NULL                 |       |            |
| retorno_previsto       | datetime(6)          | YES         | NULL                 |       |            |
| quantidade_passageiros | smallint(5) unsigned | YES         | NULL                 |       |            |
| necessita_motorista    | tinyint(3) unsigned  | NO          | 1                    |       |            |
| motorista_sugerido_id  | bigint(20) unsigned  | YES         | NULL                 | MUL   |            |
| veiculo_pretendido_id  | bigint(20) unsigned  | YES         | NULL                 | MUL   |            |
| trajeto_planejado      | text                 | YES         | NULL                 |       |            |
| polilinha_planejada    | longtext             | YES         | NULL                 |       |            |
| distancia_prevista_km  | decimal(12,3)        | YES         | NULL                 |       |            |
| observacoes            | text                 | YES         | NULL                 |       |            |
| etapa_atual            | tinyint(3) unsigned  | NO          | 1                    |       |            |
| criado_por             | bigint(20) unsigned  | NO          | NULL                 | MUL   |            |
| criado_em              | datetime(6)          | NO          | current_timestamp(6) |       |            |
| enviado_em             | datetime(6)          | YES         | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `criado_por` → `usuarios.id` (fk_revisao_autor).
- `motorista_sugerido_id` → `motoristas.usuario_id` (fk_revisao_motorista).
- `solicitacao_id` → `solicitacoes.id` (fk_revisao_solicitacao).
- `veiculo_pretendido_id` → `veiculos.id` (fk_revisao_veiculo).

## solicitacoes

Tipo: BASE TABLE. Motor: InnoDB.

| Campo            | Tipo                                                                                        | Aceita NULL | Default              | Chave | Comentário |
| ---------------- | ------------------------------------------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id               | bigint(20) unsigned                                                                         | NO          | NULL                 | PRI   |            |
| protocolo        | varchar(40)                                                                                 | NO          | NULL                 | UNI   |            |
| solicitante_id   | bigint(20) unsigned                                                                         | NO          | NULL                 | MUL   |            |
| unidade_id       | bigint(20) unsigned                                                                         | NO          | NULL                 | MUL   |            |
| revisao_atual_id | bigint(20) unsigned                                                                         | YES         | NULL                 |       |            |
| situacao         | enum('rascunho','aguardando_analise','ajustes_solicitados','aprovada','negada','cancelada') | NO          | 'rascunho'           |       |            |
| versao           | bigint(20) unsigned                                                                         | NO          | 1                    |       |            |
| criado_em        | datetime(6)                                                                                 | NO          | current_timestamp(6) |       |            |
| atualizado_em    | datetime(6)                                                                                 | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `solicitante_id` → `usuarios.id` (fk_solicitacao_autor).
- `id` → `solicitacao_revisoes.solicitacao_id` (fk_solicitacao_revisao_atual).
- `revisao_atual_id` → `solicitacao_revisoes.id` (fk_solicitacao_revisao_atual).
- `unidade_id` → `unidades.id` (fk_solicitacao_unidade).

## unidades

Tipo: BASE TABLE. Motor: InnoDB.

| Campo               | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ------------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                  | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| unidade_superior_id | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| codigo              | varchar(30)         | NO          | NULL                 | UNI   |            |
| nome                | varchar(150)        | NO          | NULL                 |       |            |
| endereco            | varchar(255)        | YES         | NULL                 |       |            |
| cidade              | varchar(100)        | YES         | NULL                 |       |            |
| uf                  | char(2)             | YES         | NULL                 |       |            |
| ativa               | tinyint(3) unsigned | NO          | 1                    |       |            |
| criado_em           | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `unidade_superior_id` → `unidades.id` (fk_unidade_superior).

## usuario_perfis

Tipo: BASE TABLE. Motor: InnoDB.

| Campo            | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ---------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id               | bigint(20) unsigned | NO          | NULL                 | PRI   |            |
| usuario_id       | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| perfil_id        | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| unidade_id       | bigint(20) unsigned | NO          | NULL                 | MUL   |            |
| vigente_desde    | datetime(6)         | NO          | NULL                 |       |            |
| vigente_ate      | datetime(6)         | YES         | NULL                 |       |            |
| ativo            | tinyint(3) unsigned | NO          | 1                    |       |            |
| concedido_por    | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| revogado_por     | bigint(20) unsigned | YES         | NULL                 | MUL   |            |
| motivo_revogacao | varchar(500)        | YES         | NULL                 |       |            |
| criado_em        | datetime(6)         | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `concedido_por` → `usuarios.id` (fk_up_concessor).
- `perfil_id` → `perfis.id` (fk_up_perfil).
- `revogado_por` → `usuarios.id` (fk_up_revogador).
- `unidade_id` → `unidades.id` (fk_up_unidade).
- `usuario_id` → `usuarios.id` (fk_up_usuario).

## usuarios

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                        | Tipo                              | Aceita NULL | Default              | Chave | Comentário |
| ---------------------------- | --------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                           | bigint(20) unsigned               | NO          | NULL                 | PRI   |            |
| unidade_id                   | bigint(20) unsigned               | NO          | NULL                 | MUL   |            |
| identificador                | varchar(100)                      | NO          | NULL                 | UNI   |            |
| nome                         | varchar(150)                      | NO          | NULL                 |       |            |
| email                        | varchar(254)                      | YES         | NULL                 | UNI   |            |
| telefone                     | varchar(30)                       | YES         | NULL                 |       |            |
| senha_hash                   | varchar(255)                      | NO          | NULL                 |       |            |
| ativo                        | tinyint(3) unsigned               | NO          | 1                    |       |            |
| deve_trocar_senha            | tinyint(3) unsigned               | NO          | 0                    |       |            |
| senha_alterada_em            | datetime(6)                       | NO          | current_timestamp(6) |       |            |
| versao                       | bigint(20) unsigned               | NO          | 1                    |       |            |
| criado_em                    | datetime(6)                       | NO          | current_timestamp(6) |       |            |
| atualizado_em                | datetime(6)                       | NO          | current_timestamp(6) |       |            |
| estado_identidade            | enum('legado','pendente','ativa') | NO          | 'legado'             |       |            |
| email_verificado_em          | datetime(6)                       | YES         | NULL                 |       |            |
| email_pendente               | varchar(254)                      | YES         | NULL                 |       |            |
| versao_seguranca             | bigint(20) unsigned               | NO          | 1                    |       |            |
| senha_temporaria_emitida_em  | datetime(6)                       | YES         | NULL                 |       |            |
| senha_temporaria_expira_em   | datetime(6)                       | YES         | NULL                 |       |            |
| senha_temporaria_usada_em    | datetime(6)                       | YES         | NULL                 |       |            |
| senha_temporaria_revogada_em | datetime(6)                       | YES         | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `unidade_id` → `unidades.id` (fk_usuario_unidade).

## veiculo_rastreadores

Tipo: BASE TABLE. Motor: InnoDB.

| Campo             | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ----------------- | ------------------- | ----------- | ------- | ----- | ---------- |
| id                | bigint(20) unsigned | NO          | NULL    | PRI   |            |
| veiculo_id        | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| rastreador_id     | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| instalado_em      | datetime(6)         | NO          | NULL    |       |            |
| removido_em       | datetime(6)         | YES         | NULL    |       |            |
| instalado_por     | bigint(20) unsigned | NO          | NULL    | MUL   |            |
| veiculo_aberto    | bigint(20) unsigned | YES         | NULL    | UNI   |            |
| rastreador_aberto | bigint(20) unsigned | YES         | NULL    | UNI   |            |

Chaves estrangeiras desta tabela:

- `instalado_por` → `usuarios.id` (fk_vr_autor).
- `rastreador_id` → `rastreadores.id` (fk_vr_rastreador).
- `veiculo_id` → `veiculos.id` (fk_vr_veiculo).

## veiculos

Tipo: BASE TABLE. Motor: InnoDB.

| Campo               | Tipo                              | Aceita NULL | Default              | Chave | Comentário |
| ------------------- | --------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                  | bigint(20) unsigned               | NO          | NULL                 | PRI   |            |
| unidade_id          | bigint(20) unsigned               | NO          | NULL                 | MUL   |            |
| categoria_id        | smallint(5) unsigned              | NO          | NULL                 | MUL   |            |
| nome                | varchar(120)                      | NO          | NULL                 |       |            |
| placa               | varchar(10)                       | NO          | NULL                 | UNI   |            |
| renavam             | varchar(20)                       | YES         | NULL                 | UNI   |            |
| chassi              | varchar(30)                       | YES         | NULL                 | UNI   |            |
| marca               | varchar(80)                       | YES         | NULL                 |       |            |
| modelo              | varchar(80)                       | YES         | NULL                 |       |            |
| ano_fabricacao      | smallint(5) unsigned              | YES         | NULL                 |       |            |
| ano_modelo          | smallint(5) unsigned              | YES         | NULL                 |       |            |
| capacidade          | smallint(5) unsigned              | NO          | NULL                 |       |            |
| quilometragem_atual | decimal(12,1)                     | NO          | 0.0                  |       |            |
| situacao_cadastro   | enum('ativo','inativo','baixado') | NO          | 'ativo'              |       |            |
| observacoes         | text                              | YES         | NULL                 |       |            |
| criado_por          | bigint(20) unsigned               | NO          | NULL                 | MUL   |            |
| versao              | bigint(20) unsigned               | NO          | 1                    |       |            |
| criado_em           | datetime(6)                       | NO          | current_timestamp(6) |       |            |
| atualizado_em       | datetime(6)                       | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `criado_por` → `usuarios.id` (fk_veiculo_autor).
- `categoria_id` → `categorias_veiculo.id` (fk_veiculo_categoria).
- `unidade_id` → `unidades.id` (fk_veiculo_unidade).

## versoes_modelo

Tipo: BASE TABLE. Motor: InnoDB.

| Campo        | Tipo         | Aceita NULL | Default              | Chave | Comentário |
| ------------ | ------------ | ----------- | -------------------- | ----- | ---------- |
| versao       | varchar(30)  | NO          | NULL                 | PRI   |            |
| instalado_em | datetime(6)  | NO          | current_timestamp(6) |       |            |
| descricao    | varchar(255) | NO          | NULL                 |       |            |

## viagem_checklist_respostas

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                | Tipo                                  | Aceita NULL | Default | Chave | Comentário |
| -------------------- | ------------------------------------- | ----------- | ------- | ----- | ---------- |
| checklist_id         | bigint(20) unsigned                   | NO          | NULL    | PRI   |            |
| item_id              | smallint(5) unsigned                  | NO          | NULL    | PRI   |            |
| resultado            | enum('ok','problema','nao_aplicavel') | NO          | NULL    |       |            |
| observacao           | varchar(1000)                         | YES         | NULL    |       |            |
| descricao_item       | varchar(150)                          | NO          | ''      |       |            |
| obrigatorio_snapshot | tinyint(3) unsigned                   | NO          | 1       |       |            |

Chaves estrangeiras desta tabela:

- `checklist_id` → `viagem_checklists.id` (fk_vcr_checklist).
- `item_id` → `checklist_itens.id` (fk_vcr_item).

## viagem_checklists

Tipo: BASE TABLE. Motor: InnoDB.

| Campo          | Tipo                    | Aceita NULL | Default              | Chave | Comentário |
| -------------- | ----------------------- | ----------- | -------------------- | ----- | ---------- |
| id             | bigint(20) unsigned     | NO          | NULL                 | PRI   |            |
| viagem_id      | bigint(20) unsigned     | NO          | NULL                 | MUL   |            |
| tipo           | enum('saida','retorno') | NO          | NULL                 |       |            |
| preenchido_por | bigint(20) unsigned     | NO          | NULL                 | MUL   |            |
| criado_em      | datetime(6)             | NO          | current_timestamp(6) |       |            |
| finalizado_em  | datetime(6)             | YES         | NULL                 |       |            |

Chaves estrangeiras desta tabela:

- `preenchido_por` → `usuarios.id` (fk_vc_autor).
- `viagem_id` → `viagens.id` (fk_vc_viagem).

## viagem_ocorrencias

Tipo: BASE TABLE. Motor: InnoDB.

| Campo          | Tipo                                                        | Aceita NULL | Default              | Chave | Comentário |
| -------------- | ----------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id             | bigint(20) unsigned                                         | NO          | NULL                 | PRI   |            |
| viagem_id      | bigint(20) unsigned                                         | NO          | NULL                 | MUL   |            |
| registrado_por | bigint(20) unsigned                                         | NO          | NULL                 | MUL   |            |
| tipo           | enum('geral','desvio_trajeto','avaria','acidente','atraso') | NO          | 'geral'              |       |            |
| ocorrido_em    | datetime(6)                                                 | NO          | NULL                 |       |            |
| descricao      | text                                                        | NO          | NULL                 |       |            |
| latitude       | decimal(10,7)                                               | YES         | NULL                 |       |            |
| longitude      | decimal(10,7)                                               | YES         | NULL                 |       |            |
| criado_em      | datetime(6)                                                 | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `registrado_por` → `usuarios.id` (fk_ocorrencia_autor).
- `viagem_id` → `viagens.id` (fk_ocorrencia_viagem).

## viagens

Tipo: BASE TABLE. Motor: InnoDB.

| Campo                  | Tipo                                                      | Aceita NULL | Default              | Chave | Comentário |
| ---------------------- | --------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                     | bigint(20) unsigned                                       | NO          | NULL                 | PRI   |            |
| protocolo              | varchar(40)                                               | NO          | NULL                 | UNI   |            |
| revisao_id             | bigint(20) unsigned                                       | NO          | NULL                 | UNI   |            |
| reserva_id             | bigint(20) unsigned                                       | NO          | NULL                 | UNI   |            |
| veiculo_id             | bigint(20) unsigned                                       | NO          | NULL                 | MUL   |            |
| motorista_id           | bigint(20) unsigned                                       | NO          | NULL                 | MUL   |            |
| situacao               | enum('programada','em_andamento','concluida','cancelada') | NO          | 'programada'         | MUL   |            |
| saida_real             | datetime(6)                                               | YES         | NULL                 |       |            |
| retorno_real           | datetime(6)                                               | YES         | NULL                 |       |            |
| quilometragem_saida    | decimal(12,1)                                             | YES         | NULL                 |       |            |
| quilometragem_retorno  | decimal(12,1)                                             | YES         | NULL                 |       |            |
| saida_registrada_por   | bigint(20) unsigned                                       | YES         | NULL                 | MUL   |            |
| retorno_registrado_por | bigint(20) unsigned                                       | YES         | NULL                 | MUL   |            |
| observacao_saida       | text                                                      | YES         | NULL                 |       |            |
| observacao_retorno     | text                                                      | YES         | NULL                 |       |            |
| versao                 | bigint(20) unsigned                                       | NO          | 1                    |       |            |
| criado_em              | datetime(6)                                               | NO          | current_timestamp(6) |       |            |

Chaves estrangeiras desta tabela:

- `reserva_id` → `reservas.id` (fk_viagem_reserva).
- `veiculo_id` → `reservas.veiculo_id` (fk_viagem_reserva).
- `motorista_id` → `reservas.motorista_id` (fk_viagem_reserva).
- `revisao_id` → `reservas.revisao_id` (fk_viagem_reserva).
- `retorno_registrado_por` → `usuarios.id` (fk_viagem_retorno_autor).
- `saida_registrada_por` → `usuarios.id` (fk_viagem_saida_autor).

## vw_administradores_permanentes

Tipo: VIEW. Motor: None.

| Campo      | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ---------- | ------------------- | ----------- | ------- | ----- | ---------- |
| vinculo_id | bigint(20) unsigned | NO          | 0       |       |            |
| usuario_id | bigint(20) unsigned | NO          | NULL    |       |            |
| perfil_id  | bigint(20) unsigned | NO          | NULL    |       |            |

## vw_custos_veiculo_mensais

Tipo: VIEW. Motor: None.

| Campo               | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ------------------- | ------------------- | ----------- | ------- | ----- | ---------- |
| veiculo_id          | bigint(20) unsigned | NO          | NULL    |       |            |
| unidade_id          | bigint(20) unsigned | NO          | NULL    |       |            |
| mes                 | varchar(10)         | YES         | NULL    |       |            |
| valor_despesas      | decimal(35,2)       | YES         | NULL    |       |            |
| quantidade_despesas | bigint(21)          | NO          | 0       |       |            |

## vw_despesas_detalhadas

Tipo: VIEW. Motor: None.

| Campo            | Tipo                                                              | Aceita NULL | Default              | Chave | Comentário |
| ---------------- | ----------------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id               | bigint(20) unsigned                                               | NO          | 0                    |       |            |
| protocolo        | varchar(40)                                                       | NO          | NULL                 |       |            |
| veiculo_id       | bigint(20) unsigned                                               | NO          | NULL                 |       |            |
| unidade_id       | bigint(20) unsigned                                               | NO          | NULL                 |       |            |
| categoria_id     | smallint(5) unsigned                                              | NO          | NULL                 |       |            |
| fornecedor_id    | bigint(20) unsigned                                               | YES         | NULL                 |       |            |
| data_despesa     | date                                                              | NO          | NULL                 |       |            |
| valor            | decimal(13,2)                                                     | NO          | NULL                 |       |            |
| descricao        | text                                                              | NO          | NULL                 |       |            |
| numero_documento | varchar(100)                                                      | YES         | NULL                 |       |            |
| situacao         | enum('registrada','em_conferencia','aprovada','paga','cancelada') | NO          | 'registrada'         |       |            |
| criado_por       | bigint(20) unsigned                                               | NO          | NULL                 |       |            |
| versao           | bigint(20) unsigned                                               | NO          | 1                    |       |            |
| criado_em        | datetime(6)                                                       | NO          | current_timestamp(6) |       |            |
| atualizado_em    | datetime(6)                                                       | NO          | current_timestamp(6) |       |            |
| placa            | varchar(10)                                                       | NO          | NULL                 |       |            |
| veiculo          | varchar(120)                                                      | NO          | NULL                 |       |            |
| categoria        | varchar(80)                                                       | NO          | NULL                 |       |            |
| fornecedor       | varchar(150)                                                      | YES         | NULL                 |       |            |
| valor_pago       | decimal(13,2)                                                     | YES         | NULL                 |       |            |
| pago_em          | datetime(6)                                                       | YES         | NULL                 |       |            |

## vw_frota

Tipo: VIEW. Motor: None.

| Campo                | Tipo                              | Aceita NULL | Default              | Chave | Comentário |
| -------------------- | --------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                   | bigint(20) unsigned               | NO          | 0                    |       |            |
| unidade_id           | bigint(20) unsigned               | NO          | NULL                 |       |            |
| categoria_id         | smallint(5) unsigned              | NO          | NULL                 |       |            |
| nome                 | varchar(120)                      | NO          | NULL                 |       |            |
| placa                | varchar(10)                       | NO          | NULL                 |       |            |
| renavam              | varchar(20)                       | YES         | NULL                 |       |            |
| chassi               | varchar(30)                       | YES         | NULL                 |       |            |
| marca                | varchar(80)                       | YES         | NULL                 |       |            |
| modelo               | varchar(80)                       | YES         | NULL                 |       |            |
| ano_fabricacao       | smallint(5) unsigned              | YES         | NULL                 |       |            |
| ano_modelo           | smallint(5) unsigned              | YES         | NULL                 |       |            |
| capacidade           | smallint(5) unsigned              | NO          | NULL                 |       |            |
| quilometragem_atual  | decimal(12,1)                     | NO          | 0.0                  |       |            |
| situacao_cadastro    | enum('ativo','inativo','baixado') | NO          | 'ativo'              |       |            |
| observacoes          | text                              | YES         | NULL                 |       |            |
| criado_por           | bigint(20) unsigned               | NO          | NULL                 |       |            |
| versao               | bigint(20) unsigned               | NO          | 1                    |       |            |
| criado_em            | datetime(6)                       | NO          | current_timestamp(6) |       |            |
| atualizado_em        | datetime(6)                       | NO          | current_timestamp(6) |       |            |
| categoria            | varchar(60)                       | NO          | NULL                 |       |            |
| unidade              | varchar(150)                      | NO          | NULL                 |       |            |
| situacao_operacional | varchar(12)                       | NO          | ''                   |       |            |

## vw_monitoramento

Tipo: VIEW. Motor: None.

| Campo                 | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| --------------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| veiculo_id            | bigint(20) unsigned | NO          | 0                    |       |            |
| placa                 | varchar(10)         | NO          | NULL                 |       |            |
| nome                  | varchar(120)        | NO          | NULL                 |       |            |
| unidade_id            | bigint(20) unsigned | NO          | NULL                 |       |            |
| instalacao_id         | bigint(20) unsigned | YES         | NULL                 |       |            |
| identificador_externo | varchar(100)        | YES         | NULL                 |       |            |
| provedor              | varchar(100)        | YES         | NULL                 |       |            |
| capturado_em          | datetime(6)         | YES         | NULL                 |       |            |
| recebido_em           | datetime(6)         | YES         | current_timestamp(6) |       |            |
| latitude              | decimal(10,7)       | YES         | NULL                 |       |            |
| longitude             | decimal(10,7)       | YES         | NULL                 |       |            |
| velocidade_kmh        | decimal(7,2)        | YES         | NULL                 |       |            |
| situacao_comunicacao  | varchar(15)         | YES         | NULL                 |       |            |

## vw_multas_detalhadas

Tipo: VIEW. Motor: None.

| Campo                     | Tipo                                                                                                 | Aceita NULL | Default              | Chave | Comentário |
| ------------------------- | ---------------------------------------------------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                        | bigint(20) unsigned                                                                                  | NO          | 0                    |       |            |
| protocolo                 | varchar(40)                                                                                          | NO          | NULL                 |       |            |
| veiculo_id                | bigint(20) unsigned                                                                                  | NO          | NULL                 |       |            |
| unidade_id                | bigint(20) unsigned                                                                                  | NO          | NULL                 |       |            |
| numero_auto               | varchar(100)                                                                                         | YES         | NULL                 |       |            |
| orgao_autuador            | varchar(150)                                                                                         | YES         | NULL                 |       |            |
| ocorrido_em               | datetime(6)                                                                                          | NO          | NULL                 |       |            |
| precisao_ocorrencia       | enum('dia','instante')                                                                               | NO          | 'instante'           |       |            |
| fim_dia_ocorrencia_utc    | datetime(6)                                                                                          | YES         | NULL                 |       |            |
| data_vencimento           | date                                                                                                 | YES         | NULL                 |       |            |
| valor                     | decimal(13,2)                                                                                        | NO          | NULL                 |       |            |
| descricao                 | text                                                                                                 | NO          | NULL                 |       |            |
| situacao                  | enum('sem_responsavel','aguardando_comprovante','em_conferencia','quitada','contestada','cancelada') | NO          | 'sem_responsavel'    |       |            |
| responsabilidade_atual_id | bigint(20) unsigned                                                                                  | YES         | NULL                 |       |            |
| criado_por                | bigint(20) unsigned                                                                                  | NO          | NULL                 |       |            |
| versao                    | bigint(20) unsigned                                                                                  | NO          | 1                    |       |            |
| criado_em                 | datetime(6)                                                                                          | NO          | current_timestamp(6) |       |            |
| responsavel_id            | bigint(20) unsigned                                                                                  | YES         | NULL                 |       |            |
| motorista_id              | bigint(20) unsigned                                                                                  | YES         | NULL                 |       |            |
| viagem_id                 | bigint(20) unsigned                                                                                  | YES         | NULL                 |       |            |
| responsavel               | varchar(150)                                                                                         | YES         | NULL                 |       |            |
| placa                     | varchar(10)                                                                                          | NO          | NULL                 |       |            |
| veiculo                   | varchar(120)                                                                                         | NO          | NULL                 |       |            |
| ultima_versao_comprovante | int(10) unsigned                                                                                     | YES         | NULL                 |       |            |
| valor_quitado             | decimal(13,2)                                                                                        | YES         | NULL                 |       |            |
| pago_em                   | datetime(6)                                                                                          | YES         | NULL                 |       |            |

## vw_notificacoes_usuario

Tipo: VIEW. Motor: None.

| Campo              | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| ------------------ | ------------------- | ----------- | -------------------- | ----- | ---------- |
| usuario_id         | bigint(20) unsigned | NO          | NULL                 |       |            |
| lida_em            | datetime(6)         | YES         | NULL                 |       |            |
| oculta_em          | datetime(6)         | YES         | NULL                 |       |            |
| versao             | bigint(20) unsigned | NO          | 1                    |       |            |
| entregue_em        | datetime(6)         | NO          | current_timestamp(6) |       |            |
| id                 | bigint(20) unsigned | NO          | 0                    |       |            |
| chave_idempotencia | varchar(160)        | NO          | NULL                 |       |            |
| tipo               | varchar(60)         | NO          | NULL                 |       |            |
| titulo             | varchar(160)        | NO          | NULL                 |       |            |
| mensagem           | varchar(1000)       | NO          | NULL                 |       |            |
| caminho_destino    | varchar(255)        | YES         | NULL                 |       |            |
| solicitacao_id     | bigint(20) unsigned | YES         | NULL                 |       |            |
| viagem_id          | bigint(20) unsigned | YES         | NULL                 |       |            |
| multa_id           | bigint(20) unsigned | YES         | NULL                 |       |            |
| despesa_id         | bigint(20) unsigned | YES         | NULL                 |       |            |
| chamado_id         | bigint(20) unsigned | YES         | NULL                 |       |            |
| criado_em          | datetime(6)         | NO          | current_timestamp(6) |       |            |

## vw_permissoes_efetivas

Tipo: VIEW. Motor: None.

| Campo         | Tipo                               | Aceita NULL | Default | Chave | Comentário |
| ------------- | ---------------------------------- | ----------- | ------- | ----- | ---------- |
| vinculo_id    | bigint(20) unsigned                | NO          | 0       |       |            |
| usuario_id    | bigint(20) unsigned                | NO          | NULL    |       |            |
| perfil_id     | bigint(20) unsigned                | NO          | NULL    |       |            |
| unidade_id    | bigint(20) unsigned                | NO          | NULL    |       |            |
| modulo_codigo | varchar(40)                        | NO          | NULL    |       |            |
| acao_codigo   | varchar(40)                        | NO          | NULL    |       |            |
| alcance       | enum('proprios','unidade','orgao') | NO          | NULL    |       |            |
| delegavel     | tinyint(3) unsigned                | NO          | 0       |       |            |
| nivel_alcance | int(2)                             | NO          | 0       |       |            |

## vw_resumo_financeiro_veiculo

Tipo: VIEW. Motor: None.

| Campo                | Tipo                | Aceita NULL | Default | Chave | Comentário |
| -------------------- | ------------------- | ----------- | ------- | ----- | ---------- |
| veiculo_id           | bigint(20) unsigned | NO          | 0       |       |            |
| placa                | varchar(10)         | NO          | NULL    |       |            |
| unidade_id           | bigint(20) unsigned | NO          | NULL    |       |            |
| despesas_registradas | decimal(35,2)       | NO          | 0.00    |       |            |
| despesas_pagas       | decimal(35,2)       | NO          | 0.00    |       |            |
| multas_registradas   | decimal(35,2)       | NO          | 0.00    |       |            |
| multas_quitadas      | decimal(35,2)       | NO          | 0.00    |       |            |
| multas_pendentes     | bigint(21)          | YES         | NULL    |       |            |

## vw_solicitacoes_atuais

Tipo: VIEW. Motor: None.

| Campo                  | Tipo                                                                                        | Aceita NULL | Default              | Chave | Comentário |
| ---------------------- | ------------------------------------------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                     | bigint(20) unsigned                                                                         | NO          | 0                    |       |            |
| protocolo              | varchar(40)                                                                                 | NO          | NULL                 |       |            |
| solicitante_id         | bigint(20) unsigned                                                                         | NO          | NULL                 |       |            |
| unidade_id             | bigint(20) unsigned                                                                         | NO          | NULL                 |       |            |
| situacao               | enum('rascunho','aguardando_analise','ajustes_solicitados','aprovada','negada','cancelada') | NO          | 'rascunho'           |       |            |
| versao                 | bigint(20) unsigned                                                                         | NO          | 1                    |       |            |
| criado_em              | datetime(6)                                                                                 | NO          | current_timestamp(6) |       |            |
| revisao_id             | bigint(20) unsigned                                                                         | NO          | 0                    |       |            |
| numero                 | int(10) unsigned                                                                            | NO          | NULL                 |       |            |
| finalidade             | text                                                                                        | YES         | NULL                 |       |            |
| origem                 | varchar(255)                                                                                | YES         | NULL                 |       |            |
| destino                | varchar(255)                                                                                | YES         | NULL                 |       |            |
| saida_prevista         | datetime(6)                                                                                 | YES         | NULL                 |       |            |
| retorno_previsto       | datetime(6)                                                                                 | YES         | NULL                 |       |            |
| quantidade_passageiros | smallint(5) unsigned                                                                        | YES         | NULL                 |       |            |
| necessita_motorista    | tinyint(3) unsigned                                                                         | NO          | 1                    |       |            |
| veiculo_pretendido_id  | bigint(20) unsigned                                                                         | YES         | NULL                 |       |            |
| motorista_sugerido_id  | bigint(20) unsigned                                                                         | YES         | NULL                 |       |            |
| trajeto_planejado      | text                                                                                        | YES         | NULL                 |       |            |
| distancia_prevista_km  | decimal(12,3)                                                                               | YES         | NULL                 |       |            |
| enviado_em             | datetime(6)                                                                                 | YES         | NULL                 |       |            |
| solicitante            | varchar(150)                                                                                | NO          | NULL                 |       |            |
| unidade                | varchar(150)                                                                                | NO          | NULL                 |       |            |

## vw_trajetos_observados

Tipo: VIEW. Motor: None.

| Campo          | Tipo                          | Aceita NULL | Default                      | Chave | Comentário |
| -------------- | ----------------------------- | ----------- | ---------------------------- | ----- | ---------- |
| registro_id    | bigint(20) unsigned           | NO          | 0                            |       |            |
| fonte          | varchar(10)                   | NO          | ''                           |       |            |
| veiculo_id     | bigint(20) unsigned           | NO          | 0                            |       |            |
| viagem_id      | bigint(20) unsigned           | YES         | NULL                         |       |            |
| ocorrido_em    | datetime(6) /* mariadb-5.3 */ | NO          | '0000-00-00 00:00:00.000000' |       |            |
| latitude       | decimal(10,7)                 | NO          | 0.0000000                    |       |            |
| longitude      | decimal(10,7)                 | NO          | 0.0000000                    |       |            |
| velocidade_kmh | decimal(7,2)                  | YES         | NULL                         |       |            |
| descricao      | varchar(1000)                 | YES         | NULL                         |       |            |

## vw_ultima_posicao_rastreador

Tipo: VIEW. Motor: None.

| Campo                | Tipo                | Aceita NULL | Default              | Chave | Comentário |
| -------------------- | ------------------- | ----------- | -------------------- | ----- | ---------- |
| id                   | bigint(20) unsigned | NO          | 0                    |       |            |
| instalacao_id        | bigint(20) unsigned | NO          | NULL                 |       |            |
| veiculo_id           | bigint(20) unsigned | NO          | NULL                 |       |            |
| viagem_id            | bigint(20) unsigned | YES         | NULL                 |       |            |
| identificador_evento | varchar(120)        | NO          | NULL                 |       |            |
| capturado_em         | datetime(6)         | NO          | NULL                 |       |            |
| recebido_em          | datetime(6)         | NO          | current_timestamp(6) |       |            |
| latitude             | decimal(10,7)       | NO          | NULL                 |       |            |
| longitude            | decimal(10,7)       | NO          | NULL                 |       |            |
| velocidade_kmh       | decimal(7,2)        | YES         | NULL                 |       |            |
| precisao_metros      | decimal(9,2)        | YES         | NULL                 |       |            |
| ignicao              | tinyint(3) unsigned | YES         | NULL                 |       |            |

## vw_viagens_detalhadas

Tipo: VIEW. Motor: None.

| Campo                  | Tipo                                                      | Aceita NULL | Default              | Chave | Comentário |
| ---------------------- | --------------------------------------------------------- | ----------- | -------------------- | ----- | ---------- |
| id                     | bigint(20) unsigned                                       | NO          | 0                    |       |            |
| protocolo              | varchar(40)                                               | NO          | NULL                 |       |            |
| revisao_id             | bigint(20) unsigned                                       | NO          | NULL                 |       |            |
| reserva_id             | bigint(20) unsigned                                       | NO          | NULL                 |       |            |
| veiculo_id             | bigint(20) unsigned                                       | NO          | NULL                 |       |            |
| motorista_id           | bigint(20) unsigned                                       | NO          | NULL                 |       |            |
| situacao               | enum('programada','em_andamento','concluida','cancelada') | NO          | 'programada'         |       |            |
| saida_real             | datetime(6)                                               | YES         | NULL                 |       |            |
| retorno_real           | datetime(6)                                               | YES         | NULL                 |       |            |
| quilometragem_saida    | decimal(12,1)                                             | YES         | NULL                 |       |            |
| quilometragem_retorno  | decimal(12,1)                                             | YES         | NULL                 |       |            |
| saida_registrada_por   | bigint(20) unsigned                                       | YES         | NULL                 |       |            |
| retorno_registrado_por | bigint(20) unsigned                                       | YES         | NULL                 |       |            |
| observacao_saida       | text                                                      | YES         | NULL                 |       |            |
| observacao_retorno     | text                                                      | YES         | NULL                 |       |            |
| versao                 | bigint(20) unsigned                                       | NO          | 1                    |       |            |
| criado_em              | datetime(6)                                               | NO          | current_timestamp(6) |       |            |
| solicitacao_id         | bigint(20) unsigned                                       | NO          | 0                    |       |            |
| solicitante_id         | bigint(20) unsigned                                       | NO          | NULL                 |       |            |
| unidade_id             | bigint(20) unsigned                                       | NO          | NULL                 |       |            |
| saida_prevista         | datetime(6)                                               | YES         | NULL                 |       |            |
| retorno_previsto       | datetime(6)                                               | YES         | NULL                 |       |            |
| finalidade             | text                                                      | YES         | NULL                 |       |            |
| origem                 | varchar(255)                                              | YES         | NULL                 |       |            |
| destino                | varchar(255)                                              | YES         | NULL                 |       |            |
| trajeto_planejado      | text                                                      | YES         | NULL                 |       |            |
| placa                  | varchar(10)                                               | NO          | NULL                 |       |            |
| veiculo                | varchar(120)                                              | NO          | NULL                 |       |            |
| motorista              | varchar(150)                                              | NO          | NULL                 |       |            |
| distancia_real_km      | decimal(13,1)                                             | YES         | NULL                 |       |            |

## vw_vinculos_ativos

Tipo: VIEW. Motor: None.

| Campo         | Tipo                | Aceita NULL | Default | Chave | Comentário |
| ------------- | ------------------- | ----------- | ------- | ----- | ---------- |
| vinculo_id    | bigint(20) unsigned | NO          | 0       |       |            |
| usuario_id    | bigint(20) unsigned | NO          | NULL    |       |            |
| perfil_id     | bigint(20) unsigned | NO          | NULL    |       |            |
| unidade_id    | bigint(20) unsigned | NO          | NULL    |       |            |
| vigente_desde | datetime(6)         | NO          | NULL    |       |            |
| vigente_ate   | datetime(6)         | YES         | NULL    |       |            |

---

[Voltar ao índice](README.md) · Referência: 09/11/2026.
