# Protocolo oficial simplificado de módulos GDA

## Descoberta e anexação

O fluxo normal solicita apenas a URL HTTPS do módulo. O Super Admin consulta `GET {URL}/gda/module`, exige JSON e valida UUID v4, código, slug, versões, URL declarada, compatibilidade, capabilities e health. A URL declarada deve ser idêntica à informada. `health.level=ERROR` bloqueia a anexação.

O UUID é a identidade principal. Uma nova descoberta atualiza o mesmo registro e reutiliza projetos provisórios pelo código, como `gda-caixas`, sem alterar vínculos com clientes.

## Handoff e vínculo permanente

O módulo envia por servidor:

```http
POST {GDA_PLATFORM_PUBLIC_URL}/modules/pairing
Content-Type: application/json
```

```json
{"module_uuid":"uuid-v4","module_url":"https://modulo.gda.app.br","request_id":"uuid-v4","pairing_token":"segredo temporário"}
```

O callback não usa sessão nem CSRF porque é uma integração máquina a máquina. Sua segurança vem de HTTPS, política SSRF, correspondência entre UUID/URL previamente descobertos e validação remota imediata do segredo em `POST /gda/integration/pair/validate`. Depois, o GDA confirma em `POST /gda/integration/pair/confirm`, fornecendo UUID/URL estáveis, protocolo, installation ID e chave pública RSA.

O `pairing_token` nunca é persistido; somente SHA-256 entra no controle de replay. A credencial de máquina retornada pelo módulo é criptografada com a chave pública da própria plataforma antes de ser salva. A chave privada permanece exclusivamente no ambiente.

## Administrar sem novo login

O botão **Administrar** só é liberado quando a conexão está `integrado`. Antes de cada acesso, o GDA autentica a credencial de máquina em `GET /gda/integration/status`. Em seguida emite JWT RS256 com `iss`, `aud`, `role=global_admin`, `jti`, `iat` e `exp`, com validade máxima de cinco minutos.

O navegador envia o token por formulário POST para `{URL}/admin-gda/auth/gda`. O token nunca aparece na query string. O módulo valida assinatura, audiência, emissor, expiração e registra o hash do `jti`, bloqueando replay.

## Configuração

```text
GDA_PLATFORM_UUID=<uuid-v4 estável>
GDA_PLATFORM_PUBLIC_URL=https://admin.gda.app.br
GDA_PLATFORM_INSTALLATION_ID=<identificador estável>
GDA_PLATFORM_PRIVATE_KEY=<PEM RSA privado>
```

No módulo, `GDA_API_URL` deve apontar para a URL pública do Super Admin. Nenhum segredo deve ser versionado.

## Compatibilidade

Os endpoints anteriores `/gda/integration/attach` e `/gda/integration/attach/confirm`, além dos serviços de chave e manifesto, permanecem disponíveis como fallback técnico para módulos antigos. A interface normal não solicita chave.
