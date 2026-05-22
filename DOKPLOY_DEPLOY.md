# Deploy en Dokploy

Este proyecto se despliega como el servicio `bitacora` dentro del proyecto `tracker`.

## Servicio

- Build type: `Dockerfile`
- Dockerfile path: `Dockerfile`
- Port interno: `80`
- Dominio recomendado: `bitacora.trackergps.cloud`
- URL de la app: `https://bitacora.trackergps.cloud/`

El contenedor sirve la app en `/` y mantiene `/bitacora_` como alias de compatibilidad porque el frontend actual usa rutas absolutas con ese prefijo.

## Variables de entorno

Configura estas variables en Dokploy, no en archivos versionados:

- `DB_HOST`
- `DB_PORT`
- `DB_USER`
- `DB_PASS`
- `DB_NAME`
- `JWT_SECRET`
- `MIGRATION_TOKEN`
- `ENVIRONMENT=production`
- `DEBUG=false`
- `FORCE_HTTPS=false`
- `SESSION_SECURE_ONLY=true`
- `ALLOWED_ORIGINS=https://bitacora.trackergps.cloud,https://admin.trackergps.cloud`

Dokploy/Traefik se encarga de HTTPS, por eso `FORCE_HTTPS` queda en `false` dentro del contenedor.

## Validación

```bash
docker build -t bitacora-tracker:latest .
docker run --rm -p 8080:80 --env-file .env.production bitacora-tracker:latest
```

Revisar:

- `http://localhost:8080/`
- `http://localhost:8080/bitacora_/`
- `http://localhost:8080/bitacora_/src/usuarios/login.php`
- `http://localhost:8080/bitacora_/src/admin/resumen.php`
