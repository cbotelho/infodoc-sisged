# Release 1.0.43

## Objetivo

Esta release adiciona fallback hibrido no assinador para RH e GED quando o upload direto por Presigned URL falhar no navegador.

## Correcoes incluidas

1. A tela [ecm/Envio_sefaz_rh.php](ecm/Envio_sefaz_rh.php) agora tenta upload direto para o R2/S3 e, em caso de falha, cai automaticamente para o endpoint multipart do assinador Python.
2. A tela [ecm/index.php](ecm/index.php) passa a usar o mesmo comportamento hibrido, com fallback automatico para o assinador.
3. O assinador ganhou a rota multipart [assinador-python/app/routes/ged.py](assinador-python/app/routes/ged.py) em /api/ged/upload para receber o fallback do GED.
4. Os arquivos de deploy e documentacao foram alinhados para publicar `WEB_IMAGE_TAG=1.0.41`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.39`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.41
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.39

## Digests publicados

1. cbotelho80/infodoc-assinador-python:1.0.39
	manifest list: sha256:3fb4168f09379b3dcb91b1a08cc427218f7782e187b7430baaad536aa1dd5413
	linux/amd64: sha256:daa4fdd4bf08ba59e43c0c5f4783d9fd1ac1838c445e5fdb4e1c19908a73166d

## Observacoes

1. O fluxo direto continua sendo a primeira tentativa.
2. Se o navegador falhar no PUT direto ao R2/S3, o upload cai para o assinador Python, evitando dependencia do PHP legado nesses dois fluxos.
3. O fallback implementado e browser -> assinador Python -> storage. Ele nao usa a fila do worker do GED PHP.
4. O CORS do bucket continua recomendavel, porque sem ele o fluxo direto segue falhando e o sistema dependera sempre do fallback.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.41`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.39`.
2. Fazer `Pull and redeploy` da stack.
3. Validar um upload no RH e um no GED com o fluxo direto liberado.
4. Validar tambem um caso em que o PUT direto falhe, confirmando que o fallback no assinador conclui o envio.
