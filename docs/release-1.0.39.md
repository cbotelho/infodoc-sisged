# Release 1.0.39

## Objetivo

Esta release melhora o feedback visual da tela de download em massa do ECM durante a geracao e a transferencia do ZIP.

## Correcoes incluidas

1. A pagina [ecm/download_lote.php](ecm/download_lote.php) agora mostra a barra de progresso imediatamente ao iniciar o download, mesmo antes de o navegador informar bytes recebidos.
2. A mesma tela troca do estado de preparacao para percentual real quando `lengthComputable` estiver disponivel.
3. O botao de download fica desabilitado durante a operacao para evitar disparos duplicados.
4. A barra permanece visivel por mais tempo ao final, reduzindo a percepcao de que nada aconteceu.
5. Os arquivos de deploy e documentacao foram alinhados para publicar `WEB_IMAGE_TAG=1.0.39`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.35`.

## Imagens publicadas

1. cbotelho80/infodoc-web:1.0.39
	digest: `sha256:c565a1f32f4e3aaf5979fc07496ec9a7ce571585afaec4740bf231bb99782e8a`
2. cbotelho80/infodoc-file-storage-worker:1.0.34
3. cbotelho80/infodoc-assinador-python:1.0.35

## Observacao de diagnostico

O erro `A listener indicated an asynchronous response by returning true, but the message channel closed before a response was received` no console do navegador nao vem desta pagina PHP; ele normalmente e emitido por extensoes do navegador ou scripts externos do contexto da aba.

## Checklist de validacao

1. Abrir `/ecm/download_lote.php` e iniciar um download com alguns PDFs.
2. Confirmar que a barra aparece imediatamente com mensagem de preparacao.
3. Confirmar que, quando o navegador informar progresso mensuravel, a barra mostra o percentual.
4. Confirmar que o download conclui e o navegador recebe o ZIP corretamente.

## Checklist de publicacao

1. Atualizar o Portainer com `WEB_IMAGE_TAG=1.0.39`, `WORKER_IMAGE_TAG=1.0.34` e `SIGNER_IMAGE_TAG=1.0.35`.
2. Fazer `Pull and redeploy` da stack.
3. Validar novamente a tela `/ecm/download_lote.php` em producao.