# Protocolo oficial de anexação de módulos

O Super Administrador recebe a URL pública HTTPS do módulo e uma chave temporária no formato `XXXX-XXXX-XXXX-XXXX`. A chave não contém endpoint ou manifesto e nunca é persistida ou registrada em logs.

## Validação

`POST {URL}/gda/integration/attach`

```json
{"attachment_key":"XXXX-XXXX-XXXX-XXXX"}
```

Essa chamada não consome a chave. O Caixas responde `200` com `ok: true`, `attachment_state: anexacao_pendente` e `module`, contendo `uuid`, `code`, `name`, `slug`, `description`, `version`, `api_version`, `integration_status`, `capabilities`, `minimum_gda_version`, `compatible_gda_versions`, `module_url` e `health`. A Plataforma valida HTTPS, domínio permitido, resolução pública, certificado, tamanho, Content-Type JSON, contrato, compatibilidade, disponibilidade e duplicidade.

A prévia validada fica somente na sessão autenticada por até dez minutos. A chave não aparece no HTML.

## Confirmação

`POST {URL}/gda/integration/attach/confirm`

```json
{"attachment_key":"XXXX-XXXX-XXXX-XXXX"}
```

Somente a confirmação consome a chave, de forma atômica e uma única vez. O Caixas responde `200` com `ok: true`, `attachment_state: anexado` e `module_uuid`. Após validar essa resposta e conferir o UUID, a Plataforma abre uma transação, atualiza ou cria o registro em `projects` e cria `project_connections`. Falhas anteriores à confirmação não alteram o catálogo.

Chave inválida, expirada, revogada ou já usada recebe `401` e `error: invalid_or_expired_key`; por segurança, o módulo não diferencia esses estados na resposta pública. Erros internos recebem `500`.

## Segurança

- somente HTTPS e hosts sob o domínio configurado;
- bloqueio de IPs privados, credenciais na URL, portas alternativas e redirecionamentos;
- verificação TLS obrigatória;
- timeout e limite de resposta;
- CSRF, autenticação de Super Administrador e limitação por usuário/IP;
- auditoria guarda código sanitizado, UUID, tipo, usuário, IP e data, nunca a chave.

Não fazem parte desta versão: GDA Identity, descoberta automática, OAuth, JWT, SSO, WebSocket, sincronização ou atualização automática.
