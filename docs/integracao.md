# Integração com Sistemas Externos

- **API REST:** Consulte a documentação em `api/` para endpoints disponíveis.

## Exemplo de requisição

```bash
curl -X POST http://localhost/infodoc-sisged/api/rest.php -d 'token=SEU_TOKEN&action=list_documents'
```

## Cloudflare R2 como storage de anexos

O projeto possui dois caminhos complementares para trabalhar com anexos no R2:

1. o fluxo legado de fila do modulo de file storage;
2. o upload direto dos fluxos GED principal e SEFAZ RH, que agora resolvem a configuracao do R2 por helper compartilhado.

### Como configurar

1. Gere a imagem web atualizada para que a AWS SDK seja instalada durante o build.
2. No Portainer, defina as variaveis abaixo no container web e no worker:

```env
FILE_STORAGE_R2_ENDPOINT=https://SEU_ACCOUNT_ID.r2.cloudflarestorage.com
FILE_STORAGE_R2_REGION=auto
FILE_STORAGE_R2_BUCKET=seu-bucket
FILE_STORAGE_R2_ACCESS_KEY_ID=sua-access-key-id
FILE_STORAGE_R2_SECRET_ACCESS_KEY=sua-secret-access-key
FILE_STORAGE_R2_OBJECT_PREFIX=ged
```

3. No GED, instale e ative o modulo de file storage Cloudflare R2 se quiser manter a sincronizacao por fila para outros campos do sistema.
4. Crie a regra de file storage para os campos de anexo desejados quando o caso de uso continuar dependendo da fila.
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

Nos fluxos GED principal e SEFAZ RH deste repositório, o upload ja pode seguir direto para o R2 quando as variaveis `FILE_STORAGE_R2_*` estiverem definidas. Quando nao estiverem, o sistema faz fallback controlado para a pasta local `upload/`.

O modulo legado de file storage continua existindo para os cenarios em que a aplicacao depende de sincronizacao por fila.

No assinador Python, o diretorio `/uploads` tambem usa envio direto ao R2 quando as variaveis `FILE_STORAGE_R2_*` estiverem definidas. Os certificados em `/certs` continuam exclusivamente locais.

Nos campos de arquivo do GED, a base continua armazenando apenas o nome do arquivo. Por isso, a visualizacao depende de o campo possuir o caminho base configurado corretamente, por exemplo `/upload/` ou a URL publica equivalente.
