# Script de backup incremental rsync

## Uso
	backup.php [opciones] fichero_configuración

## Opciones
	-d            modo directorio, se ejecuta el proceso sobre cada uno de los ficheros de configuración encontrados en el directorio dado
	-v            muestra información sobre el proceso de copia
	-rv           muestra información sobre la salida del proceso rsync (rsync -v)
	-test         comprueba la configuración sin realizar ninguna copia, implica -v
	-h            muestra esta ayuda

## Fichero de configuración (JSON), variables requeridas
	rsync_host    [string]            servicio rsync origen de la copia
	rsync_usuario [string]            usuario del servicio rsync origen de la copia
	password_file [string]            fichero local con la contraseña del servicio rsync
	copia_local   [string]            path local base de la copia de seguridad

## Fichero de configuración (JSON), variables opcionales
	inicial       [true|false]        copia inicial, no se hace rotación de copias ( por defecto: false)
	copias        [entero]            número de copias a rotar ( por defecto: 10 )
	fix_rotacion  [true|false]        corrección de problemas de rotación de copias ( por defecto: true )
	fix_permisos  [""|usuario:grupo]  corrección de permisos, se fija el usuario y grupo propietarios de todos los ficheros recuperados ( por defecto: "" (vacio) )

## Fichero de configuración de ejemplo
	{
		"rsync_host"    : "host/servicio",
		"rsync_usuario" : "backup",
		"password_file" : "/etc/rsyncd.secrets",
		"copia_local"   : "/var/backups/host.servicio/",
		"inicial"       : false,
		"copias"        : 10,
		"fix_rotacion"  : true,
		"fix_permisos"  : "microvalencia:microvalencia"
	}

## Configuración global

Configuración global opcional, indicando la ruta de logs para el modo directorio y la caducidad de los logs, tanto globales como locales.

Además se puede indicar credenciales de API Mandrill [https://mandrillapp.com](https://mandrillapp.com) y el remitente de alertas enviadas por correo. Se enviarán los errores de configuración y del proceso rsync principal en tiempo de ejecución

## Fichero de configuración global de ejemplo (valores por defecto)
	{
		"logs"             : "/var/log/backups",
	  "caducidad_logs"   : 10,
	  "mandrill_api"     : "",
	  "mandrill_destino" : ""
	}

### Créditos

MICROVALENCIA Soluciones Informáticas, S.L. 
[http://www.microvalencia.es](http://www.microvalencia.es)