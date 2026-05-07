#!/bin/bash
set -e

# Esperar a que la BD esté lista
echo "Esperando a que la base de datos esté lista..."
for i in {1..30}; do
    if php artisan tinker --execute="DB::connection()->getPDO();" 2>/dev/null; then
        echo "Base de datos lista!"
        break
    fi
    echo "Intento $i/30..."
    sleep 2
done

# Ejecutar migraciones
echo "Ejecutando migraciones..."
php artisan migrate:fresh --force --seed

# Iniciar Apache
echo "Iniciando Apache..."
apache2-foreground
