# Frota · PF

Sistema de gestão de veículos organizado como uma aplicação Laravel com páginas
renderizadas em Blade. Reúne solicitações de transporte, viagens e vistorias,
frota, agenda, monitoramento, despesas, abastecimentos, manutenção, pneus,
multas, chamados, relatórios e administração de acessos.

A aplicação atende aos perfis servidor, gestor, financeiro e administrador.
O acesso depende do vínculo ativo do usuário, das permissões por ação e do
alcance sobre os registros. As regras são verificadas no servidor.

## Documentação técnica

| Documento                                | Conteúdo                                                                    |
| ---------------------------------------- | --------------------------------------------------------------------------- |
| [Arquitetura](docs/ARQUITETURA.md)       | Camadas, fluxos, módulos, identidade, persistência e processos de execução. |
| [Estrutura de pastas](docs/ESTRUTURA.md) | Organização do repositório e limites de responsabilidade de cada diretório. |
| [Catálogo de arquivos](docs/ARQUIVOS.md) | Função e propósito de cada arquivo mantido pelo projeto.                    |

## Composição

- **Aplicação:** PHP e Laravel; controllers, validação de entrada e serviços de domínio.
- **Interface:** Blade, Bootstrap, JavaScript e CSS compilados pelo Vite.
- **Persistência:** MySQL com tabelas, views, procedures, funções e triggers versionados em SQL.
- **Processos auxiliares:** cache e filas no MySQL, workers de e-mail e agendador Laravel.
- **Arquivos:** armazenamento privado de anexos e exportações CSV/PDF.
- **Entrega:** imagens Docker e verificações automatizadas no GitHub Actions.

Os contratos de dependências estão em `composer.json`, `composer.lock`,
`package.json` e `package-lock.json`. O esquema do domínio está em
`database/sql/`; as configurações de execução estão em `config/` e nos
arquivos de Compose. O catálogo explica a responsabilidade desses arquivos e
como eles se relacionam com o restante do sistema.

## Formatação do código

Use o Laravel Pint para PHP e o Prettier para Blade, JavaScript, CSS, JSON, YAML e Markdown.
As configurações respeitam o `.editorconfig`; dependências, caches, arquivos gerados e lockfiles ficam fora do Prettier.

```bash
composer format
npm run format
```

Nos componentes `approved-route-map` e `reservation-map`, a formatação automática do JavaScript embutido fica desativada para preservar as expressões Blade e evitar mudanças de indentação a cada execução. O restante dos templates continua sendo formatado.

Para conferir sem alterar arquivos:

```bash
composer format:check
npm run format:check
```

## Análise estática

Execute `composer analyse` para analisar `app` e `routes` com PHPStan e Larastan.
O projeto usa o nível máximo (`max`, atualmente 10), com verificação dos tipos de configuração,
sem baseline ou regras de supressão.

## Execução com MySQL

O provisionamento de cache, filas e monitoramento está em [docs/MYSQL-RUNTIME.md](docs/MYSQL-RUNTIME.md).
O diagrama e o dicionário do banco estão em [docs/database-model/LEIA-ME.md](docs/database-model/LEIA-ME.md).
