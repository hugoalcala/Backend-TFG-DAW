# Endpoints de Reseñas/Calificaciones de Profesores

## Implementación Completada ✅

Se han implementado exitosamente los **5 endpoints** para gestionar reseñas y calificaciones de profesores:

| Método | Ruta | Autenticación | Descripción |
|--------|------|---------------|------------|
| **GET** | `/api/teachers/{teacherId}/ratings` | ❌ No | Obtener todas las reseñas |
| **POST** | `/api/teachers/{teacherId}/ratings` | ✅ Sí | Crear/actualizar reseña |
| **PUT** | `/api/teachers/{teacherId}/ratings/{ratingId}` | ✅ Sí | Actualizar reseña existente |
| **DELETE** | `/api/teachers/{teacherId}/ratings/{ratingId}` | ✅ Sí | Eliminar reseña |
| **POST** | `/api/teachers/{teacherId}/ratings/{ratingId}/report` | ✅ Sí | Reportar reseña inapropiada |

---

## 📋 Endpoints Detallados

### 1. GET `/api/teachers/{teacherId}/ratings` (Público)
**Obtener todas las reseñas de un profesor**

**Autenticación:** ❌ No requerida

**Rate Limiting:** 60 requests/minute

**Parámetros:**
- `teacherId` (URL parameter): ID del profesor

**Respuesta Exitosa (200):**
```json
{
  "success": true,
  "data": {
    "ratings": [
      {
        "id": 1,
        "rating": 5,
        "review": "Excelente profesor, muy didáctico",
        "student_name": "Juan Pérez",
        "student_avatar_url": "https://example.com/storage/avatars/...",
        "student_id": 2,
        "created_at": "2026-04-13T10:30:00Z"
      }
    ],
    "average": 4.5,
    "total_count": 2
  }
}
```

---

### 2. POST `/api/teachers/{teacherId}/ratings` (Protegido)
**Crear o actualizar una reseña/calificación**

**Autenticación:** ✅ Requerida (Bearer Token)

**Rate Limiting:** 30 requests/minute

**Headers Requeridos:**
```http
Authorization: Bearer {token}
Content-Type: application/json
```

**Parámetros:**
- `teacherId` (URL parameter): ID del profesor

**Body (Payload):**
```json
{
  "rating": 5,
  "review": "Muy buen profesor"
}
```

**Validaciones:**
- ✅ `rating` es **OBLIGATORIO** (número entre 1-5)
- ✅ `review` es **OPCIONAL** (puede omitirse o enviarse como null/vacío)
- ✅ El usuario debe tener rol `student` (user)
- ✅ El profesor debe existir y tener role `teacher`
- ✅ No se puede calificar el propio perfil
- ✅ Una reseña por estudiante/profesor (updateOrCreate)

**Respuesta Exitosa (201):**
```json
{
  "success": true,
  "message": "Reseña guardada exitosamente",
  "data": {
    "rating": {
      "id": 1,
      "teacher_id": 3,
      "student_id": 2,
      "rating": 5,
      "review": "Muy buen profesor",
      "created_at": "2026-04-13T10:30:00Z",
      "updated_at": "2026-04-13T10:30:00Z"
    },
    "average": 4.5,
    "total_count": 2
  }
}
```

**Códigos de Error:**

| Código | Mensaje | Causa |
|--------|---------|-------|
| 400 | Solo los estudiantes pueden crear reseñas | Usuario no tiene rol `student` |
| 400 | El usuario especificado no es un profesor | `teacherId` no corresponde a un profesor |
| 400 | No puedes calificar tu propio perfil | Intentar calificarse a sí mismo |
| 404 | Profesor no encontrado | `teacherId` no existe |
| 422 | Error de validación | `rating` fuera de rango (1-5) |
| 500 | Error al guardar la reseña | Error del servidor |

---

### 3. PUT `/api/teachers/{teacherId}/ratings/{ratingId}` (Protegido)
**Actualizar una reseña existente**

**Autenticación:** ✅ Requerida (Bearer Token)

**Rate Limiting:** 30 requests/minute

**Headers Requeridos:**
```http
Authorization: Bearer {token}
Content-Type: application/json
```

**Parámetros:**
- `teacherId` (URL parameter): ID del profesor
- `ratingId` (URL parameter): ID de la reseña

**Body (Payload):**
```json
{
  "rating": 4,
  "review": "Muy buena clase, excelente explicación"
}
```

**Validaciones:**
- ✅ Solo el autor puede actualizar su reseña
- ✅ `rating` es **OBLIGATORIO** (1-5)
- ✅ `review` es **OPCIONAL**

