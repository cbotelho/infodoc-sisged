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

## Licença

Distribuído sob a licença [GNU GPLv3](https://www.gnu.org/licenses/gpl-3.0.html).

## Créditos

Nome do sistema InfoDoc-SisGed
Autores originais: ECM Tecnologia e Soluções
---

**Observação:**
Este projeto contém diversos módulos, plugins e scripts avançados. Consulte a documentação interna de cada componente para detalhes sobre personalização e desenvolvimento.
