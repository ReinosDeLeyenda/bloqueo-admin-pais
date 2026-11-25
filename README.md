# Bloqueo Admin y Web por País, ASN e IP (WordPress)

Plugin ligero y autónomo de seguridad para WordPress que protege tanto el panel de administración (**Backend**) como la web pública (**Frontend**) mediante bases de datos locales, inteligencia de amenazas, cortafuegos WAF, bloqueo a nivel de servidor (`.htaccess`), autenticación en dos pasos, políticas de contraseñas y alertas en tiempo real por Telegram y Email.

---

## Instalación

### Método 1: Instalación rápida desde WordPress (Recomendado)
1. Ve a la sección **[Releases](../../releases)** de este repositorio en GitHub.
2. Haz clic derecho sobre el archivo **`bloqueo-admin-pais.zip`** de la última versión y selecciona **Copiar dirección de enlace** (o descárgalo en tu equipo).
3. En tu panel de WordPress, entra en **Plugins → Añadir nuevo plugin → Subir plugin → Elegir archivo**.
4. Pega el enlace copiado o selecciona el `.zip` descargado y pulsa **Instalar ahora**.
5. Activa el plugin, entra en **Bloqueo Admin → 1. Administración Base de Datos** y pulsa **Descargar / Actualizar TODAS las bases de datos**.

> **Nota sobre actualizaciones:** Aunque WordPress muestre *"Actualizaciones automáticas no disponibles"* por tratarse de un plugin privado/externo a WordPress.org, **este addon integra actualizaciones automáticas desde GitHub Releases** y permite comprobar nuevas versiones en cualquier momento pulsando **Buscar actualizaciones** en la lista de plugins.

### Método 2: Instalación manual vía FTP / SSH
1. Sube la carpeta del plugin dentro de `/wp-content/plugins/bloqueo-admin-pais/`.
2. Activa el plugin desde el menú **Plugins** de WordPress.
3. Entra en **Bloqueo Admin → 1. Administración Base de Datos** y pulsa **Descargar / Actualizar TODAS las bases de datos**.
4. Configura tus reglas en **2. Bloqueos Admin** y **3. Bloqueos Web**.

---

## Características principales

### 1. Bases de Datos Locales, Personalizadas y Auditoría
* **5 fuentes gratuitas en local (sin latencia externa por visita):**
  * **Países (`.mmdb`):** Geolocalización IPv4/IPv6 mediante lector binario `.mmdb` nativo en PHP (sin dependencias externas).
  * **ASN / Proveedores (`.mmdb`):** Identificación de operadoras y centros de datos (OVH, DigitalOcean, Hetzner, etc.).
  * **AbuseIPDB:** Lista de IPs maliciosas reportadas (compatible con espejo público ≥ 90 % o API Key oficial con puntuación exacta `0–100 %`).
  * **Nodos de Salida Tor:** Lista oficial de nodos de salida de la red Tor.
  * **FireHOL Level 1 / Spamhaus DROP:** Subredes CIDR secuestradas o maliciosas.
* **Bases de datos personalizadas (IPs / CIDR):** Añade listas propias por **URL remota** (auto-actualizable) o subiendo archivos locales (`.txt`, `.netset`, `.csv`, `.json`, `.gz`) en modo streaming de bajo consumo de memoria y caché por IP, con **presets en 1 clic** (VPNs Comerciales, Datacenters/Cloud y Proxies Abiertos).
* **Escáner de Integridad del Núcleo (Core Checksums):** Verifica todos los archivos de `wp-admin/` y `wp-includes/` contra los hashes MD5 oficiales de `api.wordpress.org` detectando archivos modificados, faltantes o inyectados.
* **Escáner de Malware y Backdoors (`wp-content`):** Analiza plugins, temas y `mu-plugins` buscando código ofuscado o webshells y detecta archivos `.php` ocultos dentro de `wp-content/uploads/`.
* **Descarga programada y Copias de Seguridad (`.json`):** Actualización automática mediante **WP-Cron** (Diaria, Semanal, Cada 2 semanas o Mensual) y exportación/importación completa de toda la configuración en `.json`.

