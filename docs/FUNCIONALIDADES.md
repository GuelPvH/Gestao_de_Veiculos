# Catálogo funcional

Gerado a partir de [config/screens.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/config/screens.php). Os estados listados são os valores previstos no catálogo. Sua presença na lista não significa que todas as transições entre eles sejam permitidas. A disponibilidade de cada ação depende do estado do registro, do alcance do usuário, das permissões e das regras dos serviços.

## Solicitações

- Endereço: `/solicitacoes`.
- Módulo de autorização: `solicitacoes`.
- Fonte principal: `vw_solicitacoes_atuais`.
- Criação prevista na configuração genérica: sim.

| Informação | Campo de origem |
|---|---|
| Protocolo | `r.protocolo` |
| Destino | `r.destino` |
| Saída prevista | `r.saida_prevista` |
| Situação | `r.situacao` |
| Finalidade | `r.finalidade` |
| Origem | `r.origem` |
| Retorno previsto | `r.retorno_previsto` |
| Passageiros | `r.quantidade_passageiros` |
| Solicitante | `r.solicitante` |
| Unidade | `r.unidade` |
| Trajeto planejado | `r.trajeto_planejado` |

Situações: `rascunho`, `aguardando_analise`, `ajustes_solicitados`, `aprovada`, `negada`, `cancelada`.

| Ação | Permissão |
|---|---|
| Editar solicitação (`edit`) | `editar` |
| Revisar envio (`send`) | `enviar` |
| Abrir nova revisão (`revision`) | `editar` |
| Aprovar solicitação (`approve`) | `aprovar` |
| Negar solicitação (`deny`) | `negar` |
| Solicitar ajustes (`adjust`) | `solicitar_ajustes` |

## Viagens

- Endereço: `/viagens`.
- Módulo de autorização: `viagens`.
- Fonte principal: `vw_viagens_detalhadas`.
- Criação prevista na configuração genérica: não.

| Informação | Campo de origem |
|---|---|
| Protocolo | `r.protocolo` |
| Veículo | `r.veiculo` |
| Destino | `r.destino` |
| Situação | `r.situacao` |
| Placa | `r.placa` |
| Motorista | `r.motorista` |
| Origem | `r.origem` |
| Saída prevista | `r.saida_prevista` |
| Retorno previsto | `r.retorno_previsto` |
| Saída registrada | `r.saida_real` |
| Retorno registrado | `r.retorno_real` |
| Km de saída | `r.quilometragem_saida` |
| Km de retorno | `r.quilometragem_retorno` |
| Distância percorrida | `r.distancia_real_km` |

Situações: `programada`, `em_andamento`, `concluida`, `cancelada`.

| Ação | Permissão |
|---|---|
| Registrar saída (`departure`) | `registrar_saida` |
| Registrar ocorrência (`occurrence`) | `registrar_ocorrencia` |
| Registrar retorno (`return`) | `registrar_retorno` |
| Cancelar viagem (`cancel`) | `cancelar` |

## Multas

- Endereço: `/financeiro/multas`.
- Módulo de autorização: `multas`.
- Fonte principal: `vw_multas_detalhadas`.
- Criação prevista na configuração genérica: sim.

| Informação | Campo de origem |
|---|---|
| Protocolo | `r.protocolo` |
| Placa | `r.placa` |
| Valor | `r.valor` |
| Situação | `r.situacao` |
| Descrição | `r.descricao` |
| Número do auto | `r.numero_auto` |
| Órgão autuador | `r.orgao_autuador` |
| Ocorrência | `r.ocorrido_em` |
| Vencimento | `r.data_vencimento` |
| Responsável | `r.responsavel` |
| Veículo | `r.veiculo` |
| Precisão da ocorrência | `r.precisao_ocorrencia` |

Situações: `sem_responsavel`, `aguardando_comprovante`, `em_conferencia`, `quitada`, `contestada`, `cancelada`.

| Ação | Permissão |
|---|---|
| Editar autuação (`edit`) | `editar` |
| Atribuir responsável (`assign`) | `atribuir_responsavel` |
| Enviar comprovante (`proof`) | `enviar_comprovante` |
| Conferir comprovante (`verify`) | `validar_pagamento` |
| Solicitar correção (`correct`) | `validar_pagamento` |
| Confirmar quitação (`settle`) | `validar_pagamento` |
| Contestar multa (`dispute`) | `contestar` |
| Cancelar multa (`cancel`) | `cancelar` |

## Frota

