# Release 1.0.32

## Objetivo

Esta release publica um ajuste no fluxo SEFAZ RH para flexibilizar a escolha do padrão de renomeio e corrigir a validação dos nomes de arquivos usando o nome sem a extensão.

## Correcoes incluidas

1. O formulário [ecm/Envio_sefaz_rh.php](ecm/Envio_sefaz_rh.php) passou a permitir que o usuário escolha o padrão de renomeio entre `1`, `2`, `3` e `4`, mantendo `3` como padrão.
2. O handler [ecm/upload_sefaz_rh.php](ecm/upload_sefaz_rh.php) passou a validar as colunas do nome do arquivo usando `PATHINFO_FILENAME`, sem considerar a extensão.
3. O fluxo continua separando erro real de upload de erro de formato do nome, com mensagens distintas para cada caso.
4. Os arquivos de ambiente e o compose de produção foram alinhados para usar `WEB_IMAGE_TAG=1.0.32`, `WORKER_IMAGE_TAG=1.0.32` e `SIGNER_IMAGE_TAG=1.0.26`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.32
2. cbotelho80/infodoc-file-storage-worker:1.0.32
3. cbotelho80/infodoc-assinador-python:1.0.26

## Checklist de validacao

1. Validar upload no SEFAZ RH com `padrao_renomeio` igual a `1`, `2`, `3` e `4`.
2. Confirmar que nomes como `27172-1#ADEVALDO DA SILVA BARBOSA#14493195215.pdf` nao sejam rejeitados indevidamente quando o padrão esperado for `3`.
3. Confirmar que a mensagem de erro diferencia falha de upload de nome invalido.
4. Confirmar que web e worker sobem com `1.0.32` e o assinador com `1.0.26`.

## Checklist de publicacao

1. Atualizar o Portainer com `IMAGE_TAG=1.0.26`, `WEB_IMAGE_TAG=1.0.32`, `WORKER_IMAGE_TAG=1.0.32` e `SIGNER_IMAGE_TAG=1.0.26`.
2. Fazer `Pull and redeploy` da stack.
3. Reexecutar o envio no SEFAZ RH com amostras de nomes usando 1, 2, 3 e 4 colunas.