# GDA Plataforma — Super Administrador

Versão 1.0.0, aplicação PHP 8.3 + MySQL/MariaDB sem framework e sem dependências externas. O pacote pode ser extraído diretamente na raiz de um domínio, subdomínio ou subdiretório Apache/cPanel.

## Requisitos

- PHP 8.3 ou superior;
- extensões `pdo`, `pdo_mysql`, `mbstring`, `json`, `session` e `zip`;
- MySQL 8+ ou MariaDB 10.6+;
- Apache com `mod_rewrite` e suporte a `.htaccess`;
- HTTPS em produção;
- escrita em `config/` e em todas as subpastas de `storage/`.

## Estrutura

- `app/Controllers`: controladores de autenticação, clientes, projetos, logs, configurações e atualizações;
- `app/Services`: banco PDO, autenticação, CSRF, logs, roteamento e atualização;
- `app/Views`: telas e layouts;
- `bootstrap/`: inicialização e autoload;
- `config/`: configuração padrão e `local.php` gerado pelo instalador;
- `database/schema.sql`: schema completo;
- `install/`: instalador web;
- `public/assets`: CSS e JavaScript sem build;
- `routes/web.php`: rotas amigáveis;
- `storage/`: backups, cache, logs, arquivos temporários e atualizações.

## Instalação local

1. Crie um banco vazio em MySQL/MariaDB.
2. Configure o DocumentRoot para a raiz do projeto, habilite `mod_rewrite` e `AllowOverride All`.
3. Garanta escrita em `config/` e `storage/`.
4. Acesse a URL no navegador e conclua o instalador.
5. Entre com o Super Administrador criado no formulário.

Não há credenciais padrão. O instalador cria a conta informada pelo responsável.

### Docker e Coolify

Em containers, configure a conexão em runtime com `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` e `DB_PASSWORD`. `APP_URL` é opcional e deve conter a URL pública HTTPS. As variáveis têm precedência sobre `config/local.php`; não versione `.env`, senhas ou o arquivo local.

Com as variáveis completas, a aplicação consulta o banco apenas para reconhecer seu estado. O instalador só é liberado quando o banco não contém tabelas. Schema parcial, ausência de Super Administrador ativo, credenciais recusadas ou indisponibilidade do banco bloqueiam a instalação sem expor os dados de conexão. Instalações tradicionais continuam usando `config/local.php`.

## Instalação no cPanel

1. Em **MultiPHP Manager**, selecione PHP 8.3 para `admin.gda.app.br`.
2. Em **MySQL Databases**, crie banco, usuário e associe o usuário ao banco com todos os privilégios.
3. Para instalação integral, envie `dist/GDA-Plataforma-v1.0.0-Source.zip` à raiz do subdomínio. Para atualizar uma instalação 0.1.0 pelo painel, use `dist/GDA-Plataforma-v1.0.0-Update.zip`.
4. Extraia o ZIP ali. `index.php`, `.htaccess`, `app/` e demais itens devem ficar diretamente na raiz.
5. Defina `config/` e `storage/` como graváveis pelo PHP (normalmente 750/770 para diretórios; varia conforme a hospedagem).
6. Confirme as extensões PDO MySQL, mbstring e ZIP no seletor de PHP.
7. Acesse `https://admin.gda.app.br`, preencha o instalador e remova permissões excessivas; mantenha `config/` gravável somente se atualizações futuras precisarem alterar configuração.
8. Abra **Configurações → Diagnóstico do sistema** e confirme todos os itens.

O `.htaccess` mantém `/public` fora da URL e bloqueia acesso web a código, configuração, banco, storage e arquivos sensíveis.

## Banco e dados iniciais

O instalador executa `database/schema.sql`, cria o Super Administrador, as configurações iniciais e o módulo **Caixas** (`gda-caixas`) em estado de implantação. Não cria clientes fictícios. Tabelas: `users`, `clients`, `projects`, `client_projects`, `settings`, `activity_logs`, `system_updates`, `migrations` e `login_attempts`.

O catálogo de projetos relaciona módulos a clientes por `client_projects`. Consulte [docs/MODULE-CAIXAS.md](docs/MODULE-CAIXAS.md) para o cadastro oficial e as URLs administrativas, e [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) para a arquitetura.

## Segurança

Senhas usam `password_hash()`/`password_verify()`. Toda mutação exige token CSRF. Consultas usam PDO com prepared statements, saídas são escapadas, o ID de sessão é regenerado no login e cookies usam HttpOnly, SameSite=Lax e Secure sob HTTPS. Há limitação de tentativas de login, proteção de diretórios e logs sem credenciais.

## Atualizações e backups

O upload aceita ZIP dentro do limite configurado. Primeiro ocorre a análise; instalar exige confirmação e senha atual. O ZIP precisa conter `update.json` e `payload/`. Caminhos absolutos, `..`, links simbólicos e alterações em `config/local.php`, backups, logs ou pacotes armazenados são rejeitados.

Durante a instalação é criado `storage/updates/update.lock` e o modo manutenção. Se solicitado, o backup ZIP inclui arquivos atuais, dump SQL gerado por PDO e manifesto. Outros backups e arquivos temporários não são incluídos. O SHA-256 calculado na análise é comparado antes da instalação e o ZIP é reaberto e revalidado. Em falha, arquivos substituídos e `version.json` são restaurados, arquivos novos são removidos e diretórios novos vazios são limpos. Se o rollback não puder ser confirmado, manutenção e trava permanecem ativas. O mecanismo v0.1.0 aceita o campo `migrations`, mas a execução automatizada de SQL de pacotes permanece desabilitada por segurança até existir um formato declarativo com lista fechada de operações.

Na versão 1.0.0, a migração idempotente do projeto Caixas é distribuída como código da própria plataforma. Após a atualização, a primeira requisição executa a convergência estrutural e de dados, preservando os vínculos em `client_projects`.

Exemplo de manifesto:

```json
{
  "app": "gda-plataforma",
  "name": "GDA Plataforma",
  "version": "1.0.0",
  "minimum_version": "0.1.0",
  "release_date": "2026-08-01",
  "description": "Melhorias da versão",
  "migrations": [],
  "preserve": ["config/local.php", "storage/backups", "storage/logs", "storage/updates"]
}
```

Para gerar a próxima atualização, copie somente os arquivos alterados para `payload/`, crie o manifesto na raiz e compacte `update.json` + `payload/` sem diretório externo. Nunca inclua `config/local.php`.

## Resolução de problemas

- **Erro 500:** consulte `storage/logs/error.log`; confirme versão/extensões do PHP.
- **Instalador não grava:** revise a permissão de `config/`.
- **URLs retornam 404:** habilite `mod_rewrite` e `AllowOverride All`.
- **ZIP indisponível:** ative a extensão PHP `zip`.
- **Banco desconectado:** confira host (em cPanel, frequentemente `localhost`), porta, nome prefixado e privilégios.
- **Upload recusado:** alinhe `upload_max_filesize`, `post_max_size` e o limite da plataforma.

## Limitações conhecidas da v1.0.0

- Não há exclusão definitiva de clientes.
- Não há SSO, provisionamento ou integração interna com o módulo Caixas nesta fase.
- Não há financeiro, planos ou cobrança.
- Migrações de pacotes são registráveis no schema, porém sua execução automática está deliberadamente desabilitada até a definição de um DSL seguro.
