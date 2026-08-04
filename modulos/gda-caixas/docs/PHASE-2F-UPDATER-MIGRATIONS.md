# Fase 2F — Atualizador e migrações automáticas

Versão: **0.13.1**

## Causa raiz

O backup criava `files.zip` dentro de `storage/updates` e iniciava uma iteração recursiva pela raiz. A exclusão era aplicada somente aos itens já visitados, sem impedir a descida no diretório do atualizador; assim, o ZIP podia tentar ler a si próprio durante o fechamento. O resultado não era validado adequadamente.

O fluxo antigo também ignorava a lista `migrations` do manifesto: varria todos os arquivos da pasta extraída e executava as migrações antes de substituir os arquivos. Arquivos ausentes ou que não retornavam callable não eram tratados de forma rigorosa.

## Solução

`MigrationRunner` valida uma lista explícita e ordenada de arquivos `NNN_nome.php`, confina cada arquivo à pasta de migrações, ignora registros já aplicados e exige retorno callable. O registro em `schema_migrations` ocorre somente depois do sucesso.

O backup agora prepara a lista antes de abrir o ZIP, poda `storage/updates`, lê cada arquivo antes de adicioná-lo, verifica o fechamento, reabre o ZIP e confirma arquivos obrigatórios. `config/local.php` e storage entram no backup, mas nunca são sobrescritos pelo pacote.

## Fluxo e falhas

O pacote é validado antes da trava. Depois, o sistema ativa manutenção, cria e valida os backups, extrai para staging, substitui arquivos, executa migrações e registra sucesso. Somente o sucesso remove manutenção e trava.

Se uma migração falhar após a substituição, ela não é registrada e a versão não é marcada como concluída no histórico. A manutenção e a trava permanecem. A mensagem pública informa a etapa sem revelar detalhes internos. O operador deve restaurar manualmente `files.zip` e `database.sql`; rollback automático ainda não é implementado.

## Homologação

Uma cópia limpa da v0.12.1 recebeu o pacote real v0.13.0 pelo mecanismo corrigido. A migração 006 criou colunas e índices, foi registrada e não foi repetida. Dados, configuração, storage, comprovantes e logins foram preservados; a geração e rotação do link funcionaram sem phpMyAdmin.

Também foram testados arquivo ausente, callable inválido, exceção, duplicação, caminhos relativos ascendentes e absolutos, nome malicioso, backup reaberto e estados de manutenção e trava.
