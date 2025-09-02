#!/bin/bash

# Braga Agenda - Command Line Installer
# Usage: bash install.sh

set -e

echo "=================================="
echo "   Braga Agenda Installer v1.0   "
echo "=================================="
echo

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Functions
print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️ $1${NC}"
}

print_info() {
    echo -e "ℹ️ $1"
}

# Check if running as root
if [[ $EUID -eq 0 ]]; then
   print_warning "Este script não deve ser executado como root"
   echo "Execute como utilizador normal com sudo quando necessário"
   exit 1
fi

echo "1. Verificando sistema..."

# Detect OS
if [[ -f /etc/os-release ]]; then
    . /etc/os-release
    OS=$ID
    VERSION=$VERSION_ID
else
    print_error "Não foi possível detectar o sistema operativo"
    exit 1
fi

print_info "Sistema: $PRETTY_NAME"

# Check PHP version
echo
echo "2. Verificando PHP..."

if ! command -v php &> /dev/null; then
    print_error "PHP não está instalado"
    
    if [[ "$OS" == "ubuntu" || "$OS" == "debian" ]]; then
        echo "Instalar com: sudo apt install php"
    elif [[ "$OS" == "centos" || "$OS" == "rhel" || "$OS" == "fedora" ]]; then
        echo "Instalar com: sudo yum install php ou sudo dnf install php"
    fi
    exit 1
fi

PHP_VERSION=$(php -r "echo PHP_VERSION;")
print_success "PHP $PHP_VERSION instalado"

# Check required PHP extensions
echo
echo "3. Verificando extensões PHP..."

REQUIRED_EXTENSIONS=("pdo_sqlite" "curl" "mbstring" "dom" "libxml")
MISSING_EXTENSIONS=()

for ext in "${REQUIRED_EXTENSIONS[@]}"; do
    if php -m | grep -q "^$ext$"; then
        print_success "Extensão $ext encontrada"
    else
        print_error "Extensão $ext não encontrada"
        MISSING_EXTENSIONS+=($ext)
    fi
done

# Install missing extensions if any
if [ ${#MISSING_EXTENSIONS[@]} -ne 0 ]; then
    echo
    print_warning "Extensões PHP em falta detectadas"
    echo "Extensões necessárias: ${MISSING_EXTENSIONS[*]}"
    
    read -p "Tentar instalar automaticamente? (y/n): " -n 1 -r
    echo
    
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        echo "Instalando extensões..."
        
        if [[ "$OS" == "ubuntu" || "$OS" == "debian" ]]; then
            PACKAGES=""
            for ext in "${MISSING_EXTENSIONS[@]}"; do
                case $ext in
                    "pdo_sqlite") PACKAGES="$PACKAGES php-sqlite3" ;;
                    "curl") PACKAGES="$PACKAGES php-curl" ;;
                    "mbstring") PACKAGES="$PACKAGES php-mbstring" ;;
                    "dom") PACKAGES="$PACKAGES php-xml" ;;
                    "libxml") PACKAGES="$PACKAGES php-xml" ;;
                esac
            done
            
            if [ -n "$PACKAGES" ]; then
                sudo apt update
                sudo apt install -y $PACKAGES
                print_success "Extensões instaladas"
            fi
            
        elif [[ "$OS" == "centos" || "$OS" == "rhel" ]]; then
            PACKAGES=""
            for ext in "${MISSING_EXTENSIONS[@]}"; do
                case $ext in
                    "pdo_sqlite") PACKAGES="$PACKAGES php-pdo" ;;
                    "curl") PACKAGES="$PACKAGES php-curl" ;;
                    "mbstring") PACKAGES="$PACKAGES php-mbstring" ;;
                    "dom") PACKAGES="$PACKAGES php-xml" ;;
                    "libxml") PACKAGES="$PACKAGES php-xml" ;;
                esac
            done
            
            if [ -n "$PACKAGES" ]; then
                sudo yum install -y $PACKAGES
                print_success "Extensões instaladas"
            fi
            
        elif [[ "$OS" == "fedora" ]]; then
            PACKAGES=""
            for ext in "${MISSING_EXTENSIONS[@]}"; do
                case $ext in
                    "pdo_sqlite") PACKAGES="$PACKAGES php-pdo" ;;
                    "curl") PACKAGES="$PACKAGES php-curl" ;;
                    "mbstring") PACKAGES="$PACKAGES php-mbstring" ;;
                    "dom") PACKAGES="$PACKAGES php-xml" ;;
                    "libxml") PACKAGES="$PACKAGES php-xml" ;;
                esac
            done
            
            if [ -n "$PACKAGES" ]; then
                sudo dnf install -y $PACKAGES
                print_success "Extensões instaladas"
            fi
        fi
    else
        print_error "Instale as extensões manualmente antes de continuar"
        exit 1
    fi
