# Tutoriais Práticos – infodoc-sisged

Este documento reúne tutoriais ilustrados para os principais fluxos do sistema. Utilize-o em treinamentos, onboarding e suporte ao usuário.

---

## 1. Acesso ao Sistema

### Passo a Passo
1. Abra o navegador e acesse o endereço do sistema.
2. Informe seu usuário e senha.
3. Clique em **Entrar**.

> ![Tela de login](imagens/login.png)

---

## 2. Cadastro de Usuário (Administrador)

### Passo a Passo
1. No menu administrativo, acesse **Usuários**.
2. Clique em **Novo Usuário**.
3. Preencha os campos obrigatórios (nome, e-mail, grupo, etc).
4. Defina permissões e clique em **Salvar**.

> ![Cadastro de usuário](imagens/cadastro_usuario.png)

---

## 3. Upload de Documentos

### Passo a Passo
1. Clique em **Novo Documento** ou **Upload**.
2. Preencha os campos obrigatórios.
3. Selecione o arquivo e clique em **Salvar**.

> ![Upload de documento](imagens/upload_documento.png)

---

## 4. Organização em Pastas

### Passo a Passo
1. Clique em **Nova Pasta**.
2. Dê um nome à pasta e salve.
3. Arraste e solte documentos para dentro da pasta.

> ![Organização em pastas](imagens/organizacao_pastas.png)

---

## 5. Busca e Filtros

### Passo a Passo
1. Use o campo de busca para localizar documentos.
2. Utilize filtros para refinar os resultados (data, tipo, autor).

> ![Busca avançada](imagens/busca_avancada.png)

---

## 6. Download e Compartilhamento

### Passo a Passo
1. Clique sobre o documento desejado.
2. Selecione **Download**.
3. Para compartilhar, clique em **Compartilhar** e gere um link ou envie para outro usuário.

> ![Compartilhamento](imagens/compartilhar_documento.png)

---

## 7. Controle de Versões

### Passo a Passo
1. Envie um novo arquivo com o mesmo nome para criar nova versão.
2. Consulte o histórico de versões no detalhe do documento.

> ![Histórico de versões](imagens/historico_versoes.png)

---

## 8. Permissões Avançadas

### Passo a Passo
1. No cadastro ou edição de documentos, clique em **Permissões**.
2. Defina os usuários ou grupos com acesso.
3. Salve as configurações.

> ![Permissões avançadas](imagens/permissoes_avancadas.png)

---

## 9. Auditoria e Histórico

### Passo a Passo
1. Acesse o módulo **Auditoria** ou **Histórico**.
2. Filtre por usuário, data ou ação.
3. Consulte detalhes de cada evento.

> ![Tela de auditoria](imagens/auditoria.png)

---

## 10. Relatórios

### Passo a Passo
1. Clique em **Relatórios** no menu.
2. Escolha o tipo de relatório e defina os filtros.
3. Exporte em PDF, Excel ou CSV.

> ![Relatórios](imagens/relatorios.png)

---

## 11. Integração via API

### Passo a Passo
1. Solicite ao administrador um token de API.
2. Faça requisições HTTP para os endpoints REST.
3. Exemplo:
   ```bash
   curl -X POST http://localhost/infodoc-sisged/api/rest.php -d 'token=SEU_TOKEN&action=list_documents'
   ```

---

## 12. Configurações do Usuário

### Passo a Passo
1. Clique no seu nome ou avatar no topo da tela.
2. Altere senha, idioma ou preferências.
3. Salve as alterações.

> ![Configurações do usuário](imagens/configuracoes_usuario.png)

---

**Dica:** Utilize este material em treinamentos presenciais, remotos ou como base para vídeos e apresentações.
