# Despliegue en Hostinger

La plataforma está hecha en **PHP 8.3 (Laravel) + MySQL** a propósito: es lo único que funciona en **todos** los planes de Hostinger, incluido el Premium (el de "hasta 3 sitios web"), que no permite Node.js ni Python.

| Plan Hostinger | ¿Funciona? | Notas |
|---|---|---|
| Single | ⚠️ | Solo 1 sitio. Sí corre, pero ocuparía tu único sitio. |
| Premium (3 sitios) | ✅ | Tiene SSH, Composer, MySQL y Cron Jobs. |
| **Business** (50 sitios) | ✅ | **Plan actual de Virtuoso.** Lo mismo que Premium + más recursos, respaldos diarios, CDN y Node.js. |
| Cloud | ✅ | Igual que Business, con más recursos. |
| VPS | ✅ | También funciona. Ahí además podrías correr AdQuantum (Python) y WhatsApp. |

La IA pesada (AdQuantum, en Python) sigue en Railway y la plataforma la llama por HTTP. Claude se llama directo desde PHP.

---

## 0. Cómo saber qué plan tienes

1. Entra a **hpanel.hostinger.com**.
2. Menú izquierdo → **Hosting** (o "Sitios web").
3. En la tarjeta de tu hosting aparece el nombre del plan: *Single*, *Premium*, *Business*, *Cloud…* o *KVM* (VPS).
4. También puedes verlo en **Facturación → Suscripciones**.

Virtuoso tiene **Business Web Hosting** (límite de 50 sitios, 50 GB, respaldos diarios, CDN disponible, Node.js disponible).
La plataforma no necesita Node.js en el servidor, pero el plan lo permite si algún día se quiere.

---

## 1. Preparar el sitio (una sola vez)

1. **Dominio o subdominio:** hPanel → *Sitios web* → *Agregar sitio web*, o usa un subdominio de un dominio que ya tengas (por ejemplo `medicos.virtuoso.mx`). Con eso ocupas 1 de tus 50 sitios.
2. **Versión de PHP:** *Avanzado → Configuración de PHP* → elige **PHP 8.3** o superior.
   - En *Extensiones PHP*, verifica que estén activas: `pdo_mysql`, `mbstring`, `openssl`, `tokenizer`, `xml`, `ctype`, `fileinfo`, `bcmath`, `curl`, `gd`, `intl`.
3. **Base de datos:** *Bases de datos → Administración* → crea una base MySQL. Anota el nombre (`u123456789_medicos`), el usuario y la contraseña.
4. **Acceso SSH:** *Avanzado → Acceso SSH* → actívalo y anota host, puerto (normalmente 65002) y usuario.

---

## 2. Subir el código

### Opción A: con Git desde hPanel (recomendada)
1. hPanel → *Avanzado → Git*.
2. Repositorio: `https://github.com/rcrm780516-prog/adquantum.git` y la rama correspondiente.
   - Como el repo es privado, agrega la **clave SSH** que te muestra hPanel en GitHub → *Settings → Deploy keys* del repo.
3. Directorio de instalación: **`repo`** (fuera de `public_html`).
4. Activa el *Auto Deployment* si quieres que cada push se actualice solo (pega el webhook en GitHub → *Settings → Webhooks*).

### Opción B: por SSH
```bash
ssh -p 65002 u123456789@IP_DEL_SERVIDOR
cd ~/domains/TU-DOMINIO
git clone -b main git@github.com:rcrm780516-prog/adquantum.git repo
```

La app quedará en `~/domains/TU-DOMINIO/repo/plataforma-medicos`.

---

## 3. Instalar (por SSH)

```bash
cd ~/domains/TU-DOMINIO/repo/plataforma-medicos
bash deploy/hostinger-setup.sh                                   # 1a vez: crea .env y se detiene
nano .env                                                        # llena MySQL, correo y ANTHROPIC_API_KEY
bash deploy/hostinger-setup.sh ~/domains/TU-DOMINIO/public_html  # 2a vez: instala y conecta el sitio
```

Después, para activar a un médico que ya pagó (mientras no esté integrado Mercado Pago):
```bash
php artisan plan:activar correo@medico.com basico
```

El script:
- instala dependencias con Composer (sin las de desarrollo),
- crea `.env` a partir de `.env.hostinger.example` si no existe y genera `APP_KEY`,
- conecta `public_html` con la carpeta `public/` de Laravel,
- corre migraciones y carga especialidades y planes,
- optimiza cachés.

Los datos de MySQL van así en `.env`:
```
DB_DATABASE=u123456789_medicos
DB_USERNAME=u123456789_medicos
DB_PASSWORD=********
```

### ¿Por qué hay que conectar `public_html`?
Hostinger sirve el sitio desde `public_html`, pero Laravel solo debe exponer su carpeta `public/`. El resto (`.env`, código, etc.) **no** debe ser accesible desde internet. El script reemplaza `public_html` por un enlace simbólico a `plataforma-medicos/public`. Si tu plan no permite enlaces simbólicos, usa el plan B que viene en `deploy/public_html.htaccess`.

---

## 4. Cron Job (recordatorios, invitaciones a reseñar, colas)

hPanel → *Avanzado → Cron Jobs* → *Personalizado*:

```
* * * * * cd /home/u123456789/domains/TU-DOMINIO/repo/plataforma-medicos && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Un solo cron, cada minuto. Laravel se encarga de programar el resto. Como no hay "workers" permanentes en hosting compartido, la cola de trabajos (`QUEUE_CONNECTION=database`) se procesa desde el scheduler.

---

## 5. Correo

hPanel → *Correos* → crea `citas@tu-dominio`. En `.env`:
```
MAIL_MAILER=smtp
MAIL_HOST=smtp.hostinger.com
MAIL_PORT=465
MAIL_SCHEME=smtps
MAIL_USERNAME=citas@tu-dominio
MAIL_PASSWORD=********
```

---

## 6. Actualizaciones

Con Git automático solo haces `git push`. Después (o desde un cron diario):
```bash
cd ~/domains/TU-DOMINIO/repo/plataforma-medicos && bash deploy/hostinger-update.sh
```

---

## 7. Límites del plan Premium a tener en cuenta

- No hay procesos permanentes: nada de WebSockets ni workers 24/7. Todo va por cron.
- No hay Node.js en el servidor. Los estilos ya van compilados en `public/css/app.css` dentro del repo. Si cambias el diseño, corre `npm run build` en tu computadora y sube el archivo.
- WhatsApp: usar la **API oficial de WhatsApp Cloud** (HTTP), que funciona desde PHP. Evolution API requiere Docker y por lo tanto VPS.
- Si la plataforma crece (miles de médicos), el siguiente paso es un plan **Business/Cloud** o un **VPS**, sin cambiar código.
