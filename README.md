# Bloqueo Admin y Web por País, ASN e IP (WordPress)

Plugin ligero, modular y autónomo de seguridad para WordPress que protege tanto el panel de administración (**Backend**) como la web pública (**Frontend**) mediante bases de datos locales, inteligencia de amenazas, cortafuegos WAF, bloqueo a nivel de servidor (`.htaccess`), URL de acceso oculta, modo ausente por horario, modo vacaciones por cuenta, autenticación en dos pasos, políticas de contraseñas, escáner de vulnerabilidades CVE, registro de actividad administrativa, monitor SSL/TLS, modo emergencia anti-crash y alertas en tiempo real por Telegram y Email.

---

## Instalación

### Método 1: Instalación rápida desde WordPress (Recomendado)
1. Ve a la sección **[Releases](../../releases)** de este repositorio en GitHub.
2. Haz clic derecho sobre el archivo **`bloqueo-admin-pais.zip`** de la última versión y selecciona **Copiar dirección de enlace** (o descárgalo en tu equipo).
3. En tu panel de WordPress, entra en **Plugins → Añadir nuevo plugin → Subir plugin → Elegir archivo**.
4. Pega el enlace copiado o selecciona el `.zip` descargado y pulsa **Instalar ahora**.
5. Activa el plugin, entra en **Bloqueo Admin → 1. Bases de Datos** y pulsa **Descargar / Actualizar TODAS las bases de datos**.

> **Nota sobre actualizaciones:** Aunque WordPress muestre *"Actualizaciones automáticas no disponibles"* por tratarse de un plugin externo a WordPress.org, **este addon integra actualizaciones automáticas desde GitHub Releases** y permite comprobar nuevas versiones en cualquier momento pulsando **Buscar actualizaciones** en la lista de plugins.

### Método 2: Instalación manual vía FTP / SSH
1. Sube la carpeta del plugin dentro de `/wp-content/plugins/bloqueo-admin-pais/`.
2. Activa el plugin desde el menú **Plugins** de WordPress.
3. Entra en **Bloqueo Admin → 1. Bases de Datos** y pulsa **Descargar / Actualizar TODAS las bases de datos**.
4. Configura tus reglas en **2. Bloqueos** (`General`, `Backend` y `Frontend`).

---

## Características principales

### 1. Bases de Datos (`Acciones` | `Configuración`)
* **5 fuentes gratuitas en local (sin latencia externa por visita):**
  * **Países (`.mmdb`):** Geolocalización IPv4/IPv6 mediante lector binario `.mmdb` nativo en PHP (sin dependencias externas).
  * **ASN / Proveedores (`.mmdb`):** Identificación de operadoras y centros de datos (OVH, DigitalOcean, Hetzner, etc.).
  * **AbuseIPDB:** Lista de IPs maliciosas reportadas (compatible con espejo público ≥ 90 % o API Key oficial con puntuación exacta `0–100 %`).
  * **Nodos de Salida Tor:** Lista oficial de nodos de salida de la red Tor.
  * **FireHOL Level 1 / Spamhaus DROP:** Subredes CIDR secuestradas o maliciosas.
* **Acciones masivas inteligentes:** Botones dedicados para **Actualizar TODAS**, **Descargar faltantes** o **Actualizar obsoletas** según la frecuencia configurada.
* **Bases de datos personalizadas (IPs / CIDR):** Añade listas propias por **URL remota** (auto-actualizable) o subiendo archivos locales (`.txt`, `.netset`, `.csv`, `.json`, `.gz`) con procesamiento en streaming y caché por IP, incluyendo **presets en 1 clic** (VPNs Comerciales, Datacenters/Cloud y Proxies Abiertos).
* **Copia de Seguridad de Base de Datos (`.sql.gz`) a Telegram o descarga directa:** Volcado MySQL en PHP puro sin dependencias externas.
* **Actualización programada, MySQL Externo y Copias de Seguridad (`.json`):** Descarga automática mediante **WP-Cron**, almacenamiento centralizado en servidor MySQL remoto con cifrado AES-256-CBC y exportación/importación completa en `.json`.

### 2. Bloqueos (`General` | `Backend` | `Frontend`)

