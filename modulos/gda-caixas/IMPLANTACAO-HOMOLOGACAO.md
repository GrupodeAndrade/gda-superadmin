# Implantação da homologação v0.12.0

## Requisitos

- PHP 8.3 com `pdo_mysql`, `mbstring`, `fileinfo`, `json`, `session` e `zip`;
- MariaDB 10.6 ou superior, banco vazio em UTF-8 (`utf8mb4`);
- Apache com `mod_rewrite` e leitura de `.htaccess` habilitados;
- escrita para o usuário do PHP em `storage/`, `storage/receipts/` e `storage/updates/`.

## Instalação nova

1. Crie um banco e usuário MariaDB exclusivos, com privilégios sobre esse banco.
2. Extraia o ZIP diretamente na raiz do subdomínio. `index.php` deve ficar nessa raiz.
3. Copie `config/local.example.php` para `config/local.php` no servidor.
4. Informe em `config/local.php` a URL HTTPS, o banco e um token de ativação global longo e temporário.
5. Abra `index.php?page=install`. O instalador cria o schema, executa em ordem as migrações pendentes e solicita o primeiro Cliente Administrador.
6. O primeiro caixa é criado automaticamente. Revise nome, período, valor e regra de Administração pagante em Configurações.
7. Abra `/admin-gda/ativar`, informe o token e crie o primeiro Global Admin com senha de pelo menos 12 caracteres.
8. Após a ativação, remova o valor de `admin_global.activation_token` de `config/local.php`.
9. Teste login e logout nas duas áreas.

## Atualização

Faça backup de banco, `config/local.php`, `storage/receipts` e demais uploads. A primeira implantação da v0.12.0 é manual. Pacotes incrementais posteriores devem conter `update.json` na raiz, ter versão superior e podem incluir migrações em `database/migrations/`. O atualizador preserva `config/local.php`, cria backup antes de copiar arquivos e registra as migrações aplicadas.

Não use o ZIP completo de homologação na tela de atualizações: ele é um pacote de implantação, não um pacote incremental.

## Rollback

1. Coloque a aplicação em manutenção.
2. Restaure os arquivos do backup anterior, preservando o `config/local.php` correto do ambiente.
3. Restaure o dump MariaDB correspondente ao mesmo instante.
4. Restaure uploads de `storage/receipts` se necessário.
5. Valide versão, schema, login cliente, login global e integridade dos caixas antes de liberar o acesso.

Rollback parcial somente de arquivos não é seguro quando uma atualização aplicou migrações.

## Verificação inicial

- Dashboard do cliente abre com o caixa selecionado;
- Configurações mostra Dados do caixa, Regras do caixa, PIX e Alterar senha;
- criação, arquivamento, reativação e exclusão de caixa vazio funcionam;
- Administração Global abre em `/admin-gda`;
- `config/local.php` não é acessível nem está em pacotes distribuídos;
- `storage` é gravável e não expõe listagem pública.
