# Endpoints de Reseñas/Calificaciones de Profesores

## Implementación Completada ✅

Se han implementado exitosamente los dos endpoints para gestionar reseñas y calificaciones de profesores.

---

## 📋 Endpoints Disponibles

### 1. GET `/api/teachers/{teacherId}/ratings` (Público)
**Obtener todas las reseñas de un profesor**

**Autenticación:** ❌ No requerida

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
        "created_at": "2026-04-13T10:30:00Z",
        "student": {
          "id": 2,
          "name": "Juan Pérez",
          "avatar_url": "https://example.com/storage/avatars/..."
        }
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

**Headers Requeridos:**
```
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
- ✅ `rating` es OBLIGATORIO (número entre 1-5)
- ✅ `review` es OPCIONAL (puede omitirse o enviarse como null/vacío)
- ✅ El estudiante debe estar autenticado
- ✅ El profesor debe existir y tener role `teacher`
- ✅ No se puede calificar el propio perfil

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
| 400 | El usuario especificado no es un profesor | `teacherId` no corresponde a un profesor |
| 400 | No puedes calificar tu propio perfil | Intentar calificarse a sí mismo |
| 404 | Profesor no encontrado | `teacherId` no existe |
| 422 | Error de validación | `rating` fuera de rango (1-5) o tipo incorrecto |
| 500 | Error al guardar la reseña | Error del servidor |

---

## 🗂️ Archivos Creados/Modificados

### Nuevos Archivos:
1. **Migración**: `database/migrations/2026_04_13_000000_create_ratings_table.php`
   - Crea la tabla `ratings` con campos: id, teacher_id, student_id, rating, review, timestamps
   - Incluye índices para consultas rápidas

2. **Modelo**: `app/Models/Rating.php`
   - Modelo con relaciones a User (teacher y student)

3. **Controlador**: `app/Http/Controllers/Api/RatingController.php`
   - `store()`: Crea o actualiza reseña
   - `getTeacherRatings()`: Obtiene todas las reseñas de un profesor

### Archivos Modificados:
1. **Modelo User**: Agregadas relaciones
   - `ratings()`: Reseñas que recibe como profesor
   - `givenRatings()`: Reseñas que ha dado como estudiante

2. **Rutas**: `routes/api.php`
   - Agregadas importación del RatingController
   - Ruta pública GET: `/teachers/{teacherId}/ratings`
   - Ruta protegida POST: `/teachers/{teacherId}/ratings`

---

## 🔍 Características Implementadas

✅ **Rating Obligatorio (1-5)**
- Validación estricta del rango

✅ **Review Opcional**
- El campo de reseña puede omitirse o ser null
- Permite crear calificaciones sin comentario

✅ **Actualización de Reseñas**
- Si un estudiante ya dejó reseña, al enviar nuevamente se actualiza
- Se recalculan automáticamente las estadísticas

✅ **Cálculo Automático de Promedio**
- Se calcula en cada operación
- Se redondea a 2 decimales

✅ **Información del Estudiante**
- Se incluye en la respuesta GET
- Contiene: id, nombre y avatar_url

✅ **Seguridad**
- Token Bearer requerido para POST
- No se puede calificar a uno mismo
- Solo profesores pueden recibir reseñas

✅ **Manejo de Errores**
- Validaciones completas
- Mensajes claros en respuestas
- Logging de errores

---

## 🚀 Uso en Postman/Frontend

### Crear una Reseña:
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

---

## 📁 Base de Datos

**Tabla `ratings`:**
```sql
id               BIGINT (Primary Key)
teacher_id       BIGINT (Foreign Key → users)
student_id       BIGINT (Foreign Key → users)
rating           TINYINT (1-5)
review           TEXT (Nullable)
created_at       TIMESTAMP
updated_at       TIMESTAMP
```

---

## ✨ Próximos Pasos Opcionales

Si necesitas agregar más funcionalidades:

1. 📊 Endpoint para estadísticas detalladas del profesor
2. 🗑️ Endpoint para eliminar reseña
3. ⭐ Filtrado por rango de ratings
4. 📅 Filtrado por fecha
5. 🔄 Paginación en listado de reseñas
6. 📧 Notificaciones al profesor cuando recibe reseña
