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
- **Processos auxiliares:** Redis, filas de e-mail, workers e agendador Laravel.
- **Arquivos:** armazenamento privado de anexos e exportações CSV/PDF.
- **Entrega:** imagens Docker e verificações automatizadas no GitHub Actions.

Os contratos de dependências estão em `composer.json`, `composer.lock`,
`package.json` e `package-lock.json`. O esquema do domínio está em
`database/sql/`; as configurações de execução estão em `config/` e nos
arquivos de Compose. O catálogo explica a responsabilidade desses arquivos e
como eles se relacionam com o restante do sistema.
