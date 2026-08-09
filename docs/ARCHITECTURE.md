# Arquitetura da GDA Plataforma

A aplicação usa MVC simples em PHP 8.3. Controladores recebem rotas amigáveis, serviços concentram autenticação, banco, migrações, logs e atualizações, e as views produzem HTML responsivo sem framework.

## Projetos e módulos

`projects` é o catálogo administrativo local dos projetos GDA. `client_projects` implementa a relação muitos-para-muitos com clientes e preserva ativações, desativações e configurações em JSON.

O registro do Caixas possui `full_name`, `admin_url` e `category`, além dos campos comuns. A migração de compatibilidade roda uma vez após a instalação e é controlada pela tabela `migrations`.

O botão **Administrar** apenas abre a URL administrativa cadastrada localmente. Não transmite usuário, senha, token ou credencial e não realiza comunicação servidor a servidor.

O painel Super Administrador permanece protegido no roteador e aceita exclusivamente usuários `super_admin`. Todas as alterações usam CSRF e prepared statements.
