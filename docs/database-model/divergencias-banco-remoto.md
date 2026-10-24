# Divergências do banco remoto

Comparação em 16/10/2026 22:42 Manaus. Remoto MariaDB 11.8.9; referência local executada em uma instância MySQL 8.4 sem rede.

SQL local de referência: SHA-256 `b093f7b047cfbe1990dce656f7d707c91093b6178d2d8d278ca8b095e2af134c`; 261587 bytes. A referência foi congelada antes da comparação.

A comparação é estrutural e textual normalizada. Diferenças de texto em rotinas/views não comprovam, sozinhas, mudança de comportamento. Os arquivos completos permitem revisão.

## Inventário

| Objeto | Local | Remoto |
|---|---:|---:|
| Tabelas | 74 | 73 |
| Colunas das tabelas | 699 | 691 |
| FKs | 182 | 178 |
| Views | 14 | 14 |
| Procedures | 42 | 42 |
| Triggers | 124 | 122 |

## Objetos ausentes ou adicionais

- tables: ausentes no remoto: veiculo_rastreadores; adicionais: nenhum.
- views: ausentes no remoto: nenhum; adicionais: nenhum.
- routines: ausentes no remoto: nenhum; adicionais: nenhum.
- triggers: ausentes no remoto: tg_instalacao_rastreador_bu, tg_posicao_bi, tg_preservar_veiculo_rastreadores_bd; adicionais: tg_posicao_sem_instalacao_bi.

## Chaves estrangeiras

- `fk_posicao_instalacao` em `posicoes_rastreamento`: local `{"columns": ["instalacao_id", "veiculo_id"], "target": "veiculo_rastreadores", "target_columns": ["id", "veiculo_id"], "delete": "RESTRICT", "update": "RESTRICT"}`; remoto `null`.
- `fk_vr_autor` em `veiculo_rastreadores`: local `{"columns": ["instalado_por"], "target": "usuarios", "target_columns": ["id"], "delete": "RESTRICT", "update": "RESTRICT"}`; remoto `null`.
- `fk_vr_rastreador` em `veiculo_rastreadores`: local `{"columns": ["rastreador_id"], "target": "rastreadores", "target_columns": ["id"], "delete": "RESTRICT", "update": "RESTRICT"}`; remoto `null`.
- `fk_vr_veiculo` em `veiculo_rastreadores`: local `{"columns": ["veiculo_id"], "target": "veiculos", "target_columns": ["id"], "delete": "RESTRICT", "update": "RESTRICT"}`; remoto `null`.

## Campos alterados

- `desafios_mfa.finalidade`: `{"COLLATION_NAME": {"local": "utf8mb4_0900_ai_ci", "remote": "utf8mb4_unicode_ci"}}`.
- `recuperacoes_mfa.justificativa`: `{"COLLATION_NAME": {"local": "utf8mb4_0900_ai_ci", "remote": "utf8mb4_unicode_ci"}}`.

Campos ausentes no remoto: veiculo_rastreadores.id, veiculo_rastreadores.instalado_em, veiculo_rastreadores.instalado_por, veiculo_rastreadores.rastreador_aberto, veiculo_rastreadores.rastreador_id, veiculo_rastreadores.removido_em, veiculo_rastreadores.veiculo_aberto, veiculo_rastreadores.veiculo_id.

Campos adicionais no remoto: nenhum.


## Checks e índices

