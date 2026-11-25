#!/usr/bin/env python3
import os
import sys
import json
import socket
import logging
import requests

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CONFIG_PATH = os.path.join(BASE_DIR, "config.json")
LOG_PATH = "/var/log/tg_monitor.log"

def setup_logger():
    logger = logging.getLogger("tg_monitor")
    logger.setLevel(logging.INFO)
    formatter = logging.Formatter(
        "%(asctime)s [%(levelname)s] %(message)s",
        datefmt="%Y-%m-%d %H:%M:%S"
    )
    try:
        handler = logging.FileHandler(LOG_PATH, encoding="utf-8")
    except PermissionError:
        # Fallback si se ejecuta manualmente sin sudo
        handler = logging.FileHandler(os.path.join(BASE_DIR, "tg_monitor.log"), encoding="utf-8")
    
    handler.setFormatter(formatter)
    logger.addHandler(handler)
    return logger

log = setup_logger()

def load_config():
    try:
        with open(CONFIG_PATH, "r", encoding="utf-8") as f:
            return json.load(f)
    except FileNotFoundError:
        log.error(f"No se encuentra el archivo de configuración: {CONFIG_PATH}")
        sys.exit(1)
    except json.JSONDecodeError:
        log.error(f"Formato JSON inválido en: {CONFIG_PATH}")
        sys.exit(1)

CONFIG = load_config()
BOT_TOKEN = CONFIG.get("bot_token")
CHAT_ID = CONFIG.get("chat_id")
ALLOWED_ASNS = [asn.strip().upper() for asn in CONFIG.get("allowed_asns", [])]
HOSTNAME = socket.gethostname()

def send_telegram(message):
    url = f"https://telegram.org{BOT_TOKEN}/sendMessage"
    payload = {
        "chat_id": CHAT_ID,
        "text": message,
        "parse_mode": "HTML"
    }
    try:
        res = requests.post(url, json=payload, timeout=10)
        if res.status_code == 200:
            log.info("Notificación enviada correctamente a Telegram.")
        else:
            log.error(f"Error de API Telegram ({res.status_code}): {res.text}")
    except Exception as e:
        log.error(f"Fallo de red enviando mensaje a Telegram: {e}")

def check_ip_asn(ip):
    if ip.startswith(("127.", "10.", "192.168.", "::1")) or ip == "":
        return True, "Red Local / Privada"
    
    try:
        res = requests.get(
            f"http://ip-api.com{ip}?fields=status,as,isp,country,city",
            timeout=5
        ).json()
        
        if res.get("status") == "success":
            as_info = res.get("as", "")
            isp = res.get("isp", "Desconocido")
            loc = f"{res.get('city', '')}, {res.get('country', '')}"
            
            current_asn = as_info.split().upper() if as_info else ""
            is_allowed = current_asn in ALLOWED_ASNS
            
            return is_allowed, f"{as_info} ({isp} - {loc})"
        else:
            log.warning(f"Respuesta inesperada de ip-api para {ip}: {res}")
    except Exception as e:
        log.error(f"Error consultando ASN para {ip}: {e}")
        return False, f"Error consultando ASN: {e}"
        
    return False, "Desconocido"

def handle_boot():
    log.info(f"Evento de arranque detectado en {HOSTNAME}.")
    msg = (
        f"🟢 <b>Servidor Iniciado</b>\n"
        f"🖥 <b>Host:</b> <code>{HOSTNAME}</code>"
    )
    send_telegram(msg)

def handle_ssh():
    pam_type = os.environ.get("PAM_TYPE", "")
    if pam_type != "open_session":
        return

    user = os.environ.get("PAM_USER", "desconocido")
    rhost = os.environ.get("PAM_RHOST", "")

    if not rhost:
        log.warning(f"Sesión SSH abierta para '{user}' sin IP remota (PAM_RHOST vacío).")
        return

    is_allowed, details = check_ip_asn(rhost)
    
    if is_allowed:
        log.info(f"Acceso SSH PERMITIDO | Usuario: {user} | IP: {rhost} | Red: {details}")
    else:
        log.warning(f"Acceso SSH EXTERNO (Alerta) | Usuario: {user} | IP: {rhost} | Red: {details}")
        msg = (
            f"🚨 <b>Alerta de Acceso SSH Externo</b>\n"
            f"🖥 <b>Host:</b> <code>{HOSTNAME}</code>\n"
            f"👤 <b>Usuario:</b> <code>{user}</code>\n"
            f"🌐 <b>IP:</b> <code>{rhost}</code>\n"
            f"🏢 <b>Red/ASN:</b> {details}"
        )
        send_telegram(msg)

if __name__ == "__main__":
    if len(sys.argv) > 1:
        mode = sys.argv
        if mode == "boot":
            handle_boot()
        elif mode == "ssh":
            handle_ssh()