**Respuesta Exitosa (200):**
```json
{
  "success": true,
  "message": "Reseña actualizada exitosamente",
  "data": {
    "rating": {
      "id": 1,
      "teacher_id": 3,
      "student_id": 2,
      "rating": 4,
      "review": "Muy buena clase, excelente explicación",
      "created_at": "2026-04-13T10:30:00Z",
      "updated_at": "2026-04-13T10:45:00Z"
    },
    "average": 4.3,
    "total_count": 2
  }
}
```

**Códigos de Error:**

| Código | Mensaje | Causa |
|--------|---------|-------|
| 400 | La reseña no pertenece a este profesor | Inconsistencia de datos |
| 403 | No tienes permiso para actualizar esta reseña | No eres el autor |
| 404 | Reseña no encontrada | `ratingId` no existe |
| 422 | Error de validación | Validación fallida |
| 500 | Error al actualizar la reseña | Error del servidor |

---

### 4. DELETE `/api/teachers/{teacherId}/ratings/{ratingId}` (Protegido)
**Eliminar una reseña**

**Autenticación:** ✅ Requerida (Bearer Token)

**Rate Limiting:** 30 requests/minute

**Headers Requeridos:**
```http
Authorization: Bearer {token}
```

**Parámetros:**
- `teacherId` (URL parameter): ID del profesor
- `ratingId` (URL parameter): ID de la reseña

**Validaciones:**
- ✅ Solo el autor puede eliminar su reseña

**Respuesta Exitosa (200):**
```json
{
  "success": true,
  "message": "Reseña eliminada exitosamente",
  "data": {
    "average": 4.0,
    "total_count": 1
  }
}
```

**Códigos de Error:**

| Código | Mensaje | Causa |
|--------|---------|-------|
| 400 | La reseña no pertenece a este profesor | Inconsistencia de datos |
| 403 | No tienes permiso para eliminar esta reseña | No eres el autor |
| 404 | Reseña no encontrada | `ratingId` no existe |
| 500 | Error al eliminar la reseña | Error del servidor |

---

### 5. POST `/api/teachers/{teacherId}/ratings/{ratingId}/report` (Protegido)
**Reportar una reseña inapropiada**

**Autenticación:** ✅ Requerida (Bearer Token)

**Rate Limiting:** 30 requests/minute

**Headers Requeridos:**
```http
Authorization: Bearer {token}
Content-Type: application/json
```

**Parámetros:**
- `teacherId` (URL parameter): ID del profesor
- `ratingId` (URL parameter): ID de la reseña a reportar

**Body (Payload):**
```json
{
  "reason": "offensive_content",
  "details": "Contiene lenguaje ofensivo"
}
```

**Valores permitidos para `reason`:**
- `offensive_content` - Contenido ofensivo
- `spam` - Spam o anuncio
- `fake_review` - Reseña falsa
- `inappropriate` - Contenido inapropiado
- `other` - Otro

**Validaciones:**
- ✅ `reason` es **OBLIGATORIO** (debe ser uno de los valores permitidos)
- ✅ `details` es **OPCIONAL** (máximo 500 caracteres)
- ✅ No se puede reportar la misma reseña dos veces desde el mismo usuario
- ✅ Verifica que la reseña exista
- ✅ Verifica que la reseña pertenece al profesor especificado

**Respuesta Exitosa (201):**
```json
{
  "success": true,
  "message": "Reporte registrado exitosamente",
  "data": {
    "report_id": 1
  }
}
```

**Códigos de Error:**

| Código | Mensaje | Causa |
|--------|---------|-------|
| 400 | La reseña no pertenece a este profesor | Inconsistencia de datos |
| 401 | No autenticado | Falta token Bearer |
| 404 | Reseña no encontrada | `ratingId` no existe |
| 409 | Ya has reportado esta reseña anteriormente | Reporte duplicado del mismo usuario |
| 422 | Error de validación | `reason` no permitido o `details` > 500 chars |
| 500 | Error al registrar el reporte | Error del servidor |

---

## 🗂️ Archivos Creados/Modificados

### Nuevos Archivos:
1. **Migración**: `database/migrations/2026_04_13_000000_create_ratings_table.php`
   - Crea tabla `ratings` con constraint único en (teacher_id, student_id)

2. **Migración**: `database/migrations/2026_04_13_100000_add_unique_constraint_to_ratings_table.php`
   - Agrega constraint único (ejecutado después de la primera migración)

3. **Migración**: `database/migrations/2026_04_13_110000_create_rating_reports_table.php`
   - Crea tabla `rating_reports` con campos para denuncias

4. **Modelo**: `app/Models/Rating.php`
   - Modelo con relaciones a User (teacher y student)

