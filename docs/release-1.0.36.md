# Release 1.0.36

## Objetivo

Esta release publica a troca do campo de numero da Caixa/Pasta do SEFAZ RH de input texto para select carregado diretamente da entidade 48, capturando o `id_registro` real para o upload.

## Correcoes incluidas

1. A tela [ecm/Envio_sefaz_rh.php](ecm/Envio_sefaz_rh.php) agora usa um select para listar as Caixas/Pastas filtradas por Secretaria, Setor e Tipo.
2. O campo oculto `numero` continua sendo enviado ao backend com `field_527`, enquanto `id_registro` recebe o `id` real da entidade 48.
3. O endpoint [ecm/get_numeros_sefaz_rh.php](ecm/get_numeros_sefaz_rh.php) passou a renderizar as opcoes do select com `data-id` do registro pai.
4. Os arquivos de deploy e documentacao foram alinhados para publicar `WEB_IMAGE_TAG=1.0.36`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.34`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.36
	digest: `sha256:d06bfb72b6bf4158f85c4f0f3ca68808955ed9a6c6494ba09b940f3bd1046cc0`
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.34

## Checklist de validacao

1. Selecionar Secretaria, Setor e Tipo na tela do SEFAZ RH e confirmar que o select de Caixa/Pasta carrega as opcoes da entidade 48.
2. Escolher uma opcao do select e confirmar que o upload deixa de falhar por falta de localizacao da entidade 48.
3. Confirmar que o web sobe com `1.0.36` e worker/assinador permanecem em `1.0.34`.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.36`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.34`.
2. Fazer `Pull and redeploy` da stack.
3. Reexecutar um upload real no SEFAZ RH.