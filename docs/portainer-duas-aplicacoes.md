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

## Variaveis recomendadas

```env
APP_PORT=8081
SIGNER_PORT=5000
APP_BASE_URL=https://gea.seu-dominio.com.br
PYTHON_SERVICE_PUBLIC_URL=https://assinador.seu-dominio.com.br
PROXY_EXTERNAL_NETWORK=proxy

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

## Fazer o deploy

1. Clique em `Deploy the stack`.
2. Aguarde o build das imagens `web` e `assinador-python`.
3. Confirme se os containers `infodoc-web` e `infodoc-assinador` ficaram em estado `running`.
4. Valide no Portainer se ambos estao conectados a rede padrao da stack e tambem a rede externa definida em `PROXY_EXTERNAL_NETWORK`.

## Validacoes apos o deploy

1. Abra `https://gea.seu-dominio.com.br` e valide o carregamento do GED.
2. Abra `https://assinador.seu-dominio.com.br/health` e confirme retorno HTTP 200.
3. Abra `https://assinador.seu-dominio.com.br/standalone/` e confirme o carregamento da interface do assinador.
4. Valide um fluxo real de assinatura iniciado pelo GED, porque esse fluxo depende de banco, token e arquivo PDF acessivel ao assinador.

## Diagnostico rapido

- Se a stack falhar ao subir, verifique primeiro se a rede `proxy` existe.
- Se o GED abrir mas o assinador nao, verifique o DNS e o proxy do dominio `assinador`.
- Se o navegador abrir o GED mas o redirecionamento para o assinador for incorreto, revise `PYTHON_SERVICE_PUBLIC_URL`.
- Se o GED nao conseguir chamar o assinador internamente, revise conectividade entre os containers e confirme que `PYTHON_SERVICE_INTERNAL_URL` continua como `http://assinador-python:5000` no compose.
- Se o assinador responder no navegador mas falhar em operacoes autenticadas, revise acesso ao banco, tabelas esperadas e caminhos persistidos dos documentos/certificados.