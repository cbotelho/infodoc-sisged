# Release 1.0.30

## Objetivo

Esta release publica o ajuste do fluxo SEFAZ RH para exigir o tipo de documento no formulario e persistir esse valor em `app_entity_49.field_563`, mantendo web e worker em `1.0.30` e o assinador em `1.0.26`.

## Correcoes incluidas

1. O formulario [ecm/Envio_sefaz_rh.php](ecm/Envio_sefaz_rh.php) passou a exibir o select obrigatorio `doctipo` logo apos o tipo de acesso.
2. O handler [ecm/upload_sefaz_rh.php](ecm/upload_sefaz_rh.php) passou a exigir `doctipo` no POST e gravar o valor em `field_563` de `app_entity_49`.
3. Os exemplos de ambiente e o compose de producao foram alinhados para usar `WEB_IMAGE_TAG=1.0.30`, `WORKER_IMAGE_TAG=1.0.30` e `SIGNER_IMAGE_TAG=1.0.26`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.30
2. cbotelho80/infodoc-file-storage-worker:1.0.30
3. cbotelho80/infodoc-assinador-python:1.0.26

## Checklist de validacao

1. Validar que o campo `Tipo de Documento` aparece como obrigatorio no fluxo SEFAZ RH.
2. Validar envio com cada opcao de `doctipo` esperada no formulario.
3. Confirmar na tabela `app_entity_49` que `field_563` recebe o valor selecionado.
4. Confirmar que web e worker sobem com `1.0.30` e o assinador com `1.0.26`.

## Checklist de publicacao

1. Atualizar o Portainer com `IMAGE_TAG=1.0.26`, `WEB_IMAGE_TAG=1.0.30`, `WORKER_IMAGE_TAG=1.0.30` e `SIGNER_IMAGE_TAG=1.0.26`.
2. Fazer `Pull and redeploy` da stack.
3. Reexecutar um envio no SEFAZ RH validando o preenchimento de `field_563`.