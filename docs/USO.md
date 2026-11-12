# Manual de uso

## Acesso e primeiro uso

1. Solicite ao administrador o cadastro e o vínculo de acesso apropriado.
2. Acesse `/entrar`. Para contas novas, utilize CPF e código temporário individual fornecido pelo administrador.
3. Defina a senha pessoal quando exigida. A política exige pelo menos 12 caracteres, maiúsculas, minúsculas, números e símbolos; com bcrypt, o limite efetivo é 72 bytes.
4. Se houver seleção de perfil, escolha o vínculo correspondente ao trabalho. A unidade e o alcance desse vínculo determinam os registros disponíveis.
5. Abra o painel. Menus e ações dependem das permissões efetivas e da habilitação das rotas.

Na opção “Esqueci minha senha”, informe o CPF, o código temporário e a nova senha. Se precisar de outro código, solicite a reemissão ao administrador, que deverá confirmar sua identidade. A emissão encerra as sessões anteriores. Esse procedimento de recuperação não exige e-mail. Contas antigas podem conservar outro identificador até a atualização do cadastro.

Quando o sistema solicitar a confirmação da sessão, informe sua senha. Se a sessão tiver expirado ou sido revogada, entre novamente. Ao encerrar o trabalho, selecione Sair. A página de conta e as notificações estão em `/conta` e `/notificacoes`.

## Solicitar um veículo

1. Abra Solicitações → nova solicitação.
2. Descreva a finalidade; selecione origem e destino no recurso de localização para registrar também as coordenadas.
3. Informe saída e retorno previstos; o retorno precisa ser posterior à saída.
4. Declare de 1 a 100 passageiros. Quando informar nomes, use um por linha, com até 150 caracteres; a lista não pode exceder a quantidade declarada.
5. Selecione um veículo ativo e com capacidade para os passageiros mais o motorista.
6. Indique se necessita de motorista. Para dirigir pessoalmente, seu cadastro de motorista e sua habilitação devem estar ativos, compatíveis com o veículo e válidos durante todo o período.
7. Acrescente trajeto, observações e, se necessário, PDF, PNG ou JPEG de até 10 MiB.
8. Salve o rascunho, confira os detalhes e use Revisar envio. Salvar rascunho não equivale a obter aprovação.
9. Acompanhe a análise. Se houver ajustes solicitados, consulte a justificativa e use o fluxo de revisão disponível antes de reenviar.

Finalidade, trajeto e observações admitem até 3.000 caracteres; origem e destino, até 255. O veículo precisa pertencer ao alcance autorizado. A interface não permite escolher arbitrariamente outro motorista sugerido no rascunho.

## Analisar solicitações e consultar agenda

Abra a solicitação pendente e confira o período, a capacidade do veículo, a finalidade e a disponibilidade. Escolha Aprovar solicitação, Negar solicitação ou Solicitar ajustes e preencha os campos da ação escolhida. Confirme o resultado nos detalhes e consulte `/agenda` para visualizar a reserva. O servidor verifica novamente as condições de aprovação e a disponibilidade. Por isso, as informações de uma página aberta anteriormente podem estar desatualizadas.

## Executar uma viagem

1. Abra Viagens e localize a viagem programada.
2. Use Registrar saída. Informe horário já ocorrido, quilometragem não negativa e responda à vistoria.
3. Para cada item, escolha `ok`, `problema` ou `nao_aplicavel`. Um problema exige descrição; todos os itens obrigatórios devem ser respondidos e a vistoria precisa de ao menos uma resposta.
4. Durante a viagem, registre ocorrências: geral, desvio de trajeto, avaria, acidente ou atraso. A ocorrência deve ser posterior à saída e não estar no futuro.
5. No encerramento, use Registrar retorno com horário, quilometragem e vistoria. Confira a situação concluída.

