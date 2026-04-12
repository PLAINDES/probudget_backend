# 🚀 API REST - ProBudget Corp

API REST para la gestión de ProBudget Corp.

---

## 📦 Requisitos

### Sin Docker

- PHP >= 8.x
- Composer
- MySQL / MariaDB

### Con Docker

- Docker
- Docker Compose

---

## ⚙️ Instalación

### 🔹 Opción 1: Con Docker (recomendado)

```bash
# Clonar repositorio
git clone <url-del-repo>
cd <nombre-del-proyecto>

# Levantar contenedores en local
docker compose -f docker-compose.local up -d --build

# Cuando haces cambios en Dockerfile o docker-composer.local
docker compose -f docker-compose.local up -d
```

#### 📌 Accesos

- API: http://localhost:8001
- Base de datos: puerto definido en `docker-compose.yml````

### 🔹 Opción 2: Sin Docker

```bash
# Clonar repositorio
git clone <url-del-repo>
cd <nombre-del-proyecto>

# Instalar dependencias
composer install
```

#### ▶️ Levantar servidor

```bash
php -S localhost:8001 -t public
```

---

## 🗄️ Configuración

1. Crear base de datos en MySQL
2. Configurar variables de entorno (ejemplo `.env` o config):

```env
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=nombre_db
DB_USERNAME=root
DB_PASSWORD=tu_password
```

---

## 📡 Endpoints

Ejemplo:

```http
GET /api/users
POST /api/auth/login
```

_(Ajusta esto según tus rutas reales)_

---

## 🛠️ Comandos útiles

### Docker

```bash
# Ver logs
docker-compose logs -f

# Detener contenedores
docker-compose down
```

---

## 📄 Licencia

Este proyecto está bajo la licencia especificada en el archivo [LICENSE.md](LICENSE.md).
