# Release 1.0.40

## Objetivo

Esta release inicia a migracao do upload em massa do SEFAZ RH para upload direto no storage com Presigned URL, reduzindo a passagem do arquivo pelo backend antes da persistencia final.

## Correcoes incluidas

1. O assinador Python ganhou os endpoints [assinador-python/app/routes/direct_upload.py](assinador-python/app/routes/direct_upload.py) para gerar URLs assinadas e confirmar objetos enviados diretamente ao storage.
2. O service [assinador-python/app/services/object_storage.py](assinador-python/app/services/object_storage.py) passou a gerar Presigned URL de `PUT`, normalizar nomes e consultar metadados do objeto no storage.
3. A rota [assinador-python/app/routes/sefaz_rh.py](assinador-python/app/routes/sefaz_rh.py) agora aceita a finalizacao do fluxo direto via `/api/sefaz-rh/upload/direct`, validando os objetos confirmados e gravando os registros no banco.
4. A tela [ecm/Envio_sefaz_rh.php](ecm/Envio_sefaz_rh.php) passou a usar o fluxo presign -> upload direto -> complete -> finalizacao quando o `PYTHON_SERVICE_PUBLIC_URL` estiver configurado, mantendo fallback para o fluxo antigo.
5. Os arquivos de deploy e documentacao foram alinhados para publicar `WEB_IMAGE_TAG=1.0.40`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.36`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.40
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.36

## Observacoes

1. Esta etapa cobre apenas o fluxo do SEFAZ RH.
2. O GED principal ainda nao foi migrado para Presigned URL.
3. O bucket precisa ter CORS habilitado para `PUT` a partir do dominio da aplicacao.

## Checklist de validacao

1. Abrir a tela `/ecm/Envio_sefaz_rh.php` em producao.
2. Selecionar os filtros e enviar um pequeno lote de PDFs validos.
3. Confirmar no DevTools que o navegador solicita `/api/uploads/presign`, faz `PUT` direto no bucket e depois chama `/api/uploads/complete` e `/api/sefaz-rh/upload/direct`.
4. Confirmar que os registros aparecem na listagem do SEFAZ RH e que os objetos existem no bucket.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.40`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.36`.
2. Fazer `Pull and redeploy` da stack.
3. Validar um upload real no SEFAZ RH e checar o CORS do bucket caso o browser bloqueie o `PUT`.