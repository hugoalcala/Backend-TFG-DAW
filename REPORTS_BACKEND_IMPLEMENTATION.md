# Sistema de Denuncias - Documentación Técnica

## ✅ Implementación Completada

Se ha implementado completamente el sistema de gestión de denuncias en el backend.

## 📦 Archivos Creados/Modificados

### Modelos
- **`app/Models/RatingReport.php`** - Modelo para denuncias (actualizado con relaciones completas)
- **`app/Models/Message.php`** - Modelo para mensajes de administración

### Controladores
- **`app/Http/Controllers/Api/ReportController.php`** - Controlador con todos los endpoints

### Middleware
- **`app/Http/Middleware/AdminMiddleware.php`** - Middleware personalizado (existe `IsAdmin.php` que ya lo hace)

### Migraciones
- **`database/migrations/2026_04_13_110000_create_rating_reports_table.php`** - Tabla de denuncias
- **`database/migrations/2026_04_13_120000_create_messages_table.php`** - Tabla de mensajes

### Rutas
- **`routes/api.php`** - Agregadas rutas de ReportController en el grupo admin

## 🔌 Endpoints Implementados

Todos los endpoints están protegidos con autenticación y verificación de rol admin.

### 1. GET `/api/admin/reports`
**Listar todas las denuncias**

Query Parameters:
- `status`: `all|pending|approved|rejected`
- `reason`: `all|offensive_content|spam|fake_review|inappropriate|other`
- `page`: número de página
- `per_page`: registros por página
- `sort_by`: campo para ordenar (default: `created_at`)
- `sort_order`: `asc|desc`

Retorna lista paginada con toda la información necesaria.

---

### 2. GET `/api/admin/reports/{reportId}`
**Obtener detalles de una denuncia específica**

Retorna:
- Información completa de la denuncia
- Datos de la reseña denunciada
- Información del profesor
- Datos del estudiante que hizo la reseña
- Información de quien denunció

---

### 3. POST `/api/admin/reports/{reportId}/approve`
**Aprobar una denuncia (elimina la reseña)**

Body:
```json
{
  "admin_notes": "Contenido inapropiado. Reseña eliminada."
}
```

Actions:
- ✅ Cambia status a `approved`
- ✅ Elimina la reseña asociada
- ✅ Registra al admin que aprobó y fecha
- ✅ Guarda notas del administrador

---

### 4. POST `/api/admin/reports/{reportId}/reject`
**Rechazar una denuncia (mantiene la reseña)**

Body:
```json
{
  "admin_notes": "Reseña legítima. No viola las normas."
}
```

Actions:
- ❌ Cambia status a `rejected`
- ❌ Mantiene la reseña
- ❌ Registra al admin que rechazó y fecha
- ❌ Guarda notas del administrador

---

### 5. POST `/api/admin/users/{userId}/message`
**Enviar mensaje a un usuario**

Body:
```json
{
  "subject": "Acción tomada en tu reseña",
  "message": "Hola, tu reseña ha sido eliminada porque violaba nuestras normas..."
}
```

Actions:
- 📨 Crea un nuevo mensaje en la base de datos
- 📨 Lo puede leer el usuario desde su panel
- 📨 Queda registrado para auditoría

---

## 📊 Estructura de Base de Datos

### Tabla `rating_reports`
```sql
- id (BIGINT, PRIMARY KEY)
- rating_id (BIGINT, FK a ratings)
- teacher_id (BIGINT, FK a users)
- reported_by_user_id (BIGINT, FK a users)
- reason VARCHAR (offensive_content, spam, fake_review, inappropriate, other)
- details TEXT
- status VARCHAR (pending, approved, rejected)
- admin_notes TEXT
- reviewed_by_user_id BIGINT (FK a users, nullable)
- reviewed_at TIMESTAMP (nullable)
- created_at, updated_at TIMESTAMP
```

### Tabla `messages`
```sql
- id (BIGINT, PRIMARY KEY)
- from_user_id (BIGINT, FK a users)
- to_user_id (BIGINT, FK a users)
- subject VARCHAR
- body TEXT
- read_at TIMESTAMP (nullable)
- created_at, updated_at TIMESTAMP
```

---

## 🔐 Seguridad

- ✅ Todos los endpoints requieren autenticación (`auth:sanctum`)
- ✅ Todos los endpoints requieren rol admin (`middleware: admin`)
- ✅ Las validaciones previenen denuncias duplicadas
- ✅ Solo admin puede aprobar/rechazar denuncias
- ✅ Se registra quién realizó cada acción y cuándo
- ✅ Las operaciones son auditables

---

## 🧪 Prueba los Endpoints

### Obtener todas las denuncias
```bash
curl -X GET "http://localhost:8000/api/admin/reports?status=pending&page=1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Ver detalles de una denuncia
```bash
curl -X GET "http://localhost:8000/api/admin/reports/1" \
  -H "Authorization: Bearer {token}" \
  -H "Accept: application/json"
```

### Aprobar una denuncia
```bash
curl -X POST "http://localhost:8000/api/admin/reports/1/approve" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"admin_notes": "Contenido ofensivo. Reseña eliminada."}'
```

### Rechazar una denuncia
```bash
curl -X POST "http://localhost:8000/api/admin/reports/1/reject" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"admin_notes": "Reseña legítima."}'
```

### Enviar mensaje a usuario
```bash
curl -X POST "http://localhost:8000/api/admin/users/2/message" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"subject": "Acción tomada", "message": "Tu reseña ha sido eliminada..."}'
```

---

## 📋 Flujo Completo

1. **Denunciar reseña** (Estudiante)
   - POST `/teachers/{id}/ratings/{id}/report`
   - Se crea un registro en `rating_reports` con status `pending`

2. **Ver denuncias** (Admin)
   - GET `/admin/reports`
   - Listar todas con filtros

3. **Revisar denuncia** (Admin)
   - GET `/admin/reports/{id}`
   - Ver detalles completos

4. **Tomar decisión** (Admin)
   - POST `/admin/reports/{id}/approve` → Elimina reseña
   - O POST `/admin/reports/{id}/reject` → Mantiene reseña

5. **Notificar usuarios** (Admin, opcional)
   - POST `/admin/users/{id}/message`
   - Envía mensaje al denunciante, estudiante o profesor

---

## ✨ Características Destacadas

- 📌 Panel intuitivo con tabla de denuncias
- 🎯 Filtros por estado y motivo
- 👤 Información de los tres usuarios involucrados
- 💬 Sistema de notas para documentar decisiones
- 📨 Envío de mensajes integrado
- 🔍 Búsqueda y paginación
- 📊 Estado claro de cada denuncia
- 🛡️ Protección contra denuncias duplicadas

---

## 🎉 ¡Listo para usar!

El backend está completamente implementado y listo para que el frontend lo consuma.

