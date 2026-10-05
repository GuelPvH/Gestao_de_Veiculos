# Frota · PF

Gestão de veículos com Laravel 13, Blade e Bootstrap 5.3. A autenticação e as consultas usam o esquema MySQL canônico em português. Identidade, sessão, vínculo selecionado, ação e alcance são verificados no servidor; valores e localização possuem permissões independentes.

O frontend inclui quatro perfis, solicitações em quatro etapas, viagens/vistorias, frota/agenda, monitoramento, financeiro, administração, relatórios e chamados. Operações de negócio permitem preenchimento e revisão, com confirmação final indisponível nesta fase. Não há contas públicas de exemplo nem persistência institucional simulada no navegador.

## Executar

Siga [docs/SETUP.md](docs/SETUP.md) para dependências, configuração privada e chave; [docs/DATABASE.md](docs/DATABASE.md) para MySQL/phpMyAdmin, backup restaurado, manutenção e importação; e [docs/AUTHENTICATION.md](docs/AUTHENTICATION.md) para o administrador inicial e os fluxos de acesso. Não execute as migrations padrão do Laravel: o esquema vem de `database/sql/frota_pf_mysql.sql`.

## Avaliar a entrega

- [Componentes e aparência](docs/COMPONENTS.md)
- [Páginas, estados e apresentação de temas](docs/SCREEN_COVERAGE.md)
- [Resultados executados e testes pendentes](docs/VERIFICATION.md)
- [Branches, pacote e aplicação no Git](docs/DELIVERY.md)

O banco remoto permanece sem alteração. O repositório contém somente exemplos de configuração, sem credenciais reais. A integração MySQL descartável é executada por `python3 scripts/qa/mysql-contract.py`, com dois containers criados exclusivamente pelo próprio comando.
