# Sistema de Gestión de Reservas Teatrales

Una plataforma web completa para la gestión de eventos, reserva de butacas en tiempo real y emisión de tickets digitales. Desarrollada como proyecto de portfolio para demostrar competencias en backend con PHP, bases de datos relacionales y despliegue en la nube.

## Características Principales

* **Selección Visual de Asientos:** Mapa interactivo de butacas que bloquea los asientos en tiempo real (evitando dobles reservas).
* **Generación de Entradas (PDF + QR):** Creación dinámica de tickets en PDF con códigos QR únicos usando `FPDF` y `phpqrcode`.
* **Notificaciones por Email:** Envío automático de confirmaciones y entradas adjuntas al cliente mediante `PHPMailer` (SMTP).
* **Panel de Administración:** Zona segura (protegida por sesión) para validar pagos, cambiar el estado de las reservas y gestionar el catálogo de obras.
* **Seguridad:** Protección contra Inyecciones SQL mediante PDO (Prepared Statements), tokens CSRF y ocultación de errores en producción (CCN-CERT).

## Stack Tecnológico

* **Backend:** PHP 8.x
* **Base de Datos:** MariaDB (Desplegada en Filess.io)
* **Frontend:** HTML5, CSS3, JavaScript Vanilla
* **Librerías Externas:** PHPMailer, FPDF, PHPQRCode
* **Despliegue:** Render + Filess.io

## Arquitectura y Despliegue

Este proyecto está diseñado para funcionar en un entorno distribuido:
1. El código fuente se ejecuta en un entorno serverless a través de **Render**.
2. La persistencia de datos se maneja externamente mediante una conexión segura por puerto a un clúster de MariaDB en **Filess.io**.
3. *Nota:* Debido a la capa gratuita de Render, la web puede tardar unos 30-50 segundos en cargar la primera vez si el servidor está en reposo.

## Configuración Local

1. git clone https://github.com/mario-sb11/teatroweb.git
2. Renombra el archivo `includes/config.php.example` a `config.php` y añade tus credenciales de BD y SMTP.
3. Importa el archivo `teatro_db.sql` (no incluido por privacidad) en tu servidor MySQL/MariaDB.
   * **¿Eres un reclutador o desarrollador interesado?** Si deseas realizar una prueba técnica local o revisar la estructura de tablas y relaciones, puedes solicitar el archivo SQL contactando directamente conmigo a través de mi perfil de GitHub o por correo electrónico.
4. Inicia un servidor local (ej. XAMPP) y accede a la ruta del proyecto.