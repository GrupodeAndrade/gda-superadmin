# Anexar módulo no Super Administrador

Em **Projetos GDA → Anexar módulo**, o Super Administrador informa somente a URL pública HTTPS. A Plataforma descobre a identidade em `GET /gda/module` e registra ou atualiza o catálogo imediatamente.

O fluxo anterior por chave permanece disponível internamente como fallback técnico, mas não faz parte da interface normal. A integração oficial segue descoberta, handoff servidor a servidor, validação, confirmação e acesso federado descritos em `MODULE-ATTACHMENT-PROTOCOL.md`.

## Fallback legado por chave

1. **Validar módulo** consulta `/gda/integration/attach`, valida a resposta e apresenta os dados reais sem escrever no banco.
2. **Confirmar anexação** consulta `/gda/integration/attach/confirm`; somente depois do sucesso remoto registra o módulo em transação.

`ModuleAttachmentService` centraliza URL, chave, chamadas HTTP, contrato da resposta, compatibilidade, health, confirmação e duplicidade. `ModuleAttachmentClient` limita o transporte HTTPS e traduz falhas remotas em mensagens seguras. `ProjectConnectionRepository` é a única camada que grava `projects` e `project_connections`.

O registro preserva UUID, código, slug, nome, versão, versão da API, URL, tipo, data da anexação, status de integração, health, capabilities, compatibilidade e versão mínima GDA. Um projeto preexistente, como o Caixas, mantém o ID e os vínculos em `client_projects`.

Tentativas falhas são limitadas por usuário e IP. A auditoria registra somente contexto não secreto. A chave permanece na sessão pelo tempo necessário à confirmação, expira em dez minutos e nunca é armazenada no banco, enviada em URL, exibida ou registrada.

## Configuração

- `GDA_MODULE_ATTACH_TIMEOUT`: padrão `5`, entre 2 e 15 segundos;
- `GDA_MODULE_ATTACH_MAX_RESPONSE_BYTES`: padrão `262144`, entre 16 KiB e 1 MiB;
- `GDA_MODULE_ALLOWED_DOMAIN`: padrão `gda.app.br`.
- UUID v4, installation ID e par RSA são criados automaticamente e persistidos na tabela técnica `platform_identity`;
- a URL HTTPS usada pelo módulo no handoff reutiliza `APP_URL`, `config/local.php` ou `settings.app_url`;
- a chave privada permanece exclusivamente no backend e nunca é enviada à interface, logs ou APIs.