5. **Modelo**: `app/Models/RatingReport.php`
   - Modelo para los reportes de reseñas

6. **Controlador**: `app/Http/Controllers/Api/RatingController.php`
   - Métodos: `store()`, `getTeacherRatings()`, `update()`, `destroy()`, `reportRating()`
   - Helper privado: `calculateTeacherStats(int $teacherId): array`

### Archivos Modificados:
1. **Modelo User**: Agregadas relaciones
   - `ratings()`: Reseñas que recibe como profesor
   - `givenRatings()`: Reseñas que ha dado como estudiante
   - `createdReports()`: Reportes que ha creado
   - `reviewedReports()`: Reportes que ha revisado como admin

2. **Modelo Rating**: Agregada relación
   - `reports()`: Reportes asociados a esta reseña

3. **Rutas**: `routes/api.php`
   - Importación de RatingController
   - Todas las rutas con rate limiting aplicado
   - Names descriptivos para cada ruta
   - Nueva ruta POST para reportar reseñas

---

## 🔍 Características Implementadas

✅ **Rating Obligatorio (1-5) + Review Opcional**
- Validación estricta del rango
- Review puede omitirse o ser null

✅ **Operaciones CRUD Completas**
- **C**reate/Update: POST (crea o actualiza si ya existe)
- **R**ead: GET (obtiene todas con stats)
- **U**pdate: PUT (solo si eres el autor)
- **D**elete: DELETE (solo si eres el autor)

✅ **Cálculo Automático de Promedio**
- Recalcula en cada operación
- Redondea a 2 decimales
- Usa helper privado para evitar duplicación

✅ **Constraint Único a Nivel de BD**
- Una reseña por (teacher_id, student_id)
- Enforce en la tabla

✅ **Información Completa del Estudiante** 
- `student_name`: Nombre del que dejó reseña
- `student_avatar_url`: Avatar (null si no tiene)
- `student_id`: Id del estudiante (crucial para frontend determinar si puede editar/eliminar)

✅ **Seguridad Robusta**
- Token Bearer requerido para POST/PUT/DELETE
- Solo estudiantes (role='user') pueden crear ratings
- Solo el autor puede editar/eliminar su reseña
- No se puede calificar a uno mismo
- Logging seguro sin exponer detalles internos

✅ **Rate Limiting**
- GET: 60 requests/minute
- POST/PUT/DELETE: 30 requests/minute

✅ **Manejo de Errores Profesional**
- Validaciones exhaustivas
- Mensajes claros en respuestas (sin detalles técnicos)
- Logging detallado con stack trace en `storage/logs/laravel.log`
- Códigos HTTP correctos

---

## 🚀 Uso en Postman/Frontend

### Crear o actualizar una Reseña:
```bash
POST /api/teachers/3/ratings
Authorization: Bearer {token}
Content-Type: application/json

{
  "rating": 5,
  "review": "Excelente clase"
}
```

### Obtener Reseñas:
```bash
GET /api/teachers/3/ratings
```

### Actualizar Reseña Existente:
```bash
PUT /api/teachers/3/ratings/1
Authorization: Bearer {token}
Content-Type: application/json

{
  "rating": 4,
  "review": "Buena clase"
}
```

### Eliminar Reseña:
```bash
DELETE /api/teachers/3/ratings/1
Authorization: Bearer {token}
```

---

## 📁 Base de Datos

**Tabla `ratings`:**
```sql
id                BIGINT (Primary Key)
teacher_id        BIGINT (Foreign Key → users, Cascade Delete)
student_id        BIGINT (Foreign Key → users, Cascade Delete)
rating            TINYINT (1-5)
review            TEXT (Nullable)
created_at        TIMESTAMP
updated_at        TIMESTAMP

UNIQUE INDEX: (teacher_id, student_id)
INDEX: teacher_id
INDEX: student_id
```

---

## 🔐 Respuestas de Error Seguras

Todas las respuestas 500 devuelven mensajes genéricos sin exponer detalles internos:

```json
{
  "success": false,
  "message": "Error al guardar la reseña"
}
```

Los detalles completos (incluyendo stack trace) se loguean en `storage/logs/laravel.log` para debugging.

---

## ✨ Mejoras Futuras Opcionales

Si necesitas agregar más funcionalidades:

1. 📊 Endpoint para estadísticas detalladas del profesor
2. 🔍 Filtrado avanzado (por rating, date range)
3. 📅 Paginación en listado de reseñas
4. 🔄 Soft deletes en reseñas
5. 📧 Notificaciones en tiempo real al profesor
6. ✅ Marcar reseña como "útil" (helpful)
7. 🚫 Moderar/ocultar reseñas reportadas

