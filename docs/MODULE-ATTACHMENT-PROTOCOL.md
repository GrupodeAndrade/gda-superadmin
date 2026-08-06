# Protocolo de anexação de módulos

## Chave e descoberta

A versão 1 usa `GDA1.<payload-base64url>.<token-secreto>`. O payload contém exclusivamente `v`, `module`, `endpoint` e `nonce`; ele localiza o módulo, mas não é fonte dos metadados cadastrados. A chave tem até 4096 bytes, o token deve possuir alta entropia e nunca é persistido ou registrado em logs.

Somente endpoints HTTPS na porta 443, no host `gda.app.br` ou em seus subdomínios, são aceitos. IPs, localhost, redes privadas, credenciais na URL, fragmentos, outros esquemas, portas arbitrárias e redirecionamentos são bloqueados. A mesma política é aplicada às URLs do manifesto.

## Requisição

`POST` com `Content-Type: application/json`, sem redirecionamentos, timeout padrão de 5 segundos e resposta máxima de 262144 bytes:

```json
{"protocol":"gda-module-attach","protocol_version":1,"attachment_key":"CHAVE_COMPLETA","platform":{"product":"gda-plataforma","version":"1.0.0","url":"https://admin.gda.app.br"},"challenge":"VALOR_ALEATORIO"}
```

O endpoint do módulo deve validar e consumir a chave uma única vez e devolver JSON com o mesmo protocolo, versão e challenge. `module` deve conter product, name, full_name, description, version, status, category, public_url, admin_url e icon. `capabilities` contém os booleanos `sso`, `client_provisioning`, `remote_updates` e `health_check`. `connection` contém `module_instance_id`, `attached_at` ISO e `key_consumed: true`.

Os campos opcionais `signature_algorithm`, `signature` e `public_key_fingerprint` reservam evolução para assinatura assimétrica. Nesta versão, HTTPS, domínio controlado, segredo de uso único e challenge fornecem a confirmação; a conexão não é apresentada como criptograficamente assinada.

## Erros e segurança

As respostas externas têm tipo e tamanho validados. Erros ao usuário são sanitizados. Chave, token, resposta bruta e cabeçalhos não entram em logs. Um transporte falso injetável permite testes sem enfraquecer a política de produção.

## Próximo passo no GDA Caixas

Implementar `POST /integracao-gda/anexar`, geração de chave de uso único com expiração, validação/consumo atômico, eco exato do challenge e emissão do manifesto acima. SSO, provisionamento, sincronização, atualização remota e health check permanecem fora desta fase.
