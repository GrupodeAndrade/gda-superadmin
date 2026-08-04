# Instalador legado isolado

O entrypoint permanece em `database/install.php` por compatibilidade com a v0.11.0. Ele é o único fluxo autorizado a criar o primeiro administrador de cliente e será substituído pelo Provisionador da GDA. Não deve receber responsabilidades globais ou integração com a Plataforma.
