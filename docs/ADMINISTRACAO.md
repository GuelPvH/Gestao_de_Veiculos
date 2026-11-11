# Administração de acesso

## Modelo de permissão

O acesso depende da identidade autenticada, da validade da sessão de negócio, do vínculo ativo e das permissões para o módulo e a ação. O sistema também verifica o alcance sobre os registros e a habilitação da rota. O nome do perfil, por si só, não concede acesso. Os níveis de alcance são: 0, sem acesso; 1, registros próprios; 2, registros da unidade; e 3, registros do órgão. A consulta de valores financeiros e de localização exige verificações específicas.

## Cadastrar e manter usuários

1. Acesse Administração → Usuários com permissão de criação.
2. Preencha os campos do cadastro, incluindo identificação e unidade.
3. Atribua o vínculo necessário; confirme perfil, unidade e vigência exibidos.
4. Entregue o código temporário individual ao titular por canal apropriado. Não o registre em issues, documentação ou chamados.
5. Oriente a definição da senha pessoal no primeiro acesso.
6. Para recuperação, confirme a identidade e emita nova credencial temporária. Isso encerra sessões anteriores.
7. Para retirar acesso, revogue o vínculo apropriado e confirme o resultado.

## Perfis e permissões

Em Perfis e permissões, crie ou duplique um perfil, se estiver autorizado. Ajuste a descrição e conceda apenas os módulos, as ações e os alcances necessários ao trabalho. A revogação de uma permissão altera o perfil; a revogação de um vínculo retira a associação do usuário. Confira o resultado com uma conta de homologação que represente o acesso pretendido.

## Rotas e configurações

As rotas técnicas registram caminho, método, módulo, implementação, proteção e visibilidade. O cadastro desses metadados não cria um ponto de acesso em PHP. Antes de habilitar um recurso, confira sua definição em routes/web.php. As configurações operacionais dispõem de ações próprias de atualização e exportação, com auditoria.

## Auditoria e sessão

Para reconstruir uma operação, consulte o ator, o perfil, o evento, a entidade, o identificador e a data registrados na auditoria. Restrinja a divulgação desses dados às pessoas autorizadas. O sistema carrega as permissões a cada requisição; uma aba aberta não comprova que a autorização continua válida.

O fluxo atual não usa passkey/MFA. A URL antiga `/seguranca/mfa` redireciona ao painel; campos históricos no SQL não significam recurso ativo.


---
[Voltar ao índice](README.md) · Referência: 09/11/2026.