#### Subpestaña `General`
* **Detección segura de IP (Anti-Spoofing):** Selector explícito de origen de IP (`REMOTE_ADDR`, `CF-Connecting-IP` de Cloudflare, `X-Forwarded-For` o `X-Real-IP`).
* **Simulador de IP en vivo:** Analiza cualquier IPv4/IPv6 y ruta URL para comprobar su país, ASN, riesgo AbuseIPDB, estado Tor/FireHOL y el veredicto exacto de las reglas de Backend y Frontend.

#### Subpestaña `Backend`
* **Modo Vacaciones y Restricción Geo/ASN por Cuenta:** Suspensión temporal y reglas por usuario.
* **Enlaces Mágicos sin contraseña (*Magic Links*):** Acceso por Telegram y Email.
* **Auto-baneo de subredes (/24 y /64):** Bloqueo de rangos CIDR completos.
* **URL de Acceso Personalizada (*Hide Login*):** Oculta `/wp-login.php` y `/wp-admin` tras un *slug* secreto personalizable, reescribiendo enlaces de login/2FA/recuperación y bloqueando accesos directos.
* **Indicador visual de Llave Secreta:** Si inicias sesión utilizando la **Llave Secreta URL**, el fondo del escritorio de WordPress se tiñe de **amarillo** como recordatorio visual de sesión privilegiada de bypass.
* **Alerta visual de cambio de ASN / ISP:** Muestra un **banner rojo** en el panel comparando ASN, operador (ISP), país e IP cuando una nueva conexión difiere de la sesión anterior, con botones rápidos para confirmar (**OK**) o **Cambiar contraseña**.
* **Modo Ausente por Horario (*Away Mode*):** Restringe el acceso al panel durante franjas horarias y días de la semana configurables (incluyendo franjas nocturnas que cruzan la medianoche).
* **Modo Vacaciones por Cuenta:** Suspende temporalmente el inicio de sesión de cuentas específicas durante un rango exacto de fechas y horas, cerrando sus sesiones activas al instante y reactivándolas automáticamente al finalizar.
* **Rutas personalizables con comodines (`*`) y `.htaccess`:** Protege `/wp-admin/*`, `/wp-login.php`, `/xmlrpc.php`, `/wp-json/wp/v2/users*` y archivos sensibles (`*.log`, `.zip`, `.rar`, `.tar.gz`, `.sql`, `.env`, `.git*`), con bloqueo rápido a nivel de servidor en Apache/LiteSpeed.
* **Blindaje de vectores alternativos:** Desactivación de `xmlrpc.php` y bloqueo de enumeración de usuarios (`/?author=N` y endpoint `/wp-json/wp/v2/users`).
* **Filtros exclusivos (Permitidos o Bloqueados):** Control independiente por **País** (con buscador autocompletable y **atajos por regiones**: UE, Europa, Norteamérica, Sudamérica, Asia, África y Oceanía) y por **ASN**, además de listas Blanca y Negra CIDR con tooltip de metadatos.
* **Protección CAPTCHA Multi-Proveedor (8 estilos):** Soporta **CAPTCHA Local Matemático**, **ALTCHA Proof-of-Work Local (SHA-256 sin API)**, **Google reCAPTCHA v3**, **Google reCAPTCHA v2**, **Cloudflare Turnstile**, **hCaptcha**, **Friendly Captcha** y **MTCaptcha** en **Login, Registro, Recuperar clave y Comentarios**.
* **Autenticación en Dos Factores (2FA):** App Autenticadora (TOTP con QR generado 100 % en local), verificación por email, 8 códigos de respaldo, dispositivos de confianza (30 días) y obligatoriedad para Administradores.
* **Escudo Anti-Secuestro de Sesión (*Session Hijack Binding*):** Vincula la cookie de sesión a la subred IP y navegador.
* **Política de Contraseñas, HIBP y Sesiones en Vivo:** Longitud mínima, complejidad, historial de **5 últimas contraseñas**, caducidad cada X días, bloqueo de claves filtradas (*Have I Been Pwned*), gestor de sesiones activas con expulsión remota y alerta inmediata si se crea o asciende un Administrador.
#### Subpestaña `Frontend`
* **Challenge JS Antibots (Pantalla de espera SHA-256):** Reto criptográfico ligero en navegador con cookie HMAC.
* **Rutas Trampa (Auto-Baneo Instantáneo):** Detección de accesos a rutas de reconocimiento (/.env, /wp-config.php.bak, etc.).
* **Reglas por ámbito/URL con lógica Y/O:** Aplicable a toda la web pública (`Global`) o a rutas específicas (`/tienda/*`, `/formulario*`, etc.) encadenando condiciones arrastrables.
* **Cortafuegos WAF (Inspección de Payload y Cabeceras):** Analiza URLs (incluyendo longitud > 2048 caracteres, *null-bytes* y doble codificación), cabecera `HTTP_REFERER`, formularios `GET`/`POST` y subidas de archivos (`FILES`) contra **SQLi, XSS, Path Traversal / LFI, inyección PHP** y ejecutables, con auto-baneo opcional.
* **Rate Limiting (Límite de peticiones y errores 404):** Controla el máximo de peticiones/minuto y errores 404/minuto por IP para frenar scrapers y escáneres de vulnerabilidades, con auto-baneo opcional.
* **Bloqueo de User-Agents y peticiones vacías:** Lista personalizable de bots maliciosos, scrapers e IA (`sqlmap`, `nikto`, `python-requests`, `GPTBot`, `Bytespider`, etc.) y bloqueo opcional sin cabecera `User-Agent`.
* **Protección Honeypot (Anti-Spam):** Campo trampa invisible + firma HMAC temporal + validación de `HTTP_REFERER` local en **Comentarios, Registro, Recuperar contraseña, Contact Form 7 y WPForms**, con auto-baneo opcional.
* **Redirección personalizada por país y Bots SEO:** Redirige países concretos (`PAÍS => URL`) sin bucles y verifica por doble DNS inverso (`rDNS` + `FCrDNS`) a **Googlebot**, **Bingbot**, **Applebot** y **DuckDuckBot**.

