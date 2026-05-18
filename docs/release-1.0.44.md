# Release 1.0.44

## Objetivo

Esta release publica a camada web com o JavaScript de fallback hibrido para RH e GED, complementando o assinador 1.0.39 ja publicado.

## Correcoes incluidas

1. A tela [ecm/Envio_sefaz_rh.php](ecm/Envio_sefaz_rh.php) em producao passa a tentar upload direto e, em caso de falha, aciona o fallback para o assinador Python.
2. A tela [ecm/index.php](ecm/index.php) em producao passa a usar o mesmo comportamento hibrido para o GED.
3. Os arquivos de deploy e documentacao foram alinhados para publicar `WEB_IMAGE_TAG=1.0.42`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.39`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.42
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.39

## Digests publicados

1. cbotelho80/infodoc-web:1.0.42
	manifest: sha256:c80485f9cf8c83173a68aed8065398049416748b18fd0b3b42d19d9a9f6d7d1d

## Observacoes

1. Sem esta publicacao da camada web, o navegador continua executando o JavaScript antigo e o fallback nao aparece no Network.
2. O erro de CORS do R2 continua esperado na primeira tentativa enquanto o bucket nao estiver com CORS correto.
3. A partir desta release, apos a falha do PUT direto, o browser deve fazer POST para o assinador.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.42`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.39`.
2. Fazer `Pull and redeploy` da stack.
3. Recarregar a tela com `Ctrl+F5`.
4. Validar que, apos o erro de CORS no PUT direto, aparece POST para `/api/sefaz-rh/upload` ou `/api/ged/upload`.
