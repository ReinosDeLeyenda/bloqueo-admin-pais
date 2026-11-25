# Monitor de Servidor Linux por Telegram (`tg_monitor`)

Script ligero en Python que envía notificaciones instantáneas a un chat de Telegram cuando:
1. El servidor Linux se inicia o reinicia (mediante `systemd`).
2. Se establece una sesión SSH desde una IP cuyo ASN **no** esté en la lista blanca configurada (mediante `PAM`).

---

## Estructura de archivos

```text
bonus/
├── install.sh           # Instalador automático (systemd + PAM)
├── uninstall.sh         # Desinstalador limpio
├── tg_monitor.py        # Script principal de monitoreo
├── config.example.json  # Plantilla de configuración
├── config.json          # Credenciales reales (ignorado por Git)
└── README.md            # Documentación
```

---

## Instalación rápida

1. **Entra en el directorio `bonus`:**
   ```bash
   cd /ruta/a/tu-repo/bonus
   ```

2. **Crea el archivo de configuración con tus credenciales:**
   ```bash
   cp config.example.json config.json
   nano config.json
   ```
   Rellena los campos con tus datos:
   ```json
   {
       "bot_token": "123456789:ABCdefGHIjklMNOpqrsTUVwxyz",
       "chat_id": "987654321",
       "allowed_asns": [
           "AS57269"
       ]
   }
   ```
   *(Puedes añadir tantos ASN permitidos como necesites dentro de la lista `allowed_asns`).*

3. **Ejecuta el instalador:**
   ```bash
   sudo ./install.sh
   ```
   El script instalará `python3-requests` si falta, ajustará los permisos (`600` para `config.json`), creará el servicio `systemd` y configurará el gancho en `/etc/pam.d/sshd`.

---

## Pruebas

Puedes verificar que todo funciona antes de reiniciar o conectarte desde otra red:

* **Simular arranque del servidor:**
  ```bash
  sudo python3 tg_monitor.py boot
  ```

* **Simular acceso SSH desde una IP externa (ej. Google `8.8.8.8`):**
  ```bash
  sudo PAM_TYPE=open_session PAM_USER=root PAM_RHOST=8.8.8.8 python3 tg_monitor.py ssh
  ```

---

## Desinstalación

Para desactivar las notificaciones y limpiar el servicio `systemd` junto con la entrada en `PAM`:

```bash
sudo ./uninstall.sh
```
*(Tus archivos del repositorio y tu `config.json` no se borrarán).*
