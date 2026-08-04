# Fase 2A — Fundação multiempresa

## Auditoria inicial

A base legada era single-tenant. `users`, `cycles`, `participants`, `app_settings` e `mobile_access_logs` não possuíam empresa. As tabelas financeiras e operacionais referenciam ciclos, participantes ou usuários, mas alguns handlers buscavam entidades principais somente pelo ID. O maior risco era um ID previsível permitir leitura ou mutação cruzada. `ClientContext` apenas armazenava um ID opcional e a migração 002 adicionava somente `role`.

## Modelo e decisões

`companies` contém `id`, identificador futuro da GDA, nome, identificador interno único, status e timestamps. Não existe chave estrangeira para a Plataforma externa.

Vínculo direto:

- `users`: a empresa é opcional apenas para `global_admin`;
- `cycles`: raiz do agregado operacional;
- `participants`: impede reutilização acidental entre empresas;
- `app_settings`: chave composta por empresa e configuração;
- `mobile_access_logs`: telemetria operacional por empresa.

Vínculo indireto pelo ciclo:

- `cycle_participants`, `cycle_points`, `contributions`, `payouts`;
- `message_templates` personalizados, `message_queue`;
- `payment_receipts` e seus históricos de conferência.

`companies`, `schema_migrations`, `tenant_migration_log` e `app_updates` são globais. Templates com `cycle_id IS NULL` são padrões estruturais globais e somente podem ser sobrepostos no ciclo do cliente.

## Migração 003

`003_multi_tenant_foundation.php` detecta tabelas, colunas e índices antes de alterá-los. Dados sem empresa provocam a criação única de `legacy-default`; bases vazias não recebem empresa fictícia. Usuários legados viram `client_admin`, exceto um eventual `global_admin`. A associação é registrada de modo idempotente em `tenant_migration_log`.

DDL do MySQL/MariaDB causa commit implícito, portanto uma transação não oferece atomicidade real para todas as alterações. A migração evita `DROP TABLE`, `TRUNCATE`, remoção de registros e credenciais. O executor existente registra falha sem registrar parâmetros ou credenciais.

## Contexto e middleware

`ClientContext::fromAuthenticatedSession()` lê somente `$_SESSION['user']['company_id']`; GET e POST são ignorados. `companyId()` falha quando o contexto não existe. O middleware confirma autenticação, papel de cliente, empresa ativa e vínculo atual do usuário no banco. `global_admin` é bloqueado até existir um fluxo futuro de contexto autorizado; não há impersonação.

## Autorização

- `global_admin`: somente permissões `global.*`;
- `client_admin`: visualização, operação, administração e backup da própria empresa;
- `operator`: visualização e operação;
- papel ausente ou desconhecido: negação padrão.

Atualizações são estruturais e não podem ser executadas por clientes. O reset global com `TRUNCATE` foi substituído por uma exceção e não está exposto em rota.

## Backup

Backups ficam em `storage/backups/company-<id>/...`, incluem `company_id`, consultam o ciclo e os participantes no contexto atual e rejeitam restauração quando payload, ciclo ou participante pertencem a outra empresa. O backup estrutural completo do atualizador continua global.

## Testes

`tests/multi_tenancy_test.php` usa SQLite em memória, sem credenciais ou banco de produção. `tests/mariadb_migration_test.php` reproduz o schema v0.11.0 e homologa execução, reexecução, estado vazio, estado parcial, falha controlada e isolamento em MariaDB. Os resultados estão em `docs/PHASE-2A1-MARIADB-HOMOLOGATION.md`.

## Limitações e Fase 2B

- O código procedural ainda usa o ciclo atual como guarda indireta em vários handlers; a extração completa para repositories deve continuar.
- Não há seleção, impersonação ou telas globais.
- Restrições condicionais de papel/empresa são aplicadas na aplicação; uma estratégia de constraints compatível com as versões suportadas do MySQL será definida depois.
- Repetir a homologação em cópia anonimizada do ambiente-alvo quando sua versão de MySQL/MariaDB diferir da versão testada.
- Criar provisionamento, auditoria persistente de ações e testes HTTP end-to-end.
