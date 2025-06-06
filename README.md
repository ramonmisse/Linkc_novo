# Sistema de Pagamento - Gerenciamento de Links Cielo

Sistema web desenvolvido em PHP para gerenciamento de links de pagamento via API da Cielo, com controle de usuários e níveis de acesso.

## 🚀 Funcionalidades

- **Autenticação de Usuários**: Sistema de login/cadastro com 3 níveis de acesso (admin, editor, usuario)
- **Geração de Links**: Criação de links de pagamento com integração à API da Cielo
- **Cálculo de Juros**: Sistema automático de cálculo (0% até 3x, 4% de 4x a 6x)
- **Controle de Acesso**: Usuários veem apenas seus links, admins/editores veem todos
- **Atualização de Status**: Webhook e consultas manuais para atualizar status dos pagamentos
- **Interface Responsiva**: Design moderno com Bootstrap 5

## 📋 Pré-requisitos

- PHP 7.4 ou superior
- MySQL 5.7 ou MariaDB 10.3+
- Extensões PHP: PDO, cURL, JSON
- Servidor web (Apache/Nginx)
- Conta na Cielo para API (ambiente sandbox para testes)

## 🛠️ Instalação

### 1. Clone ou baixe os arquivos

Coloque todos os arquivos no diretório raiz do seu servidor web.

### 2. Configure o banco de dados

```bash
# Crie o banco de dados MySQL
mysql -u root -p < database.sql
