# Release 1.0.20

## Objetivo

Esta release corrige o empacotamento da imagem de producao para remover arquivos temporarios da pasta `upload/` do build Docker, evitando falha de pull por falta de espaco em disco no host de deploy.

## Correcoes incluidas

1. A pasta `upload/` deixou de entrar no contexto Docker com seus PDFs temporarios.
2. Apenas `upload/.htaccess` e `upload/file_proxy.php` permanecem na imagem, porque sao necessarios para o fallback de leitura no R2.
3. O `Dockerfile` agora garante a criacao do diretorio `upload/` dentro da imagem.
4. Mantido o fallback para servir documentos de `/upload/...` a partir do R2 quando o arquivo local temporario ja tiver sido removido.
5. Atualizadas as tags padrao de compose e exemplos de ambiente para `1.0.20`.

## Impacto esperado

1. O pull da imagem no Portainer deixa de tentar extrair PDFs operacionais antigos dentro da camada da aplicacao.
2. A imagem web fica menor e mais limpa.
3. O fluxo legado de abertura por `/upload/<arquivo>` continua funcional mesmo apos a limpeza da pasta temporaria.

## Checklist de publicacao

1. Publicar as imagens `cbotelho80/infodoc-web:1.0.20`, `cbotelho80/infodoc-file-storage-worker:1.0.20` e `cbotelho80/infodoc-assinador-python:1.0.20`.
2. Atualizar a stack no Portainer com `IMAGE_TAG=1.0.20` ou tags especificas por servico.
3. Recriar a stack para forcar pull das imagens limpas.
4. Validar a abertura de um PDF legado em `/upload/...`.
5. Validar upload novo, sincronizacao no R2 e reabertura posterior.