### 3. Auditoría y Hardening
* **Índice de Seguridad e Informe Ejecutivo Imprimible / PDF:** Evalúa tus defensas activas con puntuación proporcional (`0–100` y nota `A+` a `D`), enlaces directos a cada control pendiente o parcial, y un informe de inteligencia de amenazas de los últimos 30 días (países y ASNs de origen, objetivo Web vs Backend, clasificación de exploits/vectores y Top 5 URLs más atacadas).
* **Escáner Gratuito de Vulnerabilidades Conocidas (CVE — Core, Plugins y Temas):** Consulta la API pública de `wpvulnerability.net` (sin registro ni API Key) cruzando la versión exacta de WordPress, plugins y temas instalados para detectar fallos publicados, puntuación CVSS y versiones parcheadas.
* **Escáner de Integridad (Core + Plugins), Visor Diff y Restauración en 1 clic:** Compara los archivos del núcleo de WordPress y de los plugins oficiales de WordPress.org contra sus hashes MD5 oficiales, muestra el **Diff** línea a línea y permite **restaurar archivos modificados o faltantes en 1 clic** desde el SVN oficial de WordPress.org.
* **Escáner de Malware y Backdoors (`wp-content`):** Analiza plugins, temas y `mu-plugins` buscando código ofuscado o webshells y detecta archivos `.php` ocultos dentro de `wp-content/uploads/`.
* **Detector de Plugins Abandonados o Retirados:** Consulta `api.wordpress.org` para identificar plugins cerrados por seguridad o que llevan más de 1 o 2 años sin actualizaciones.
* **Escáner de Inyecciones en Base de Datos y Auditoría Programada:** Analiza `wp_posts` y `wp_options` en busca de scripts ocultos (`<script>`, `eval`, redirecciones JS o PHP inyectado) y ejecuta auditorías automáticas periódicas (diarias o semanales) con alertas de CVEs, integridad, malware, SSL y archivos críticos a Telegram y Email.
* **Monitor de Certificado SSL y Vigilancia SHA-256:** Comprueba la fecha de caducidad del certificado HTTPS (alertando si quedan ≤ 15 días) y vigila cambios no autorizados en `wp-config.php` y `.htaccess`.
* **Endurecimiento de WordPress (Hardening en 1 clic), WAF Pre-WordPress y Auditoría de Permisos:** Desactiva el editor de archivos (`DISALLOW_FILE_EDIT`), oculta la versión de WordPress, bloquea PHP en `uploads/`, añade cabeceras HTTP de seguridad, configura WAF temprano en `.user.ini` (`auto_prepend_file`) y audita permisos de archivos y carpetas críticas.
* **Buscador de Archivos, Temas y Plugins sin Uso:** Detecta temas inactivos, plugins desactivados y archivos huérfanos en `wp-content/uploads/` sin referencias en la Biblioteca de Medios ni en la base de datos, con vista previa (imágenes y código) y envío individual o masivo a cuarentena.
* **Analizador de Logs Web (`error.log`) y Accesos Fallidos por SSH (`auth.log` / Symlinks):** Inspecciona escaneos HTTP y ataques de fuerza bruta o bloqueos `DENY sshd` por puerto 22 con detección automática de enlaces simbólicos personalizados y bloqueo masivo en 1 clic.
* **Parcheo Virtual Automático (vPatching CVE), Firewall HTTP Saliente, Magic Bytes/SVG, Monitor de Roles, Contraseñas Débiles, Inspector WP-Cron (con descripción de tareas) y Monitor DNS:** Protección activa y auditoría integral.
* **Modo Emergencia Anti-Crash (`mu-plugins`):** Cargador ultraligero en `wp-content/mu-plugins/` que captura errores fatales en el backend (incluso dentro de este mismo plugin) y envía al instante un **enlace mágico firmado** por Telegram y Email para entrar aislando plugins.

