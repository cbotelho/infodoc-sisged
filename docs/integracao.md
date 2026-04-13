# Integração com Sistemas Externos

- **API REST:** Consulte a documentação em `api/` para endpoints disponíveis.

## Exemplo de requisição

```bash
curl -X POST http://localhost/infodoc-sisged/api/rest.php -d 'token=SEU_TOKEN&action=list_documents'
```

## Cloudflare R2 como storage de anexos

O GED ja possui uma camada de file storage em plugins/ext/file_storage_modules. A melhor forma de integrar Cloudflare R2 e habilitar o provider Cloudflare R2 no painel de modulos, em vez de alterar diretamente o fluxo do core.

### Como configurar

1. Gere a imagem web atualizada para que a AWS SDK seja instalada durante o build.
2. No Portainer, defina as variaveis abaixo no container web:

```env
FILE_STORAGE_R2_ENDPOINT=https://SEU_ACCOUNT_ID.r2.cloudflarestorage.com
FILE_STORAGE_R2_REGION=auto
FILE_STORAGE_R2_BUCKET=seu-bucket
FILE_STORAGE_R2_ACCESS_KEY_ID=sua-access-key-id
FILE_STORAGE_R2_SECRET_ACCESS_KEY=sua-secret-access-key
FILE_STORAGE_R2_OBJECT_PREFIX=ged
```

3. No GED, instale e ative o modulo de file storage Cloudflare R2.
4. Crie a regra de file storage para os campos de anexo desejados.
5. Agende a execucao de cron/file_storage.php para processar a fila de sincronizacao.

Na stack de producao deste repositório, o servico file-storage-worker ja executa essa fila continuamente.

## Limpeza automatica de log, tmp e cache

Para ambientes com alto volume, a stack de producao pode executar o script `cron/runtime_cleanup.php` por meio do servico `runtime-cleanup-worker`.

O comportamento foi desenhado para ser conservador:

1. Em `log/`, arquivos antigos sao truncados, nao removidos.
2. Em `tmp/` e `cache/`, itens antigos sao removidos por idade.
3. Arquivos sentinela como `.gitkeep`, `index.html`, `index.php` e `.htaccess` sao preservados.
4. A rotina usa lock para evitar execucao concorrente.

Variaveis de ambiente suportadas no Portainer:

```env
RUNTIME_CLEANUP_ENABLED=true
RUNTIME_CLEANUP_INTERVAL=21600
RUNTIME_CLEANUP_LOG_RETENTION_DAYS=7
RUNTIME_CLEANUP_TMP_RETENTION_DAYS=7
RUNTIME_CLEANUP_CACHE_RETENTION_DAYS=7
RUNTIME_CLEANUP_DRY_RUN=false
```

Recomendacao operacional: execute a rotina a cada poucas horas e controle a retencao por idade. Em ambiente de alto volume, isso e mais seguro do que apagar tudo em uma janela fixa de 7 dias.

### Ambiente local com Docker

No compose local, a pasta vendor do provider R2 fica preservada em um volume dedicado para nao ser sobrescrita pelo bind mount do projeto.

Se voce executar o GED fora do Docker, rode o Composer manualmente em plugins/ext/file_storage_modules/r2.

### Observacao importante

Com a arquitetura atual do GED web, o upload de anexos ainda passa primeiro pela area local de uploads e depois e sincronizado para o storage externo pelo mecanismo de fila do modulo file storage. Para eliminar completamente a persistencia local desde o primeiro byte, seria necessario refatorar o fluxo de upload do core.

No assinador Python, o comportamento e diferente: apenas o diretorio /uploads foi adaptado para uso direto do R2 quando as variaveis FILE_STORAGE_R2_* estiverem definidas. Ou seja, o assinador pode enviar uploads diretamente ao bucket, enquanto o GED web continua usando o fluxo local + fila. Os certificados em /certs continuam exclusivamente locais.
