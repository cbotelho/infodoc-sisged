# Deploy via Portainer com duas aplicacoes publicas

Este guia publica o GED PHP e o assinador Python em dois dominios separados, mantendo a comunicacao interna entre os containers pela rede Docker.

## Topologia

- GED PHP: `https://gea.seu-dominio.com.br`
- Assinador Python: `https://assinador.seu-dominio.com.br`
- Comunicacao interna entre containers: `http://assinador-python:5000`

## Antes de criar a stack

1. Garanta que a VPS tenha Docker, Portainer e acesso ao repositório.
2. Garanta que exista uma rede Docker externa para o proxy reverso.
3. Garanta que os dois dominios apontem para a VPS.
4. Garanta que o proxy reverso ou publicador HTTP da sua infraestrutura encaminhe:
   - `gea.seu-dominio.com.br` para a porta `8081` do host ou diretamente para o container `infodoc-web`.
   - `assinador.seu-dominio.com.br` para a porta `5000` do host ou diretamente para o container `infodoc-assinador`.
5. Garanta que o banco de dados aceite conexao a partir da VPS.

## Criar a rede externa do proxy

Se a rede ainda nao existir, crie antes do deploy:

```bash
docker network create proxy
```

Se sua infraestrutura usar outro nome, ajuste a variavel `PROXY_EXTERNAL_NETWORK` no Portainer.

## Criar a stack no Portainer

1. Acesse `Stacks`.
2. Clique em `Add stack`.
3. Defina o nome da stack como `infodoc-sisged`.
4. Escolha `Repository`.
5. Informe a URL do repositório.
6. Informe a branch correta.
7. Em `Compose path`, informe `docker-compose.production.yml`.
8. Em `Environment variables`, cadastre as variaveis abaixo ajustando para o seu ambiente.
9. Garanta que a VPS tenha acesso ao Docker Hub para baixar as imagens publicadas no namespace `cbotelho80`.

## Variaveis recomendadas

```env
IMAGE_TAG=1.0.16
WEB_IMAGE_TAG=1.0.18
WORKER_IMAGE_TAG=1.0.16
SIGNER_IMAGE_TAG=1.0.16
APP_PORT=8081
SIGNER_PORT=5000
APP_BASE_URL=https://gea.seu-dominio.com.br
PYTHON_SERVICE_PUBLIC_URL=https://assinador.seu-dominio.com.br
PROXY_EXTERNAL_NETWORK=proxy
PHP_UPLOAD_MAX_FILESIZE=1024M
PHP_POST_MAX_SIZE=1024M
PHP_MAX_FILE_UPLOADS=100

DB_SERVER=seu-host-do-banco
DB_SERVER_PORT=3306
DB_SERVER_USERNAME=seu-usuario
DB_SERVER_PASSWORD=sua-senha
DB_DATABASE=seu_banco

DB_HOST=seu-host-do-banco
DB_PORT=3306
DB_USER=seu-usuario
DB_PASSWORD=sua-senha
DB_NAME=seu_banco

SIGNER_SECRET_KEY=gere-uma-chave-forte-com-pelo-menos-32-caracteres
TOKEN_EXPIRY=3600
```

`IMAGE_TAG` continua como fallback global. Quando apenas um servico for publicado em uma nova versao, defina a variavel especifica do servico no Portainer:

- `WEB_IMAGE_TAG` para o GED PHP
- `WORKER_IMAGE_TAG` para o file storage worker
- `SIGNER_IMAGE_TAG` para o assinador Python

## Fazer o deploy

1. Clique em `Deploy the stack`.
2. Aguarde o pull das imagens `cbotelho80/infodoc-web`, `cbotelho80/infodoc-assinador-python` e `cbotelho80/infodoc-file-storage-worker` nas tags definidas nas variaveis da stack. Se nenhuma tag especifica for informada, cada servico usa o fallback de `IMAGE_TAG`.
3. A imagem da aplicação cria automaticamente em runtime as pastas `backups`, `uploads/attachments`, `uploads/attachments_preview`, `uploads/images` e `uploads/users`, inclusive quando os volumes estão vazios.
4. Se precisar ajustar upload em lote sem alterar código, configure `PHP_MAX_FILE_UPLOADS` nas variaveis da stack. Exemplo: `100` permite receber ate 100 arquivos por requisicao, desde que `PHP_POST_MAX_SIZE` e `PHP_UPLOAD_MAX_FILESIZE` continuem compatíveis com o volume enviado.
5. Confirme se os containers `infodoc-web`, `infodoc-assinador` e `infodoc-file-storage-worker` ficaram em estado `running`.
6. Valide no Portainer se os tres servicos estao conectados a rede padrao da stack e tambem a rede externa definida em `PROXY_EXTERNAL_NETWORK`.

