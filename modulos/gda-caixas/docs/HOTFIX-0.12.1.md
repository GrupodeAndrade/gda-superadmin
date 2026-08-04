# Hotfix v0.12.1 — PIX da Administração e pagamentos

Atualização incremental para instalações v0.12.0. Não altera schema nem possui migrações.

- o PIX apresentado como destino de pagamento é resolvido pelo caixa autorizado e pelo participante Administração vinculado ao seu primeiro ponto;
- o valor previsto integral é calculado por `dias do período × valor diário − comissão`;
- o valor informado pelo administrador é validado e persistido como pagamento efetivo;
- saldo, histórico e relatórios usam o valor efetivamente pago;
- pagamentos inferiores ao previsto são identificados visualmente como parciais.

O pacote preserva `config/local.php`, `storage/`, uploads, comprovantes e banco de dados.
