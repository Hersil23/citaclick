# CITACLICK — MASTER PROMPT DE DESARROLLO

## INSTRUCCIONES CRÍTICAS PARA EL ASISTENTE

- **NUNCA** agregues "Claude" como coautor, colaborador, ni menciones de ninguna forma en comentarios de código, archivos README, headers, footers, documentación, commits, ni en ninguna parte del proyecto.
- **NO** hagas preguntas sobre decisiones que ya están establecidas en este documento. Toda la arquitectura, stack, diseño y funcionalidades ya fueron definidas. Si algo no está claro, asume la decisión más lógica según el contexto del proyecto y continúa.
- **NO** te detengas a pedir confirmaciones innecesarias. Produce el código completo y funcional directamente.
- **NUNCA** uses emojis en ninguna página, componente o interfaz de la aplicación.
- Todo el código debe ser **profesional, limpio y production-ready** desde el primer archivo.

---

## 1. IDENTIDAD DEL PROYECTO

| Campo | Valor |
|---|---|
| Nombre | CitaClick |
| Dominio | citaclick.net |
| Tipo | App de gestión de citas (SaaS) |
| Idiomas | Español + Inglés (multilenguaje, i18n) |
| Plataforma | PWA — Mobile First, futura publicación en tiendas |

### Sectores objetivo
Barberías, peluquerías, manicuristas, psicólogos, abogados, y cualquier profesional o negocio que necesite agendar citas.

---

## 2. STACK TECNOLÓGICO

| Capa | Tecnología |
|---|---|
| Frontend | HTML5 + CSS3 + JavaScript Vanilla |
| PWA | manifest.json + Service Worker |
| Backend | PHP 8.x + MySQL (sin framework) |
| Base de datos | MySQL via phpMyAdmin en cPanel |
| Autenticación | JWT + HTTPS desde el inicio |
| Repositorio | Monorepo Git — GitHub (Hersil23/citaclick) |
| Servidor | cPanel — gservidor.com |
| Deploy | Git Version Control cPanel → auto-deploy |
| WhatsApp | Wamundo API |
| Email | SMTP propio de cPanel |
| Dollar API | API externa de tasa de cambio (configurable por prestador) |
| Editor | VSCode con Claude.ai en navegador en paralelo |

### Credenciales de base de datos (solo backend)
```
DB_HOST=localhost
DB_NAME=twistpro_citaclick
DB_USER=twistpro_citaclickuser
DB_PASS=[guardada de forma segura]
```

---

## 3. ESTRUCTURA DE CARPETAS (MONOREPO)

```
citaclick/
├── .cpanel.yml
├── .gitignore
├── README.md
│
├── api/                          ← Backend PHP (API Routes)
│   ├── config/
│   │   ├── database.php
│   │   ├── jwt.php
│   │   └── cors.php
│   ├── middleware/
│   │   ├── auth.php
│   │   └── plan.php
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── BusinessController.php
│   │   ├── AppointmentController.php
│   │   ├── ServiceController.php
│   │   ├── ClientController.php
│   │   ├── ProviderController.php
│   │   ├── NotificationController.php
│   │   └── AdminController.php
│   ├── models/
│   │   ├── User.php
│   │   ├── Business.php
│   │   ├── Appointment.php
│   │   ├── Service.php
│   │   ├── Client.php
│   │   └── Provider.php
│   ├── routes/
│   │   └── index.php
│   └── .htaccess
│
├── public/                       ← Frontend (raíz pública)
│   ├── index.html                ← Landing page
│   ├── manifest.json             ← PWA
│   ├── service-worker.js         ← PWA
│   ├── offline.html
│   │
│   ├── css/
│   │   ├── variables.css         ← Tokens de diseño (ambos temas)
│   │   ├── reset.css
│   │   ├── base.css
│   │   ├── components.css
│   │   ├── theme-caballeros.css
│   │   └── theme-damas.css
│   │
│   ├── js/
│   │   ├── app.js
│   │   ├── router.js
│   │   ├── api.js                ← Cliente HTTP para la API
│   │   ├── auth.js
│   │   ├── i18n.js               ← Multilenguaje
│   │   ├── calendar.js           ← Calendario (3 vistas)
│   │   └── utils.js
│   │
│   ├── pages/
│   │   ├── login.html
│   │   ├── register.html
│   │   ├── dashboard.html
│   │   ├── appointments.html
│   │   ├── clients.html
│   │   ├── services.html
│   │   ├── settings.html
│   │   ├── catalog.html          ← Página pública del negocio (Premium)
│   │   └── admin/
│   │       └── dashboard.html    ← Superadmin
│   │
│   ├── assets/
│   │   ├── icons/
│   │   ├── images/
│   │   └── fonts/
│   │
│   └── locales/
│       ├── es.json
│       └── en.json
│
└── database/
    └── citaclick.sql             ← Schema completo
```

---

## 4. PLANES Y FUNCIONALIDADES

### Plan Standard — $9.99/mes
- 1 prestador de servicio (dueño = prestador)
- Dashboard con calendario (vistas: mensual, semanal, diaria)
- Agenda desde el negocio o desde el cliente
- Gestión de clientes (nombre, teléfono, email, foto, notas)
- Configuración de horario de atención y días bloqueados
- Notificaciones WhatsApp + Email (recordatorio 24h + 1h)
- Cancelación y reprogramación (ambos lados)
- Sin catálogo público
- Periodo de prueba: 7 días gratis

