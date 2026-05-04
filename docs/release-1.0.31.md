# Release 1.0.31

## Objetivo

Esta release publica um patch de robustez no fluxo SEFAZ RH para reduzir o risco de timeout com PDFs grandes e alinhar o repositório com as imagens `web` e `file-storage-worker` já publicadas em `1.0.31`.

## Correcoes incluidas

1. O handler [ecm/upload_sefaz_rh.php](ecm/upload_sefaz_rh.php) passou a preprocessar a contagem de páginas dos PDFs antes de abrir a transação do banco.
2. O mesmo handler passou a usar `stored_name` e `total_paginas` pré-calculados, reduzindo o tempo de conexão ocupada durante o insert/commit.
3. Foi mantida uma proteção defensiva para `field_563`, permitindo que o fluxo não quebre caso algum ambiente ainda não tenha essa coluna disponível.
4. Os arquivos de ambiente e o compose de produção foram alinhados para usar `WEB_IMAGE_TAG=1.0.31`, `WORKER_IMAGE_TAG=1.0.31` e `SIGNER_IMAGE_TAG=1.0.26`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.31
2. cbotelho80/infodoc-file-storage-worker:1.0.31
3. cbotelho80/infodoc-assinador-python:1.0.26

## Checklist de validacao

1. Validar upload de PDF grande no fluxo SEFAZ RH.
2. Confirmar que a transação conclui sem erro 500 durante o envio.
3. Confirmar que `field_563` continua sendo gravado normalmente nos ambientes onde a coluna existe.
4. Confirmar que web e worker sobem com `1.0.31` e o assinador com `1.0.26`.

## Checklist de publicacao

1. Atualizar o Portainer com `IMAGE_TAG=1.0.26`, `WEB_IMAGE_TAG=1.0.31`, `WORKER_IMAGE_TAG=1.0.31` e `SIGNER_IMAGE_TAG=1.0.26`.
2. Fazer `Pull and redeploy` da stack.
3. Reexecutar o upload no SEFAZ RH com um PDF grande para validar a correção.