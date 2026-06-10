# Release 1.0.60

## Objetivo

Esta release consolida a correcao de visualizacao de arquivos no fluxo de upload, eliminando erro 404 causado por divergencia entre nome/chave de objeto no storage.

## Correcoes incluidas

1. O proxy [upload/file_proxy.php](upload/file_proxy.php) passou a tentar resolucao mais tolerante de nome de arquivo para leitura no R2.
2. Foi adicionado fallback por nome saneado quando o nome original nao existe no storage.
3. O stream de leitura passou a testar chaves candidatas em multiplos prefixos para reduzir falso 404.
4. O fallback local tambem considera variacao saneada de nome para manter compatibilidade com uploads legados.
5. A stack web foi atualizada para a imagem `cbotelho80/infodoc-web:1.0.60`.

## Imagem publicada

1. cbotelho80/infodoc-web:1.0.60

## Digest publicado

1. cbotelho80/infodoc-web:1.0.60
   manifest: sha256:0b53303bfcea24e302ab1236a56734eead228c023c8074a6fddde75a9c745828

## Validacao concluida

1. Usuario voltou a visualizar arquivos enviados no fluxo afetado.
2. Erro 404 de visualizacao foi eliminado no ambiente atualizado.

## Checklist de publicacao

1. Atualizar `WEB_IMAGE_TAG=1.0.60` no Portainer.
2. Fazer `Pull and redeploy` da stack.
3. Validar upload e abertura de arquivo em tenant GEA e CIPEMAC.