- Endereço: `/frota`.
- Módulo de autorização: `frota`.
- Fonte principal: `vw_frota`.
- Criação prevista na configuração genérica: sim.

| Informação | Campo de origem |
|---|---|
| Placa | `r.placa` |
| Veículo | `r.nome` |
| Categoria | `r.categoria` |
| Situação | `r.situacao_operacional` |
| Marca | `r.marca` |
| Modelo | `r.modelo` |
| Ano | `r.ano_modelo` |
| Capacidade | `r.capacidade` |
| Quilometragem | `r.quilometragem_atual` |
| Unidade | `r.unidade` |

Situações: `disponivel`, `em_viagem`, `manutencao`, `indisponivel`, `inativo`, `baixado`.

| Ação | Permissão |
|---|---|
| Editar veículo (`edit`) | `editar` |
| Bloquear período (`block`) | `gerenciar` |
| Liberar bloqueio manual (`release`) | `gerenciar` |

## Monitoramento

- Endereço: `/monitoramento`.
- Módulo de autorização: `rastreamento`.
- Fonte principal: `vw_monitoramento`.
- Criação prevista na configuração genérica: não.

| Informação | Campo de origem |
|---|---|
| Placa | `r.placa` |
| Veículo | `r.nome` |
| Comunicação | `r.situacao_comunicacao` |
| Última posição | `r.capturado_em` |
| Latitude | `r.latitude` |
| Longitude | `r.longitude` |
| Velocidade (km/h) | `r.velocidade_kmh` |
| Provedor | `r.provedor` |
| Fonte | `r.fonte` |

Situações: `transmitindo`, `sem_comunicacao`, `sem_rastreador`, `rastreador_inativo`.

| Ação | Permissão |
|---|---|

## Despesas

- Endereço: `/financeiro/despesas`.
- Módulo de autorização: `despesas`.
- Fonte principal: `vw_despesas_detalhadas`.
- Criação prevista na configuração genérica: sim.

| Informação | Campo de origem |
|---|---|
| Protocolo | `r.protocolo` |
| Veículo | `r.veiculo` |
| Valor | `r.valor` |
| Situação | `r.situacao` |
| Data da despesa | `r.data_despesa` |
| Categoria | `r.categoria` |
| Descrição | `r.descricao` |
| Fornecedor | `r.fornecedor` |
| Documento | `r.numero_documento` |
| Pagamento confirmado | `r.valor_pago` |
| Data de pagamento | `r.pago_em` |

Situações: `registrada`, `em_conferencia`, `aprovada`, `paga`, `cancelada`.

| Ação | Permissão |
|---|---|
| Editar despesa (`edit`) | `editar` |
| Enviar para conferência (`submit`) | `editar` |
| Conferir despesa (`verify`) | `aprovar` |
| Confirmar pagamento (`pay`) | `validar_pagamento` |
| Cancelar despesa (`cancel`) | `cancelar` |

## Abastecimentos

- Endereço: `/financeiro/abastecimentos`.
- Módulo de autorização: `despesas`.
- Fonte principal: `despesas`.
- Criação prevista na configuração genérica: sim.

| Informação | Campo de origem |
|---|---|
| Protocolo | `r.protocolo` |
| Placa | `v.placa` |
| Quantidade | `f.quantidade` |
| Valor | `r.valor` |
| Situação | `r.situacao` |
| Combustível | `f.combustivel` |
| Unidade de medida | `f.unidade_medida` |
| Preço unitário | `f.preco_unitario` |
| Quilometragem | `f.quilometragem` |
| Data | `r.data_despesa` |

Situações: sem enumeração no catálogo.

| Ação | Permissão |
|---|---|
| Editar abastecimento (`edit`) | `editar` |

## Manutenção

- Endereço: `/financeiro/manutencoes`.
- Módulo de autorização: `despesas`.
- Fonte principal: `manutencoes`.
- Criação prevista na configuração genérica: sim.

| Informação | Campo de origem |
|---|---|
| Protocolo | `r.protocolo` |
| Placa | `v.placa` |
| Tipo | `r.tipo` |
| Situação | `r.situacao` |
| Descrição | `r.descricao` |
| Início previsto | `r.inicio_previsto` |
| Fim previsto | `r.fim_previsto` |
| Quilometragem | `r.quilometragem` |
| Despesa vinculada | `d.valor` |

Situações: `planejada`, `em_execucao`, `concluida`, `cancelada`.

