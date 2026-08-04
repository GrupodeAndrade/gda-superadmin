# Atualização v0.13.0

Atualização suportada: **v0.12.1 para v0.13.0**.

## Procedimento

1. Faça backup integral dos arquivos, do banco de dados e de `storage/`.
2. Não use o atualizador interno da v0.12.1 para esta implantação: a homologação identificou que o backup de arquivos dessa versão pode falhar silenciosamente.
3. Em janela de manutenção, extraia `Sistema-de-Caixa-Atualizacao-v0.13.0.zip` em uma pasta limpa, remova `update.json` e copie o restante sobre a raiz da aplicação. Não apague arquivos existentes e não substitua `config/local.php` nem `storage/`.
4. Execute a migração `database/migrations/006_participant_access_links.php` pelo mecanismo de migrações da aplicação ou por uma execução PHP administrativa controlada. A migração é idempotente. Confirme sua entrada em `schema_migrations`.
5. Recarregue a aplicação e confirme a versão **v0.13.0**.
6. No cliente, gere um link para um participante e confirme a abertura de `/p/{token}`.
7. Confirme que o portal mostra o PIX da Administração do caixa.
8. Envie um comprovante de teste e confirme que ele ficou associado somente ao participante e caixa do link.
9. Abra o link de um caixa arquivado e confirme o modo somente leitura.
10. Regenere o link e confirme que o endereço anterior foi invalidado imediatamente.

## Itens preservados

O procedimento manual deve preservar:

- `config/local.php`;
- banco de dados e registros existentes;
- `storage/`;
- uploads e comprovantes;
- backups existentes.

## Rollback manual

Se a atualização falhar, interrompa o uso do sistema. Na pasta `storage/updates/backups/`, localize o diretório criado para a versão anterior. Restaure `files.zip` na raiz e restaure `database.sql` no mesmo banco. Preserve uma cópia do estado com falha antes do rollback. Depois, confirme a versão v0.12.1 e os logins cliente e global.

A migração 006 apenas adiciona colunas e índices. Não remova essas colunas manualmente durante o rollback; a aplicação v0.12.1 as ignora.
