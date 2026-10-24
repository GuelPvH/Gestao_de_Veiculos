# Organização do repositório

## Árvore principal

```text
.
├── .github/                 # Atualizações de dependências e integração contínua
├── app/
│   ├── Console/Commands/    # Comandos de esquema, outbox e saúde dos processos
│   ├── Http/
│   │   ├── Controllers/    # Coordenação HTTP por módulo
│   │   ├── Middleware/     # Sessão, vínculo, MFA, rotas e privacidade
│   │   └── Requests/       # Validação das entradas e ações
│   ├── Jobs/               # Entregas assíncronas da outbox
│   ├── Models/             # Identidade e adaptador legado de solicitações
│   ├── Providers/          # Registro de serviços e integração do framework
│   ├── Services/           # Regras, autorização, leituras e fluxos de negócio
│   └── Support/Debugbar/   # Telemetria de consultas sem bindings sensíveis
├── bootstrap/              # Inicialização e cache do framework
├── config/                 # Configuração por assunto e catálogo das telas
├── database/
│   ├── seeders/            # Seeder padrão sem carga institucional
│   └── sql/
│       ├── baselines/     # Versão inicial do modelo relacional
│       └── patches/       # Alterações e verificações incrementais
├── docker/                 # Apache, entrypoint, PHP e imagem de manutenção
├── docs/                   # Arquitetura, estrutura e catálogo de arquivos
├── lang/pt_BR/             # Mensagens de validação em português
├── public/                 # Entrada HTTP e recursos estáticos públicos
├── resources/
│   ├── css/               # Estilos e tokens visuais
│   ├── js/                # Interação, sessão e WebAuthn no navegador
│   └── views/             # Páginas e componentes Blade
├── routes/                 # Endpoints HTTP, comandos e agendamentos
├── scripts/
│   ├── database/          # Manutenção, conferência e dados locais
│   ├── qa/                # Contratos de integração e verificações de navegador
│   └── setup/             # Preparação da chave da aplicação
├── storage/                # Arquivos, sessões, logs e saídas geradas
└── tests/
    ├── Feature/           # Requisições, páginas, autorização e fluxos
    ├── Support/           # Fixtures, esquema de teste e workers auxiliares
    ├── Unit/              # Comportamentos isolados
    └── database/          # Testes Python da manutenção de banco
```

## Divisão interna dos serviços

| Diretório em `app/Services/` | Responsabilidade                                                                   |
| ---------------------------- | ---------------------------------------------------------------------------------- |
| `Admin/`                     | Usuários, vínculos, perfis, permissões delegáveis e ativação de rotas.             |
| `Auth/`                      | Identidade, senhas, sessão de negócio, WebAuthn, outbox e saúde da entrega.        |
| `Authorization/`             | Contexto de permissões e leitura conjunta do acesso e das rotas.                   |
| `Files/`                     | Upload, metadados, armazenamento e descarte de arquivos privados sem vínculo.      |
| `Finance/`                   | Despesas, pagamentos, abastecimentos, manutenção e pneus.                          |
| `Fines/`                     | Ciclo de multas e comprovantes.                                                    |
| `Notifications/`             | Estado das notificações de cada destinatário.                                      |
| `Read/`                      | Leitura genérica, indicadores, detalhes operacionais e referências de formulários. |
| `Reports/`                   | Consultas de monitoramento, exportação e renderização PDF.                         |
| `Requests/`                  | Consulta especializada e transições das solicitações de transporte.                |
| `Tickets/`                   | Mensagens, atendimento e anexos de chamados.                                       |
| `Trips/`                     | Saída, retorno e ocorrências de viagens.                                           |
| `Vehicles/`                  | Cadastro e disponibilidade dos veículos.                                           |

Repositórios de leitura constroem consultas já limitadas pelo alcance. Serviços
de workflow coordenam gravações e transições. A localização de uma classe em
`Models/` não implica que todo o domínio use Eloquent: o adaptador
`Solicitacao` preserva uma API de compatibilidade para o repositório de leitura.

## Organização das views

`resources/views/auth/` reúne o acesso, identidade e MFA. `components/layouts/`
define a composição comum das páginas. Os diretórios `navigation/`, `forms/`,
`tables/`, `ui/`, `auth/` e `fleet/` dentro de `components/` concentram partes
reutilizáveis.

As páginas de solicitações ficam em `gestor/solicitacoes/`, mas sua autorização
depende da ação e do vínculo ativo. `records/` oferece listagens, detalhes e
formulários compartilhados por módulos. Seus subdiretórios `forms/` e `extras/`
contêm, respectivamente, campos de edição/operação e blocos de detalhes.

`finance/tyres/` cuida das páginas de pneus; `tickets/` reúne chamados;
`reports/` contém a interface de exportação e a apresentação usada no PDF.
As demais pastas organizam painel, agenda, conta, configuração, monitoramento,
notificações, revisão visual e páginas de erro.

## Arquivos da raiz

Os arquivos `composer.*` e `package*.json` descrevem e fixam dependências.
`vite.config.js` conecta os assets ao Laravel. `phpunit.xml` e `phpstan.neon`
configuram testes e análise estática. `.editorconfig`, `.gitattributes`,
`.prettierrc.json` e `.prettierignore` padronizam a edição e a formatação.

`Dockerfile` descreve as imagens. Os três arquivos de Compose definem a
topologia padrão, o desenvolvimento e a alternativa de banco local. Os
arquivos `.env.example` e `.env.local.example` são modelos de configuração;
os valores reais de uma instalação não fazem parte da documentação.

## Código mantido e conteúdo gerado

| Local                                                 | Natureza e finalidade                                                   |
| ----------------------------------------------------- | ----------------------------------------------------------------------- |
| `app/`, `routes/`, `config/`, `resources/`            | Código e contratos mantidos pela aplicação.                             |
| `database/sql/`                                       | Fontes versionadas do modelo relacional e de sua evolução.              |
| `public/icons/`, `public/images/`, `public/licenses/` | Recursos visuais distribuídos com a interface e seus avisos de licença. |
| `vendor/`, `node_modules/`                            | Dependências instaladas a partir dos lockfiles; não são código próprio. |
| `public/build/`                                       | Bundles, fontes e manifest produzidos pelo Vite.                        |
| `bootstrap/cache/`, `storage/framework/`              | Caches, views compiladas e outros dados transitórios do framework.      |
| `storage/app/private/`, `storage/logs/`               | Dados privados e registros produzidos pela execução.                    |
| `artifacts/`, `playwright-report/`, `test-results/`   | Resultados locais ou de integração, quando gerados pelas ferramentas.   |

Os `.gitignore` dentro de diretórios de execução mantêm a estrutura necessária
no Git e excluem seu conteúdo gerado. O [catálogo](ARQUIVOS.md) descreve os
arquivos mantidos pelo repositório; dependências, caches e dados de uma instalação
não são enumerados como arquivos próprios do sistema.

Para uma alteração de fluxo, comece pelas rotas, pelo controller e pelo serviço
do módulo. Para uma alteração de exibição, consulte a view, seus componentes e
os assets. Para uma alteração de acesso ou persistência, acompanhe também
`AccessContext`, o catálogo de telas e o contrato SQL correspondente.

A execução de cache e filas no MySQL está documentada em [MYSQL-RUNTIME.md](MYSQL-RUNTIME.md).
A pasta `docs/database-model/` reúne o retrato estrutural, o diagrama e o dicionário do banco remoto.
