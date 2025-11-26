# Guia Rápido do Usuário – infodoc-sisged

Este guia apresenta, de forma resumida, os principais passos para utilizar o sistema de Gestão Eletrônica de Documentos (GED) **infodoc-sisged**.

---

## 1. Acesso ao Sistema
- Abra o navegador e acesse o endereço fornecido pela empresa.
- Faça login com seu usuário e senha.

## 2. Upload de Documentos
1. Clique em **Novo Documento** ou **Upload**.
2. Preencha os campos obrigatórios.
3. Selecione o arquivo desejado e clique em **Salvar**.

## 3. Organização em Pastas
- Crie pastas em **Nova Pasta**.
- Arraste e solte documentos para organizar.

## 4. Busca e Filtros
- Utilize o campo de busca para localizar documentos.
- Use filtros por data, tipo ou responsável.

## 5. Download e Compartilhamento
- Clique no documento e selecione **Download**.
- Use **Compartilhar** para gerar link ou enviar a outro usuário.

## 6. Controle de Versões
- Envie um novo arquivo com mesmo nome para criar nova versão.
- Consulte o histórico de versões no detalhe do documento.

## 7. Permissões
- Defina quem pode visualizar, editar ou excluir documentos ao cadastrar ou editar.

## 8. Auditoria e Relatórios
- Consulte o módulo **Auditoria** para ver histórico de ações.
- Gere relatórios no menu **Relatórios**.

## 9. Configurações do Usuário
- Altere senha, idioma e preferências no seu perfil.

## 10. Integração via API
- Solicite ao administrador um token de API.
- Use comandos como:
  ```bash
  curl -X POST http://localhost/infodoc-sisged/api/rest.php -d 'token=SEU_TOKEN&action=list_documents'
  ```

## 11. Suporte
- Em caso de dúvidas, consulte o manual completo ou entre em contato com o suporte da empresa.

---

**Dica:** Para melhor experiência, utilize navegadores atualizados (Chrome, Firefox, Edge).