### 2. Bloqueos Admin (Backend) y Seguridad de Acceso
* **Detección segura de IP (Anti-Spoofing):** Selector explícito de origen de IP (`REMOTE_ADDR`, `CF-Connecting-IP` de Cloudflare, `X-Forwarded-For` o `X-Real-IP`).
* **Rutas personalizables con comodines (`*`):** Protege `/wp-admin/*`, `/wp-login.php`, `/wp-register.php`, `/xmlrpc.php`, `/wp-json/wp/v2/users*` y archivos sensibles (`*.log`, `*.zip`, `*.rar`, `*.tar.gz`, `*.sql`, `*.env`, `*.git*`).
* **Bloqueo rápido vía `.htaccess` (opcional):** Corta descargas directas de archivos sensibles y `xmlrpc.php` en Apache/LiteSpeed antes de cargar PHP.
* **Blindaje de vectores alternativos:** Desactivación de `xmlrpc.php` y bloqueo de enumeración de usuarios (`/?author=N` y endpoint `/wp-json/wp/v2/users`).
* **Filtros exclusivos (Permitidos o Bloqueados):** Control independiente por **País** (con buscador autocompletable y **atajos por regiones**: UE, Europa, Norteamérica, Sudamérica, Asia, África y Oceanía) y por **ASN**.
* **Listas Blanca y Negra con soporte CIDR:** Admite IPs individuales y subredes IPv4/IPv6 (`192.168.1.0/24`, `2001:db8::/32`), con tooltip de metadatos al pasar el ratón por la lista negra.
* **Simulador de IP en vivo:** Analiza cualquier IPv4/IPv6 y ruta URL para comprobar su país, ASN, riesgo AbuseIPDB, estado Tor/FireHOL y el veredicto exacto de las reglas de Admin y Web.
* **Autenticación en Dos Factores (2FA):** Soporte local para App Autenticadora (TOTP con código QR generado 100 % en el navegador), verificación por email, 8 códigos de respaldo de un solo uso, dispositivos de confianza (30 días) y obligatoriedad para Administradores.
* **Política y Caducidad de Contraseñas:** Exige longitud mínima, mayúsculas, minúsculas, números y símbolos, impide reutilizar las **últimas 5 contraseñas**, permite configurar **rotación obligatoria cada X días** (con avisos preventivos) y añade opción en el perfil de usuario para **forzar caducidad inmediata**.
* **Protección contra contraseñas filtradas (Have I Been Pwned):** Comprueba contraseñas expuestas en brechas públicas mediante *k-Anonymity* al cambiar clave o iniciar sesión.
* **Defensa activa con caducidad (TTL):** Auto-baneo temporal configurable en horas tras X intentos fallidos y bloqueo instantáneo de intentos con usuarios inexistentes.
* **Llave secreta URL de emergencia:** Acceso de respaldo mediante parámetro URL con validación de complejidad Unicode LTR, generador aleatorio y botones ocultar/copiar.

### 3. Bloqueos Web (Frontend)
* **Reglas por ámbito/URL con lógica Y/O:** Aplicable a toda la web pública (`Global`) o a rutas específicas (`/tienda/*`, `/formulario*`, etc.) encadenando condiciones arrastrables.
* **Cortafuegos WAF (Inspección de Payload):** Analiza peticiones `GET`, `POST`, URLs y subidas de archivos (`FILES`) contra ataques **SQL Injection (SQLi), Cross-Site Scripting (XSS), Path Traversal / LFI, inyección PHP** y subida de archivos ejecutables, con auto-baneo opcional.
* **Rate Limiting (Límite de peticiones y errores 404):** Controla el máximo de peticiones/minuto y errores 404/minuto por IP para frenar scrapers y escáneres de vulnerabilidades, con auto-baneo opcional.
* **Bloqueo de User-Agents y peticiones vacías:** Lista personalizable de bots maliciosos, scrapers e IA (`sqlmap`, `nikto`, `python-requests`, `GPTBot`, `Bytespider`, etc.) y bloqueo opcional de peticiones sin cabecera `User-Agent`.
* **Protección Honeypot (Anti-Spam):** Campo trampa invisible + firma HMAC temporal en **Comentarios, Registro, Recuperar contraseña, Contact Form 7 y WPForms**, con auto-baneo opcional.
* **Redirección personalizada por país:** Redirige países concretos a URLs específicas (`PAÍS => URL`) con prevención automática de bucles.
* **Exclusión de bots SEO verificados:** Doble verificación DNS inversa (`rDNS` + `FCrDNS`) para **Googlebot**, **Bingbot**, **Applebot** y **DuckDuckBot** con caché de 24 horas.
* **Pantalla 403 personalizada:** Página de acceso denegado autónoma con iconos SVG o redirección a URL externa.

### 4. Notificaciones Avanzadas (Telegram y Email) y Diseñadores Visuales
* **Alertas instantáneas o resúmenes agrupados:** Configurable por canal (**Telegram**, **Email** o **Ambos**) y frecuencia (individual, cada 1h, 3h, 6h, 12h, 24h, 7d, 14d o mensual).
* **Avisos de conexión exitosa con detección de cambio de ASN:** Notifica inicios de sesión por usuario o globalmente hacia destinos específicos de Telegram o Email, alertando si el operador/ASN difiere de la última sesión o de sesiones activas.
* **Constructor visual de plantillas Email (`.json`):** Editor modular *drag & drop* con anchos configurables (`100%`, `50%`, `33%`), vista previa en tiempo real y exportación/importación de plantillas.
* **Diseñador Rich Text para Telegram:** Editor visual con soporte para **Rich Articles HTML**, citas desplegables (`expandable`), spoilers, tablas y variables dinámicas con vista previa en vivo.

### 5. Widget del Escritorio, Estadísticas e Historial (MySQL)
* **Módulo en el Escritorio principal de WordPress:** Muestra un resumen oscuro de los últimos 30 días con el **Top 5 de IPs bloqueadas**, **Top 5 de países**, **Top 5 de accesos fallidos** (indicando si el usuario existe) y el estado de actualizaciones pendientes.
* **Tabla dedicada en MySQL (`bap_blocked_logs`) o MySQL Externo:** Almacenamiento local o centralizado multi-sitio con cifrado AES-256-CBC de credenciales y retención automática configurable.
* **Migración desde IP2Location Country Blocker:** Importación en 1 clic desde la tabla MySQL de IP2Location.
* **Panel gráfico e informes CSV:** Gráficas de evolución de 14 días, distribución horaria (`00h–23h`), barras por país y URLs atacadas, tabla interactiva ordenable con contador de intentos por IP y exportación en CSV.

---

## Requisitos
* WordPress 6.0 o superior.
* PHP 8.0 o superior (con extensión `zlib` recomendada para descomprimir `.mmdb.gz`).