- Check `anexos.ck_anexo_alvo`: local `(((((((((`revisao_id` is not null) + (`ocorrencia_id` is not null)) + (`checklist_id` is not null)) + (`despesa_id` is not null)) + (`manutencao_id` is not null)) + (`multa_id` is not null)) + (`chamado_id` is not null)) + (`mensagem_id` is not null)) = 1)`; remoto `(`revisao_id` is not null) + (`ocorrencia_id` is not null) + (`checklist_id` is not null) + (`despesa_id` is not null) + (`manutencao_id` is not null) + (`multa_id` is not null) + (`chamado_id` is not null) + (`mensagem_id` is not null) = 1`.
- Check `exportacao_registros.ck_er_alvo`: local `(((((((((((((`solicitacao_id` is not null) + (`viagem_id` is not null)) + (`veiculo_id` is not null)) + (`despesa_id` is not null)) + (`multa_id` is not null)) + (`chamado_id` is not null)) + (`usuario_id` is not null)) + (`perfil_id` is not null)) + (`rota_id` is not null)) + (`auditoria_id` is not null)) + (`posicao_rastreamento_id` is not null)) + (`posicao_manual_id` is not null)) = 1)`; remoto `(`solicitacao_id` is not null) + (`viagem_id` is not null) + (`veiculo_id` is not null) + (`despesa_id` is not null) + (`multa_id` is not null) + (`chamado_id` is not null) + (`usuario_id` is not null) + (`perfil_id` is not null) + (`rota_id` is not null) + (`auditoria_id` is not null) + (`posicao_rastreamento_id` is not null) + (`posicao_manual_id` is not null) = 1`.
- Check `veiculo_rastreadores.ck_vr_periodo`: local `((`removido_em` is null) or (`removido_em` > `instalado_em`))`; remoto `None`.
- Índice `veiculo_rastreadores.PRIMARY`: local `[('id', 0, None, 'BTREE')]`; remoto `None`.
- Índice `veiculo_rastreadores.fk_vr_autor`: local `[('instalado_por', 1, None, 'BTREE')]`; remoto `None`.
- Índice `veiculo_rastreadores.fk_vr_rastreador`: local `[('rastreador_id', 1, None, 'BTREE')]`; remoto `None`.
- Índice `veiculo_rastreadores.fk_vr_veiculo`: local `[('veiculo_id', 1, None, 'BTREE')]`; remoto `None`.
- Índice `veiculo_rastreadores.uq_vr_identidade`: local `[('id', 0, None, 'BTREE'), ('veiculo_id', 0, None, 'BTREE')]`; remoto `None`.
- Índice `veiculo_rastreadores.uq_vr_rastreador_aberto`: local `[('rastreador_aberto', 0, None, 'BTREE')]`; remoto `None`.
- Índice `veiculo_rastreadores.uq_vr_veiculo_aberto`: local `[('veiculo_aberto', 0, None, 'BTREE')]`; remoto `None`.

## Definições de objetos com mesmo nome

- Views: 3 definições com diferença textual após normalização lexical: vw_frota, vw_monitoramento, vw_ultima_posicao_rastreador.
- Procedures: 0 definições com diferença textual após normalização lexical: nenhuma.
- Triggers: 0 definições com diferença textual após normalização lexical: nenhuma.

## Dependências da tabela ausente

Nenhuma definição capturada menciona a tabela ausente.

## Particularidades do rastreamento

O campo posicoes_rastreamento.instalacao_id permanece no remoto, porém sua FK local não existe. A trigger adicional tg_posicao_sem_instalacao_bi rejeita posições futuras e verifica o período real da viagem quando ela é informada. Ela não restabelece a referência à tabela de instalações ausente. O diagrama mostra somente as FKs efetivas.


## Equivalências de engine

Foram identificados seis campos JSON do SQL local materializados como LONGTEXT utf8mb4_bin com JSON_VALID no MariaDB. São validações automáticas documentadas separadamente dos checks de negócio. A largura de exibição de inteiros, aspas de defaults e o indicador DEFAULT_GENERATED foram normalizados na comparação; o desenho preserva os tipos físicos remotos.


## Limites e decisões

A captura remota inicial e final teve o mesmo hash estrutural. Isso não impede modificações posteriores no banco. Nenhuma migração, correção ou criação de tabela foi executada no remoto. Definições divergentes foram preservadas para revisão em comparacao-estrutural.json. A abertura na janela original do Firefox foi bloqueada pelo ambiente. Posteriormente, a importação, a presença das 22 páginas, o salvamento e a renderização da página Frota e documentos foram confirmados no editor oficial do draw.io pelo navegador integrado do Codex. A validação local também cobre XML, referências de células, conteúdo, cardinalidades derivadas e geometria.
