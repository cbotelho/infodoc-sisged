# Release 1.0.34

## Objetivo

Esta release publica o ajuste que volta a enviar os arquivos do GED e do SEFAZ RH diretamente para o R2 por padrao, evitando gravacao final em disco local da VPS.

## Correcoes incluidas

1. O helper [ecm/object_storage_helper.php](ecm/object_storage_helper.php) passou a considerar `GED_ENABLE_SYNC_R2_UPLOAD=1` como padrao quando o bucket R2 estiver configurado.
2. O compose de producao e os arquivos de ambiente foram alinhados para subir o servico `web` com `GED_ENABLE_SYNC_R2_UPLOAD=1` por padrao.
3. O assinador Python passou a expor um endpoint dedicado de upload SEFAZ RH, reutilizando o fluxo de object storage do serviço e permitindo que a tela PHP envie direto para esse backend.
4. A documentacao de deploy foi alinhada para publicar `WEB_IMAGE_TAG=1.0.34`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.34`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.34
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.34

## Checklist de validacao

1. Validar que o upload no SEFAZ RH nao retorna mais erro de `armazenamento local de fallback`.
2. Confirmar que o objeto final e criado diretamente no bucket R2 configurado.
3. Confirmar que web, worker e assinador sobem com `1.0.34`.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.34`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.34`.
2. Fazer `Pull and redeploy` da stack.
3. Reexecutar um upload real no SEFAZ RH e no GED para confirmar envio direto ao R2.