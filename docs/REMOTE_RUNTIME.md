# Desenvolvimento com banco remoto

O banco remoto da equipe é o padrão de desenvolvimento. A conexão fica no `.env` privado; host, banco, usuário, senha e identificadores de infraestrutura reais não são publicados no Git. O banco local com dados fictícios continua disponível como opção individual.

## Configuração e início

Prepare dependências, chave e segredos conforme [SETUP.md](SETUP.md). Configure a conexão remota nos campos `DB_CONNECTION=mysql`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`. Mantenha `DB_URL` e `DB_SOCKET` vazios para que a aplicação use esses campos. O acesso remoto deve estar autorizado pelo servidor do banco.

Na primeira execução ou após alterar dependências:

```sh
bash scripts/dev-remote.sh --build \
  --expected-host HOST_DO_BANCO_REMOTO \
  --expected-database NOME_DO_BANCO_REMOTO
```

Nas próximas vezes, repita sem `--build`. Os argumentos devem corresponder ao alvo autorizado do `.env`; alternativamente, exporte as variáveis privadas `FLEET_REMOTE_DB_HOST` e `FLEET_REMOTE_DB_DATABASE` antes de executar o script.

O script seleciona somente `docker-compose.yml` e `docker-compose.override.yml`, mesmo se `COMPOSE_FILE` estiver definido para usar o banco local. Confere a configuração efetiva antes de consultar a versão do banco, exige esquema compatível com 1.0.5 e aguarda a saúde da aplicação, Redis, Vite, workers e scheduler por até 180 segundos. O phpMyAdmin acompanha a mesma conexão remota.

Aplicação: http://localhost:8080. phpMyAdmin: http://localhost:8081. Entre com uma conta autorizada já existente no banco da equipe; as contas fictícias descritas em [LOCAL_RUNTIME.md](LOCAL_RUNTIME.md) não pertencem ao remoto.

O desenvolvimento remoto conserva o MFA administrativo habilitado. A origem e o RP ID ainda não estão preenchidos na configuração privada: antes de usar o perfil Administrador, configure HTTPS, `FLEET_MFA_ORIGIN` e `FLEET_MFA_RP_ID` conforme [SECURITY_ROLLOUT.md](SECURITY_ROLLOUT.md). A conferência da conexão, do esquema e das rotas públicas não comprova o acesso administrativo por passkey.

Iniciar o desenvolvimento não importa SQL, executa seeds ou cria contas de teste. Não execute migrations padrão, `migrate:fresh`, `seed-local.php` ou a carga fictícia nesse banco. Uma instalação existente deve receber apenas os patches protegidos descritos em [SECURITY_ROLLOUT.md](SECURITY_ROLLOUT.md), com backup integral restaurado e verificado em outra instância.

## Banco local opcional

Para uma instalação individual com dados fictícios, configure uma pasta privada persistente e execute `bash scripts/dev-local.sh`, conforme [SETUP.md](SETUP.md). Esse caminho usa `compose.local.yml`, seleciona `mysql-local/frota_pf_local` e conserva a conexão remota do `.env`.

Para retornar ao remoto, execute `scripts/dev-remote.sh` com o host e o banco esperados. Os volumes e os dados do MySQL local são preservados. Os dois modos ocupam as mesmas portas da instalação quando alternados; confirme o modo desejado antes de editar dados pela aplicação ou pelo phpMyAdmin.

## Conferência de 11/10/2026

O servidor remoto foi autenticado em MariaDB 11.8.9 e sua versão inicial era 1.0.0. O backup foi restaurado em uma instância isolada. A cópia restaurada recebeu a atualização 1.0.5 e apresentou equivalência completa dos metadados com o esquema canônico: 74 tabelas, 14 views, 42 procedures e 124 triggers. Os três usuários e as oito solicitações existentes foram preservados nessa cópia.

O banco remoto foi atualizado e conferido em 1.0.5, com 74 tabelas, 14 views, 42 procedures e 124 triggers. A comparação completa de 11 classes de metadados com o esquema canônico não apresentou diferenças. A identidade do servidor correspondeu à do backup; os checksums das 62 tabelas originais sem transformação permaneceram iguais aos do backup, e os checksums de usuários e solicitações corresponderam aos da cópia atualizada e testada. Foram preservados os três usuários e as oito solicitações; as invariantes de credenciais e datas passaram.

As imagens da aplicação e do Vite foram reconstruídas. O script remoto concluiu a ativação com sucesso: aplicação, os dois workers, scheduler, Redis e Vite estão saudáveis. A conexão PHP efetiva confirmou o banco remoto em MariaDB 11.8.9, o esquema 1.0.5 e as 14 views consultáveis. A trava da manutenção foi liberada após a verificação do banco.

Aplicação, workers, scheduler e phpMyAdmin usam o alvo remoto autorizado. A autenticação MySQL a partir do phpMyAdmin passou com a credencial privada. A rota de saúde, a página de entrada, o phpMyAdmin e o Vite responderam HTTP 200; o painel sem sessão respondeu com redirecionamento HTTP 302. O cookie da aplicação é separado do modo local; os e-mails permanecem no transporte `log` e o MFA administrativo permanece habilitado. Nenhuma conta institucional foi usada em teste de login e nenhuma conta de teste foi criada no remoto.

As tentativas anteriores incluíram interrupções protegidas e restauração do backup; o bloqueio inicial por prazo de sessão de 20 segundos ocorreu antes de importar SQL. A hospedagem também interrompeu a leitura final temporariamente com `ERROR 1226`, por `MAX_CONNECTIONS_PER_HOUR=500`; a conferência foi retomada e concluída após o acesso voltar a ficar disponível. Esse limite de conexões por hora continua pertencendo ao serviço de hospedagem. O resultado confirmado do banco e do runtime está no [relatório sanitizado](verification/remote-database.json); backups, credenciais e provas de restauração permanecem no diretório privado externo ao Git. O volume e os dados do MySQL local opcional foram preservados.

Os registros que afirmam que o banco remoto não havia sido configurado, autenticado ou alterado em [VERIFICATION.md](VERIFICATION.md), [IMPLEMENTATION.md](IMPLEMENTATION.md) e [DATABASE.md](DATABASE.md) descrevem as auditorias anteriores e foram conservados como evidência histórica.

Os testes da aplicação continuam isolados: PHPUnit força SQLite em memória e transportes fictícios no ambiente e em `$_SERVER`; a integração MySQL aceita somente seu banco descartável em loopback. Esses testes não usam o banco da equipe.
