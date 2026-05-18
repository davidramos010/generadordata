# Configuración Docker para GeneradorData

Esta configuración permite ejecutar la página web GeneradorData usando Docker con soporte completo para PHP y MySQL.

## Archivos incluidos

- `Dockerfile`: Imagen personalizada con PHP 8.1 y Apache
- `docker-compose.yml`: Orquestación de servicios (web + MySQL)
- `init.sql`: Script de inicialización de la base de datos
- `.dockerignore`: Archivos a excluir del contexto Docker

## Requisitos

- Docker
- Docker Compose

## Instrucciones de uso

### 1. Construir y ejecutar los contenedores

```bash
docker-compose up --build
```

### 2. Acceder a la aplicación

- **Página web**: http://localhost:8082
- **Base de datos MySQL**: localhost:3306

### 3. Detener los contenedores

```bash
docker-compose down
```

### 4. Detener y eliminar volúmenes (datos de BD)

```bash
docker-compose down -v
```

## Configuración de la base de datos

La base de datos se inicializa automáticamente con:
- **Base de datos**: admin_BD
- **Usuario**: userBD
- **Contraseña**: passBD
- **Tabla**: conter (para el contador de visitas)

## Características

- ✅ Servidor web Apache con PHP 8.1
- ✅ Soporte para MySQL con extensiones mysqli y PDO
- ✅ Contador de visitas funcional
- ✅ Volúmenes persistentes para la base de datos
- ✅ Hot reload (cambios en archivos se reflejan automáticamente)
- ✅ Puerto personalizado 8082

## Solución de problemas

Si tienes problemas con permisos:
```bash
sudo chown -R $USER:$USER .
```

Si necesitas reconstruir completamente:
```bash
docker-compose down -v
docker-compose up --build --force-recreate
```
