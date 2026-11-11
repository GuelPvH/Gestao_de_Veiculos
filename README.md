# Frota · PF — documentação do sistema

Sistema de gestão de veículos organizado como uma aplicação Laravel com páginas
renderizadas em Blade. Reúne solicitações de transporte, viagens e vistorias,
frota, agenda, monitoramento, despesas, abastecimentos, manutenção, pneus,
multas, chamados, relatórios e administração de acessos.

A aplicação atende aos perfis servidor, gestor, financeiro e administrador.
O acesso depende do vínculo ativo do usuário, das permissões por ação e do
alcance sobre os registros. As regras são verificadas no servidor.


## Documentação técnica

| Objetivo | Documento |
|---|---|
| Aprender a usar o sistema | [Manual de uso](docs/USO.md) |
| Consultar todas as telas, estados e ações | [Catálogo funcional](docs/FUNCIONALIDADES.md) |
| Administrar usuários e permissões | [Guia de administração](docs/ADMINISTRACAO.md) |
| Entender as camadas e decisões | [Arquitetura](docs/ARQUITETURA.md) |
| Desenvolver e entender contratos | [Referência técnica](docs/TECNICA.md) |
| Instalar, operar e diagnosticar | [Guia de operação](docs/OPERACAO.md) |
| Entender o banco atual | [Banco remoto](docs/BANCO.md) |
| Consultar campos e relações | [Dicionário atual](docs/DICIONARIO.md) |
| Consultar rotinas e views | [Objetos SQL](docs/OBJETOS-SQL.md) |
| Localizar arquivos | [Estrutura](docs/ESTRUTURA.md) e [catálogo](docs/ARQUIVOS.md) |
| Conferir fontes e limites | [Evidências](docs/EVIDENCIAS.md) |

## Composição

- **Aplicação:** PHP e Laravel; controllers, validação de entrada e serviços de domínio.
- **Interface:** Blade, Bootstrap, JavaScript e CSS compilados pelo Vite.
- **Persistência.** Banco compatível com MySQL, com esquema versionado em `database/sql/`.
- **Filas e agendamento.** No Docker Compose, o banco armazena o cache e as filas. Serviços separados processam e-mails e executam o agendador do Laravel.
- **Arquivos:** armazenamento privado de anexos e exportações CSV/PDF.
- **Entrega:** Construção de imagens Docker e verificações automatizadas no GitHub Actions.

As dependências são declaradas em `composer.json` e `package.json`, 
com versões fixadas em `composer.lock` e `package-lock.json`. 
As configurações da aplicação ficam em `config/`, 
e os arquivos do Docker Compose definem os serviços e seus 
ambientes de execução. Para localizar os componentes e entender suas 
responsabilidades, consulte o catálogo de arquivos.

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

## Tecnologia

PHP ^8.3, Laravel ^13.17, Blade, Bootstrap, Vite, Leaflet e Dompdf. Persistência compatível com MySQL; servidor remoto consultado: MariaDB 11.8.9. Sessões em arquivos, cache e filas em banco na configuração Compose.