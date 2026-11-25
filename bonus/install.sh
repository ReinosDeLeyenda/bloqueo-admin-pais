#!/usr/bin/env bash
set -e

if [ "$EUID" -ne 0 ]; then
    echo "❌ Por favor, ejecuta este script como root (usa: sudo ./install.sh)"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE}")" && pwd)"
PYTHON_SCRIPT="$SCRIPT_DIR/tg_monitor.py"
CONFIG_FILE="$SCRIPT_DIR/config.json"
EXAMPLE_FILE="$SCRIPT_DIR/config.example.json"
SERVICE_FILE="/etc/systemd/system/tg-boot-notify.service"
PAM_FILE="/etc/pam.d/sshd"
LOG_FILE="/var/log/tg_monitor.log"

echo "📂 Directorio detectado: $SCRIPT_DIR"

# 1. Verificar o crear config.json
if [ ! -f "$CONFIG_FILE" ]; then
    if [ -f "$EXAMPLE_FILE" ]; then
        cp "$EXAMPLE_FILE" "$CONFIG_FILE"
        chmod 600 "$CONFIG_FILE"
        echo "⚠️  Se ha creado 'config.json' a partir de la plantilla."
        echo "👉 Edítalo con tu TOKEN y CHAT_ID (nano $CONFIG_FILE) y vuelve a ejecutar sudo ./install.sh"
        exit 1
    else
        echo "❌ Error: No se encuentra config.json ni config.example.json"
        exit 1
    fi
fi

if grep -q "TU_TOKEN_DE_TELEGRAM" "$CONFIG_FILE"; then
    echo "⚠️  Parece que aún no has puesto tu token real en $CONFIG_FILE."
    echo "👉 Edítalo primero y vuelve a ejecutar sudo ./install.sh"
    exit 1
fi

# 2. Instalar dependencia python3-requests si falta
if ! python3 -c "import requests" &> /dev/null; then
    echo "📦 Instalando python3-requests..."
    apt-get update -qq && apt-get install -y -qq python3-requests
fi

# 3. Asegurar permisos y archivo de log
echo "🔒 Configurando permisos y archivo de log ($LOG_FILE)..."
chmod 600 "$CONFIG_FILE"
chmod +x "$PYTHON_SCRIPT"
touch "$LOG_FILE"
chmod 640 "$LOG_FILE"

# 4. Crear y activar el servicio systemd
echo "⚙️  Configurando servicio systemd (tg-boot-notify.service)..."
cat <<EOF > "$SERVICE_FILE"
[Unit]
Description=Notificacion de inicio de servidor por Telegram
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=/usr/bin/python3 $PYTHON_SCRIPT boot

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable tg-boot-notify.service

# 5. Configurar gancho PAM en SSH (evitando duplicados)
echo "🔑 Configurando notificación SSH en $PAM_FILE..."
PAM_LINE="session optional pam_exec.so seteuid /usr/bin/python3 $PYTHON_SCRIPT ssh"

if grep -Fq "$PYTHON_SCRIPT" "$PAM_FILE"; then
    sed -i "\|$PYTHON_SCRIPT|c\\$PAM_LINE" "$PAM_FILE"
    echo "   -> Línea existente actualizada en PAM."
else
    echo "" >> "$PAM_FILE"
    echo "# Notificacion SSH por Telegram (tg_monitor)" >> "$PAM_FILE"
    echo "$PAM_LINE" >> "$PAM_FILE"
    echo "   -> Línea añadida a PAM."
fi

echo ""
echo "✅ ¡Instalación completada con éxito!"
echo "🧪 Puedes probar el envío ahora mismo ejecutando:"
echo "   sudo python3 $PYTHON_SCRIPT boot"