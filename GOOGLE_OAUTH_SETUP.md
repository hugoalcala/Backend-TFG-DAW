# Configuración de Google OAuth - Guía Paso a Paso

## 1. Acceder a Google Cloud Console

1. Ve a [Google Cloud Console](https://console.cloud.google.com/)
2. Inicia sesión con tu cuenta de Google
3. Haz clic en el selector de proyectos (parte superior)
4. Haz clic en "Nuevo Proyecto"

## 2. Crear un Nuevo Proyecto

1. Nombre del Proyecto: `EduConnect` (o el nombre que prefieras)
2. Organización: (dejar en blanco) 
3. Ubicación: (dejar por defecto)
4. Haz clic en "Crear"
5. Espera a que se complete la creación

## 3. Habilitar Google+ API

1. Desde el dashboard del proyecto, ve a "APIs y servicios" > "Biblioteca"
2. Busca "Google+ API"
3. Haz clic en "Google+ API"
4. Haz clic en el botón "Habilitar"

## 4. Crear Credenciales OAuth 2.0

1. Ve a "APIs y servicios" > "Credenciales"
2. Haz clic en "Crear Credenciales" > "ID de cliente OAuth"
3. Si se te pide que configures la pantalla de consentimiento:
   - Selecciona "Externo" (para desarrollo)
   - Haz clic en "Crear"

## 5. Configurar la Pantalla de Consentimiento

Si fue redirigido desde el paso anterior:

1. En "Información de la app":
   - Nombre de la app: `EduConnect`
   - Email de soporte del usuario: tu email
   - Información de contacto del desarrollador: tu email

2. Haz clic en "Guardar y continuar"

3. En "Permisos":
   - Haz clic en "Agregar o quitar permisos"
   - Busca estos scopes:
     - `openid`
     - `profile`
     - `email`
   - Selecciona cada uno y haz clic en "Actualizar"
   - Haz clic en "Guardar y continuar"

4. En "Usuarios de prueba":
   - Haz clic en "Agregar usuarios"
   - Añade el email de Google que usarás para probar
   - Haz clic en "Agregar"

5. Haz clic en "Guardar y continuar"

## 6. Crear ID de Cliente OAuth (Continuación)

Los pasos anteriores te llevarán aquí automáticamente:

1. Tipo de aplicación: Selecciona "Aplicación web"
2. Nombre: `EduConnect - Backend`
3. Orígenes autorizados de JavaScript:
   - `http://localhost:3000` (para desarrollo local)
   - `http://localhost:5173` (si usas Vite)
   - Agrega otros dominios según sea necesario

4. URIs de redirección autorizados:
   - `http://localhost:3000/auth/google/callback`
   - `http://localhost:5173/auth/google/callback`
   - Agrega el dominio real en producción

5. Haz clic en "Crear"

## 7. Copiar Credenciales

Se te mostrarán:
- **Client ID**: Un string terminado en `.apps.googleusercontent.com`
- **Client Secret**: Un string secreto (protégelo)

## 8. Actualizar Variables de Entorno

En tu archivo `.env`:

```env
GOOGLE_CLIENT_ID=COPIA_TU_CLIENT_ID_AQUI
GOOGLE_CLIENT_SECRET=COPIA_TU_CLIENT_SECRET_AQUI
GOOGLE_REDIRECT_URI=http://localhost:3000/auth/google/callback
```

En tu archivo frontend `.env`:

```env
VITE_GOOGLE_CLIENT_ID=COPIA_TU_CLIENT_ID_AQUI
VITE_API_URL=http://localhost:8000
```

## 9. Configurar el Frontend (Ejemplo con React/Vue)

### Con JavaScript puro:

```html
<!-- Botón para iniciar autenticación -->
<button onclick="startGoogleAuth()">Iniciar sesión con Google</button>

<script>
async function startGoogleAuth() {
  try {
    // 1. Obtener URL de Google desde el backend
    const response = await fetch('http://localhost:8000/api/auth/google');
    const data = await response.json();
    
    // 2. Redirigir a Google
    window.location.href = data.google_auth_url;
  } catch (error) {
    console.error('Error:', error);
  }
}

// En la página callback (/auth/google/callback):
async function handleGoogleCallback() {
  const code = new URLSearchParams(window.location.search).get('code');
  
  if (!code) {
    console.error('No code received from Google');
    return;
  }
  
  try {
    // 3. Intercambiar código por token
    const response = await fetch('http://localhost:8000/api/auth/google/callback', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ code })
    });
    
    const data = await response.json();
    
    if (data.token) {
      // 4. Guardar token y redirigir al dashboard
      localStorage.setItem('token', data.token);
      window.location.href = '/dashboard';
    }
  } catch (error) {
    console.error('Error en callback:', error);
  }
}

// Llamar cuando cargue la página callback
document.addEventListener('DOMContentLoaded', handleGoogleCallback);
</script>
```

### Con Google Sign-In Button (Más moderno):

```html
<script src="https://accounts.google.com/gsi/client" async defer></script>

<div id="g_id_onload"
     data-client_id="COPIA_TU_CLIENT_ID_AQUI"
     data-callback="handleCredentialResponse">
</div>

<div class="g_id_signin" data-type="standard"></div>

<script>
function handleCredentialResponse(response) {
  // response.credential es el JWT del usuario
  // Enviarlo al backend para validación
  fetch('http://localhost:8000/api/auth/google/callback', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ 
      code: response.credential // Para este caso necesitarías procesar el JWT
    })
  })
  .then(res => res.json())
  .then(data => {
    localStorage.setItem('token', data.token);
    window.location.href = '/dashboard';
  });
}
</script>
```

## 10. Para Producción

Cuando pasees a producción:

1. El proyecto debe pasar de "Externo" a "En producción" en la pantalla de consentimiento
2. Actualiza `GOOGLE_REDIRECT_URI` con tu dominio real:
   ```env
   GOOGLE_REDIRECT_URI=https://tudominio.com/auth/google/callback
   ```
3. Agrega los orígenes y URIs de redirección en Google Cloud Console

## Troubleshooting

### Error: "redirect_uri_mismatch"
- Verifica que el `GOOGLE_REDIRECT_URI` en `.env` coincida exactamente con el registrado en Google Cloud Console
- Incluye el protocolo (http/https) y el puerto

### Error: "invalid_client"
- Verifica que `GOOGLE_CLIENT_ID` y `GOOGLE_CLIENT_SECRET` sean correctos

### La app no se abre después de Google
- Verifica que la URL de redirección existe en tu frontend
- Comprueba que el backend está ejecutándose

### Error de CORS
- Asegúrate de configurar CORS en tu backend Laravel si el frontend está en diferente origen
- Añade en `config/cors.php`:
  ```php
  'allowed_origins' => ['http://localhost:3000', 'http://localhost:5173'],
  'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE'],
  ```
