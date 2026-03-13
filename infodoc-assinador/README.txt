
Infodoc Assinador - Projeto de exemplo
=====================================

Conteúdo:
- index.php: interface de upload e assinatura (exemplo)
- upload.php: recebe PDF e salva em uploads/
- assinar.php: tenta usar FPDI/TCPDF (se instalado) ou faz assinatura demo com openssl_sign
- download.php: baixa arquivo assinado
- certs/: pasta com certificados (exemplo)
- uploads/: PDFs enviados
- signed/: PDFs assinados (saída)
- assets/: css/js/img

ATENÇÃO IMPORTANTE:
- Este projeto é um exemplo. Para implementar produção com PAdES-LTV/ICP-Brasil use bibliotecas oficiais
  e siga normas de segurança (HSM, armazenamento seguro de chaves, HTTPS, etc.).

Gerar um certificado de teste (.pfx) usando OpenSSL (Linux/macOS):
---------------------------------------------------------------
openssl genrsa -out test_key.pem 2048
openssl req -new -key test_key.pem -out test_req.csr -subj "/CN=Usuario Teste/OU=TI/O=Org/L=Cidade/ST=Estado/C=BR"
openssl x509 -req -days 365 -in test_req.csr -signkey test_key.pem -out test_cert.pem
# criar pfx protegido por senha
openssl pkcs12 -export -out certs/test_cert.pfx -inkey test_key.pem -in test_cert.pem -passout pass:senha123

Em servidores Windows, use o OpenSSL instalado ou ferramentas equivalentes.

Composer & dependências:
------------------------
Se desejar usar FPDI/TCPDF para assinatura visível e embedding PAdES, execute:
composer require tecnickcom/tcpdf setasign/fpdi-tcpdf

Uso:
- Abra index.php no navegador (via servidor Apache/nginx + PHP)
- Envie um PDF em Upload
- Selecione o certificado em /certs (exemplo fornecido)
- Clique em Assinar

Limitações do exemplo:
- assinar.php faz fallback para uma assinatura demonstrativa se as libs não estiverem instaladas.
- Para PAdES formal/validação ITI, é necessário integrar rotina de timestamp (TSA) e OCSP/CRL.

