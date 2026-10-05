# Verificação executada

Resultados da implementação reconstruída em 05/10/2026. A auditoria inicial é de 04/10/2026; os resultados abaixo pertencem à execução atual. A implementação foi executada em ambiente de trabalho isolado, sem operar o VS Code ou Firefox da máquina do usuário.

| Verificação | Resultado real |
|---|---|
| PHP nativo | 8.3.6, com PDO MySQL/SQLite, DOM/XML, mbstring, intl, fileinfo e demais extensões necessárias |
| Composer | Instalação do lock; `validate --strict` passou |
| Auditorias de dependências | npm: zero vulnerabilidades; Composer: zero avisos de segurança e pacotes abandonados reportados |
| Pint / PHPStan | Passaram; PHPStan sem erros |
| PHPUnit | 90 casos: 82 passaram, 0 falhas, 8 ignorados por infraestrutura de daemon (MySQL real/Mailpit); 780 asserções executadas |
| Guardas Python da manutenção | 10 testes passaram, incluindo alvos, versão/charset, arquivos privados, dump alterado, definers, ordenação independente de collation e recuperação em falha controlada |
| Build Vite | Passou, Bootstrap 5.3.8, Vite 8.3.2; manifest e CSS/JS locais |
| Inicialização Laravel | Cache de views e rotas passou; 122 rotas listadas; caches de revisão limpos ao terminar |
| Workflow CI | actionlint 1.7.12 passou; download conferido pela hash já fixada no workflow |
| SQL recebido | Bytes e SHA-256 originais conferidos; não importado no servidor remoto |
| Operações de negócio | 100% dos fluxos POST implementados com procedures canônicas, concorrência otimista (versão), auditoria e arquivos privados |
| Git | Branch chore/runtime-validation ativa; histórico e autoria preservados conforme requisitos da entrega |

## Navegador, temas e tamanhos

Chromium 143.0.7499.0 executou a revisão de HTML renderizado pelo kernel Laravel, com fixtures isoladas de leitura. O navegador carrega assets locais; a viewport, o tema Bootstrap e o zoom variam sobre o documento carregado. Esse método verifica layout e comportamento dos componentes, mas não substitui um servidor MySQL nem valida a sessão de domínio.

Na execução final: **135 combinações de telas/estados**, **1080 verificações nos tamanhos normais** (1440×900, 768, 390 e 320 px, nos dois temas), **104 verificações com zoom de 200%**, **52 análises axe** e **8 interações de teclado/foco**. Não houve falha registrada, imagem ausente, asset recusado, ID duplicado ou erro JavaScript. Foram produzidas **74 capturas** de páginas representativas.

As análises axe usam WCAG 2 A/AA e 2.1 AA; zero ocorrências nesse conjunto não equivale a certificação integral de acessibilidade. A revisão também conferiu desktop/celular, logo, campos, estados, cores e apresentação dos temas. Tabelas largas podem rolar internamente; a página não apresentou rolagem horizontal. O erro inicial na tela de acesso em 320 px com zoom de 200% foi corrigido e a execução final repetiu esse caso.

Interações verificadas: abertura do offcanvas, Escape e retorno do foco, menu da conta por teclado, rejeição de etapa vazia, preenchimento do wizard com referência autorizada de veículo, foco da revisão e confirmação indisponível, Escape do modal e diálogo de descarte. A conta visível nas capturas é fixture de teste; não existe seed de conta pública no sistema.

## O que os testes comprovam

Testes HTTP exercitam CSRF real, limites de login antes da consulta, mensagens públicas e rotas protegidas. As fixtures de leitura materializam tabelas/views SQLite a partir das colunas do SQL original e usam os catálogos canônicos. Exercitam próprios/unidade/órgão, IDs diretos, ausência de valores e coordenadas sem permissão, filtros/paginação, seleção de campos de relatórios, notificações, notas internas, rotas desativadas e operações de negócio sem escrita. Esse recurso não emula procedures, triggers, constraints ou locks MySQL.

As ferramentas de manutenção receberam testes adversariais isolados. A aprovação pós-importação também exige 167 FKs e 84 checks nomeados, chaves primárias e collation das tabelas; esses checks no servidor aguardam a integração abaixo. Os números do esquema são contratos estáticos conferidos, não resultado de uma importação executada neste ambiente.

## Pendências materiais

- **MySQL real:** 7 testes de autenticação estão preparados e não foram executados. Um MySQL nativo 8.0.46 foi instalado/inicializado, mas a criação de socket UNIX foi bloqueada pelo ambiente. Docker não está disponível. Nenhum teste SQLite é apresentado como prova desses contratos.
- **Integração descartável:** `python3 scripts/qa/mysql-contract.py` exige Docker e clientes nativos; cria duas instâncias locais próprias para importar o SQL, conferir objetos, restaurar backup, provocar uma importação parcial, recuperar dados, testar privilégios e executar a autenticação. O comando e o job CI estão preparados, mas não executados aqui. Não aceitam a conexão remota do aplicativo.
- **Servidor remoto:** conexão TCP indisponível (errno 101). Não houve login SQL remoto, backup testado, limpeza, importação ou criação de administrador. A etapa destrutiva permanece bloqueada pelos pré-requisitos de DATABASE.md.
- **Docker/Compose:** arquivos e workflow verificados, mas build e inicialização dos containers ainda precisam de runtime Docker. phpMyAdmin foi configurado; não foi aberto contra o banco remoto.
- **SMTP e integrações operacionais:** não testados. E-mail permanece desabilitado; pagamentos, GPS externo, uploads e exportação operacional completa não fazem parte do backend desta fase.
- **GitHub:** o push com dry-run falhou por ausência de credenciais. As branches estão no bundle; CI remoto, PRs, merge e implantação não foram executados.

Não trate o aplicativo como homologado para produção antes da integração MySQL, do teste de acesso e da configuração efetiva do ambiente autorizado.

## Reproduzir

Com PHP e dependências instaladas:

```sh
vendor/bin/pint --test
vendor/bin/phpstan analyse --no-progress --memory-limit=1G
php vendor/bin/phpunit
python3 -m unittest discover -s tests/database -v
npm run build
php artisan view:cache
php artisan route:cache
```

Para revisão do navegador, crie dois diretórios privados fora do Git, defina `FLEET_UI_EXPORT_DIR` e `FLEET_UI_REPORT_DIR`, execute `php vendor/bin/phpunit --filter ReadPagesTest` e depois `node scripts/qa/browser.mjs`. Instale previamente o Chromium do Playwright (`npx playwright install chromium`) ou defina `FLEET_CHROMIUM_PATH` para um executável compatível. `FLEET_UI_FILTER` permite selecionar um subconjunto; os testes de interação requerem Painel do Gestor e formulário de solicitações do Servidor. Os HTMLs temporários contêm tokens CSRF de fixture e não devem ser publicados.

A integração real exige Docker, `mysql`, `mysqldump`, Python e PHP com PDO MySQL:

```sh
python3 scripts/qa/mysql-contract.py
```

Fontes oficiais consultadas para compatibilidade: [Laravel 13](https://laravel.com/docs/13.x/releases), [Bootstrap 5.3](https://getbootstrap.com/docs/5.3/getting-started/introduction/) e [phpMyAdmin](https://docs.phpmyadmin.net/en/latest/intro.html). Locks e versões estáveis existentes foram preservados; não se adotou documentação de versão dev como requisito de instalação.
