# Release 1.0.38

## Objetivo

Esta release adiciona uma pagina web de download em massa no modulo ECM para usuarios nao tecnicos, sem depender de execucao manual de scripts.

## Correcoes incluidas

1. A pagina [ecm/download_lote.php](ecm/download_lote.php) permite filtrar por fluxo, Secretaria, Setor, Tipo e Caixa/Pasta.
2. O endpoint [ecm/download_lote_api.php](ecm/download_lote_api.php) carrega filtros e lista os PDFs relacionados a caixa selecionada para GED principal e SEFAZ RH.
3. O endpoint [ecm/download_lote_zip.php](ecm/download_lote_zip.php) gera um ZIP com os PDFs marcados e devolve o arquivo para download no navegador.
4. O helper [ecm/object_storage_helper.php](ecm/object_storage_helper.php) ganhou suporte a baixar arquivos do R2 ou do fallback local para um caminho temporario.
5. Os arquivos de deploy e documentacao foram alinhados para publicar `WEB_IMAGE_TAG=1.0.38`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.35`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.38
	digest: `sha256:db3dc1cb3c8463ae76cde66313b6340bb75c15433194e35e69426dad6c9bc906`
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.35

## Checklist de validacao

1. Abrir `/ecm/download_lote.php` e confirmar o carregamento de Secretaria, Setor, Tipo e Caixa/Pasta.
2. Carregar os documentos da caixa selecionada e validar a grade com selecao individual e selecionar todos.
3. Baixar um ZIP com PDFs marcados e confirmar que o navegador recebe o arquivo corretamente.
4. Confirmar que o web sobe com `1.0.38`, o worker permanece em `1.0.34` e o assinador em `1.0.35`.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.38`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.35`.
2. Fazer `Pull and redeploy` da stack.
3. Validar o acesso a `/ecm/download_lote.php` e um download real.