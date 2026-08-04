# Atualizador do GDA Caixas

O atualizador aceita exclusivamente pacotes ZIP cujo `update.json` declare:

```json
{
  "product": "gda-caixas",
  "version": "1.0.1",
  "minimum_version": "1.0.0",
  "migrations": [],
  "database_changes": false,
  "files": []
}
```

Pacotes de qualquer outro produto são rejeitados, inclusive identificadores da linhagem anterior. Não existe compatibilidade de atualização a partir do projeto legado.

Antes de substituir arquivos, o serviço valida o manifesto, os caminhos declarados, a versão mínima e a coerência entre `database_changes` e `migrations`. Em seguida cria backup, ativa manutenção, instala os arquivos, executa apenas as migrações declaradas e registra o resultado em `app_updates`.

O arquivo `config/local.php` e os diretórios de runtime são protegidos contra substituição por pacote.
