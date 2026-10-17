# Frota · PF

Gestão de veículos com Laravel 13, Blade e Bootstrap 5.3. A autenticação e as consultas usam o esquema MySQL canônico em português. Identidade, sessão, vínculo selecionado, ação e alcance são verificados no servidor; valores e localização possuem permissões independentes.

O frontend inclui quatro perfis, solicitações em quatro etapas, viagens/vistorias, frota/agenda, monitoramento, financeiro, administração, relatórios e chamados. As telas de operação apresentam revisão e confirmação, com gravação efetiva sujeita às permissões e regras do banco. Não há contas públicas de exemplo nem persistência institucional simulada no navegador.

## Executar

Siga [docs/SETUP.md](docs/SETUP.md) para dependências, configuração privada e chave; [docs/DATABASE.md](docs/DATABASE.md) para MySQL/phpMyAdmin, backup restaurado, manutenção e importação; e [docs/AUTHENTICATION.md](docs/AUTHENTICATION.md) para o administrador inicial e os fluxos de acesso. Não execute as migrations padrão do Laravel: o esquema vem de `database/sql/frota_pf_mysql.sql`.

## Avaliar a entrega

- [Componentes e aparência](docs/COMPONENTS.md)
- [Páginas, estados e apresentação de temas](docs/SCREEN_COVERAGE.md)
- [Resultados executados e testes pendentes](docs/VERIFICATION.md)
- [Matriz de finalização e lacunas de homologação](docs/FINALIZATION.md)
- [Branches, pacote e aplicação no Git](docs/DELIVERY.md)

Nas verificações históricas descritas em [docs/VERIFICATION.md](docs/VERIFICATION.md), o banco remoto foi preservado. O modo de desenvolvimento remoto atual e sua conferência estão em [docs/REMOTE_RUNTIME.md](docs/REMOTE_RUNTIME.md). O repositório contém somente exemplos de configuração, sem credenciais reais. A integração MySQL descartável é executada por `python3 scripts/qa/mysql-contract.py`, com dois containers criados exclusivamente pelo próprio comando.


## Desenvolvimento com banco remoto

O padrão da equipe é usar o banco remoto configurado privadamente no `.env`.
O passo a passo completo para quem acabou de clonar, incluindo configuração e
chave da aplicação, está em [docs/SETUP.md](docs/SETUP.md). Consulte também o
[guia do runtime remoto](docs/REMOTE_RUNTIME.md) para a alternância com o banco local opcional.

Depois de preparar a configuração, na primeira execução:

```bash
bash scripts/dev-remote.sh --build \
  --expected-host HOST_DO_BANCO_REMOTO \
  --expected-database NOME_DO_BANCO_REMOTO
```

Para iniciar nas próximas vezes:

```bash
bash scripts/dev-remote.sh \
  --expected-host HOST_DO_BANCO_REMOTO \
  --expected-database NOME_DO_BANCO_REMOTO
```

Use os valores de host e banco fornecidos pelo responsável. O script confere o
alvo e a versão do esquema antes de recriar os serviços; não importa SQL nem
dados fictícios. Ele seleciona os arquivos Compose do desenvolvimento remoto
explicitamente, mesmo quando `COMPOSE_FILE` estiver definido para o banco local.

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

## Segurança e atualização 1.0.5

Consulte [IMPLEMENTATION.md](docs/IMPLEMENTATION.md) para as mudanças e a validação desta entrega, e [SECURITY_ROLLOUT.md](docs/SECURITY_ROLLOUT.md) para atualizar o banco existente e configurar os serviços antes da publicação.
