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
php artisan migrate:fresh --force

# Iniciar Apache
echo "Iniciando Apache..."
apache2-foreground
