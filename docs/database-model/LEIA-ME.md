# Modelo do banco remoto — gestão de frota

Snapshot de 16/10/2026 22:42 (Manaus): 73 tabelas, 691 campos, 178 FKs, 14 views, 42 procedures e 122 triggers.

- **gestao-frota-banco-remoto.drawio**: diagrama editável com 22 páginas, índice navegável e domínios.
- **visualizacao-banco.html**: consulta visual local com busca por tabela, navegação e zoom.
- **dicionario-de-dados.md**: campos, defaults, índices, checks e relacionamentos.
- **divergencias-banco-remoto.md**: diferenças estruturais em relação ao SQL local.
- **objetos-programaveis.md**: definições de views, procedures e triggers.
- **inventario-banco-remoto.json**: captura dos metadados, sem credenciais nem registros operacionais.
- **catalogo-relacionamentos.json**, **comparacao-estrutural.json**, **validacao-diagrama.json**: evidências de rastreabilidade e validação.
- **definicoes-banco-remoto.sql**: DDL capturada para consulta; não é migração para execução.
- **previas/**: prévias SVG e PNG de todas as páginas.

Para abrir no draw.io que está no navegador, use **Arquivo → Abrir de → Dispositivo** e selecione o arquivo `.drawio`. O modelo foi importado e salvo no editor oficial do draw.io no navegador integrado do Codex, como Gestão de frota - Banco remoto.drawio, com 22 páginas. A janela original do Firefox não foi alterada. A comprovação está em comprovacao-importacao-drawio.json e drawio-importado.jpg.

As FKs aparecem uma única vez, na página da tabela filha. Os cartões de referência não são tabelas adicionais. Cada célula de campo é editável; os conectores contêm o nome e as colunas da FK nos metadados e no tooltip. Use os identificadores R001…R178 para localizar o vínculo no catálogo. Linhas descontínuas são FKs físicas não identificadoras, conforme a legenda.

O banco remoto foi consultado somente para leitura. Nenhuma migração ou correção foi aplicada.
