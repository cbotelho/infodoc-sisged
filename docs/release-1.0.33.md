# Release 1.0.33

## Objetivo

Esta release publica um patch de robustez no fluxo SEFAZ RH para reduzir o risco de erro 500 com PDFs grandes e restaurar o compose de producao usado no Portainer.

## Correcoes incluidas

1. O handler [ecm/upload_sefaz_rh.php](ecm/upload_sefaz_rh.php) libera a conexao com o banco antes do preprocessamento de PDFs e abre uma nova conexao apenas antes da transacao.
2. A contagem de paginas de PDF passou a usar um caminho seguro que registra a falha em log e segue com `0` paginas quando a leitura do arquivo falha.
3. O arquivo [docker-compose.production.yml](docker-compose.production.yml) foi restaurado como YAML valido de stack do Portainer, incluindo `web`, `file-storage-worker`, `runtime-cleanup-worker` e `assinador-python`.
4. O upload sincronizado para o R2 foi reabilitado por padrao quando o bucket estiver configurado, evitando a persistencia final em disco local da VPS.
5. Os arquivos de ambiente e documentacao foram alinhados para usar `WEB_IMAGE_TAG=1.0.33`, `WORKER_IMAGE_TAG=1.0.33` e `SIGNER_IMAGE_TAG=1.0.26`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.33
2. cbotelho80/infodoc-file-storage-worker:1.0.33
3. cbotelho80/infodoc-assinador-python:1.0.26
4. Digest publicado para web e worker: `sha256:cfe2b01677cea83a66aac0b7f458552fe14a4e1147df4ff192f877c7a0201ce3`

## Checklist de validacao

1. Validar que a stack sobe no Portainer usando o compose path `docker-compose.production.yml`.
2. Confirmar que os containers `infodoc-web`, `infodoc-file-storage-worker`, `infodoc-runtime-cleanup-worker` e `infodoc-assinador` ficam em estado `running`.
3. Reexecutar o upload no SEFAZ RH com um PDF grande e confirmar ausencia de erro 500.
4. Confirmar que web e worker sobem com `1.0.33` e o assinador com `1.0.26`.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.33`, `WORKER_IMAGE_TAG=1.0.33` e `SIGNER_IMAGE_TAG=1.0.26`.
2. Fazer `Pull and redeploy` da stack.
3. Se o Portainer ainda apontar erro de YAML, limpar o conteudo salvo da stack antiga e reenviar o compose restaurado deste repositório.