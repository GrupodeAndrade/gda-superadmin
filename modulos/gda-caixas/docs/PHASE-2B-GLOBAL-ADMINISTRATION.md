# Fase 2B — Administração Global

## Auditoria inicial

Antes desta fase, `AdminGlobal` continha apenas uma policy mínima e documentação. `AuthService` aceitava qualquer usuário retornado pelo repository, a sessão não tinha `auth_scope`, o cookie era compartilhado e o roteador principal misturava a referência ao atualizador global com a área operacional. Isso permitia confusão de sessão e deixava o papel global sem uma superfície administrativa independente.

## Arquitetura

O entrypoint `admin-gda/index.php` possui roteador e bootstrap próprios. Ele inicializa `GDA_GLOBAL_SESSION` com path `/admin-gda`; a área operacional usa `GDA_CLIENT_SESSION` com escopo `client`. A sessão global guarda somente `user_id`, `role`, `auth_scope` e `authenticated_at`.

`RequireGlobalAdminMiddleware` revalida em banco que o usuário continua ativo, possui papel `global_admin`, não possui `company_id` e não excedeu a idade máxima da sessão. A policy usa lista explícita de capacidades e nega operação de cliente e impersonação. O backend verifica middleware e policy em todas as rotas protegidas.

## Rotas

- `GET|POST /admin-gda/login`
- `GET|POST /admin-gda/ativar`
- `POST /admin-gda/logout`
- `GET /admin-gda/dashboard`
- `GET /admin-gda/clientes`
- `GET /admin-gda/clientes/{id}`
- `POST /admin-gda/clientes/{id}/ativar`
- `POST /admin-gda/clientes/{id}/inativar`
- `POST /admin-gda/clientes/{id}/validar`
- `GET /admin-gda/caixas`
- `GET /admin-gda/atividades`
- `GET|POST /admin-gda/atualizacoes`
- `GET /admin-gda/diagnostico`
- `GET|POST /admin-gda/configuracoes`

Não existem rotas para lançar contribuições, pagamentos, alterar participantes ou entrar como cliente.

## Ativação e autenticação

A primeira conta global só pode ser criada quando não existe outro `global_admin`. A ativação exige CSRF, token não vazio de `config/local.php`, comparação com `hash_equals`, e senha de ao menos 12 caracteres. A conta recebe `company_id=NULL`, `role=global_admin` e hash `PASSWORD_DEFAULT`. Após a criação, a rota responde como inexistente. Nenhuma conta global é criada por migração ou instalador.

Login global rejeita `client_admin`, `operator`, conta inativa e qualquer global com empresa. Login e logout regeneram/destroem a sessão apropriada e são auditados. Falhas não revelam se o e-mail existe.

## Administração e diagnóstico

O dashboard apresenta métricas consolidadas reais, versões, atualização e atividades. Clientes podem ser visualizados, validados, ativados ou inativados sem exclusão. A visão de caixas é somente leitura. Detalhes mostram usuários, caixas, participantes, configurações operacionais, atividade, erros, migrações e status do ambiente sem abrir contexto operacional.

O diagnóstico mostra versões, extensões, storage, migrações pendentes e órfãos. Não lê nem renderiza configuração sensível, DSN, tokens, hashes, senhas ou stack traces.

## Atualizações e configurações

O atualizador legado é chamado apenas após middleware e capacidade `updates.manage`. Ele mantém validação de pacote, backup, preservação de `config/local.php` e histórico em `app_updates`, adicionando auditoria global de início, sucesso e falha.

`global_settings` armazena somente `module_name`, `maintenance_mode`, `update_policy` e `structural_limit`. Segredos não são aceitos na interface. `app_settings` continua por cliente.

## Migração 004 e auditoria

`004_global_administration.php` adiciona `users.active`, índice de papel/estado, `global_audit_logs` e `global_settings`. É incremental, idempotente, não cria usuários e não contém `DROP`, `TRUNCATE` ou remoção de dados.

A auditoria registra ativação, login, logout, falha de login, mudanças de status, validação, diagnóstico, atualizações e configurações. Metadata usa lista permitida e descarta senha, token, hash e segredos.

## Testes

`tests/global_administration_test.php` usa MariaDB descartável e validou 23 controles: ativação única, token inválido, papéis bloqueados, sessões cruzadas, global com empresa, middleware, policy, dashboard, clientes reais, inativação/reativação, CSRF, auditoria, diagnóstico e atualizador. Os testes multiempresa existentes continuam aprovados.

## Limitações e Fase 2C

- Não há impersonação, SSO, API, HMAC ou provisionamento.
- A validação de ambiente é local e síncrona; não integra a GDA Plataforma.
- O histórico de erros usa eventos globais sanitizados, sem sistema externo de observabilidade.
- Políticas de manutenção e atualização são armazenadas, mas sua automação não pertence a esta fase.
- A Fase 2C poderá definir integração externa e provisionamento sem reutilizar sessões de cliente.
