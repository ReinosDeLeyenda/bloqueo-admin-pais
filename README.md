# Bloqueo Admin y Web por País, ASN e IP (WordPress)

Plugin ligero y autónomo de seguridad para WordPress que protege tanto el panel de administración (**Backend**) como la web pública (**Frontend**) mediante bases de datos locales, inteligencia de amenazas, bloqueo a nivel de servidor (`.htaccess`), autenticación en dos pasos y defensa activa contra fuerza bruta.

---

## Instalación

### Método 1: Instalación rápida desde WordPress (Recomendado)
Normalmente es el método más rápido para la primera instalación:

1. Ve a la sección **[Releases](../../releases)** de este repositorio en GitHub.
2. Haz clic derecho sobre el archivo **`bloqueo-admin-pais.zip`** de la última versión y selecciona **Copiar dirección de enlace** (o descárgalo en tu equipo).
3. En tu panel de WordPress, entra en **Plugins → Añadir nuevo plugin → Subir plugin → Elegir archivo**.
4. Pega el enlace copiado (en el cuadro de nombre de archivo de Windows/macOS) o selecciona el `.zip` descargado y pulsa **Instalar ahora**.
5. Activa el plugin, entra en **Bloqueo Admin → 1. Administración Base de Datos** y pulsa **Descargar / Actualizar TODAS las bases de datos**.

> **Nota sobre actualizaciones:** Diga lo que diga WordPress en la ficha del plugin (como *"Actualizaciones automáticas no disponibles"* al no estar alojado en el directorio oficial de WordPress.org), **este addon cuenta con actualizaciones automáticas integradas directamente desde GitHub Releases** y permite comprobar nuevas versiones en cualquier momento pulsando **Buscar actualizaciones** en la lista de plugins.

### Método 2: Instalación manual vía FTP / SSH
1. Descarga o clona este repositorio dentro de `/wp-content/plugins/bloqueo-admin-pais/`.
2. Activa el plugin desde el menú **Plugins** de WordPress.
3. Entra en **Bloqueo Admin → 1. Administración Base de Datos** y pulsa **Descargar / Actualizar TODAS las bases de datos**.
4. Configura tus reglas en **2. Bloqueos Admin** y **3. Bloqueos Web**.

---

## Características principales

### 1. Bases de Datos Locales y Actualización Automática
* **5 fuentes gratuitas en local (sin latencia externa por visita):**
  * **Países (`.mmdb`):** Geolocalización IPv4/IPv6 mediante lector binario `.mmdb` nativo en PHP (sin dependencias Composer).
  * **ASN / Proveedores (`.mmdb`):** Identificación de operadoras y centros de datos (OVH, DigitalOcean, Hetzner, etc.).
  * **AbuseIPDB:** Lista de IPs maliciosas reportadas (compatible con espejo público ≥ 90 % o API Key oficial gratuita con puntuación exacta `0–100 %`).
  * **Nodos de Salida Tor:** Lista oficial de nodos de salida de la red Tor.
  * **FireHOL Level 1 / Spamhaus DROP:** Subredes CIDR secuestradas o maliciosas.
* **Descarga masiva y programada:** Actualización en 1 clic o automática mediante **WP-Cron** (Diaria, Semanal, Cada 2 semanas o Mensual).
* **Exportar / Importar configuración (`.json`):** Permite clonar todas las reglas entre instalaciones de WordPress (excluyendo claves privadas por defecto).

### 2. Bloqueos Admin (Backend) y Seguridad de Acceso
* **Detección segura de IP (Anti-Spoofing):** Selector explícito de origen de IP (`REMOTE_ADDR`, `CF-Connecting-IP` de Cloudflare, `X-Forwarded-For` o `X-Real-IP`).
* **Rutas personalizables con comodines (`*`):** Protege por defecto `/wp-admin/*`, `/wp-login.php`, `/wp-register.php`, `/xmlrpc.php`, `/wp-json/wp/v2/users*` y archivos sensibles (`*.log`, `*.zip`, `*.rar`, `*.tar.gz`, `*.sql`, `*.env`, `*.git*`).
* **Bloqueo rápido vía `.htaccess` (opcional):** Corta descargas directas de archivos sensibles y `xmlrpc.php` en Apache/LiteSpeed antes de cargar PHP.
* **Blindaje de vectores alternativos:** Desactivación de `xmlrpc.php` y bloqueo de enumeración de usuarios (`/?author=N` y endpoint `/wp-json/wp/v2/users`).
* **Filtros exclusivos (Permitidos o Bloqueados):** Control independiente por **País** (con buscador autocompletable y **atajos por regiones**: UE, Europa, Norteamérica, Sudamérica, Asia, África y Oceanía) y por **ASN**.
* **Listas Blanca y Negra con soporte CIDR:** Admite tanto IPs individuales como subredes IPv4 e IPv6 (`192.168.1.0/24`, `2001:db8::/32`).
* **Simulador / Buscador de IP en vivo:** Permite analizar cualquier IP para ver su país, ASN, riesgo AbuseIPDB, estado Tor/FireHOL y el veredicto exacto de las reglas de Admin y Web.
* **Autenticación en Dos Factores (2FA):** Soporte local para App Autenticadora (TOTP con código QR generado en el navegador), verificación por email, códigos de respaldo y dispositivos de confianza (30 días).
* **Protección de contraseñas (Have I Been Pwned):** Comprueba contraseñas filtradas mediante *k-Anonymity* al cambiar clave o iniciar sesión.
* **Defensa activa con caducidad (TTL):** Auto-baneo temporal configurable en horas tras X intentos fallidos y bloqueo instantáneo de intentos con usuarios inexistentes.
* **Llave secreta URL de emergencia:** Acceso de respaldo mediante parámetro URL con validación de complejidad Unicode LTR, generador aleatorio y botón ocultar/copiar.
* **Notificaciones por email:** Alertas instantáneas o resúmenes agrupados en múltiples plantillas visuales y de texto (**Completa**, **Top 10**, **Top 5** y **Solo texto**).

### 3. Bloqueos Web (Frontend)
* **Reglas independientes del panel con lógica Y/O:** Aplicable a toda la web pública o a rutas específicas (`/tienda/*`, `/descargas/*`, etc.) encadenando condiciones arrastrables.
* **Redirección personalizada por país:** Permite redirigir países concretos a URLs específicas (`PAÍS => URL`) con prevención automática de bucles.
* **Exclusión de bots SEO verificados:** Doble verificación DNS inversa (`rDNS` + confirmación directa) para **Googlebot**, **Bingbot**, **Applebot** y **DuckDuckBot** con caché de 24 horas.
* **Pantalla 403 personalizada:** Página de acceso denegado autónoma con iconos SVG (candado de seguridad y mira de rastreo) o redirección a URL externa.

### 4. Estadísticas, Historial y Exportación (MySQL)
* **Tabla dedicada en MySQL (`bap_blocked_logs`) o MySQL Externo:** Almacenamiento local o centralizado con retención automática configurable.
* **Migración directa desde IP2Location Country Blocker:** Importación en 1 clic desde la tabla MySQL de IP2Location.
* **Panel gráfico e informes CSV:** Gráficas de evolución de 14 días, distribución por país, gráfica horaria (`00h–23h`), tabla interactiva ordenable con contador de intentos por IP y exportación en CSV.

---

## Requisitos
* WordPress 6.0 o superior.
* PHP 8.0 o superior (con extensión `zlib` recomendada para descomprimir `.mmdb.gz`).
