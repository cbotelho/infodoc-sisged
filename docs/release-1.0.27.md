# Release 1.0.27

## Objetivo

Esta release corrige a contagem de paginas de PDF nos fluxos GED e SEFAZ RH, reduz o tempo de build da imagem PHP e consolida a combinacao de deploy com web e worker em `1.0.27` e assinador em `1.0.26`.

## Correcoes incluidas

1. A contagem de paginas de PDF em [ecm/upload.php](ecm/upload.php) deixou de depender da classe global `FPDF`.
2. A contagem de paginas de PDF em [ecm/upload_sefaz_rh.php](ecm/upload_sefaz_rh.php) tambem passou a usar `Smalot\\PdfParser\\Parser`.
3. O `Dockerfile` da imagem PHP passou a usar `COPY --chown=www-data:www-data` e `install -d`, removendo o `chown -R` global que tornava o build muito lento.
4. O compose e os exemplos de ambiente foram alinhados para usar `WEB_IMAGE_TAG=1.0.27`, `WORKER_IMAGE_TAG=1.0.27` e `SIGNER_IMAGE_TAG=1.0.26`.
5. A validacao funcional confirmou upload e visualizacao no GED principal e no fluxo SEFAZ RH.
6. A visualizacao dos arquivos continua dependente do caminho base configurado no campo de arquivo, pois a base armazena apenas o nome do arquivo.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.27
2. cbotelho80/infodoc-file-storage-worker:1.0.27
3. cbotelho80/infodoc-assinador-python:1.0.26

## Checklist de validacao

1. Validar upload de PDF no GED principal.
2. Validar upload de PDF no fluxo SEFAZ RH.
3. Confirmar a gravacao do numero de paginas sem erro `Class "FPDF" not found`.
4. Confirmar que os campos de arquivo possuem o caminho base configurado para visualizacao.
5. Confirmar que web e worker sobem com `1.0.27` e o assinador com `1.0.26`.

## Checklist de publicacao

1. Atualizar o Portainer com `IMAGE_TAG=1.0.26`, `WEB_IMAGE_TAG=1.0.27`, `WORKER_IMAGE_TAG=1.0.27` e `SIGNER_IMAGE_TAG=1.0.26`.
2. Fazer `Pull and redeploy` da stack.
3. Reexecutar os testes de upload e visualizacao antes de limpar imagens antigas.