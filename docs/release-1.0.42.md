# Release 1.0.42

## Objetivo

Esta release corrige a normalizacao de nomes no assinador Python para tornar as chaves de upload direto ASCII puro antes da geracao de Presigned URL.

## Correcoes incluidas

1. O service [assinador-python/app/services/object_storage.py](assinador-python/app/services/object_storage.py) passou a normalizar nomes para ASCII puro, removendo acentos e convertendo caracteres nao suportados em `_`.
2. O fluxo de upload direto para R2/S3 fica mais previsivel para nomes com caracteres especiais como `Ç`, acentos e outros caracteres Unicode.
3. Os arquivos de deploy e documentacao foram alinhados para publicar `WEB_IMAGE_TAG=1.0.41`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.38`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.41
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.38

## Digests publicados

1. cbotelho80/infodoc-assinador-python:1.0.38
	manifest list: sha256:4ddfc940146bb3e4a5e74d8135e430983c078863d5c2fa7b682c294cf489e8a5
	linux/amd64: sha256:e787f3e9bda92d191e4d26dd18c8565e10b9e87a25d23ea8805dbb2a8f90d9db

## Observacoes

1. Esta release nao elimina a necessidade de configurar CORS no bucket R2 para permitir `PUT` a partir de `https://gea.infodocsisged.com.br`.
2. O erro de browser visto como `A listener indicated an asynchronous response...` nao e a causa do problema de upload; ele costuma vir de extensoes do navegador.
3. A falha principal observada em producao continua sendo o bloqueio de preflight por CORS no endpoint do R2.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.41`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.38`.
2. Fazer `Pull and redeploy` da stack.
3. Configurar ou revisar o CORS do bucket R2 antes de retestar o upload direto.
4. Repetir um envio real no GED e no SEFAZ RH com arquivo cujo nome contenha acentos para confirmar a nova normalizacao.