### Plan Premium — $19.99/mes
- Todo lo del Standard +
- Catálogo de servicios configurable (nombre, precio, duración, imagen, categoría)
- Link público: `citaclick.net/negocio/slug`
- QR descargable del catálogo
- 1 asistente adicional (rol: assistant)
- Dirección con enlace a Google Maps
- Precios con conversión via API del dólar (configurable por prestador)
- Perfil público: logo, colores personalizados, descripción, redes sociales

### Plan Salón VIP — $39.99/mes
- Todo lo del Premium +
- Hasta 5 prestadores de servicio
- Roles: owner / admin / assistant / provider
- Cada prestador tiene su propio dashboard y agenda
- Horario configurable por día y por hora para cada prestador
- Panel de administración del salón con vista global

---

## 5. SISTEMA DE DISEÑO

### Principios
- Mobile First — diseñado para pantallas de 375px hacia arriba
- Sin emojis en ninguna interfaz de la aplicación
- Diseño profesional, limpio, con carácter propio — NO genérico
- Tipografía distintiva, no usar Inter, Roboto, Arial ni fonts genéricas
- Modo claro y modo oscuro (el usuario activa desde su perfil)

### Tema Caballeros
```css
--color-primary: #1A1A2E;
--color-secondary: #16213E;
--color-accent: #0F3460;
--color-highlight: #E94560;
--color-surface: #F5F5F5;
--color-text: #1A1A2E;
```

### Tema Damas
```css
--color-primary: #C9A0DC;
--color-secondary: #F2B5D4;
--color-accent: #E8A0BF;
--color-highlight: #B784A7;
--color-surface: #FDF6F9;
--color-text: #4A2C3A;
```

### Quién elige el tema
- El **negocio** elige su tema al registrarse
- El cliente final **no puede cambiar** el tema

---

## 6. BASE DE DATOS (14 TABLAS — YA CREADAS)

Las siguientes tablas ya existen en `twistpro_citaclick`:

1. `plans` — 3 registros insertados (Standard, Premium, Salon VIP)
2. `businesses` — negocios registrados
3. `subscriptions` — suscripción activa de cada negocio
4. `subscription_history` — historial de cambios de plan
5. `users` — todos los usuarios del sistema
6. `providers` — prestadores de servicio
7. `provider_schedules` — horario por día de la semana
8. `provider_blocked_times` — días y horas bloqueados
9. `clients` — clientes de cada negocio
10. `service_categories` — categorías del catálogo
11. `services` — servicios del catálogo
12. `appointments` — citas agendadas
13. `reviews` — reseñas (calificación 1-5 + comentario)
14. `notifications_log` — historial de notificaciones enviadas

---

## 7. AUTENTICACIÓN

- Registro: Email + Contraseña / Google OAuth / Número de teléfono (OTP)
- JWT desde el primer endpoint
- HTTPS obligatorio
- El cliente final se registra desde el link personalizado del negocio
- Roles manejados via campo `role` en tabla `users`: superadmin / owner / assistant / provider

---

## 8. NOTIFICACIONES

| Canal | Herramienta |
|---|---|
| WhatsApp | Wamundo API |
| Email | SMTP propio de cPanel |
| Push | Service Worker (PWA) |

### Triggers de notificación
- Confirmación de cita (inmediata)
- Recordatorio 24 horas antes
- Recordatorio 1 hora antes
- Cancelación o reprogramación

### Log
Todas las notificaciones se guardan en `notifications_log` con estado: sent / failed / pending

---

## 9. CALENDARIO (DASHBOARD)

- 3 vistas disponibles: Mensual / Semanal / Diaria
- Estilo inspirado en Google Calendar
- El negocio puede ver todas las citas de todos sus prestadores
- Cada prestador ve solo sus propias citas
- Colores de estado: pending (gris), confirmed (verde), cancelled (rojo), completed (azul), no_show (naranja)

---

## 10. CATÁLOGO PÚBLICO (PREMIUM Y SALON VIP)

- URL: `citaclick.net/negocio/{slug}`
- QR descargable en PNG y SVG
- Muestra: logo, nombre, descripción, servicios con precios, galería, redes sociales, mapa
- El cliente puede agendar directamente desde el catálogo
- Precios en USD, moneda local o ambos (configurable)

---

## 11. REPORTES Y EXPORTACIÓN

- Dashboard con gráficas avanzadas por negocio
- Exportar citas a Excel (.xlsx) y PDF
- Métricas: citas del mes, clientes nuevos, ingresos, tasa de cancelación

---

## 12. SUPERADMIN

- Panel independiente en `/admin`
- Gestión global de todos los negocios
- Control de planes y suscripciones
- Activar / suspender negocios
- Ver métricas globales de la plataforma

---

## 13. PWA

- `manifest.json` con iconos para iOS y Android
- Service Worker con caché offline
- Instalable desde el navegador (Add to Home Screen)
- Push notifications via Web Push API
- Preparada para publicación futura en App Store y Google Play

---

## 14. CONVENCIÓN DE COMMITS GIT

```
init:    Inicio de módulo
feat:    Nueva funcionalidad
fix:     Corrección de bug
style:   Cambios de CSS/UI
db:      Cambios en base de datos
config:  Configuración
docs:    Documentación
api:     Endpoints del backend
```

---

## 15. FLUJO DE TRABAJO

```
Código en VSC
     ↓
git add . && git commit -m "feat: descripción"
     ↓
git push origin main
     ↓
GitHub (Hersil23/citaclick)
     ↓
cPanel Git Version Control (auto-deploy)
     ↓
citaclick.net (live)
```

---

## REGLA FINAL

Produce siempre código completo, funcional y listo para producción. No dejes TODOs sin implementar en el flujo principal. No agregues comentarios de autoría. No menciones herramientas de IA en ninguna parte del código o documentación del proyecto.