fi

# Create directories
echo
echo "4. Criando estrutura de diretórios..."

DIRS=("data" "uploads")

for dir in "${DIRS[@]}"; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        print_success "Criado diretório: $dir/"
    else
        print_success "Diretório já existe: $dir/"
    fi
    
    chmod 755 "$dir"
done

# Initialize database
echo
echo "5. Inicializando base de dados..."

if php -r "
require_once 'includes/Database.php';
try {
    \$db = new Database();
    echo 'SUCCESS';
} catch (Exception \$e) {
    echo 'ERROR: ' . \$e->getMessage();
    exit(1);
}
" | grep -q "SUCCESS"; then
    print_success "Base de dados SQLite criada"
else
    print_error "Falha ao criar base de dados"
    exit 1
fi

# Setup scrapers
echo
echo "6. Configurando scrapers..."

if php setup-gnration-scraper.php > /dev/null 2>&1; then
    print_success "Scraper Gnration configurado"
else
    print_warning "Aviso: Scraper já pode estar configurado"
fi

# Create sample data
echo
echo "7. Criando dados de exemplo..."

php -r "
require_once 'includes/Database.php';
require_once 'includes/Event.php';

\$db = new Database();
\$eventModel = new Event(\$db);

\$sampleEvents = [
    [
        'title' => 'Concerto de Inauguração',
        'description' => 'Evento de exemplo para demonstrar o sistema',
        'event_date' => date('Y-m-d H:i:s', strtotime('+1 week')),
        'category' => 'Música',
        'url' => 'https://example.com/event1'
    ],
    [
        'title' => 'Exposição de Arte Contemporânea',
        'description' => 'Mostra de artistas locais de Braga',
        'event_date' => date('Y-m-d H:i:s', strtotime('+2 weeks')),
        'category' => 'Arte',
        'url' => 'https://example.com/event2'
    ]
];

foreach (\$sampleEvents as \$event) {
    \$eventModel->createEvent(
        \$event['title'],
        \$event['description'],
        \$event['event_date'],
        \$event['category'],
        null,
        \$event['url']
    );
}

echo count(\$sampleEvents) . ' eventos criados';
"

print_success "Dados de exemplo criados"

# Set permissions
echo
echo "8. Configurando permissões..."

chmod 644 config.php
chmod 755 *.php
chmod 755 admin/*.php

print_success "Permissões configuradas"

# Final steps
echo
echo "=================================="
print_success "Instalação concluída com sucesso!"
echo "=================================="
echo

print_info "Próximos passos:"
echo "• Aceder ao site: Abrir index.php no browser"
echo "• Painel admin: Abrir admin/ no browser"  
echo "• Testar scraper: php test-gnration-scraper.php"
echo "• Executar scrapers: php run-scrapers.php"
echo

print_info "Automatização (opcional):"
echo "Adicionar ao crontab para executar scrapers de hora em hora:"
echo "crontab -e"
echo "Adicionar linha: 0 * * * * cd $(pwd) && php run-scrapers.php"
echo

print_info "Segurança:"
echo "• Considere adicionar autenticação HTTP ao diretório admin/"
echo "• Configure firewall se necessário"
echo "• Mantenha backups regulares da base de dados"
echo

print_success "Braga Agenda está pronto para usar!"