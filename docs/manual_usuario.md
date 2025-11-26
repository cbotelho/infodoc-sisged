# Manual do Usuário – infodoc-sisged

Bem-vindo ao sistema de Gestão Eletrônica de Documentos (GED) **infodoc-sisged**! Este manual apresenta, de forma simples e ilustrada, como utilizar as principais funções do sistema.

---

## 1. Apresentação
O infodoc-sisged é uma plataforma web para organizar, armazenar, buscar e compartilhar documentos digitais com segurança e praticidade.

---

## 2. Acesso ao Sistema

- Abra seu navegador e acesse o endereço fornecido pela sua empresa (ex: `http://localhost/infodoc-sisged` ou um domínio próprio).
- Você verá a tela de login.

---

## 3. Cadastro e Login

- **Login:** Informe seu usuário e senha e clique em **Entrar**.
- **Esqueceu a senha?** Clique em "Esqueci minha senha" e siga as instruções.
- **Primeiro acesso:** Caso o cadastro seja feito pelo administrador, você receberá os dados de acesso por e-mail.

---

## 4. Navegação pelo Menu

Após o login, o menu principal estará disponível (geralmente à esquerda ou no topo). Os módulos principais são:

- **Documentos:** Área central para upload, consulta e organização dos arquivos.
- **Pastas:** Estrutura para separar documentos por categorias, projetos ou setores.
- **Busca:** Pesquisa rápida e avançada de documentos.
- **Configurações:** Ajustes de perfil, senha, idioma e preferências.

---

## 5. Gestão de Documentos

### 5.1 Upload de Documentos
1. Clique em **Novo Documento** ou **Upload**.
2. Preencha os campos obrigatórios (nome, descrição, categoria, etc).
3. Clique em **Selecionar Arquivo** e escolha o arquivo desejado.
4. Clique em **Salvar**.

**Exemplo ilustrado:**
![Tela de upload de documento](imagens/upload_documento.png)

> *Dica: Capture a tela do formulário de upload e salve como `docs/imagens/upload_documento.png` para ilustrar este passo.*

### 5.2 Organização em Pastas
- Crie novas pastas clicando em **Nova Pasta**.
- Arraste e solte documentos para dentro das pastas (se disponível).

**Exemplo ilustrado:**
![Organização em pastas](imagens/organizacao_pastas.png)

### 5.3 Busca e Filtros
- Utilize o campo de busca para localizar documentos por nome, conteúdo ou tags.
- Use filtros por data, tipo de documento, responsável, etc.

**Exemplo ilustrado:**
![Busca avançada de documentos](imagens/busca_avancada.png)

### 5.4 Download e Compartilhamento
- Clique sobre o documento desejado e selecione **Download** para baixar.
- Para compartilhar, clique em **Compartilhar** e gere um link ou envie para um usuário específico.

**Exemplo ilustrado:**
![Opções de download e compartilhamento](imagens/compartilhar_documento.png)

### 5.5 Controle de Versões
- Ao enviar um novo arquivo com o mesmo nome, o sistema pode criar uma nova versão.
- Consulte o histórico de versões no detalhe do documento.

**Exemplo ilustrado:**
![Histórico de versões](imagens/historico_versoes.png)

---

## 6. Permissões e Compartilhamento

- Os documentos podem ter permissões restritas por usuário ou grupo.
- O administrador define quem pode visualizar, editar ou excluir cada documento.
- Para compartilhar, utilize a função **Compartilhar** e selecione os usuários ou grupos desejados.

---

## 7. Consulta de Histórico

- Cada documento possui um histórico de alterações e acessos.
- Para visualizar, acesse o detalhe do documento e clique em **Histórico**.

---

## 8. Configurações do Usuário

- Clique em seu nome ou avatar no topo da tela.
- Altere senha, idioma, foto de perfil e preferências pessoais.

---

## 8.1 Fluxos para Outros Módulos

### Auditoria e Histórico de Ações
- Acesse o módulo **Auditoria** ou **Histórico** no menu administrativo.
- Visualize logs de acesso, alterações e exclusões de documentos.
- Filtre por usuário, data, ação ou documento.

**Exemplo ilustrado:**
![Tela de auditoria](imagens/auditoria.png)

### Relatórios
- No menu principal, clique em **Relatórios**.
- Escolha o tipo de relatório (por usuário, por data, por pasta, etc).
- Defina filtros e exporte em PDF, Excel ou CSV.

**Exemplo ilustrado:**
![Tela de relatórios](imagens/relatorios.png)

### Permissões Avançadas
- No cadastro ou edição de documentos, clique em **Permissões**.
- Defina quais usuários ou grupos podem visualizar, editar ou excluir.
- Utilize permissões hierárquicas para facilitar a gestão em grandes equipes.

**Exemplo ilustrado:**
![Configuração de permissões](imagens/permissoes_avancadas.png)

---

## 9. Fluxos Específicos

### 9.1 Integração via API (REST)
O sistema permite integração com outros sistemas por meio de APIs RESTful.

**Exemplo de uso:**
1. Solicite ao administrador um token de acesso à API.
2. Realize requisições HTTP para os endpoints disponíveis, por exemplo:
   ```bash
   curl -X POST http://localhost/infodoc-sisged/api/rest.php -d 'token=SEU_TOKEN&action=list_documents'
   ```
3. Consulte a documentação técnica para detalhes dos parâmetros e formatos de resposta.

**Principais ações da API:**
- Listar documentos
- Fazer upload via API
- Buscar documentos por critérios
- Baixar arquivos

### 9.2 Cadastro de Usuários

#### a) Cadastro pelo Administrador
1. Acesse o módulo **Usuários** no menu administrativo.
2. Clique em **Novo Usuário**.
3. Preencha os dados obrigatórios (nome, e-mail, grupo, etc).
4. Defina permissões e grupos de acesso.
5. O novo usuário receberá um e-mail com as instruções de acesso (se configurado).

#### b) Auto-registro (quando habilitado)
1. Na tela de login, clique em **Registrar-se** ou **Criar Conta**.
2. Preencha o formulário de cadastro.
3. Confirme o e-mail (se necessário).
4. Aguarde aprovação do administrador (se exigido pela política da empresa).

### 9.3 Dicas Avançadas de Uso

- **Busca Avançada:** Use filtros combinados (data, tags, autor) para refinar resultados.
- **Favoritos:** Marque documentos como favoritos para acesso rápido.
- **Notificações:** Ative notificações para ser avisado sobre alterações em documentos importantes.
- **Histórico de Versões:** Consulte versões anteriores e restaure se necessário.
- **Exportação de Dados:** Utilize funções de exportação para gerar relatórios ou cópias de segurança dos seus documentos.
- **Atalhos de Teclado:** Alguns módulos oferecem atalhos para agilizar operações (consulte o menu de ajuda do sistema).
- **Personalização:** Ajuste preferências de idioma, tema e notificações no seu perfil.

---

## 10. Dúvidas Frequentes

- **Não consigo fazer upload:** Verifique o tamanho do arquivo e sua conexão.
- **Não encontro um documento:** Use filtros e verifique se possui permissão de acesso.
- **Erro de permissão:** Fale com o administrador do sistema.

---

## 11. Contato e Suporte

- Para dúvidas técnicas ou problemas, entre em contato com o suporte da sua empresa ou o administrador do sistema.
- Consulte também a seção de FAQ e a documentação técnica para mais informações.

---

**Dica:** Para melhor experiência, utilize navegadores atualizados (Google Chrome, Firefox, Edge).

---

*Este manual pode ser atualizado conforme novas funcionalidades forem implementadas.*
