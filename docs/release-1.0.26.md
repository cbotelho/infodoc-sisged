# Release 1.0.26

## Objetivo

Esta release consolida o ajuste estrutural do upload GED e SEFAZ RH para usar a mesma estrategia de armazenamento do assinador, com configuracao centralizada do R2, fallback local controlado e empacotamento alinhado para publicacao limpa de web, worker e assinador.

## Correcoes incluidas

1. O GED passou a resolver configuracoes do R2 por uma camada central em vez de depender apenas de getenv no request.
2. Os uploads de GED e SEFAZ RH agora compartilham o helper [ecm/object_storage_helper.php](ecm/object_storage_helper.php), com upload via SDK AWS compativel com R2.
3. O helper aceita configuracao por ambiente de runtime, $_ENV, $_SERVER e arquivos .env conhecidos do projeto.
4. Quando o R2 nao estiver habilitado, o GED faz fallback controlado para a pasta local upload/.
5. A tela principal do GED removeu o autocomplete legado baseado em jQuery UI e adotou datalist nativo, reduzindo o erro de frontend ligado a getClientRects.
6. As tags padrao de compose e exemplos de ambiente foram alinhadas para 1.0.26.

## Imagens a publicar

1. cbotelho80/infodoc-web:1.0.26
2. cbotelho80/infodoc-file-storage-worker:1.0.26
3. cbotelho80/infodoc-assinador-python:1.0.26

## Checklist de validacao

1. Validar upload real no GED principal com R2 habilitado.
2. Validar upload real no fluxo SEFAZ RH com R2 habilitado.
3. Confirmar que o objeto foi gravado no bucket com prefixo esperado.
4. Validar o assinador em /health e em um fluxo real iniciado pelo GED.
5. Confirmar que web, worker e assinador sobem com a tag 1.0.26 sem erro de pull.

## Checklist de publicacao

1. Publicar as tres imagens da release 1.0.26.
2. Atualizar a stack no Portainer com IMAGE_TAG=1.0.26 ou tags especificas por servico.
3. Recriar a stack para forcar pull limpo das imagens.
4. Executar os testes funcionais antes da limpeza final de imagens e containers antigos.