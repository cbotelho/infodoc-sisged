# Copilot Instructions for infodoc-sisged

## Visão Geral
Este projeto é um sistema de Gestão Eletrônica de Documentos (GED) modular, extensível e seguro, desenvolvido em PHP. A arquitetura é baseada em diretórios especializados para APIs, módulos, plugins, automações e núcleo da aplicação.

## Estrutura e Componentes-Chave
- **api/**: Endpoints REST e integrações externas (ex: ipn.php, rest.php)
- **ecm/**: Funcionalidades centrais de gestão documental
- **cron/**: Scripts de automação (backup, notificações, importações)
- **includes/**: Núcleo da aplicação (classes, funções, libs, padrões)
- **modules/** e **plugins/**: Expansão de funcionalidades sem alterar o core
- **config/**: Configurações sensíveis (banco, segurança, servidor)
- **template/** e **css/**: Temas, skins e personalização visual
- **uploads/**, **cache/**, **tmp/**: Armazenamento de arquivos e dados temporários

## Convenções e Padrões
- **Modularidade**: Novas funcionalidades devem ser implementadas como módulos ou plugins, nunca alterando diretamente o núcleo em includes/.
- **Internacionalização**: Utilize includes/languages/ para strings e traduções.
- **Segurança**: Sempre valide permissões e utilize funções de proteção CSRF e autenticação presentes em includes/.
- **Automação**: Scripts em cron/ seguem padrão de execução autônoma e podem ser agendados via cron do sistema.
- **Integração**: APIs externas devem ser implementadas em api/ e seguir padrão REST.

## Fluxos de Desenvolvimento
- **Configuração**: Ajuste config/database.php e permissões de pastas antes de rodar localmente.
- **Debug**: Utilize scripts de teste em assinatura/ e ecm/ para validação de integrações e uploads.
- **Personalização**: Temas e templates podem ser modificados em template/ e css/.
- **Documentação**: Consulte docs/ para arquitetura, exemplos e guias rápidos.

## Exemplos de Arquivos Importantes
- **api/rest.php**: Exemplo de endpoint REST
- **ecm/get_registro.php**: Operação de consulta documental
- **cron/backup.php**: Script de automação de backup
- **includes/application_core.php**: Núcleo de inicialização
- **config/database.php**: Configuração de banco de dados

## Observações
- Não altere arquivos em vendor/ ou libs/ diretamente.
- Sempre consulte a documentação interna de cada módulo/plugin antes de modificar.
- Siga a estrutura de diretórios para manter a organização e facilitar futuras integrações.

---
Consulte readme.md e docs/ para detalhes adicionais sobre arquitetura e fluxos.
