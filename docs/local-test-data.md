# Dados de desenvolvimento local

Banco: `mysql-local / frota_pf_local`. Todos os dados são fictícios.

## Acesso

```bash
bash scripts/dev-local.sh
```

URL: http://localhost:8080. O comando preserva os dados quando o banco já está populado.

| Perfil | Identificador |
|---|---|
| Gestor | teste01 |
| Servidor | teste02 |
| Financeiro | teste03 |
| Administrador | teste04 |

A senha fica somente em `/tmp/frota-private/local-test-login.txt` (chmod 600). As credenciais MySQL ficam em `/tmp/frota-private/local.env`, fora do Git. Para preservar as credenciais após limpar `/tmp`, guarde os dois arquivos em diretório privado persistente e use `FLEET_LOCAL_PRIVATE_DIR` com esse caminho.

## Carga e validação

O script recusa outro host ou banco e não executa carga sobre usuários existentes. Os cadastros principais são inseridos antes dos dependentes. Relações cíclicas usam uma referência inicialmente nula e são concluídas depois. Checks, FKs e triggers permanecem habilitados.

Configuração única, versão instalada, módulos, ações, rotas e permissões mantêm os valores canônicos da aplicação; não são completados artificialmente. Catálogos editáveis têm 50 entradas. Tabelas derivadas e históricos podem superar 50 por causa dos relacionamentos.

Há 100 solicitações: 50 aprovadas com viagens e 50 aguardando análise com veículo/motorista indicados e datas futuras. O banco registra revisões adicionais para preparar esses cenários. As 50 viagens estão em andamento; pagamentos e autuações são apenas cenários fictícios. Os 46 perfis adicionais não possuem permissões concedidas. Sessões e recuperações sintéticas estão expiradas.

Validação: 167 FKs conferidas, 0 referências órfãs, 14 views consultáveis e 50 arquivos conferidos por tamanho e SHA-256.

| Tabela | Registros |
|---|---|
| `abastecimentos` | 50 |
| `acoes` | 24 |
| `anexos` | 50 |
| `arquivos` | 50 |
| `auditoria` | 150 |
| `categorias_chamado` | 50 |
| `categorias_despesa` | 50 |
| `categorias_veiculo` | 50 |
| `chamado_eventos` | 50 |
| `chamado_mensagens` | 50 |
| `chamados` | 50 |
| `checklist_itens` | 50 |
| `configuracao_sistema` | 1 |
| `controle_vinculos` | 50 |
| `despesa_eventos` | 50 |
| `despesas` | 50 |
| `documentos_veiculo` | 50 |
| `exportacao_campos` | 50 |
| `exportacao_registros` | 50 |
| `exportacoes` | 50 |
| `fornecedores` | 50 |
| `manutencao_itens` | 50 |
| `manutencoes` | 50 |
| `modulo_acoes` | 77 |
| `modulos` | 16 |
| `motoristas` | 50 |
| `multa_comprovantes` | 50 |
| `multa_conferencias` | 50 |
| `multa_eventos` | 50 |
| `multa_responsabilidades` | 50 |
| `multas` | 50 |
| `notificacao_destinatarios` | 50 |
| `notificacao_eventos` | 50 |
| `ocupacoes_agenda` | 100 |
| `pagamentos_despesa` | 50 |
| `pagamentos_multa` | 50 |
| `perfil_permissoes` | 119 |
| `perfis` | 50 |
| `permissoes` | 231 |
| `pneu_instalacoes` | 50 |
| `pneus` | 50 |
| `posicoes_manuais` | 50 |
| `posicoes_rastreamento` | 50 |
| `preferencias_usuario` | 50 |
| `rastreadores` | 50 |
| `recuperacoes_senha` | 50 |
| `relatorio_campos` | 69 |
| `reservas` | 100 |
| `rota_permissoes` | 17 |
| `rotas_sistema` | 16 |
| `sessoes` | 50 |
| `solicitacao_eventos` | 200 |
| `solicitacao_paradas` | 150 |
| `solicitacao_passageiros` | 150 |
| `solicitacao_revisoes` | 150 |
| `solicitacoes` | 100 |
| `unidades` | 50 |
| `usuario_perfis` | 50 |
| `usuarios` | 50 |
| `veiculo_rastreadores` | 50 |
| `veiculos` | 50 |
| `versoes_modelo` | 1 |
| `viagem_checklist_respostas` | 150 |
| `viagem_checklists` | 50 |
| `viagem_ocorrencias` | 50 |
| `viagens` | 50 |
