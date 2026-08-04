# Fase 2E — Link individual e portal do participante

Versão: **0.13.0**

## Entrega

- link exclusivo por participante e caixa em `/p/{token}`;
- token aleatório de 256 bits, armazenado somente como hash SHA-256;
- exibição do token original apenas depois de gerar ou regenerar;
- ativação, desativação e regeneração restritas ao administrador do cliente;
- isolamento por empresa, caixa e participante;
- consulta de pontos, aportes, pagamentos, comprovantes e mensagens próprios;
- PIX exibido exclusivamente a partir da Administração do caixa;
- upload de comprovante vinculado ao contexto do token;
- caixa arquivado disponível somente para consulta;
- registro do último acesso sem registrar o token.

## Banco de dados

A migração `006_participant_access_links.php` adiciona os metadados do link a `cycle_participants`. Ela é idempotente, compatível com MariaDB e não remove nem transforma dados financeiros. Links legados não são promovidos ao novo mecanismo.

## Segurança

O portal não depende de sessão autenticada. Tokens inválidos, rotacionados, desativados ou pertencentes a empresa inativa recebem a mesma resposta genérica. Cabeçalhos impedem cache, reduzem vazamento de referência e restringem fontes de conteúdo. O nome armazenado de cada comprovante é aleatório e o arquivo permanece fora da pasta pública.

## Homologação local

Foram executados cenários em MariaDB descartável para geração, revogação, rotação, isolamento, caixa arquivado, PIX, comprovantes e último acesso. Também foram validados o portal em computador e celular, a ausência de rolagem horizontal e as regressões de multiempresa e multi-caixa.
