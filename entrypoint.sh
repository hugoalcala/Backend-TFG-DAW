#!/bin/bash
set -e

# Esperar a que la BD esté lista
echo "Esperando a que la base de datos esté lista..."
for i in {1..30}; do
    if php artisan migrate:status --no-ansi > /dev/null 2>&1; then
        echo "Base de datos lista!"
        break
    fi
    echo "Intento $i/30, reintentando..."
    sleep 2
done

# Ejecutar migraciones
echo "Ejecutando migraciones..."
php artisan migrate --force

# Iniciar Apache
echo "Iniciando Apache..."
a2dismod mpm_event 2>/dev/null || true
a2enmod mpm_prefork 2>/dev/null || true
apache2-foreground
