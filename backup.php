#!/usr/bin/php
<?php

  function ln($txt = ''){ echo $txt.PHP_EOL; }
  function cmd($cmd){
    echo ' '.$cmd.PHP_EOL;
    // TODO: ejecutar $cmd
  }

  function error($txt){
    ln();
    ln('Error: '.$txt);
    ln();
    return false;
  }

  function ayuda(){
    ln();
    ln('Script de backup incremental rsync');
    ln('===================================');    
    ln();
    ln('Uso');
    ln();
    ln(' backup [opciones] fichero_configuración');
    ln();
    ln('Opciones');
    ln();
    ln(' -v                                    muestra información sobre el proceso de copia');
    ln(' -h                                    muestra esta ayuda');
    ln();
    ln('Fichero de configuración, variables requeridas');
    ln();
    ln(' rsync_host      [string]              servicio rsync origen de la copia');
    ln(' rsync_usuario   [string]              usuario del servicio rsync origen de la copia');
    ln(' password_file   [string]              fichero local con la contraseña del servicio rsync');
    ln(' copia_local     [string]              path local base de la copia de seguridad');
    ln();
    ln('Fichero de configuración, variables opcionales');
    ln();
    ln(' inicial         [true|false]          copia inicial, no se hace rotación de copias ( por defecto: false)');
    ln(' copias          [entero]              número de copias a rotar ( por defecto: 10 )');
    ln(' fix_rotacion    [true|false]          corrección de problemas de rotación de copias ( por defecto: true )');
    ln(' fix_permisos    [""|usuario:grupo]    corrección de permisos, se fija el usuario y grupo propietarios de todos los ficheros recuperados');
    ln();
    ln('MICROVALENCIA Soluciones Informáticas, S.L. - www.microvalencia.es');
    ln();
    return true;
  }

  // obtener las opciones de línea de comando y mostrar la ayuda si es necesario

  $opciones = array();
  $fichero_configuracion = '';
  $opciones_validas = array('h', 'v');

  if(count($argv) > 1){
    for($i=1; $i<count($argv); $i++){
      if(substr($argv[$i], 0, 1) == '-') $opciones[] = substr($argv[$i], 1);
      elseif($i == count($argv) - 1) $fichero_configuracion = $argv[$i];
      else return ayuda();
    }  
  }
    
  foreach($opciones as $opcion){
    if(!in_array($opcion, $opciones_validas)) return ayuda();
  }
  if(in_array('h', $opciones) || $fichero_configuracion == '') return ayuda();
  
  $verbose = in_array('v', $opciones);

  // comprobar si el fichero de configuración existe

  if(!file_exists($fichero_configuracion)){
    $fichero_configuracion = '/etc/backups/'.$fichero_configuracion;
    if(!file_exists($fichero_configuracion)){
      return error('No puedo encontrar el fichero de configuración');
    }
  }

  if($verbose){
    ln();
    ln('Usando configuración: '.$fichero_configuracion);
  }

  $cfg = json_decode(file_get_contents($fichero_configuracion));
  if(!is_object($cfg)) return error('Fichero de configuración no válido');
  
  // comprobar si existe la configuración básica y si es válida

  $configuracion_basica = array('rsync_host', 'rsync_usuario', 'password_file', 'copia_local');
  foreach($configuracion_basica as $o) if(!isset($cfg->$o)) return error('Configuración insuficiente');

  if(!file_exists($cfg->password_file)) return error('El fichero local con la contraseña del servicio rsync no existe ('.$cfg->password_file.')');
  // TODO: comprobar permisos del fichero de password
  
  if(substr($cfg->copia_local, -1) == '/') $cfg->copia_local = substr($cfg->copia_local, 0, -1);
  if(!file_exists($cfg->copia_local) || !is_dir($cfg->copia_local) || substr($cfg->copia_local, 0, 1) != '/') return error('El directorio de copia local no existe o no es válido ('.$cfg->copia_local.')');
  // TODO: comprobar permisos de escritura del directorio de copia local

  // comprobar valores por defecto de la configuración opcional
  
  if(!isset($cfg->inicial)) $cfg->inicial = false;
  elseif(!is_bool($cfg->inicial)) return error('El valor de configuración copia inicial no es válido');

  if(!isset($cfg->copias)) $cfg->copias = 10;
  elseif(!is_int($cfg->copias)) return error('El valor de configuración de número de copias no es válido');

  if(!isset($cfg->fix_rotacion)) $cfg->fix_rotacion = true;
  elseif(!is_bool($cfg->fix_rotacion)) return error('El valor de configuración de corrección de problemas rotación no es válido');

  if(!isset($cfg->fix_permisos)) $cfg->fix_permisos = "";
  else {
    $permisos = explode(':', $cfg->fix_permisos);
    if(count($permisos) != 2 || trim($permisos[0]) == '' || trim($permisos[1]) == ''){
      return error('El valor de configuración de corrección de permisos no es válido');
    }
  }

  $t_inicio = time();
  $t = date('Ymd_Gi', $t_inicio);

  if($verbose){
    ln('  rsync_host      : '.$cfg->rsync_host);
    ln('  rsync_usuario   : '.$cfg->rsync_usuario);
    ln('  copia_local     : '.$cfg->copia_local);
    ln('  copias          : '.$cfg->copias);
    ln('  inicial         : '.(($cfg->inicial)?'true':'false'));
    ln('  fix_rotacion    : '.(($cfg->fix_rotacion)?'true':'false'));
    ln('  fix_permisos    : '.$cfg->fix_permisos);
    ln();
    ln('marca de tiempo inicial '.$t);
    ln();
  }

  if(!$cfg->inicial){
    if($verbose) ln('rotando copias...');

    // eliminamos la última copia
    if(file_exists($cfg->copia_local.'/backup.'.($cfg->copias - 1))){
      cmd('rm -rf '.$cfg->copia_local.'/backup.'.($cfg->copias - 1));
    }

    // rotando copias recursivas
    for($i = $cfg->copias - 2; $i>=1; $i--){
      if(file_exists($cfg->copia_local.'/backup.'.$i)){
        cmd('mv '.$cfg->copia_local.'/backup.'.$i.' '.$cfg->copia_local.'/backup.'.($i + 1));
      }
    }

    // duplicando la copia inicial
    if(file_exists($cfg->copia_local.'/backup.0')){
      cmd('cp -al '.$cfg->copia_local.'/backup.0 '.$cfg->copia_local.'/backup.1');
    }
    
    if($cfg->fix_rotacion){
      if($verbose) ln('corrigiendo rotación...');
      for($i=1; $i<=$cfg->copias; $i++){
        if(file_exists($cfg->copia_local.'/backup.'.$i.'/backup.'.($i - 1))){
          if(!file_exists($cfg->copia_local.'/fix')) cmd('mkdir '.$cfg->copia_local.'/fix');
          cmd('mv '.$cfg->copia_local.'/backup.'.$i.'/backup.'.($i - 1).' '.$cfg->copia_local.'/fix');
        }
      }
      if(file_exists($cfg->copia_local.'/fix')){
        cmd('rm -rf '.$cfg->copia_local.'/fix');
        // TODO: enviar alerta de fix_rotacion
      }
    }

  }

  if($verbose) ln('copiando...');
  if(!file_exists($cfg->copia_local.'/backup.0')) cmd('mkdir '.$cfg->copia_local.'/backup.0');
  cmd('rsync -av --delete --password-file='.$cfg->password_file.' rsync://'.$cfg->rsync_host.'@'.$cfg->rsync_usuario.'/* '.$cfg->copia_local.'/backup.0/');

  if($cfg->fix_permisos != ''){
    if($verbose) ln('corrigiendo permisos...');
    cmd('chown -R '.$cfg->fix_permisos.' '.$cfg->copia_local.'/backup.0/');
    cmd('chmod -R g-s '.$cfg->copia_local.'/backup.0/');
    cmd('find '.$cfg->copia_local.'/backup.0/ -type d -exec chmod 775 {} \;');
    cmd('find '.$cfg->copia_local.'/backup.0/ -type f -exec chmod 664 {} \;');
  }
  
  $t_fin = time();
  $duracion = $t_fin - $t_inicio;
  if($verbose){
    ln();
    ln('marca de tiempo final '.$t);
    ln('duración del proceso '.$duracion.'s');
    ln();
  }