### 4. Plantillas (`Email` | `Telegram`)
* **Constructor visual de plantillas Email (`.json`):** Editor modular *drag & drop* con anchos configurables (`100%`, `50%`, `33%`), vista previa en tiempo real y exportación/importación de plantillas.
* **Diseñador Rich Text para Telegram:** Editor visual con soporte para **Rich Articles HTML**, citas desplegables (`expandable`), spoilers, tablas y variables dinámicas con vista previa en vivo.

### 5. Notificaciones
* **Canales de Alerta y Resúmenes Programados:** Envío instantáneo o resúmenes agrupados (de 1 hora a mensual) por **Telegram**, **Email** o **Ambos**.
* **Avisos de Conexión Exitosa por Usuario:** Rutas de notificación personalizadas por usuario o globales con detección automática de cambio de ASN/operador.

### 6. Estadísticas (`Bloqueos` | `Actividad Admin`) y Widget del Escritorio
* **Filtro instantáneo (`Todo` | `Backend` | `Frontend`):** Filtra tarjetas resumen, gráficas de evolución de 14 días, distribución horaria (`00h–23h`), barras por país/URL y tabla de logs.
* **Registro de Actividad Administrativa (*Activity Log*):** Audita activación/desactivación/borrado de plugins, cambios de tema, altas/bajas/cambios de rol de usuarios y modificaciones en opciones críticas de WordPress.
* **Migración desde Wordfence e IP2Location:** Importación en 1 clic desde las tablas MySQL de **Wordfence** (`wfHits` / `wfBlockedIPLog`) y **IP2Location Country Blocker**, además de exportación CSV y widget resumen de 30 días en el Escritorio.

---

## Estructura del Proyecto

```text
bloqueo-admin-pais/
├── bloqueo-admin-pais.php        # Archivo principal y cargador de módulos
├── uninstall.php                 # Limpieza completa de tablas, opciones, mu-plugin y archivos
├── common/                       # Motor compartido (MMDB, CIDR, evaluador, DBs, Email/Telegram, Updater)
├── frontend/                     # Cortafuegos público, WAF, Rate Limit, Honeypot, User-Agents y SEO bots
└── backend/                      # Panel de administración dividido en 6 pestañas principales
    ├── admindb/                  # 1. Bases de Datos [Acciones | Configuración] (Locales, Custom DBs, MySQL Externo, JSON)
    ├── bloqueoa/                 # 2. Bloqueos [General | Backend] (Hide Login, Away Mode, Firewall, Simulador IP)
    ├── bloqueow/                 # 2. Bloqueos -> Subpestaña Frontend (Reglas Y/O por URL, WAF, Rate Limit y Bots)
    ├── auditoria/                # 3. Auditoría y Hardening (CVEs, Integridad, Diff/SVN, Malware, Abandonados, BD, SSL, Permisos, Emergencia)
    ├── plantillase/              # 4. Plantillas -> Subpestaña Email (Constructor visual modular)
    ├── plantillast/              # 4. Plantillas -> Subpestaña Telegram (Diseñador Rich Text)
    ├── notificaciones/           # 5. Notificaciones (Canales de alerta, frecuencias y avisos de conexión)
    ├── estadisticas/             # 6. Estadísticas [Bloqueos | Actividad Admin] (Gráficas, Logs, Activity Log e Importadores)
    ├── adminuser/                # Seguridad de cuentas: 2FA, HIBP, Políticas, Sesiones, Modo Vacaciones y Alertas Visuales
    └── comun/                    # Widget del Escritorio y selector de países compartido
```

