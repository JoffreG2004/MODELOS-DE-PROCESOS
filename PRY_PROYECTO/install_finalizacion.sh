#!/bin/bash

##############################################
# INSTALACIÓN RÁPIDA - SISTEMA DE FINALIZACIÓN
# Ejecutar: bash install_finalizacion.sh
##############################################

echo "================================================"
echo "🚀 INSTALACIÓN: Sistema de Finalización Manual"
echo "================================================"
echo ""

# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Directorio del proyecto
PROJECT_DIR="/opt/lampp/htdocs/MODELOS-DE-PROCESOS/PRY_PROYECTO"

# Verificar que estamos en el directorio correcto
if [ ! -f "$PROJECT_DIR/sql/mejoras_reservas_finalizacion.sql" ]; then
    echo -e "${RED}❌ Error: No se encuentra el archivo SQL${NC}"
    echo "Asegúrate de estar en el directorio correcto"
    exit 1
fi

# PASO 1: Ejecutar script SQL
echo -e "${YELLOW}📊 PASO 1: Ejecutando script SQL...${NC}"
echo ""

read -p "Usuario de MySQL (default: root): " MYSQL_USER
MYSQL_USER=${MYSQL_USER:-root}

read -sp "Contraseña de MySQL: " MYSQL_PASS
echo ""

# Ejecutar SQL
mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" crud_proyecto < "$PROJECT_DIR/sql/mejoras_reservas_finalizacion.sql"

if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Base de datos actualizada correctamente${NC}"
else
    echo -e "${RED}❌ Error al ejecutar SQL. Verifica usuario y contraseña.${NC}"
    exit 1
fi

echo ""

# PASO 2: Verificar campos
echo -e "${YELLOW}🔍 PASO 2: Verificando instalación...${NC}"

RESULT=$(mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" crud_proyecto -e "SHOW COLUMNS FROM reservas WHERE Field = 'duracion_estimada'" --batch --skip-column-names)

if [ -n "$RESULT" ]; then
    echo -e "${GREEN}✅ Campo 'duracion_estimada' creado${NC}"
else
    echo -e "${RED}❌ Error: Campo 'duracion_estimada' no existe${NC}"
    exit 1
fi

# Verificar procedimiento
PROC=$(mysql -u "$MYSQL_USER" -p"$MYSQL_PASS" crud_proyecto -e "SHOW PROCEDURE STATUS WHERE Name = 'activar_reservas_programadas'" --batch --skip-column-names)

if [ -n "$PROC" ]; then
    echo -e "${GREEN}✅ Procedimiento 'activar_reservas_programadas' creado${NC}"
else
    echo -e "${RED}❌ Warning: Procedimiento no encontrado${NC}"
fi

echo ""

# PASO 3: Configurar variables de entorno
echo -e "${YELLOW}📝 PASO 3: Configurar variables de entorno${NC}"

ENV_FILE="$PROJECT_DIR/.env"

if [ ! -f "$ENV_FILE" ]; then
    echo -e "${YELLOW}⚠️  Archivo .env no existe, creando...${NC}"
    touch "$ENV_FILE"
fi

# Agregar configuraciones si no existen
if ! grep -q "N8N_WEBHOOK_NOSHOW" "$ENV_FILE"; then
    echo "" >> "$ENV_FILE"
    echo "# Notificaciones No-Show" >> "$ENV_FILE"
    echo "N8N_WEBHOOK_NOSHOW=http://localhost:5678/webhook/reserva-noshow" >> "$ENV_FILE"
    echo "ADMIN_EMAIL=admin@lesalondelumiere.com" >> "$ENV_FILE"
    echo "ADMIN_NAME=Administrador" >> "$ENV_FILE"
    echo "ADMIN_PHONE=+593999999999" >> "$ENV_FILE"
    echo -e "${GREEN}✅ Variables agregadas a .env${NC}"
else
    echo -e "${GREEN}✅ Variables ya existen en .env${NC}"
fi

echo ""

# PASO 4: Configurar cron job (opcional)
echo -e "${YELLOW}⏰ PASO 4: Configurar cron job (opcional)${NC}"
read -p "¿Deseas configurar el cron job para notificaciones automáticas? (s/n): " SETUP_CRON

if [ "$SETUP_CRON" = "s" ] || [ "$SETUP_CRON" = "S" ]; then
    CRON_CMD="*/5 * * * * /usr/bin/php $PROJECT_DIR/scripts/enviar_notificaciones_noshow.php >> $PROJECT_DIR/logs/noshow.log 2>&1"
    
    # Verificar si ya existe
    crontab -l 2>/dev/null | grep -q "enviar_notificaciones_noshow.php"
    
    if [ $? -eq 0 ]; then
        echo -e "${GREEN}✅ Cron job ya existe${NC}"
    else
        # Agregar cron job
        (crontab -l 2>/dev/null; echo "$CRON_CMD") | crontab -
        echo -e "${GREEN}✅ Cron job agregado (ejecuta cada 5 minutos)${NC}"
    fi
else
    echo -e "${YELLOW}⏭️  Saltando configuración de cron job${NC}"
    echo "Puedes ejecutar manualmente: php scripts/enviar_notificaciones_noshow.php"
fi

echo ""

# PASO 5: Crear directorio de logs
echo -e "${YELLOW}📁 PASO 5: Crear directorio de logs${NC}"

mkdir -p "$PROJECT_DIR/logs"
touch "$PROJECT_DIR/logs/noshow.log"
chmod 666 "$PROJECT_DIR/logs/noshow.log"

echo -e "${GREEN}✅ Directorio de logs creado${NC}"

echo ""
echo "================================================"
echo -e "${GREEN}✅ INSTALACIÓN COMPLETADA${NC}"
echo "================================================"
echo ""
echo "📋 PRÓXIMOS PASOS:"
echo ""
echo "1. Configurar N8N Workflow:"
echo "   - URL: http://localhost:5678"
echo "   - Crear webhook: /webhook/reserva-noshow"
echo "   - Ver guía: docs/INSTALACION_FINALIZACION_MANUAL.md"
echo ""
echo "2. Probar el sistema:"
echo "   php $PROJECT_DIR/scripts/enviar_notificaciones_noshow.php"
echo ""
echo "3. Ver panel admin:"
echo "   http://localhost/PRY_PROYECTO/admin.php"
echo ""
echo "📖 Documentación completa:"
echo "   $PROJECT_DIR/docs/INSTALACION_FINALIZACION_MANUAL.md"
echo ""
echo "================================================"
