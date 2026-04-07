# 🚀 API REST – Proeducative Corp

API REST desarrollada en PHP para la gestión de presupuesto y servicios relacionados.

---

## 📋 Pre-requisitos

- PHP >= 7.2 (si no usas Docker)
- MySQL >= 5.7 o MariaDB >= 10.4
- Docker + Docker Compose (recomendado)

---

## ⚙️ Instalación

### 🐳 Opción 1: Usando Docker (recomendado)

```bash
git clone <repo>
cd probudget_backend
cp .env.example .env
docker compose up -d
```

---

### 🌐 Accesos

- API:
  http://localhost:8001

- phpMyAdmin:
  http://localhost:8080

---

### 🔑 Credenciales base de datos

- Servidor: `db`
- Usuario: `root`
- Password: `root`
- Base de datos: `civil_probudget`

---

### 🧩 Base de datos (opcional)

Si necesitas datos iniciales:

- Importar archivo `scripts/civil_probudget.sql` en phpMyAdmin

---

## 💻 Opción 2: Sin Docker (modo clásico)

```bash
php -S localhost:8001 -t public
```

⚠️ Debes tener configuradas manualmente:

- PHP
- MySQL/MariaDB
- Variables de entorno

---

## 📁 Estructura del proyecto

```
├── public/          # Punto de entrada
|-- controller/      # Controladores
├── model/src             # Lógica de la aplicación
├── vendor/          # Dependencias PHP
├── docker-compose.yml
├── Dockerfile
└── .env.example
```

---

## ⚠️ Notas importantes

- No uses `localhost` como DB host dentro de Docker → usa `db`
- No subas `.env` al repositorio
- Usa `.env.example` como plantilla
- Docker corre en segundo plano (`-d`)

---

## 📄 Licencia

Este proyecto está bajo la licencia especificada en el archivo:

```
LICENSE.md
```

---

## 🧠 Tips

- Ver logs:

```bash
docker compose logs -f
```

- Reiniciar contenedores:

```bash
docker compose down
docker compose up -d
```
