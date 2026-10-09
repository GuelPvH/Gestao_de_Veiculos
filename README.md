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


## Desenvolvimento com banco remoto

O padrão da equipe é usar o banco remoto configurado privadamente no `.env`.
O passo a passo completo para quem acabou de clonar, incluindo configuração e
chave da aplicação, está em [docs/SETUP.md](docs/SETUP.md).

Depois de preparar a configuração, na primeira execução:

```bash
docker compose --profile admin up --build -d
```

Para iniciar nas próximas vezes:

```bash
docker compose --profile admin up -d
```

Aplicação: http://localhost:8080. phpMyAdmin: http://localhost:8081.
O `docker-compose.override.yml` é carregado automaticamente, monta o código local
e inicia o Vite. Alterações em PHP, rotas e Blade aparecem ao atualizar a página;
CSS e JavaScript em `resources` usam a atualização automática do Vite.
Reconstrua as imagens ao alterar dependências ou o Dockerfile.

## Banco local opcional

O banco local com dados fictícios é uma alternativa para desenvolvimento individual,
separada da configuração padrão da equipe. Consulte a seção de banco local em
[docs/SETUP.md](docs/SETUP.md) e as contas e contagens em
[docs/local-test-data.md](docs/local-test-data.md).
