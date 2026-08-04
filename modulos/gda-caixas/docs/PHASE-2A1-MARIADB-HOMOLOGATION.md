# Fase 2A.1 — Homologação MariaDB

## Ambiente

A migração foi homologada em MariaDB 10.4.32, fornecido pela instalação local do XAMPP. Foi iniciada uma instância independente em porta própria, com datadir descartável no workspace e sem acesso aos bancos ou credenciais da instalação normal. Os bancos foram criados explicitamente com `utf8mb4_unicode_ci`.

## Cenários

- `gda_2a1_legacy`: schema v0.11.0, migração 002 e dados de caracterização em todas as áreas solicitadas;
- `gda_2a1_empty`: schema legado sem registros;
- `gda_2a1_partial`: `companies` incompleta, parte das colunas presente, índices ausentes e vínculos parciais;
- `gda_2a1_failure`: falha controlada causada por uma tabela de log deliberadamente incompatível, seguida de reparo e retomada.

Todos os bancos e dados eram artificiais e foram mantidos somente durante a execução da instância descartável.

## Problemas encontrados e correções

1. `app_settings` não possui coluna `id`; `company_id AFTER id` falhava. A migração agora usa `AFTER setting_key` nessa tabela.
2. `CREATE TABLE IF NOT EXISTS companies` não adaptava uma tabela parcial. A migração passou a detectar e adicionar cada coluna e índice obrigatório.
3. O charset padrão da instância era `latin1`. As tabelas criadas pela migração agora declaram `InnoDB`, `utf8mb4` e `utf8mb4_unicode_ci` explicitamente.
4. As entidades operacionais tinham `company_id` nullable. Após o backfill, ciclos, participantes, configurações e acessos móveis passam a `NOT NULL`; somente usuários permanecem nullable para suportar `global_admin`.
5. Foram adicionadas chaves estrangeiras idempotentes de `users`, `cycles`, `participants`, `app_settings` e `mobile_access_logs` para `companies`.

## Resultados

A primeira execução criou `legacy-default`, vinculou todos os dados legados, preservou o papel `client_admin`, criou índices, chaves e o registro de migração. A segunda execução terminou sem erros, duplicações ou alterações de contagem.

Contagens antes, após a primeira execução e após a segunda:

| Tabela | Antes | Primeira | Segunda |
|---|---:|---:|---:|
| users | 1 | 1 | 1 |
| cycles | 1 | 1 | 1 |
| participants | 2 | 2 | 2 |
| contributions | 1 | 1 | 1 |
| payouts | 1 | 1 | 1 |
| app_settings | 1 | 1 | 1 |
| message_queue | 1 | 1 | 1 |
| payment_receipts | 1 | 1 | 1 |

No banco vazio, nenhuma empresa, conta ou `global_admin` foi criada. No estado parcial, a empresa existente foi preservada, `legacy-default` foi criada somente para o ciclo sem vínculo, e a reexecução manteve duas empresas sem duplicação.

## Falha controlada

DDL no MariaDB provoca commits implícitos, portanto rollback transacional completo não é possível. A falha controlada foi registrada com mensagem sanitizada, sem credenciais. Os dados permaneceram presentes; após corrigir o bloqueio, a mesma migração retomou e pôde ser reexecutada. A segurança depende do caráter incremental e idempotente, não de rollback físico de DDL.

## Isolamento MariaDB

Com dois clientes, foram validados ciclos, participantes, contribuições, mensagens, configurações e backups. IDs de outra empresa retornaram não encontrado e não puderam ser removidos. Empresa inativa e usuário sem empresa foram bloqueados. `company_id` enviado por GET ou POST não alterou o contexto da sessão.

## Conclusão

A migração 003 está homologada para MariaDB 10.4.32 nos cenários cobertos. Antes de produção, permanece recomendada a execução do mesmo script em uma cópia anonimizada e descartável do banco do ambiente-alvo, especialmente se a versão do MariaDB/MySQL for diferente.