A opção de cancelamento depende da autorização e do estado da viagem. Para uma viagem programada, a operação cancela a solicitação, a viagem e a reserva em conjunto, com justificativa e controle de versão. Se o sistema informar que o registro mudou, atualize a página e confira os dados antes de tentar novamente.

## Frota e monitoramento

Na área Frota, consulte a placa, a identificação do veículo, a categoria e a situação. Conforme suas permissões, cadastre ou edite veículos, bloqueie períodos e libere bloqueios manuais. A liberação de um bloqueio manual não elimina outras causas de indisponibilidade.

A área Monitoramento apresenta a situação de comunicação e, quando há permissão específica, a última posição e a velocidade. O Histórico de trajetos permite consultar os percursos. O projeto também contém um componente de mapa simulado; o movimento exibido nesse componente não comprova a recepção de telemetria real. Antes de tomar uma decisão operacional, confira a fonte e a data de captura.

## Financeiro

**Despesas:** cadastre veículo, categoria, data, valor positivo e descrição; acrescente fornecedor, documento e anexo. Envie para conferência. A conferência pode aceitar ou solicitar correção; nesse caso exige justificativa. Para confirmar pagamento, informe data, comprovante e confirmação. Cadastrar uma despesa não comprova pagamento.

**Abastecimentos:** informe combustível, unidade (litro, m³ ou kWh), quantidade, preço unitário, quilometragem e data. Quantidade e preço devem ser positivos. Registre tanque completo quando aplicável. A edição não permite trocar arbitrariamente o veículo do registro.

**Manutenção:** registre tipo preventiva, corretiva ou vistoria, período previsto e descrição. Use Iniciar manutenção para registrar início real e Concluir manutenção para informar fim real e, quando aplicável, próxima revisão por data ou quilometragem. Cancelamento exige justificativa e confirmação.

**Pneus:** em `/financeiro/pneus`, cadastre código, medida e despesa de aquisição. Instale vinculando veículo, posição, data e quilometragem. Ao remover, informe data, quilometragem e motivo. Descarte exige justificativa e confirmação. Consulte o histórico do pneu antes de registrar uma movimentação.

**Multas:** registre autuação; atribua responsável; envie comprovante; faça a conferência. As ações disponíveis incluem solicitar correção, confirmar quitação, contestar e cancelar. A presença de comprovante não significa automaticamente quitação. Consulte a situação e o histórico do registro.

## Chamados, notificações e relatórios

Para abrir um chamado, informe a categoria, o assunto e a descrição. Depois, acompanhe as respostas e os anexos. A equipe autorizada pode atribuir o atendimento, responder, registrar notas e resolver o chamado. As situações previstas são aberto, em atendimento, aguardando solicitante e resolvido.

Em Notificações, marque eventos como lidos ou oculte-os conforme necessário. Em Relatórios, escolha dados e campos disponíveis, confira a prévia e exporte CSV ou PDF. Downloads são privados e passam por nova autorização. Um relatório não amplia seu acesso a valores financeiros ou localização.

## Problemas frequentes

| Sintoma                       | Providência                                                                     |
| ----------------------------- | ------------------------------------------------------------------------------- |
| Menu ou ação ausente          | Confira o vínculo ativo e peça revisão da permissão/rota ao administrador.      |
| Registro mudou                | Atualize a página, confira a situação e refaça somente a ação ainda necessária. |
| Local não aceito              | Selecione origem e destino com coordenadas válidas.                             |
| Veículo não aparece           | Verifique unidade, alcance, situação cadastral e capacidade.                    |
| Não consigo dirigir           | Verifique cadastro de motorista, categoria e validade da CNH.                   |
| Anexo recusado                | Confira formato, tamanho e autorização sobre o registro.                        |
| Valor/localização não aparece | Essas informações exigem permissão específica.                                  |
| Download negado               | Verifique sessão e autorização atual sobre o registro.                          |

---

[Voltar ao índice](README.md) · Referência: 09/11/2026.
