# Implantação em hospedagem

Use `IMPLANTACAO-HOMOLOGACAO.md` como procedimento oficial. O pacote deve ser extraído diretamente na raiz do subdomínio, sem pasta externa.

Nunca distribua nem substitua `config/local.php`. Os diretórios `storage/receipts` e `storage/updates` precisam ser graváveis pelo PHP e permanecer protegidos contra acesso direto.
