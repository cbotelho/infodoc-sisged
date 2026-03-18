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

### Ambiente local com Docker

No compose local, a pasta vendor do provider R2 fica preservada em um volume dedicado para nao ser sobrescrita pelo bind mount do projeto.

Se voce executar o GED fora do Docker, rode o Composer manualmente em plugins/ext/file_storage_modules/r2.

### Observacao importante

Com a arquitetura atual do GED, o upload ainda passa localmente pela area de uploads e depois e sincronizado para o storage externo pelo mecanismo de fila. Para eliminar completamente a persistencia local desde o primeiro byte, seria necessario refatorar o fluxo de upload do core.
