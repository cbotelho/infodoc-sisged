# infodoc-sisged

**Sistema Integrado de Gestão Eletrônica de Documentos (GED)**

## Visão Geral

O `infodoc-sisged` é uma plataforma robusta para gestão eletrônica de documentos, desenvolvida em PHP, com arquitetura modular e extensível, oferece recursos avançados de automação, controle de acesso, integração com APIs e personalização visual.

## Principais Funcionalidades

- **Gestão de Documentos**: Upload, organização, controle de versões e permissões.
- **Automação**: Scripts de tarefas agendadas para backup, notificações, importações e mais.
- **Módulos e Plugins**: Estrutura para expansão de funcionalidades sem alterar o núcleo.
- **Internacionalização**: Suporte a múltiplos idiomas.
- **Segurança**: Proteção CSRF, autenticação, controle de permissões, logs e auditoria.
- **Frontend Personalizável**: Temas, templates e recursos visuais modernos.
- **APIs**: Endpoints REST e integrações externas (telefonia, e-mail, etc).

## Estrutura do Projeto

```
api/         # Endpoints externos
config/      # Arquivos de configuração
cron/        # Scripts de automação
css/         # Estilos CSS
ecm/         # Módulos de gestão documental
includes/    # Núcleo da aplicação (classes, funções, libs)
js/          # Scripts JavaScript
modules/     # Módulos adicionais
plugins/     # Plugins e extensões
template/    # Templates e temas
uploads/     # Uploads de usuários
...
```

## Instalação

1. **Pré-requisitos**

   - PHP 7.4 ou superior
   - MySQL/MariaDB
   - Servidor Web (Apache, Nginx)
   - Extensões PHP: `gd`, `mbstring`, `curl`, etc.
2. **Configuração**

   - Clone o repositório para o diretório do seu servidor web.
   - Configure o arquivo `config/database.php` com os dados do seu banco de dados.
   - Ajuste permissões das pastas `uploads/`, `cache/`, `tmp/` e `log/` para leitura e escrita pelo servidor web.
   - Importe o banco de dados (ver instruções ou arquivos SQL fornecidos).
3. **Acesso**

   - Acesse o sistema via navegador: `http://localhost/infodoc-sisged` (ajuste conforme seu ambiente).

## Docker Local

O projeto pode ser executado localmente com Docker Compose usando dois serviços:

- `web`: PHP + Apache para a aplicação GED.
- `assinador-python`: Flask + Gunicorn para o módulo de assinatura.

### Arquivos adicionados

- `Dockerfile`: imagem local do PHP/Apache.
- `assinador-python/Dockerfile`: imagem local do assinador Python.
- `.env.docker.example`: variáveis de ambiente de exemplo para o Compose.

### Passos rápidos

1. Copie `.env.docker.example` para `.env` na raiz do projeto e ajuste os dados do banco.
2. Suba os containers:

   ```bash
   docker compose up --build
   ```

3. Acesse:

   - GED PHP: `http://localhost:8081`
   - Assinador standalone: `http://localhost:5000/standalone/`

### Observações

- Se o banco estiver na sua máquina Windows, use `host.docker.internal` como host.
- O PHP conversa com o assinador pela rede interna do Docker usando `http://assinador-python:5000`.
- A URL pública do assinador para o navegador fica configurada separadamente.

## Produção com Portainer

Para deploy na VPS via Portainer, use o arquivo `docker-compose.production.yml` em uma Stack criada pelo modo Repository.

### Arquivos para produção

- `docker-compose.production.yml`: stack de produção com restart automático, rede externa de proxy e volumes persistentes.
- `.env.production.portainer.example`: exemplo de variáveis para colar e ajustar no Portainer.
- `docs/portainer-duas-aplicacoes.md`: passo a passo para publicar o GED e o assinador em domínios separados.

### Fluxo recomendado

1. No Portainer, acesse Stacks e escolha Add stack.
2. Selecione a opção Repository.
3. Informe a URL do repositório, a branch desejada e o caminho do compose como `docker-compose.production.yml`.
4. Configure as variáveis usando `.env.production.portainer.example`.
5. Faça o deploy da stack.

### Cenário recomendado para duas aplicações públicas

Este projeto suporta o cenário em que o GED PHP e o assinador Python ficam em domínios distintos, desde que o navegador use a URL pública do assinador e o backend PHP continue chamando o serviço internamente pela rede Docker.

Exemplo:

- `APP_BASE_URL=https://gea.seu-dominio.com.br`
- `PYTHON_SERVICE_PUBLIC_URL=https://assinador.seu-dominio.com.br`

Nesse modelo:

- o domínio `gea.seu-dominio.com.br` aponta para o container `web`;
- o domínio `assinador.seu-dominio.com.br` aponta para o container `assinador-python`;
- o backend PHP continua chamando o assinador internamente por `http://assinador-python:5000`.

### Alternativa com rota explícita no mesmo domínio

Se preferir publicar tudo no mesmo domínio, o projeto também suporta rota explícita para o serviço Flask.

Exemplo:

- `APP_BASE_URL=https://gea.seu-dominio.com.br`
- `PYTHON_SERVICE_PUBLIC_URL=https://gea.seu-dominio.com.br/assinador`

Nesse modelo:

- o domínio principal continua apontando para o container `web`;
- a rota explícita `/assinador` deve ser encaminhada pelo proxy reverso para o container `assinador-python` na porta `5000`;
- o backend PHP continua chamando o assinador internamente por `http://assinador-python:5000`.

Exemplos prontos de proxy reverso:

- Apache: `docs/proxy-rota-explicita-apache.conf.example`
- Nginx: `docs/proxy-rota-explicita-nginx.conf.example`

### Observações de produção

- O `web` fala com o assinador internamente por `http://assinador-python:5000`.
- Os dados persistentes ficam em volumes Docker, sem depender de bind mount do código fonte.
- O modo Web editor do Portainer não é indicado para este compose, porque o build usa contextos locais do repositório.
- A rede externa definida por `PROXY_EXTERNAL_NETWORK` precisa existir antes do deploy da stack.
- Se optar por rota explícita no domínio atual, publique essa rota no proxy reverso em vez de expor a porta `5000` diretamente para o usuário final.
- A stack aceita `IMAGE_TAG` como fallback global e também tags por serviço: `WEB_IMAGE_TAG`, `WORKER_IMAGE_TAG` e `SIGNER_IMAGE_TAG`.

## Licença

Distribuído sob a licença [GNU GPLv3](https://www.gnu.org/licenses/gpl-3.0.html).

## Créditos

Nome do sistema InfoDoc-SisGed
Autores originais: ECM Tecnologia e Soluções
---

**Observação:**
Este projeto contém diversos módulos, plugins e scripts avançados. Consulte a documentação interna de cada componente para detalhes sobre personalização e desenvolvimento.
