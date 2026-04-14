---
name: UI Redesign Pendiente
description: Pulido visual profesional de todas las páginas de la app — clientes, servicios, citas, prestadores, reportes, settings, dashboard
type: project
---

Todas las páginas funcionales necesitan pulido visual profesional. El backend está alineado al schema real de producción. La funcionalidad base está completa (CRUD clientes, servicios, citas, horarios, settings).

**Why:** Las páginas se ven genéricas — tablas planas, iconos Unicode baratos, sin contenedores visuales, mucho espacio vacío, inputs sin iconos. El usuario quiere aspecto profesional tipo SaaS.

**How to apply:** Usar el skill frontend-design para reescribir el CSS/HTML de cada página manteniendo la funcionalidad JS existente. Mobile-first. El design system ya existe en variables.css (tokens, colores, tipografía DM Sans + Playfair Display). Temas: caballeros (dark/elegant) y damas (soft/feminine). No romper el JS — solo mejorar estructura HTML y estilos.

**Real production DB schema (verified via curl):**
- services: id, business_id, category_id, name, description, price_usd, price_local, currency_mode, duration_minutes, image_url, is_active, sort_order
- clients: id, business_id, name, id_number, email, phone, photo_url, notes, registered_via, created_at
- appointments: id, business_id, provider_id, client_id, service_id, appointment_date, start_time, end_time, status, price_charged, currency, notes, cancelled_by, cancel_reason, created_at, updated_at
- providers: id, business_id, user_id, name, bio, avatar_url, is_active, created_at
- businesses: id, name, slug, business_type, email, phone, address, city, country, currency_code, currency_mode, logo_url, description, theme, instagram, whatsapp, facebook, google_maps_url, is_active, created_at
- users: id, business_id, name, email, phone, password_hash, google_id, role, avatar_url, is_active, created_at
- subscriptions: id, business_id, plan_id, status, start_date, trial_ends_at, starts_at, ends_at, created_at

**Deploy:** cPanel Git Version Control → Update from Remote. No hay CI/CD automático.
