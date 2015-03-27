# Script de backup incremental rsync

## Uso

	backup.php [opciones] fichero_configuración

## Opciones

	-v                                    muestra información sobre el proceso de copia
	-h                                    muestra esta ayuda

## Fichero de configuración (JSON), variables requeridas

	rsync_host      [string]              servicio rsync origen de la copia
	rsync_usuario   [string]              usuario del servicio rsync origen de la copia
	password_file   [string]              fichero local con la contraseña del servicio rsync
	copia_local     [string]              path local base de la copia de seguridad

## Fichero de configuración (JSON), variables opcionales

	inicial         [true|false]          copia inicial, no se hace rotación de copias ( por defecto: false)
	copias          [entero]              número de copias a rotar ( por defecto: 10 )
	fix_rotacion    [true|false]          corrección de problemas de rotación de copias ( por defecto: true )
	fix_permisos    [""|usuario:grupo]    corrección de permisos, se fija el usuario y grupo propietarios de todos los ficheros recuperados

## Créditos

MICROVALENCIA Soluciones Informáticas, S.L. 
(http://www.microvalencia.es)http://www.microvalencia.es