# Compatibilidad Xtream Codes (IPTV Smarters, XCIPTV, TiviMate…)

Fork Panel XC expone la superficie HTTP que usan las apps tipo Xtream Codes,
autenticando con las credenciales de una **línea** (`user_lines`).

## Endpoints

| Endpoint | Uso |
| --- | --- |
| `/player_api.php?username=&password=` | Login JSON (`user_info` + `server_info`) |
| `/player_api.php?...&action=get_live_categories` | Categorías live |
| `…&action=get_live_streams` | Canales live |
| `…&action=get_vod_categories` / `get_vod_streams` / `get_vod_info` | VOD |
| `…&action=get_series_categories` / `get_series` / `get_series_info` | Series |
| `…&action=get_short_epg` / `get_simple_data_table` | EPG (vacío si no hay datos) |
| `/get.php?username=&password=&type=m3u_plus&output=ts` | Lista M3U |
| `/xmltv.php?username=&password=` | Guía XMLTV |
| `/live/{username}/{password}/{id}.m3u8` | Canal (gateway HLS del panel) |
| `/movie/{username}/{password}/{id}.{ext}` | Película (gateway) |
| `/series/{username}/{password}/{episode_id}.{ext}` | Episodio (gateway) |
| `/stream/{token}` | Playlist/segmento firmado del gateway |

La respuesta de login incluye `user_info` (auth, status, exp_date,
max_connections, allowed_output_formats) y `server_info` (url, port,
server_protocol).

## IPTV Smarters Player

1. Añadir usuario → **Xtream Codes API**.
2. Nombre: cualquiera.
3. Usuario y contraseña: los de una **línea** (Users & Lines → Lines).
4. URL del servidor: `http://81.181.198.198` (o `http://tu-dominio`).
   - Si la app pide la URL completa XC:
     `http://81.181.198.198/player_api.php?username=USUARIO&password=CLAVE`
5. Guardar y entrar.

También puedes usar la lista M3U:

```
http://81.181.198.198/get.php?username=USUARIO&password=CLAVE&type=m3u_plus&output=ts
```

Y la EPG:

```
http://81.181.198.198/xmltv.php?username=USUARIO&password=CLAVE
```

## Requisitos

- La línea debe existir, estar **enabled** y no expirada.
- El canal debe estar `enabled` y su fuente ser `http://` o `https://`.
- El panel actúa de **gateway**: descarga el master/variante HLS, reescribe sus
  URIs a `/stream/{token}` firmados y sirve los segmentos. Así puede contar
  viewers, conexiones y bandwidth.
- nginx debe enrutar `player_api.php`, `get.php`, `xmltv.php`, `/live|movie|series`
  y `/stream/` al front controller. El instalador actual ya lo hace.

## Estadísticas

Cada petición de playlist/segmento actualiza `stream_sessions`. Con sesiones
activas en los últimos 45 s se calculan:

- `stream_stats.current_viewers` / `total_connections`
- `stream_stats.bandwidth` (kbps, suma de bitrates estimados)
- `user_lines.connected` y `user_lines.bitrate`
- `streams.status` = `online`/`offline`

Las estadísticas del dashboard y de la pestaña **Stats** del canal se alimentan
de esos valores.

## Limitaciones actuales

- No se aplica el límite de conexiones por línea (solo login/expiración).
- No hay transcodificación ni empaquetado propio: se reenvían los bytes de la
  fuente. Si el origen exige IP/User-Agent distintos, la app podría fallar.
- Fuentes locales `file://` del Library Scanner no se sirven por HTTP todavía.
- EPG depende de que `epg_channels`/`epg_programmes` tengan datos.
- El bandwidth es una estimación (tamaño de segmento / 6 s); no sustituye a un
  medidor de tráfico.
- Con HTTPS, `server_info` anuncia el puerto 443; asegúrate de tener TLS y de que
  la app use `https://`.

## Seguridad

Las credenciales de línea viajan en la URL (así funciona XC). Usa HTTPS en
producción, rota la contraseña de la línea si se filtra y no reutilices la
contraseña de admin como contraseña de línea.
