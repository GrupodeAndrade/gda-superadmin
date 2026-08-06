# Anexar projeto no Super Administrador

Em **Projetos GDA → Anexar projeto**, o Super Administrador informa somente a chave gerada pela Administração Global do módulo. A Plataforma valida a estrutura, aplica a política contra SSRF, consulta o endpoint e valida o manifesto oficial.

O registro ocorre em transação. Um product novo cria `projects`; o mesmo product e a mesma instância atualizam os metadados permitidos; outra instância para o mesmo product é bloqueada. O Caixas preexistente (`gda-caixas`) mantém o mesmo ID, histórico e vínculos em `client_projects`.

`project_connections` armazena instância, protocolo, endpoint, estado, capabilities, fingerprint opcional, usuário e datas. Não armazena chave nem token. Estados previstos: anexado, verificado, pendente, erro e revogado.

Depois do sucesso, o projeto aparece imediatamente no catálogo e na seleção de módulos dos clientes. Nenhum cliente é criado ou provisionado. A página de detalhes mostra a integração e identifica recursos futuros sem habilitá-los.

Tentativas falhas são limitadas por usuário e IP/sessão numa janela curta. A auditoria registra ação, projeto, product, instância, resultado e código sanitizado, nunca o segredo.

## Configuração

- `GDA_MODULE_ATTACH_TIMEOUT` — padrão `5`, intervalo de 2 a 15 segundos;
- `GDA_MODULE_ATTACH_MAX_RESPONSE_BYTES` — padrão `262144`, entre 16 KiB e 1 MiB;
- `GDA_MODULE_ALLOWED_DOMAIN` — padrão `gda.app.br`;
- `GDA_PLATFORM_PUBLIC_URL` — URL pública HTTPS da Plataforma.

Não existem segredos de integração em configuração versionada.
