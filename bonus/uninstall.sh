#!/usr/bin/env bash
set -e

if [ "$EUID" -ne 0 ]; then
    echo "❌ Por favor, ejecuta este script como root (usa: sudo ./uninstall.sh)"
    exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE}")" && pwd)"
PYTHON_SCRIPT="$SCRIPT_DIR/tg_monitor.py"
SERVICE_NAME="tg-boot-notify.service"
SERVICE_FILE="/etc/systemd/system/$SERVICE_NAME"
PAM_FILE="/etc/pam.d/sshd"
LOG_FILE="/var/log/tg_monitor.log"

echo "🧹 Desinstalando monitor de Telegram..."

# 1. Desactivar y eliminar el servicio systemd
if [ -f "$SERVICE_FILE" ]; then
    echo "⚙️  Eliminando servicio systemd ($SERVICE_NAME)..."
    systemctl disable "$SERVICE_NAME" 2>/dev/null || true
    rm -f "$SERVICE_FILE"
    systemctl daemon-reload
    echo "   -> Servicio eliminado."
else
    echo "ℹ️  El servicio systemd no estaba instalado."
fi

# 2. Limpiar el gancho PAM de SSH
if grep -Fq "$PYTHON_SCRIPT" "$PAM_FILE"; then
    echo "🔑 Eliminando regla SSH de $PAM_FILE..."
    sed -i '/# Notificacion SSH por Telegram (tg_monitor)/d' "$PAM_FILE"
    sed -i "\|$PYTHON_SCRIPT|d" "$PAM_FILE"
    echo "   -> Regla eliminada de PAM."
else
    echo "ℹ️  No se encontraron reglas en $PAM_FILE."
fi

# 3. Eliminar archivo de log
if [ -f "$LOG_FILE" ]; then
    rm -f "$LOG_FILE"
    echo "🗑️  Archivo de log ($LOG_FILE) eliminado."
fi

echo ""
echo "✅ Desinstalación completada."
echo "📁 Tus archivos locales y 'config.json' se mantienen intactos en $SCRIPT_DIR."