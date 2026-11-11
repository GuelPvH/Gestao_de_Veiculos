# Frota · PF — documentação do sistema

Aplicação web que reúne a gestão de solicitações de transporte, reservas, viagens, vistorias, frota, despesas e acessos de usuários.

## Comece pelo seu objetivo

| Objetivo | Documento |
|---|---|
| Aprender a usar o sistema | [Manual de uso](USO.md) |
| Consultar todas as telas, estados e ações | [Catálogo funcional](FUNCIONALIDADES.md) |
| Administrar usuários e permissões | [Guia de administração](ADMINISTRACAO.md) |
| Entender as camadas e decisões | [Arquitetura](ARQUITETURA.md) |
| Desenvolver e entender contratos | [Referência técnica](TECNICA.md) |
| Instalar, operar e diagnosticar | [Guia de operação](OPERACAO.md) |
| Entender o banco atual | [Banco remoto](BANCO.md) |
| Consultar campos e relações | [Dicionário atual](DICIONARIO.md) |
| Consultar rotinas e views | [Objetos SQL](OBJETOS-SQL.md) |
| Localizar arquivos | [Estrutura](ESTRUTURA.md) e [catálogo](ARQUIVOS.md) |
| Conferir fontes e limites | [Evidências](EVIDENCIAS.md) |

## Tecnologia

PHP ^8.3, Laravel ^13.17, Blade, Bootstrap, Vite, Leaflet e Dompdf. Persistência compatível com MySQL; servidor remoto consultado: MariaDB 11.8.9. Sessões em arquivos, cache e filas em banco na configuração Compose.