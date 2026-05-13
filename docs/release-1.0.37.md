# Release 1.0.37

## Objetivo

Esta release corrige o filtro de Caixa/Pasta do SEFAZ RH para funcionar com a estrutura real de producao, onde a entidade 48 precisa ser localizada por secretaria e setor mesmo quando `field_526` nao casar.

## Correcoes incluidas

1. O endpoint [ecm/get_numeros_sefaz_rh.php](ecm/get_numeros_sefaz_rh.php) agora tenta primeiro com `field_526` e, se nada for encontrado, recarrega as opcoes usando apenas secretaria e setor.
2. A rota [assinador-python/app/routes/sefaz_rh.py](assinador-python/app/routes/sefaz_rh.py) aplica o mesmo fallback ao resolver o registro pai pelo numero.
3. A validacao do registro selecionado deixa de reprovar a Caixa/Pasta apenas por divergencia de `field_526`, mantendo a validacao de numero, secretaria e setor.
4. Os arquivos de deploy e documentacao foram alinhados para publicar `WEB_IMAGE_TAG=1.0.37`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.35`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.37
	digest: `sha256:7e7d08907aacb2c874303516681e47ceb8758f91847742f117e7e66054b3fe66`
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.35
	digest: `sha256:f5765855b8013732e72c9fb92d4974388a49b87614d0856b5430900557a53d92`

## Checklist de validacao

1. Selecionar Secretaria e Setor validos do SEFAZ RH em producao e confirmar que o select de Caixa/Pasta lista os registros da entidade 48 mesmo quando `field_526` nao estiver consistente.
2. Escolher uma Caixa/Pasta do select e confirmar que o upload nao falha mais por falta de localizacao na entidade 48.
3. Confirmar que o web sobe com `1.0.37`, o worker permanece em `1.0.34` e o assinador sobe com `1.0.35`.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.37`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.35`.
2. Fazer `Pull and redeploy` da stack.
3. Reexecutar um upload real no SEFAZ RH.