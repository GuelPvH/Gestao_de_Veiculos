# Instalação e operação

## Preparação

Prepare uma cópia de trabalho separada. Instale uma versão do PHP compatível com composer.json, o Composer e uma versão do Node compatível com o Vite definido no projeto. Como alternativa, utilize os estágios do Dockerfile. Crie o arquivo privado .env a partir dos exemplos e configure APP_KEY, URL, ambiente, banco e transporte de e-mail. Mantenha os valores privados fora do controle de versão. Em instalações existentes, preserve a chave da aplicação, pois os dados cifrados dependem dela.

O banco de domínio é mantido por SQL versionado e patches; DatabaseSeeder não cria dados institucionais. Não trate `artisan migrate` como instalador completo. Consulte database/sql/manifest.json, baselines/ e patches/ e os scripts explícitos de manutenção. A aplicação exige esquema 1.0.8; fleet:check-schema valida e não aplica patches.

## Docker

O arquivo Compose principal define a aplicação, worker-auth, worker-security e o agendador, que compartilham um volume de armazenamento. O arquivo de sobreposição destina-se ao desenvolvimento, enquanto compose.local.yml adapta a execução local. Ao preparar produção, confira quais arquivos Compose serão carregados para não aplicar configurações de desenvolvimento indevidamente.

Após preparar banco e configuração em um ambiente próprio:

```sh
docker compose -f docker-compose.yml build
docker compose -f docker-compose.yml up -d
```

Por padrão, o serviço web publica a porta 8080 na interface local. Para permitir acesso externo, configure o intermediário de acesso, a proteção TLS e a URL da instalação no .env.

## Verificação após implantação

```sh
php artisan fleet:check-schema
php artisan fleet:worker-health auth-mails
php artisan fleet:worker-health security-mails
php artisan fleet:delivery-health
```

Execute essas verificações no ambiente da aplicação. Confira as respostas de /up e /entrar, o acesso autorizado aos dados e a persistência dos arquivos. Reserve as operações de teste para homologação. O funcionamento dos processos não comprova a entrega de e-mails: verifique o transporte configurado, a caixa de saída e o resultado de cada entrega. Com MAIL_MAILER=log, as mensagens são registradas em vez de enviadas por SMTP.

## Filas e sessões

Cache e filas usam MySQL na configuração Compose; sessões ficam em arquivos no volume compartilhado. Workers consomem auth-mails e security-mails com timeout de 60 segundos e retry_after de 120 segundos. Scheduler executa relay e supervisão. Redis não é requisito obrigatório dessa configuração.

## Backup e recuperação

Mantenha cópias do banco, dos arquivos privados, da configuração e das chaves em local protegido. Antes de usar backup.sh, verify-backup.sh ou recover.sh, leia as instruções e as condições de execução de cada script. Teste a restauração em ambiente isolado e confira os relacionamentos, os anexos e a versão do esquema. Um erro de interface, por si só, não justifica restaurar ou recriar o banco de produção. Esta análise não executou cópia de segurança, migração ou recuperação, nem verificou as políticas de retenção do provedor.

## Diagnóstico

| Falha | Verificação |
|---|---|
| Aplicação não inicia | Versão do esquema, credenciais, conectividade e logs do entrypoint. |
| E-mails atrasados | Scheduler, workers, fila/outbox, transporte e health checks. |
| Sessões inconsistentes | Volume compartilhado, chave, permissões de arquivos e validade de sessão de negócio. |
| Página sem assets | Build Vite, manifesto e imagem implantada. |
| Erro de conflito | Recarregar registro; conferir versão e transições permitidas. |
| Download ausente | Metadados, arquivo privado, storage e autorização vigente. |
| Monitoramento desatualizado | Data e fonte da posição, rastreador e integração efetiva. |

Não cole .env, credenciais, tokens ou mensagens pessoais em relatórios públicos. Registre horário, módulo, identificador não sensível e erro reproduzível.


---
[Voltar ao índice](README.md) · Referência: 09/11/2026.