| Ação | Permissão |
|---|---|
| Editar manutenção (`edit`) | `editar` |
| Iniciar manutenção (`start`) | `editar` |
| Concluir manutenção (`complete`) | `editar` |
| Cancelar manutenção (`cancel`) | `cancelar` |

## Usuários

- Endereço: `/administracao/usuarios`.
- Módulo de autorização: `usuarios`.
- Fonte principal: `usuarios`.
- Criação prevista na configuração genérica: sim.

| Informação | Campo de origem |
|---|---|
| Identificador | `r.identificador` |
| Nome | `r.nome` |
| Unidade | `u.nome` |
| Situação | `r.ativo` |
| E-mail | `r.email` |
| Telefone | `r.telefone` |
| Cadastro | `r.criado_em` |
| Situação | `r.ativo` |

Situações: `0`, `1`.

| Ação | Permissão |
|---|---|
| Editar usuário (`edit`) | `editar` |
| Atribuir vínculo (`links`) | `delegar` |
| Revogar vínculo (`revoke`) | `editar` |
| Emitir código temporário (`temporary`) | `gerenciar` |

## Perfis e permissões

- Endereço: `/administracao/perfis`.
- Módulo de autorização: `perfis`.
- Fonte principal: `perfis`.
- Criação prevista na configuração genérica: sim.

| Informação | Campo de origem |
|---|---|
| Código | `r.codigo` |
| Perfil | `r.nome` |
| Situação | `r.ativo` |
| Descrição | `r.descricao` |
| Situação | `r.ativo` |

Situações: `0`, `1`.

| Ação | Permissão |
|---|---|
| Editar perfil (`edit`) | `editar` |
| Duplicar perfil (`duplicate`) | `criar` |
| Conceder permissão (`grant`) | `delegar` |
| Revogar permissão (`revoke`) | `delegar` |

## Rotas técnicas

- Endereço: `/administracao/rotas`.
- Módulo de autorização: `rotas`.
- Fonte principal: `rotas_sistema`.
- Criação prevista na configuração genérica: sim.

| Informação | Campo de origem |
|---|---|
| Nome | `r.nome` |
| Caminho | `r.caminho` |
| Método | `r.metodo_http` |
| Situação | `r.ativa` |
| Chave | `r.chave` |
| Módulo | `r.modulo_codigo` |
| Descrição | `r.descricao` |
| Implementação | `r.implementada` |
| Proteção | `r.protegida` |
| Visível no menu | `r.visivel_menu` |
| Ordem de exibição | `r.ordem` |
| Situação | `r.ativa` |

Situações: `0`, `1`.

| Ação | Permissão |
|---|---|
| Editar metadados (`edit`) | `editar` |

## Auditoria

- Endereço: `/administracao/auditoria`.
- Módulo de autorização: `auditoria`.
- Fonte principal: `auditoria`.
- Criação prevista na configuração genérica: não.

| Informação | Campo de origem |
|---|---|
| Evento | `r.evento` |
| Ator | `r.ator_nome_snapshot` |
| Entidade | `r.entidade` |
| Data | `r.criado_em` |
| Perfil do ator | `r.perfil_nome_snapshot` |
| Identificador do registro | `r.entidade_id` |
| Descrição do evento | `r.descricao` |

Situações: sem enumeração no catálogo.

| Ação | Permissão |
|---|---|

## Chamados

- Endereço: `/chamados`.
- Módulo de autorização: `chamados`.
- Fonte principal: `chamados`.
- Criação prevista na configuração genérica: sim.

| Informação | Campo de origem |
|---|---|
| Protocolo | `r.protocolo` |
| Assunto | `r.assunto` |
| Categoria | `c.nome` |
| Situação | `r.situacao` |
| Descrição | `r.descricao` |
| Abertura | `r.criado_em` |

Situações: `aberto`, `em_atendimento`, `aguardando_solicitante`, `resolvido`.

| Ação | Permissão |
|---|---|
| Responder chamado (`respond`) | `responder` |
| Atribuir atendimento (`assign`) | `atender` |
| Resolver chamado (`resolve`) | `resolver` |

## Telas especializadas

Além do catálogo: painel, agenda, conta, notificações, relatórios, pneus, configurações, apresentação e histórico de trajetos têm controllers ou rotas específicos. Consulte o manual de uso e [routes/web.php](https://github.com/GuelPvH/Gestao_de_Veiculos/blob/4ebe9d1ae80d0d6d8fa45b486155b27b4f6c124f/routes/web.php).


---
[Voltar ao índice](README.md) · Referência: 09/11/2026.
