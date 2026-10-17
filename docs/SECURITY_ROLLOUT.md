# Aplicar e operar a versão 1.0.5

## Banco existente

O SQL completo é exclusivo de instalação vazia. Para uma instalação 1.0.2 existente, aplique os módulos 1.0.3 identidade, 1.0.4 solicitações e 1.0.5 sessão/MFA nessa ordem. Cada módulo tem pré/pós-verificação e checksum no manifesto. MySQL DDL confirma implicitamente; a recuperação de falha parcial depende de backup restaurável.

Interrompa todos os escritores, inclusive workers e consumidores antigos. Use os procedimentos de [DATABASE.md](DATABASE.md) para conferir destino, grants, backup e restauração em outra instância. A nova operação `upgrade` exige a mesma prova privada, confere que o banco não mudou desde o backup, verifica hashes e recusa versão desconhecida ou histórico descontínuo.

```bash
python3 scripts/database/database.py upgrade \
  --client-options "$arquivoOpcoes" --expected-database "$bancoAlvo" \
  --maintenance-confirmed --backup "$pastaPrivada/before.sql" \
  --proof "$pastaPrivada/restored.json"
```

A chamada mostra as versões planejadas. Após conferir o resultado, acrescente `--apply --marker "$pastaPrivada/upgrade-started.json"`. O marcador deve ser exclusivo, em diretório protegido fora do Git. A trava de manutenção permanece em uma conexão nativa dedicada até a conferência e eventual recuperação. Falha ao obtê-la não inicia alterações; perda da conexão ou da propriedade impede restauração automática. Os clientes não reconectam silenciosamente durante um lote. Em falha, a ferramenta tenta restaurar o snapshot testado e confere objetos/dados; mantenha manutenção até a recuperação estar confirmada.

O entrypoint da aplicação, workers e scheduler executa `php artisan fleet:check-schema` e recusa um banco sem os módulos necessários. Isso não aplica SQL nem cria contas. O patch revoga sessões existentes; usuários precisarão entrar novamente.

## Transporte e serviços

Configure privadamente a senha do Redis e os dados de banco/SMTP. Não substitua a APP_KEY de uma instalação em uso: outbox e sessões cifradas dependem dela. Para o destino público, configure HTTPS, cookies Secure, APP_ENV=production, APP_DEBUG=false, origem/RP ID de WebAuthn e proxies reais. SMTP requer transporte TLS e remetente/domínio homologados; MAIL_URL fica vazio.

```dotenv
REDIS_PASSWORD=SEGREDO_PRIVADO_UNICO
QUEUE_CONNECTION=redis
CACHE_STORE=redis
FLEET_RECOVERY_QUEUE_CONNECTION=redis
REDIS_QUEUE_RETRY_AFTER=120
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_TIMEOUT=15
FLEET_MFA_ORIGIN=https://SEU_HOST
FLEET_MFA_RP_ID=SEU_HOST
```

As flags de ativação, recuperação, confirmação e avisos continuam falsas por padrão. Construa e inicie serviços com a configuração privada; valide destinatários sintéticos, queue workers, scheduler, TLS e DNS antes de habilitar os recursos correspondentes. SMTP aceito não significa posse do endereço confirmada.

```bash
docker compose up --build -d app redis worker-auth worker-security scheduler
docker compose exec app php artisan config:cache
docker compose exec app php artisan fleet:dispatch-mail
docker compose exec app php artisan queue:monitor redis:auth-mails,redis:security-mails --max=100
docker compose exec app php artisan fleet:delivery-health
```

Os workers reiniciam automaticamente e têm parada graciosa de 75 segundos. Após deploy, recrie os serviços ou use `queue:restart` e confira que os processos receberam a nova configuração. O scheduler executa o relay a cada minuto. `fleet:delivery-health` emite JSON agregado e retorna código 1 em degradação: idade das pendências habilitadas, leases vencidos, falhas finais em 24 horas, memória/AOF Redis e ausência de atividade dos processos. O scheduler executa a verificação a cada cinco minutos, com avisos deduplicados por 15 minutos; os limites `FLEET_DELIVERY_*` e `FLEET_REDIS_MEMORY_ALERT_PERCENT` são configuráveis. Configure um monitor externo para esse estado e os logs. Healthchecks verificam atividade recente do worker/scheduler; backlog e falhas técnicas vão aos logs operacionais, de modo que um alerta externo possa funcionar sem depender do SMTP.

Depois de comprovar o transporte, habilite privadamente apenas as funções prontas:

```dotenv
FLEET_RECOVERY_QUEUE_ENABLED=true
FLEET_RECOVERY_MAIL_ENABLED=true
FLEET_ACTIVATION_MAIL_ENABLED=true
FLEET_VERIFICATION_MAIL_ENABLED=true
FLEET_SECURITY_MAIL_ENABLED=true
```

Limpe/recrie o cache de configuração e reinicie os workers. O Compose usa cache Redis compartilhado também para os limites de tentativas. Redis não publica porta no host. Faça backup/restauração do volume AOF e monitore memória; noeviction rejeita novas escritas quando a memória acaba. A outbox permite reconciliar pedidos ainda elegíveis depois de recuperar a fila. Uma instância de cache com política de eviction distinta deve ser separada da fila; bancos lógicos não isolam essa política.

## Usuários, MFA e contingência

Contas legadas não são automaticamente confirmadas. Planeje a obtenção de prova dos endereços, retenção de auditoria e atendimento de pessoas sem e-mail. Cadastros novos com vínculo coordenado podem receber convite individual. Contas pendentes sem transporte permanecem bloqueadas; não transforme isso em acesso liberado pela simples edição de uma flag.

O primeiro administrador usa `fleet:create-admin`, ativa o acesso pelo convite, entra novamente e cadastra uma passkey/chave de segurança em HTTPS. Homologue a recuperação com duas aprovações independentes antes de depender dela. Perda de todos os fatores do único administrador requer contingência operacional fora do fluxo normal com dois responsáveis independentes; não existe bypass público.

No GitHub, configure proteção da branch main e exija os checks do workflow. A criação do job Required checks não altera a proteção por si só. Configure grants mínimos de runtime, conta de manutenção separada, TLS MySQL quando remoto, retenção e alertas externos. Essas configurações pertencem ao ambiente de destino e não são comprovadas apenas pelos arquivos do projeto.
