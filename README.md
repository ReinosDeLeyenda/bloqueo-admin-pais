\# Bloqueo Admin y Web por País, ASN e IP (WordPress)



Plugin ligero y autónomo de seguridad para WordPress que protege tanto el panel de administración (\*\*Backend\*\*) como la web pública (\*\*Frontend\*\*) mediante bases de datos locales, inteligencia de amenazas, bloqueo a nivel de servidor (`.htaccess`) y defensa activa contra fuerza bruta.



\---



\## Características principales



\### 1. Bases de Datos Locales y Actualización Automática

\* \*\*5 fuentes gratuitas en local (sin latencia externa por visita):\*\*

&#x20; \* \*\*Países (`.mmdb`):\*\* Geolocalización IPv4/IPv6 mediante lector binario `.mmdb` nativo en PHP (sin dependencias Composer).

&#x20; \* \*\*ASN / Proveedores (`.mmdb`):\*\* Identificación de operadoras y centros de datos (OVH, DigitalOcean, Hetzner, etc.).

&#x20; \* \*\*AbuseIPDB:\*\* Lista de IPs maliciosas reportadas (compatible con espejo público ≥ 90 % o API Key oficial gratuita con puntuación exacta `0–100 %`).

&#x20; \* \*\*Nodos de Salida Tor:\*\* Lista oficial de nodos de salida de la red Tor.

&#x20; \* \*\*FireHOL Level 1 / Spamhaus DROP:\*\* Subredes CIDR secuestradas o maliciosas.

\* \*\*Descarga masiva y programada:\*\* Actualización en 1 clic o automática mediante \*\*WP-Cron\*\* (Diaria, Semanal, Cada 2 semanas o Mensual).

\* \*\*Exportar / Importar configuración (`.json`):\*\* Permite clonar todas las reglas entre instalaciones de WordPress (excluyendo claves privadas por defecto).



\### 2. Bloqueos Admin (Backend)

\* \*\*Rutas personalizables con comodines (`\*`):\*\* Protege por defecto `/wp-admin/\*`, `/wp-login.php`, `/wp-register.php`, `/xmlrpc.php`, `/wp-json/wp/v2/users\*` y archivos sensibles (`\*.log`, `\*.zip`, `\*.rar`, `\*.tar.gz`, `\*.sql`, `\*.env`, `\*.git\*`).

\* \*\*Bloqueo rápido vía `.htaccess` (opcional):\*\* Corta descargas directas de archivos sensibles en Apache/LiteSpeed antes de cargar PHP.

\* \*\*Filtros exclusivos (Permitidos o Bloqueados):\*\* Control independiente por \*\*País\*\* (con buscador autocompletable en español) y por \*\*ASN\*\*.

\* \*\*Defensa activa:\*\* Auto-baneo tras X intentos fallidos y bloqueo instantáneo de intentos con usuarios inexistentes.

\* \*\*Llave secreta URL de emergencia:\*\* Acceso de respaldo mediante parámetro URL con validación de complejidad Unicode LTR, generador aleatorio y botón ocultar/copiar.

\* \*\*Notificaciones por email:\*\* Alertas instantáneas o resúmenes agrupados (1h, 6h, 12h, diario, semanal, quincenal o mensual).



\### 3. Bloqueos Web (Frontend)

\* \*\*Reglas independientes del panel:\*\* Aplicable a toda la web pública o solo a rutas específicas (`/tienda/\*`, `/descargas/\*`, etc.).

\* \*\*Exclusión de bots SEO verificados:\*\* Doble verificación DNS inversa (`rDNS` + confirmación directa) para \*\*Googlebot\*\*, \*\*Bingbot\*\*, \*\*Applebot\*\* y \*\*DuckDuckBot\*\* con caché de 24 horas.

\* \*\*Redirección o mensaje 403 personalizado.\*\*



\### 4. Estadísticas e Historial

\* Panel con contadores en tiempo real, ranking de \*\*Top Países\*\* y \*\*Top ASNs\*\*.

\* Tabla interactiva ordenable por cualquier columna con consulta directa a \*\*IP2Location\*\* y \*\*AbuseIPDB\*\*.



\---



\## Instalación



1\. Descarga o clona este repositorio dentro de `/wp-content/plugins/bloqueo-admin-pais/`.

2\. Activa el plugin desde el menú \*\*Plugins\*\* de WordPress.

3\. Entra en \*\*Bloqueo Admin -> 1. Administración Base de Datos\*\* y pulsa \*\*Descargar / Actualizar TODAS las bases de datos\*\*.

4\. Configura tus reglas en \*\*2. Bloqueos Admin\*\* y \*\*3. Bloqueos Web\*\*.



\---



\## Requisitos

\* WordPress 6.0 o superior.

\* PHP 8.0 o superior (con extensión `zlib` recomendada para descomprimir `.mmdb.gz`).

