# ASMARA PROJECT

Sistem Management Agenda

## Stack

`LARAVEL 12`

`VUE.JS 3`

`NODE.JS 22`

`PHP 8.4`

`MARIADB 11.8.3`

## Installation

Install vendor Laravel dengan composer

```bash
  cd backend/
  composer install
```

Install node_modules Vue.js dengan npm

```bash
  cd frontend/
  npm install
```

Install node_modules pada whatsapp service dengan npm

```bash
  cd whatsapp-service/
  npm install
```

## Running Project

Laravel (Backend)

```bash
  cd backend/
  php artisan migrate --seed
```

Queque

```bash
# Option 1: Database Queue (Simple)
php artisan queue:table
php artisan migrate
php artisan queue:work --tries=3

# Option 2: Redis Queue (Recommended for Production)
# Edit .env: QUEUE_CONNECTION=redis
php artisan queue:work redis --tries=3
```

Cron

```bash
cd /ASMARA && php artisan schedule:run >> /dev/null 2>&1
```

```bash
  php artisan serve
```

Vue.js (Frontend)

```bash
  cd frontend/
  npm run dev
```

whatsapp service

```bash
  cd whatsapp-service/
  node server.js
```

![Logo](https://upload.wikimedia.org/wikipedia/commons/thumb/3/36/Logo.min.svg/2560px-Logo.min.svg.png)

![Logo](https://images.icon-icons.com/2699/PNG/512/vuejs_logo_icon_169247.png)

![Logo](https://upload.wikimedia.org/wikipedia/commons/thumb/d/d9/Node.js_logo.svg/1200px-Node.js_logo.svg.png)
