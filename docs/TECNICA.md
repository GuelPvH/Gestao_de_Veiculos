# Referência técnica

## Fluxo de execução

Uma requisição HTTP passa pelos componentes intermediários, pelo controlador e pela validação de entrada. Em seguida, o serviço ou repositório consulta o banco por meio do Query Builder ou de uma rotina armazenada. A resposta pode ser uma página Blade, dados em JSON, um redirecionamento ou um arquivo. O catálogo config/screens.php define parâmetros de várias consultas. As telas especializadas usam os mesmos controles de autorização.

## Contratos importantes

- Solicitações: RequestWorkflow envia formulário, coordenadas, passageiros e vínculo do anexo à procedure sp_criar_solicitacao_completa. Em falha, o serviço descarta arquivo recém-criado que permaneça sem vínculo.
- Viagens: TripWorkflow confere versão, estado, permissão de consulta e ação. Saída/retorno chamam procedures. Ocorrências usam transação com bloqueio de linha, incremento de versão e auditoria.
- Cancelamento de viagem programada também exige autorização para cancelar a solicitação e a versão correspondente.
- Manutenções usam token de versão hexadecimal de 64 caracteres nos formulários; não presumir que toda entidade usa contador inteiro.
- Datas de viagem são interpretadas no fuso configurado e convertidas para UTC. A conexão MySQL configura timezone +00:00.
- Arquivos usam armazenamento privado e autorização por registro; uma URL conhecida não dispensa permissão.
- Relatórios CSV/PDF revalidam acesso. Dompdf é configurado sem execução de PHP/JavaScript e sem carregamento remoto.
- A outbox usa reserva temporária e jobs por identificador; aceitação SMTP e gravação no banco não são uma operação atômica. Não há garantia de envio exatamente uma vez.

## HTTP e integrações

As rotas são de aplicação web com sessão e CSRF, não uma API pública REST documentada para terceiros. POSTs alteram estado; a edição de solicitação usa PATCH. Algumas consultas usam JSON, como status de sessão. Não reutilize formulários como API sem preservar autenticação, CSRF, autorização e concorrência.

## Desenvolvimento

Os requisitos e as versões das dependências estão definidos em composer.json/composer.lock e package.json/package-lock.json. Faça a instalação em uma cópia de desenvolvimento conectada a um banco isolado. `composer setup` instala dependências, prepara chave e compila assets; não provisiona sozinho o domínio institucional. `composer dev` inicia o fluxo definido pelo projeto.

Qualidade: `composer format:check`, `npm run format:check`, `composer analyse`, `composer test` e `npm run build`. Execute os testes em ambiente dedicado. Não conecte ao banco remoto operacional as suítes que preparam ou alteram dados de teste. Consulte phpunit.xml, tests/ e scripts/qa/ antes de executar. Nenhum desses testes foi executado nesta atividade documental.

## Onde alterar uma funcionalidade

Comece em routes/web.php; identifique controller e Form Request; leia o serviço responsável, autorização e SQL chamado; confira config/screens.php e as views correspondentes. Atualize testes de comportamento e contratos quando houver mudança. Alterar o formulário não dispensa a validação dos dados no servidor.

---

[Voltar ao índice](README.md) · Referência: 09/11/2026.
