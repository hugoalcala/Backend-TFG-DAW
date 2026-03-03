#!/bin/bash

# Ejemplos de cURL para probar los endpoints de autenticación
# Ejecuta estos comandos en tu terminal

# ==========================================
# 1. REGISTRO DE USUARIO
# ==========================================

# Crear una nueva cuenta
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Juan Pérez",
    "email": "juan@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'

# Respuesta esperada:
# {
#   "message": "User registered successfully",
#   "user": { "id": 1, "name": "Juan Pérez", "email": "juan@example.com", ... },
#   "token": "1|abc123xyz..."
# }


# ==========================================
# 2. INICIO DE SESIÓN
# ==========================================

# Iniciar sesión con credenciales
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "juan@example.com",
    "password": "password123"
  }'

# Respuesta esperada:
# {
#   "message": "Login successful",
#   "user": { "id": 1, "name": "Juan Pérez", "email": "juan@example.com" },
#   "token": "1|xyz789abc..."
# }


# ==========================================
# 3. OBTENER DATOS DEL USUARIO (PROTEGIDO)
# ==========================================

# Nota: Reemplaza {token} con el token obtenido del login
TOKEN="1|abc123xyz..."

curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer $TOKEN"

# Respuesta esperada:
# { "id": 1, "name": "Juan Pérez", "email": "juan@example.com", ... }


# ==========================================
# 4. CERRAR SESIÓN (PROTEGIDO)
# ==========================================

curl -X POST http://localhost:8000/api/logout \
  -H "Authorization: Bearer $TOKEN"

# Respuesta esperada:
# { "message": "Logged out successfully" }


# ==========================================
# 5. OBTENER URL DE GOOGLE
# ==========================================

# Obtener URL para redirigir al usuario a Google
curl -X GET http://localhost:8000/api/auth/google

# Respuesta esperada:
# {
#   "google_auth_url": "https://accounts.google.com/o/oauth2/v2/auth?client_id=...&redirect_uri=...&scope=..."
# }


# ==========================================
# 6. CALLBACK DE GOOGLE
# ==========================================

# Después que Google redirige, intercambia el código por un token local
# Nota: Reemplaza {codigo} con el código recibido de Google

curl -X POST http://localhost:8000/api/auth/google/callback \
  -H "Content-Type: application/json" \
  -d '{
    "code": "4/0AY0e-g7Xn..."
  }'

# Respuesta esperada:
# {
#   "message": "Google authentication successful",
#   "user": { "id": 2, "name": "Usuario Google", "email": "user@gmail.com", ... },
#   "token": "2|xyz789abc..."
# }


# ==========================================
# ERRORES COMUNES
# ==========================================

# Registro fallido - Email ya existe
curl -X POST http://localhost:8000/api/register \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Otro Usuario",
    "email": "juan@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
# Respuesta: 422 - Validation Error

# Login fallido - Credenciales inválidas
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "juan@example.com",
    "password": "wrongpassword"
  }'
# Respuesta: 422 - The provided credentials are invalid

# Acceso sin token
curl -X GET http://localhost:8000/api/me
# Respuesta: 401 - Unauthorized
