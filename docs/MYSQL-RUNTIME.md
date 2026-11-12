# Cache e filas no MySQL

A aplicação usa o MySQL configurado em DB_HOST/DB_DATABASE também para cache, limites de tentativas, bloqueios do agendador e filas de e-mail. As sessões permanecem em arquivos no volume compartilhado.

O Compose define CACHE_STORE=database, QUEUE_CONNECTION=database e FLEET_RECOVERY_QUEUE_CONNECTION=database. Os processos de execução das filas consomem auth-mails e security-mails, com tempo limite de 60 segundos, retry_after de 120 segundos e pausa de 5 segundos quando não há tarefas. Não há serviço Redis obrigatório.

Instalações existentes: antes de iniciar a aplicação, aplique database/sql/runtime-cache.sql no banco configurado; ele apenas cria cache/cache_locks se ausentes. As tabelas jobs e failed_jobs já pertencem ao esquema. O patch versionado 1.0.7 inclui o mesmo provisionamento para a atualização completa, após os patches anteriores. O provisionamento isolado não marca versões anteriores como aplicadas.

O patch 1.0.5.2 restaura a tabela, a integridade referencial e as triggers canônicas de instalações de rastreadores; ele interrompe a atualização se não for seguro preservar as posições existentes. O patch 1.0.8 atualiza a criação de solicitações para confirmar rascunho, coordenadas, passageiros e vínculo do anexo em uma única transação MySQL. Aplique as atualizações pelo fluxo versionado, que executa os patches pendentes em ordem, antes de executar a versão da aplicação que envia os novos parâmetros da procedure. O entrypoint exige a versão 1.0.8 via `fleet:check-schema` e recusa iniciar com um banco anterior.

Ao migrar uma instalação ativa, pause o agendador e os processos de execução das filas, confira as filas Redis, preserve tarefas pendentes e copie entradas de cache ainda válidas (incluindo contadores de tentativas) antes de parar Redis. Nunca descarte uma fila pendente. O volume Redis antigo pode ser mantido para recuperação.

Validação: fleet:check-schema, fleet:worker-health auth-mails, fleet:worker-health security-mails e fleet:delivery-health. O monitoramento usa o armazenamento de cache configurado e só coleta métricas Redis quando ele está explicitamente em uso.

O uso de cache e a consulta periódica às filas acrescentam consultas ao banco remoto. A mudança centraliza esses serviços, mas não garante menor latência.

## Validação da instalação ativa

Troca concluída em 21/10/2026 (America/Manaus). Foram provisionadas somente cache/cache_locks no banco remoto, sem aplicar patches de negócio anteriores ou alterar seus marcadores de versão. As filas Redis estavam vazias e três entradas de cache ainda válidas foram copiadas com seus prazos. O container Redis foi parado, mantendo seu volume para recuperação.

Verificados no MySQL remoto: leitura/escrita de cache, bloqueio exclusivo, limite de tentativas e consumo de uma tarefa isolada para outbox inexistente (sem envio de e-mail). Aplicação, processos de execução das filas e agendador saudáveis; fleet:delivery-health sem alertas com Redis parado. /up e /entrar responderam HTTP 200.

Suíte local: 215 testes, 1.694 asserções, 31 testes de integração ignorados por ausência dos ambientes dedicados. Os testes de banco locais usam SQLite isolado; a verificação de execução acima usou o MySQL remoto configurado.

---

[Voltar ao índice](README.md) · Referência: 09/11/2026.
