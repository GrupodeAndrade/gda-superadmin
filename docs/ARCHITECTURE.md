# Arquitetura da GDA Plataforma

A aplicação usa MVC simples em PHP 8.3. Controladores recebem rotas amigáveis, serviços concentram autenticação, banco, migrações, logs e atualizações, e as views produzem HTML responsivo sem framework.

## Projetos e módulos

`projects` é o catálogo estrutural dos módulos GDA. `client_projects` implementa a relação muitos-para-muitos com clientes e preserva ativações, desativações e configurações futuras em JSON.

O registro do Caixas possui `full_name`, `admin_url` e `category`, além dos campos comuns. A migração de compatibilidade roda uma vez após a instalação e é controlada pela tabela `migrations`.

O painel Super Administrador permanece protegido no roteador e aceita exclusivamente usuários `super_admin`. Todas as alterações usam CSRF e prepared statements.

## Anexação de módulos

O fluxo é dividido entre parser da chave, política contra SSRF, transporte HTTP injetável, validador do manifesto, serviço transacional e repositório. `project_connections` preserva a identidade da instância sem guardar a chave. `projects` continua sendo o catálogo e `client_projects` continua sendo o vínculo entre clientes e módulos.
