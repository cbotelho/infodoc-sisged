#!/bin/bash
# setup_database.sh - Script de configuração do banco de dados
# Cria as tabelas necessárias para o assinador Python

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${YELLOW}=== Configuração do Banco de Dados - Assinador Python ===${NC}"

# Carregar variáveis do arquivo .env
if [ -f ../.env ]; then
    source ../.env
    echo -e "${GREEN}✓ Arquivo .env carregado${NC}"
else
    echo -e "${RED}✗ Arquivo .env não encontrado. Use .env.example como template.${NC}"
    exit 1
fi

# Verificar se o MySQL está acessível
echo -n "Verificando conexão com MySQL... "
if mysqladmin ping -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" --silent; then
    echo -e "${GREEN}OK${NC}"
else
    echo -e "${RED}FALHOU${NC}"
    echo "Verifique as credenciais no arquivo .env"
    exit 1
fi

# Executar script SQL
echo "Criando tabelas no banco $DB_NAME..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" < create_tables.sql

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✓ Tabelas criadas com sucesso!${NC}"
else
    echo -e "${RED}✗ Erro ao criar tabelas${NC}"
    exit 1
fi

# Verificar se as tabelas foram criadas
TABLES=$(mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" -N -B -e "SHOW TABLES FROM $DB_NAME LIKE 'sessoes_assinatura'")
if [ -n "$TABLES" ]; then
    echo -e "${GREEN}✓ Tabela 'sessoes_assinatura' encontrada${NC}"
else
    echo -e "${RED}✗ Tabela 'sessoes_assinatura' não foi criada${NC}"
fi

# Criar índices para performance
echo "Criando índices adicionais..."
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" <<EOF
CREATE INDEX IF NOT EXISTS idx_token ON sessoes_assinatura(token);
CREATE INDEX IF NOT EXISTS idx_data ON sessoes_assinatura(data_inicio);
EOF

echo -e "${GREEN}✅ Configuração concluída com sucesso!${NC}"
echo ""
echo "Resumo:"
echo "- Host: $DB_HOST"
echo "- Banco: $DB_NAME"
echo "- Usuário: $DB_USER"
echo ""
echo "Próximos passos:"
echo "1. Ative o ambiente virtual: source ../venv/bin/activate"
echo "2. Execute o serviço: python ../run.py"