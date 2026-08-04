# Administradores múltiplos — solução temporária

Disponível na versão final **v0.99** do Caixa Diário.

Esta implementação permite que um `client_admin` cadastre outros administradores operacionais dentro da mesma empresa já existente.

Todos os administradores criados recebem, exclusivamente no backend:

- `role = client_admin`;
- `company_id` igual ao da sessão do administrador criador;
- acesso aos mesmos caixas e dados da empresa.

Não é criada empresa, tenant ou banco adicional. Campos de papel e empresa enviados pelo navegador são ignorados. Usuários de outras empresas e `global_admin` nunca aparecem na tela.

Administradores podem criar usuários, editar nomes, ativar, inativar e redefinir senhas. Não podem inativar a si próprios nem deixar a empresa sem administrador ativo. Senhas exigem no mínimo 12 caracteres e são persistidas somente por `password_hash()`.

Esta é uma solução temporária para o ambiente atual, antes da migração prevista para `caixas.gda.app.br`. Ela não oferece nem deve ser apresentada como isolamento entre clientes ou entre administradores: todos compartilham integralmente a empresa e seus caixas.

O desenvolvimento deste projeto foi encerrado na v0.99 e permanece apenas em manutenção crítica. Todas as novas funcionalidades serão implementadas exclusivamente no novo projeto **GDA Caixas**, que começará diretamente na versão **v1.0.0**.
