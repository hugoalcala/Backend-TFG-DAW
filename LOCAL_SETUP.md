# 🚀 Configuración para Desarrollo Local - Backend

## Prerrequisitos

- PHP 8.1+ (o ver `composer.json`)
- MySQL o base de datos compatible
- Composer
- Node.js (para npm si lo necesitas)

---

## Instalación Inicial

```bash
# 1. Clonar repositorio
git clone https://github.com/hugoalcala/Backend-TFG-DAW.git
cd Backend-TFG-DAW

# 2. Instalar dependencias
composer install

# 3. Copiar archivo de entorno
cp .env.example .env

# 4. Generar APP_KEY
php artisan key:generate

# 5. Crear base de datos (opcional, depende del servidor MySQL)
# Crea una DB llamada 'educonnect' manualmente o:
# mysql -u root -p < database/dump.sql

# 6. Ejecutar migraciones
php artisan migrate --seed

# 7. Iniciar servidor
php artisan serve
```

---

## Archivo `.env` para Desarrollo Local

```env
APP_NAME=EduConnect
APP_ENV=local
APP_KEY=base64:qtsO4bHQ4Cw4CbNCq2UEecQyYnoHBc1kKPc+edrz9Mk=
APP_DEBUG=true
APP_URL=http://localhost:8000

# Base de datos - MySQL Local
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=educonnect
DB_USERNAME=root
DB_PASSWORD=Admin

# Desarrollo
SESSION_DRIVER=database
CACHE_STORE=database
QUEUE_CONNECTION=database

# CORS - Local (ya configurado en config/cors.php)
# Agrega localhost:5173 para tu frontend Vite

# Google OAuth (opcional para desarrollo)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_CLIENT_CALLBACK_URL=http://localhost:8000/api/auth/google/callback
```

---

## ✅ Verificar que funciona

```bash
# Desde otra terminal:
curl http://localhost:8000/api/teachers -X GET

# Deberías ver una lista de profesores en JSON
```

---

## 🔄 Comando útiles en desarrollo

```bash
# Ver rutas disponibles
php artisan route:list

# Ejecutar migraciones específicas
php artisan migrate:fresh --seed

# Generar modelo con migraciones
php artisan make:model ModelName -m

# Limpiar cache
php artisan cache:clear
php artisan config:clear

# Ver logs
tail -f storage/logs/laravel.log
```

---

## 📝 Estructura del Backend

```
routes/api.php          → Todas las rutas API
app/Http/Controllers/   → Lógica de las rutas
app/Models/             → Modelos (User, Post, etc)
database/migrations/    → Cambios en BD
database/seeders/       → Datos iniciales
config/cors.php         → Configuración CORS
config/auth.php         → Autenticación
```

---

## 🚨 Problemas Comunes

**Error: SQLSTATE[HY000] [1045] Access denied for user**
- Base de datos MySQL no está ejecutándose
- Usuario/contraseña en `.env` es incorrecta
- Solución: Verifica `DB_USERNAME` y `DB_PASSWORD`

**Error: Composer packages not found**
```bash
composer dump-autoload
php artisan config:clear
```

**Error: Migration not found**
```bash
php artisan migrate:fresh --seed
```

**CORS error desde frontend**
- Verifica que `http://localhost:5173` está en `config/cors.php`
- Reinicia el servidor: `php artisan serve`

---

## 🎯 Frontend conectado

El frontend local (http://localhost:5173) ya está configurado para conectarse a:
```
http://localhost:8000/api
```

Verifica que `VITE_API_BASE_URL=http://localhost:8000/api` en `.env.local` del frontend.

---

## 📦 Despliegue a Producción

Cuando esté listo, el backend usa `render.yaml` para deployment automático a Render:

```bash
git add .
git commit -m "chore: ready for production"
git push origin dev
# Render automáticamente hace deploy desde render.yaml
```

Ver `render.yaml` para más detalles.
