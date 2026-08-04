# Auditoria da arquitetura anterior

## Pontos fortes

- Prepared statements são usados nas entradas variáveis; senhas usam `password_hash`/`password_verify`.
- CSRF já cobria as principais mutações e uploads possuem validações de tamanho e MIME.
- Storage sensível fica fora de `public`; atualizações validam traversal e preservam configuração local.
- Transações protegem operações críticas como conferência de comprovante e restauração.

## Problemas e acoplamentos

- `pages.php` (aprox. 87 KB) mistura HTML, consultas, validação e regras de negócio.
- `bootstrap.php` mistura conexão, helpers, cálculos, backup e evolução de schema em runtime.
- `index.php` mantinha roteamento por `switch`, sem restrição explícita de método HTTP.
- Logout por GET permitia encerramento induzido da sessão; login não regenerava o identificador.
- Autenticação e autorização não tinham limites próprios ou papéis explícitos.
- Migrações coexistem com alterações de schema executadas em toda requisição.
- Instalador e atualizador dependem de funções globais da aplicação.

## Riscos preservados para migração incremental

- Regras financeiras compactadas em handlers exigem testes de caracterização antes da extração total.
- Não existe suíte automatizada nem banco de teste versionado.
- O modelo atual ainda é single-tenant; isolamento real pertence à Fase 2.
- Serviço externo de QR Code e detecção de IP local continuam acoplamentos operacionais.

## Código obsoleto

Não foi removido código de domínio nesta fase sem cobertura capaz de provar equivalência. A rotina `reset_test_data` permanece tecnicamente morta e perigosa, mas sua remoção deve ocorrer depois de testes de caracterização e busca em pacotes de atualização legados.
