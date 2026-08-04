# Atualização manual v0.13.1

Atualização suportada: **v0.13.0 para v0.13.1**.

Esta versão corrige o próprio atualizador. Não aplique este pacote pelo atualizador da v0.13.0.

## Procedimento

1. Coloque a aplicação em janela de manutenção.
2. Faça uma cópia integral de todos os arquivos, incluindo `config/local.php`, `storage/`, uploads e comprovantes.
3. Exporte o banco de dados completo e confirme que o arquivo SQL pode ser aberto.
4. Envie `Sistema-de-Caixa-Atualizacao-Manual-v0.13.1.zip` para uma pasta temporária da hospedagem.
5. Extraia o ZIP em uma pasta limpa e confira `update.json` e os arquivos listados.
6. Remova `update.json` da pasta extraída e copie o restante diretamente sobre a raiz da aplicação, substituindo somente os arquivos correspondentes.
7. Não execute migração: a v0.13.1 não altera o banco e declara `migrations: []`.
8. Confirme que o topo de `CHANGELOG.md` registra v0.13.1 e que a Administração Global exibe essa versão.
9. Entre na Administração Global e abra **Atualizações**. Confirme as informações sobre backup, staging, migrações, manutenção e histórico.
10. Execute **Diagnóstico** e confirme banco, PHP, extensão ZIP, storage e migrações pendentes.
11. Em ambiente controlado, teste a criação de backup e confirme que `files.zip` abre e contém `index.php` e `config/local.php`, e que `database.sql` não está vazio.
12. Teste o upload de um pacote futuro somente em ambiente controlado antes de usar em produção.

## Validações funcionais

- login do cliente e da Administração Global;
- pagamentos e histórico;
- portal e links existentes dos participantes;
- PIX da Administração;
- upload e consulta de comprovantes;
- ausência de manutenção ou trava após uma atualização bem-sucedida.

## Rollback manual

Se houver falha, interrompa o acesso, restaure integralmente a cópia dos arquivos v0.13.0 e importe o banco exportado antes da atualização. Preserve uma cópia do estado com falha para diagnóstico. Confirme a versão v0.13.0 e os dois logins antes de reabrir o sistema.
