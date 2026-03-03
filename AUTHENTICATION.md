# Guía de Configuración de Autenticación

## Endpoints Disponibles

### 1. Registro de Usuario
**POST** `/api/register`

```json
{
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

**Respuesta (201)**:
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "created_at": "2026-02-04T12:00:00Z",
    "updated_at": "2026-02-04T12:00:00Z"
  },
  "token": "1|abc123xyz..."
}
```

### 2. Inicio de Sesión
**POST** `/api/login`

```json
{
  "email": "juan@example.com",
  "password": "password123"
}
```

**Respuesta (200)**:
```json
{
  "message": "Login successful",
  "user": {
    "id": 1,
    "name": "Juan Pérez",
    "email": "juan@example.com"
  },
  "token": "1|abc123xyz..."
}
```

### 3. Cerrar Sesión
**POST** `/api/logout`

Requiere: `Authorization: Bearer {token}`

**Respuesta (200)**:
```json
{
  "message": "Logged out successfully"
}
```

### 4. Obtener Datos del Usuario Actual
**GET** `/api/me`

Requiere: `Authorization: Bearer {token}`

**Respuesta (200)**:
```json
{
  "id": 1,
  "name": "Juan Pérez",
  "email": "juan@example.com",
  "created_at": "2026-02-04T12:00:00Z",
  "updated_at": "2026-02-04T12:00:00Z"
}
```

## Autenticación con Google

### Configuración Inicial

1. **Obtener credenciales de Google** en [Google Cloud Console](https://console.cloud.google.com/)
   - Crear nuevo proyecto
   - Habilitar Google+ API
   - Crear credenciales OAuth 2.0 (tipo: web)
   - Obtener Client ID y Client Secret

2. **Actualizar variables de entorno** en `.env`:
```env
GOOGLE_CLIENT_ID=tu_client_id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=tu_client_secret
GOOGLE_REDIRECT_URI=http://localhost:3000/auth/google/callback
```

### Flujo de Autenticación Con Google

#### Paso 1: Obtener URL de Autorización
**GET** `/api/auth/google`

**Respuesta (200)**:
```json
{
  "google_auth_url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=...&redirect_uri=...&scope=..."
}
```

El frontend debe redirigir al usuario a esta URL. Google redirigirá de vuelta a `GOOGLE_REDIRECT_URI` con un código de autorización.

#### Paso 2: Intercambiar Código por Token Local
Cuando Google redirige de vuelta a tu frontend con el código, envía:

**POST** `/api/auth/google/callback`

```json
{
  "code": "codigo_de_autorizacion_de_google"
}
```

**Respuesta (200)**:
```json
{
  "message": "Google authentication successful",
  "user": {
    "id": 2,
    "name": "Usuario Google",
    "email": "usuario@gmail.com",
    "google_id": "123456789",
    "created_at": "2026-02-04T12:00:00Z",
    "updated_at": "2026-02-04T12:00:00Z"
  },
  "token": "2|xyz789abc..."
}
```

### Flujo Recomendado (Frontend)

```javascript
// 1. Obtener URL de Google
const response = await fetch('http://localhost:8000/api/auth/google');
const data = await response.json();

// 2. Redirigir usuario a Google
window.location.href = data.google_auth_url;

// 3. Después de que Google redirija (en callback page)
const code = new URLSearchParams(window.location.search).get('code');

// 4. Intercambiar código por token
const authResponse = await fetch('http://localhost:8000/api/auth/google/callback', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ code })
});

const authData = await authResponse.json();
localStorage.setItem('token', authData.token);
```

## Seguridad - Headers Requeridos

Para todas las rutas protegidas, incluye el header:
```
Authorization: Bearer {token}
```

## Campos de Usuario

```sql
{
  id: integer (Primary Key)
  name: string
  email: string (unique)
  password: string (hasheada)
  google_id: string (nullable, unique)
  google_token: string (nullable)
  google_refresh_token: string (nullable)
  google_token_expires_at: timestamp (nullable)
  email_verified_at: timestamp (nullable)
  remember_token: string (nullable)
  created_at: timestamp
  updated_at: timestamp
}
```

## Cambios Implementados

1. ✅ **Modelo User** actualizado con campos de Google
2. ✅ **Migración** creada para agregar campos de Google
3. ✅ **AuthController** actualizado con métodos:
   - `register()` - Crear cuenta
   - `login()` - Iniciar sesión
   - `logout()` - Cerrar sesión
   - `googleRedirect()` - Obtener URL de Google
   - `googleCallback()` - Procesar callback de Google
4. ✅ **Rutas API** configuradas
5. ✅ **Configuración** de servicios actualizada
6. ✅ **Variables de entorno** agregadas

## Notas Importantes

- Los tokens son válidos indefinidamente hasta que se ejecute logout
- Google OAuth usa cURL en lugar de Socialite para evitar problemas de dependencias
- Los refresh tokens de Google se almacenan para futuro uso
- El email es único en la base de datos (no puedes tener 2 cuentas con el mismo email)
- Si un usuario se registra con email y luego intenta autenticarse con Google con el mismo email, se actualiza la cuenta existente
