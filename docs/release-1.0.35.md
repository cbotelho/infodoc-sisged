# Release 1.0.35

## Objetivo

Esta release publica o ajuste da tela PHP do SEFAZ RH para enviar o `id_registro` correto da Caixa/Pasta junto ao upload, evitando falhas de resolucao da entidade 48 no backend Python.

## Correcoes incluidas

1. A tela [ecm/Envio_sefaz_rh.php](ecm/Envio_sefaz_rh.php) agora resolve o `id_registro` por AJAX quando o usuario informa o numero da Caixa/Pasta.
2. O mesmo arquivo faz uma ultima resolucao do `id_registro` antes do submit do upload para o endpoint Python do assinador.
3. Os arquivos de deploy e documentacao foram alinhados para publicar `WEB_IMAGE_TAG=1.0.35`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.34`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.35
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.34

## Checklist de validacao

1. Informar Secretaria, Setor, Tipo e Numero de Caixa/Pasta na tela SEFAZ RH.
2. Confirmar que o upload deixa de retornar a mensagem `Nenhuma Caixa/Pasta foi encontrada na entidade 48 com os filtros informados`.
3. Confirmar que o web sobe com `1.0.35` e worker/assinador permanecem em `1.0.34`.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.35`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.34`.
2. Fazer `Pull and redeploy` da stack.
3. Reexecutar um upload real no SEFAZ RH.