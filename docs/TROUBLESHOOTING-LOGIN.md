# No puedo iniciar sesión — diagnóstico y solución

Aplica a instalaciones hechas con `install.sh` en Ubuntu/Debian.

## 1. Comprueba qué responde el API

```bash
# Debe responder 401 con {"error":"Authentication required"}
curl -i http://127.0.0.1/api/auth/me

# Prueba el login con la contraseña que imprimió el instalador
curl -i -X POST http://127.0.0.1/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"TU_PASSWORD"}'
```

Interpretación:

| Respuesta | Significado |
| --- | --- |
| `401 {"error":"Authentication required"}` | El API funciona; el problema es credenciales o frontend. |
| `422` | El API funciona; faltan campos en la petición. |
| `500` | El API no puede leer configuración o conectar a la base de datos. |
| `502/504` | nginx no llega a PHP-FPM (socket o servicio caído). |
| HTML/404 | nginx no está enrutando `/api` al front controller. |

## 2. Causa habitual: PHP-FPM no puede leer `config/.env`

El instalador antiguo usaba el pool `www` (usuario `www-data`), pero
`config/.env` pertenece a `fork_panel_xc`. Sin acceso al `.env`, el API no tiene
credenciales de base de datos y el login devuelve **500**.

Comprobación:

```bash
sudo -u www-data test -r /home/fork_panel_xc/config/.env && echo "legible" || echo "NO legible"
sudo journalctl -u php8.3-fpm -n 50 --no-pager | grep -iE 'denied|permission|env'
```

### Arreglo rápido (30 segundos)

```bash
sudo chgrp www-data /home/fork_panel_xc/config/.env
sudo chmod 640 /home/fork_panel_xc/config/.env
sudo systemctl reload php8.3-fpm
```

Vuelve a probar el login.

### Arreglo definitivo (recomendado)

El instalador actual crea un pool propio de PHP-FPM que corre como
`fork_panel_xc` y usa su propio socket. Vuelve a ejecutarlo sin reconstruir el
frontend ni tocar la base de datos:

```bash
cd /root   # o donde tengas el instalador
curl -fsSL https://raw.githubusercontent.com/TalaveraSama/fork-panel-xc/main/install.sh -o install.sh
bash -n install.sh
sudo bash install.sh --skip-frontend
```

Reutiliza la contraseña de base de datos y la del admin que ya están en
`config/.env`, crea `/etc/php/8.3/fpm/pool.d/fork_panel_xc.conf`, apunta nginx a
`/run/php/php8.3-fork_panel_xc.sock` y reinicia los servicios.

Verifica:

```bash
ls -l /run/php/php8.3-fork_panel_xc.sock
sudo systemctl status php8.3-fpm --no-pager
curl -i http://127.0.0.1/api/auth/me
```

## 3. Contraseña incorrecta o reseteada

Si el login responde `401 {"error":"Invalid username or password"}`, resetea la
contraseña desde la terminal (no necesita el panel):

```bash
sudo -u fork_panel_xc php /home/fork_panel_xc/console.php user:password admin 'NuevaClaveSegura123'
```

Comprueba que el usuario existe:

```bash
mysql -uroot fork_panel_xc -e "SELECT id, username, enabled FROM users;"
```

## 4. Base de datos

```bash
# Credenciales guardadas por el instalador
sudo grep -E '^(DB_|APP_ADMIN_PASSWORD)' /home/fork_panel_xc/config/.env

# Prueba de conexión con esas credenciales
mysql -u fork_panel_xc -p fork_panel_xc -e "SELECT 1;"
```

Si la contraseña del `.env` no coincide con la del usuario de MariaDB, vuelve a
ejecutar el instalador: hace `ALTER USER` con la contraseña del `.env`, así que
ambas quedan sincronizadas.

## 5. Servicios y socket

```bash
sudo systemctl status php8.3-fpm nginx mariadb redis-server --no-pager
ls -l /run/php/*.sock
sudo tail -n 50 /var/log/nginx/error.log
sudo tail -n 50 /home/fork_panel_xc/storage/logs/php-fpm-error.log
```

Si el socket del pool no existe, revisa la configuración:

```bash
sudo php-fpm8.3 -tt 2>&1 | tail -n 20
```

## 6. Reinicio limpio

```bash
sudo systemctl restart mariadb redis-server php8.3-fpm nginx
sudo systemctl restart fork-panel-xc-worker fork-panel-xc-scheduler
curl -i http://127.0.0.1/api/auth/me
```

## 7. Si el instalador falla con `syntax error near unexpected token`

El archivo `install.sh` local está corrupto o fue copiado con caracteres de
Windows. Descárgalo de nuevo y valídalo antes de ejecutarlo:

```bash
curl -fsSL https://raw.githubusercontent.com/TalaveraSama/fork-panel-xc/main/install.sh -o install.sh
bash -n install.sh          # debe terminar sin errores
sudo bash install.sh --skip-frontend
```
