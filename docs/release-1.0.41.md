# Release 1.0.41

## Objetivo

Esta release estende a migracao para upload direto no storage com Presigned URL, cobrindo agora o SEFAZ RH e tambem o GED principal.

## Correcoes incluidas

1. O assinador Python ganhou o endpoint [assinador-python/app/routes/ged.py](assinador-python/app/routes/ged.py) para finalizar no banco os uploads diretos do GED principal.
2. A tela [ecm/index.php](ecm/index.php) passou a usar o fluxo presign -> upload direto -> complete -> finalizacao quando o `PYTHON_SERVICE_PUBLIC_URL` estiver configurado, mantendo fallback para `upload.php`.
3. A tela [ecm/Envio_sefaz_rh.php](ecm/Envio_sefaz_rh.php) permanece no fluxo direto com Presigned URL introduzido na etapa anterior.
4. O service [assinador-python/app/services/object_storage.py](assinador-python/app/services/object_storage.py) e os endpoints [assinador-python/app/routes/direct_upload.py](assinador-python/app/routes/direct_upload.py) continuam como base comum do fluxo direto para os dois modulos.
5. Os arquivos de deploy e documentacao foram alinhados para publicar `WEB_IMAGE_TAG=1.0.41`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.37`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.41
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.37

## Digests publicados

1. cbotelho80/infodoc-web:1.0.41
	manifest list: sha256:8eba91de2c69e4b204e57f69fc55fcb09b75936ef2ae4ca97d7a4cda27f79e44
	linux/amd64: sha256:de550da6126eb4b7495e5db8997f387401a73434e41b41c7819e7bcea7c58003
2. cbotelho80/infodoc-file-storage-worker:1.0.34
	linux/amd64: sha256:12f40978618f242eacca05b286ba2451ca3213791ae6847fea0e2ed94ebca7b2
3. cbotelho80/infodoc-assinador-python:1.0.37
	manifest list: sha256:de86552977316a2e9c59604ee00c278fc65715be4f7cb7604148f9407d32d35f
	linux/amd64: sha256:123873cc9b11ea093b4fc477278a3c14e0e618a189345f929994671ea9b3857a

## Observacoes

1. O upload direto depende de CORS habilitado no bucket para `PUT` a partir do dominio da aplicacao.
2. Quando `PYTHON_SERVICE_PUBLIC_URL` nao estiver configurado, as telas continuam usando o fluxo antigo mediado pelo servidor.
3. OCR inline do GED nao foi migrado para o fluxo direto nesta etapa; o campo permanece vazio na finalizacao via Python.

## Checklist de validacao

1. Abrir `/ecm/index.php` e `/ecm/Envio_sefaz_rh.php` em producao.
2. Enviar um pequeno lote valido em cada tela.
3. Confirmar no DevTools que o navegador solicita `/api/uploads/presign`, faz `PUT` direto no bucket e depois chama `/api/uploads/complete` e o endpoint finalizador do modulo.
4. Confirmar que os registros aparecem nas listagens do GED e do SEFAZ RH e que os objetos existem no bucket.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.41`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.37`.
2. Fazer `Pull and redeploy` da stack.
3. Validar um upload real em cada tela e checar o CORS do bucket caso o browser bloqueie o `PUT`.