---

## Comparativa frente a Wordfence e IP2Location

Este plugin sustituye en una sola herramienta ligera a **Wordfence Security**, **IP2Location Country Blocker**, **WP 2FA** y **Limit Login Attempts**, sin requerir cuentas externas, telemetría ni suscripciones:

| Característica | **Bloqueo Admin y Web** (Este plugin) | **Wordfence Security** (Free / Premium) | **IP2Location Country Blocker** |
| :--- | :--- | :--- | :--- |
| **Geobloqueo por País y ASN** | **100 % incluido** (.mmdb local, regiones, ASN y reglas Y/O por URL) | País solo en Premium; sin bloqueo nativo por ASN | Sí (requiere cuenta/token externo) |
| **Redirecciones por País y Bots SEO** | Redirección PAÍS => URL + doble verificación DNS (`rDNS` + `FCrDNS`) | No incluye redirecciones por país | Sí |
| **Listas de Amenazas (IPs/CIDR)** | **Tiempo real gratis** (Tor, FireHOL, AbuseIPDB + listas propias ilimitadas) | Retraso de 30 días en Free; tiempo real solo en Premium | Solo proxies con API comercial |
| **Cortafuegos WAF y Rate Limit** | Payload (`GET`/`POST`/`FILES`/`Referer`), Rate Limit, User-Agents, Honeypot y `.htaccess` | WAF avanzado con reglas *0-day* comerciales en Premium | No incluye WAF ni Rate Limit |
| **Acceso, 2FA, CAPTCHA y Anti-Crash** | **Hide Login**, **Away Mode**, **Modo Vacaciones**, **8 CAPTCHAs**, 2FA, HIBP, caducidad de claves, sesiones en vivo y **Modo Emergencia `mu-plugins`** | Solo reCAPTCHA v3, 2FA y HIBP; sin Hide Login, Away Mode ni modo emergencia | No incluye CAPTCHA, 2FA ni sesiones |
| **Escáner CVE, SSL y Restauración** | **Escáner CVE gratuito**, Checksums Core + Plugins, **Diff + Restauración 1 clic**, Malware, **Archivos sin uso**, **Logs Web/SSH**, **Monitor SSL/SHA-256**, Permisos y Cron | Escáner completo + reparación (base de CVEs comercial) | No incluye escáner de archivos |
| **Alertas, Auditoría y Migración** | **Telegram (Rich Articles)** + Email visual, cambio ASN, **Activity Log**, widget e **importador Wordfence/IP2Location** | Solo alertas básicas por email | Resumen básico por email |
| **Consumo y Privacidad** | **Muy ligero y 100 % autónomo** (1 tabla propia, cero telemetría) | Pesado (>25 tablas MySQL y conexión a servidores externos) | Moderado (dependiente de API/token) |

---

## Opcional: Auditoría de accesos fallidos por SSH desde WordPress

Si tienes acceso por SSH al servidor y quieres inspeccionar intentos fallidos de autenticación (sshd) desde **3. Auditoría y Hardening**:

1. Crea un enlace simbólico en /var/log/ con el nombre que desees (el desplegable del plugin lo detectará automáticamente):
   sudo ln -s /var/log/auth.log /var/log/bap-ssh.log
   *(En RHEL, AlmaLinux o Rocky Linux sustituye /var/log/auth.log por /var/log/secure).*

2. Concede permiso de solo lectura al usuario del servidor web (ej. www-data, nginx o apache) sobre el archivo original:
   sudo setfacl -m u:www-data:r /var/log/auth.log

---

## Requisitos
* WordPress 6.0 o superior.
* PHP 8.0 o superior (con extensión `zlib` recomendada para descomprimir `.mmdb.gz`).