## Checklist final antes do deploy

1. Confirme que a rede Docker externa `proxy` ja existe na VPS.
2. Confirme que o DNS de `gea.infodocsisged.com.br` e `assinador.infodocsisged.com.br` aponta para a VPS.
3. Confirme que o banco `sisged_gea` aceita conexao da VPS em `195.200.4.41:3306`.
4. Confirme que `FILE_STORAGE_R2_BUCKET=gea` esta definido nas variaveis da stack.
5. Confirme que `SIGNER_SECRET_KEY` nao esta com placeholder e ja usa uma chave forte.
6. Confirme que o compose path no Portainer aponta para `docker-compose.production.yml`.
7. Confirme que o repositório e a branch escolhidos no Portainer correspondem a esta versao com suporte a R2.
8. Confirme que as tags da stack estao corretas para cada servico. Exemplo: `WEB_IMAGE_TAG=1.0.18`, `WORKER_IMAGE_TAG=1.0.16` e `SIGNER_IMAGE_TAG=1.0.16`.
9. Confirme que o servidor consegue acessar `docker.io/cbotelho80` para fazer pull das imagens.
10. Confirme que `PHP_MAX_FILE_UPLOADS` atende o volume esperado de upload em lote.

## Validacoes apos o deploy

1. Abra `https://gea.seu-dominio.com.br` e valide o carregamento do GED.
2. Abra `https://assinador.seu-dominio.com.br/health` e confirme retorno HTTP 200.
3. Abra `https://assinador.seu-dominio.com.br/standalone/` e confirme o carregamento da interface do assinador.
4. Valide um fluxo real de assinatura iniciado pelo GED, porque esse fluxo depende de banco, token e arquivo PDF acessivel ao assinador.

## Validacao guiada do assinador com R2

1. Abra `https://assinador.infodocsisged.com.br/standalone/`.
2. Envie um PDF pequeno no modo standalone.
3. Confirme que o upload retorna sucesso e gera uma URL em `/standalone/uploads/<arquivo>`.
4. Abra essa URL no navegador e confirme que o PDF e servido normalmente.
5. Assine o PDF com um certificado ja presente em `/app/certs`.
6. Confirme que o retorno da assinatura informa `signed_file` com nome `assinado_...pdf`.
7. Abra `/standalone/download/<arquivo-assinado>` e confirme o download do PDF assinado.
8. No bucket `gea`, confirme a criacao de objetos sob o prefixo `ged/assinador-python/uploads/`.
9. Confirme no container do assinador que `/app/certs` continua local e que os certificados nao foram enviados ao R2.
10. Confirme que nao existe dependencia operacional do volume local `/app/uploads` na stack de producao, porque o fluxo do assinador agora usa R2 para esse diretorio.

## Diagnostico rapido

- Se a stack falhar ao subir, verifique primeiro se a rede `proxy` existe.
- Se o GED abrir mas o assinador nao, verifique o DNS e o proxy do dominio `assinador`.
- Se o navegador abrir o GED mas o redirecionamento para o assinador for incorreto, revise `PYTHON_SERVICE_PUBLIC_URL`.
- Se o GED nao conseguir chamar o assinador internamente, revise conectividade entre os containers e confirme que `PYTHON_SERVICE_INTERNAL_URL` continua como `http://assinador-python:5000` no compose.
- Se o assinador responder no navegador mas falhar em operacoes autenticadas, revise acesso ao banco, tabelas esperadas e caminhos persistidos dos documentos/certificados.
- Se o upload do standalone falhar, revise as variaveis `FILE_STORAGE_R2_*` no servico `assinador-python`.
- Se o download do PDF assinado falhar, verifique se o objeto foi gravado no bucket `gea` no prefixo `ged/assinador-python/uploads/`.
- Se os certificados sumirem do standalone, revise apenas o volume `signer_certs`, porque ele continua local e nao usa R2.