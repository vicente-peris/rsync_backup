#!/usr/bin/php
<?php

  $t_inicio = time();
  $GLOBALS['prelog'] = '';
  $GLOBALS['argv'] = $argv;

  function ln($txt = ''){ 
    $out = $txt.PHP_EOL; 
    if(isset($GLOBALS['verbose']) && $GLOBALS['verbose']) echo $out;
    if(isset($GLOBALS['log'])) file_put_contents($GLOBALS['log'], $out, FILE_APPEND);
    else $GLOBALS['prelog'] .= $out;
  }

  function ayuda(){
    ln();
    ln('Script de backup incremental rsync');
    ln('===================================');    
    ln();
    ln('Uso');
    ln();
    ln(' backup.php [opciones] fichero_configuración');
    ln();
    ln('Opciones');
    ln();
    ln(' -d              modo directorio, se ejecuta el proceso sobre cada uno de los ficheros de configuración encontrados en el directorio dado, implica -v');
    ln(' -v              muestra información sobre el proceso de copia');
    ln(' -rv             muestra información sobre la salida del proceso rsync (rsync -v)');
    ln(' -test           comprueba la configuración sin realizar ninguna copia, implica -v');
    ln(' -h              muestra esta ayuda');
    ln();
    ln('Fichero de configuración (JSON), variables requeridas');
    ln();
    ln(' rsync_host      [string]              servicio rsync origen de la copia');
    ln(' rsync_usuario   [string]              usuario del servicio rsync origen de la copia');
    ln(' password_file   [string]              fichero local con la contraseña del servicio rsync');
    ln(' copia_local     [string]              path local base de la copia de seguridad');
    ln();
    ln('Fichero de configuración (JSON), variables opcionales');
    ln();
    ln(' inicial         [true|false]          copia inicial, no se hace rotación de copias ( por defecto: false)');
    ln(' copias          [entero]              número de copias a rotar ( por defecto: 10 )');
    ln(' fix_rotacion    [true|false]          corrección de problemas de rotación de copias ( por defecto: true )');
    ln(' fix_permisos    [""|usuario:grupo]    corrección de permisos, se fija el usuario y grupo propietarios de todos los ficheros recuperados');
    ln();
    ln('MICROVALENCIA Soluciones Informáticas, S.L. - www.microvalencia.es');
    ln();
  }

  function cmd($cmd){
    ln(' '.$cmd);
    $log = array();
    exec($cmd.' 2>&1', $log);
    foreach($log as $l) ln('    '.$l);
    if(strpos($cmd, 'rsync ') === 0){
      foreach($log as $l){
        if(strpos($l, 'rsync error:') === 0){
          alerta($l);
          break;
        }
      }
    }
  }

  function error($txt){
    ln();
    ln('Error: '.$txt);
    ln();
    alerta($txt);
    exit(1);
  }

  function alerta($txt){
    if(isset($GLOBALS['mandrill_api']) && isset($GLOBALS['mandrill_destino'])){
      ln();
      ln('enviando alerta: '.$txt);
      ln();
      $alerta  = '<strong>'.$txt.'</strong><br/>';
      $alerta .= 'cmd: '.implode(' ', $GLOBALS['argv']);
      mandrill('Alerta backup rsync', $alerta);
    }
  }

  function mandrill($asunto, $html, $txt = '', $tags = array()){

    $post = array();
    $post['key'] = $GLOBALS['mandrill_api'];
    $post['message'] = array();
    if($html != '') $post['message']['html'] = $html;
    if($txt != '') $post['message']['txt'] = $txt;
    else $post['message']['auto_text'] = true;
    $post['message']['subject'] = $asunto;
    $post['message']['from_email'] = $GLOBALS['mandrill_destino'];
    $post['message']['from_name'] = "Script backup rsync";
    $post['message']['to'] = array();
    $post['message']['to'][] = array('email' => $GLOBALS['mandrill_destino']);
    $post['message']['track_opens'] = false;
    $post['message']['track_clicks'] = false;
    if(is_array($tags) && count($tags) > 0) $post['message']['tags'] = $tags;
    
    $ch = curl_init(); 
    curl_setopt($ch, CURLOPT_URL, 'https://mandrillapp.com/api/1.0/messages/send.json'); 
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); 
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE); 
    curl_setopt($ch, CURLOPT_POST, TRUE); 
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post));   
    $data = curl_exec($ch); 
    curl_close($ch);

  }

  // obtener las opciones de línea de comando y mostrar la ayuda si es necesario

  $opciones = array();
  $fichero_configuracion = '';
  $opciones_validas = array('h', 'v', 'rv', 'd', 'test');

  if(count($argv) > 1){
    for($i=1; $i<count($argv); $i++){
      if(substr($argv[$i], 0, 1) == '-') $opciones[] = substr($argv[$i], 1);
      elseif($i == count($argv) - 1) $fichero_configuracion = $argv[$i];
      else {
        ayuda();
        error('Sintáxis incorrecta');
      }
    }  
  }
    
  foreach($opciones as $opcion){
    if(!in_array($opcion, $opciones_validas)){
      ayuda();
      error('Opciones no reconocidas');
    }
  }

  if($fichero_configuracion == ''){
    ayuda();
    error('Falta fichero de configuracion');
  }

  if(in_array('h', $opciones)){
    // mostrar ayuda
    ayuda();
    exit(0);
  } 

  $GLOBALS['verbose'] = in_array('v', $opciones) || in_array('d', $opciones) || in_array('test', $opciones);
  $configuracion_basica = array('rsync_host', 'rsync_usuario', 'password_file', 'copia_local');

  // obteniendo configuración global y estableciendo valores por defecto
  
  $f_config = '/etc/backup.conf';
  if(file_exists($f_config) && is_readable($f_config)){
    $cfg_global = json_decode(file_get_contents($f_config));
    if(!is_object($cfg_global)) $cfg_global = new stdClass();
  } else $cfg_global = new stdClass();

  if(!isset($cfg_global->logs)) $cfg_global->logs = '/var/log/backups/';
  if(substr($cfg_global->logs, -1) != '/') $cfg_global->logs .= '/';
  if(!isset($cfg_global->caducidad_logs)) $cfg_global->caducidad_logs = 10;

  if(isset($cfg_global->mandrill_api)) $GLOBALS['mandrill_api'] = $cfg_global->mandrill_api;
  if(isset($cfg_global->mandrill_destino)) $GLOBALS['mandrill_destino'] = $cfg_global->mandrill_destino;

  if(in_array('d', $opciones)){
    // modo directorio
    
    ln();
    ln('iniciando modo directorio...');

    if(file_exists($cfg_global->logs) && is_writable($cfg_global->logs)){
      $GLOBALS['log'] = $cfg_global->logs.date('Ymd_Gi', $t_inicio).'.log';
      if(file_exists($GLOBALS['log'])){
        $i_log = 0;
        do {
          $i_log++;
          $GLOBALS['log'] = $cfg_global->logs.date('Ymd_Gi', $t_inicio).'_'.$i_log.'.log';
        } while(file_exists($GLOBALS['log']));
      }
      file_put_contents($GLOBALS['log'], $GLOBALS['prelog']);
      unset($GLOBALS['prelog']);
      ln('usando fichero de log '.$GLOBALS['log']);
      ln();
    } else {
      ln('el directorio de logs ('.$cfg_global->logs.') no existe o no tiene permisos de escritura, continuando sin registro de logs');
      ln();
    }

    if(!file_exists($fichero_configuracion) || !is_dir($fichero_configuracion)){
      error('No se puede encontrar el directorio con los ficheros de configuración');
    }

    if(substr($fichero_configuracion, -1) != '/') $fichero_configuracion .= '/';
    $path = $fichero_configuracion;

    if($dir = opendir($path)){
      while(($f = readdir($dir)) !== false){
        if(is_file($path.$f)){
          ln('copiando '.$path.$f.' ...');
          $cfg = json_decode(file_get_contents($path.$f));
          if(!is_object($cfg)) ln('No parece un fichero de configuración, ignorando');
          else {
            $ok = true;
            foreach($configuracion_basica as $o) if(!isset($cfg->$o)) $ok = false;
            if(!$ok) ln('El fichero no tiene la configuración básica, ignorando');
            else {
              cmd(__FILE__.((in_array('v', $opciones))?' -v':'').((in_array('rv', $opciones))?' -rv':'').((in_array('test', $opciones))?' -test':'').' '.$path.$f);
            }
          }
        }
      }
      closedir($dir);
    }

    if(!in_array('test', $opciones)){
      ln('eliminando logs antiguos');
      cmd('find '.$cfg_global->logs.' -type f -name *.log -mtime +'.$cfg_global->caducidad_logs.' -exec rm {} \\;');
    }

    ln();

    exit(0); // fin modo directorio
  }

  // comprobar si el fichero de configuración existe

  if(!file_exists($fichero_configuracion)){
    $fichero_configuracion = '/etc/backups/'.$fichero_configuracion;
    if(!file_exists($fichero_configuracion)){
      error('No puedo encontrar el fichero de configuración');
    }
  }

  ln();
  ln('usando configuración '.$fichero_configuracion);

  $cfg = json_decode(file_get_contents($fichero_configuracion));
  if(!is_object($cfg)) error('Fichero de configuración no válido');
  
  // comprobar si existe la configuración básica y si es válida

  foreach($configuracion_basica as $o) if(!isset($cfg->$o)) error('Configuración insuficiente');

  if(!file_exists($cfg->password_file)) error('El fichero local con la contraseña del servicio rsync no existe ('.$cfg->password_file.')');
  if(decoct(fileperms($cfg->password_file) & 0777) != '600') error('El fichero local con la contraseña debe tener los siguientes permisos: -rw------- (600)');
  if(posix_geteuid() != fileowner($cfg->password_file)) error('La copia debe ejecutarse desde el propietario del fichero local de la contraseña');
  
  if(substr($cfg->copia_local, -1) == '/') $cfg->copia_local = substr($cfg->copia_local, 0, -1);
  if(!file_exists($cfg->copia_local) || !is_dir($cfg->copia_local) || substr($cfg->copia_local, 0, 1) != '/') error('El directorio de copia local no existe o no es válido ('.$cfg->copia_local.')');
  if(!is_writable($cfg->copia_local)) error('El directorio de copia local no tiene permisos de escritura');

  // comprobar valores por defecto de la configuración opcional
  
  if(!isset($cfg->inicial)) $cfg->inicial = false;
  elseif(!is_bool($cfg->inicial)) error('El valor de configuración copia inicial no es válido');

  if(!isset($cfg->copias)) $cfg->copias = 10;
  elseif(!is_int($cfg->copias)) error('El valor de configuración de número de copias no es válido');

  if(!isset($cfg->fix_rotacion)) $cfg->fix_rotacion = true;
  elseif(!is_bool($cfg->fix_rotacion)) error('El valor de configuración de corrección de problemas rotación no es válido');

  if(!isset($cfg->fix_permisos)) $cfg->fix_permisos = '';
  elseif($cfg->fix_permisos != ''){
    $permisos = explode(':', $cfg->fix_permisos);
    if(count($permisos) != 2 || trim($permisos[0]) == '' || trim($permisos[1]) == ''){
      error('El valor de configuración de corrección de permisos no es válido');
    }
  }

  ln('  rsync_host      : '.$cfg->rsync_host);
  ln('  rsync_usuario   : '.$cfg->rsync_usuario);
  ln('  copia_local     : '.$cfg->copia_local);
  ln('  copias          : '.$cfg->copias);
  ln('  inicial         : '.(($cfg->inicial)?'true':'false'));
  ln('  fix_rotacion    : '.(($cfg->fix_rotacion)?'true':'false'));
  ln('  fix_permisos    : '.$cfg->fix_permisos);
  ln();

  if(in_array('test', $opciones)){
    ln('configuración correcta ¡listo para hacer copias!');
    ln();
    exit(0);
  }

  ln('marca de tiempo inicial '.date('Ymd_Gi', $t_inicio));

  // iniciamos el log y guardamos el prelog
  
  if(!file_exists($cfg->copia_local.'/logs')) mkdir($cfg->copia_local.'/logs');
  $GLOBALS['log'] = $cfg->copia_local.'/logs/'.date('Ymd_Gi', $t_inicio).'.log';
  if(file_exists($GLOBALS['log'])){
    $i_log = 0;
    do {
      $i_log++;
      $GLOBALS['log'] = $cfg->copia_local.'/logs/'.date('Ymd_Gi', $t_inicio).'_'.$i_log.'.log';
    } while(file_exists($GLOBALS['log']));
  }
  file_put_contents($GLOBALS['log'], $GLOBALS['prelog']);
  unset($GLOBALS['prelog']);
  ln('usando fichero de log '.$GLOBALS['log']);
  ln();

  // comienza la fiesta :)

  if(!$cfg->inicial){
    ln('rotando copias...');
    $cmd_rotacion = false;

    // eliminamos la última copia
    if(file_exists($cfg->copia_local.'/backup.'.($cfg->copias - 1))){
      cmd('rm -rf '.$cfg->copia_local.'/backup.'.($cfg->copias - 1));
      $cmd_rotacion = true;
    }

    // rotando copias recursivas
    for($i = $cfg->copias - 2; $i>=1; $i--){
      if(file_exists($cfg->copia_local.'/backup.'.$i)){
        cmd('mv '.$cfg->copia_local.'/backup.'.$i.' '.$cfg->copia_local.'/backup.'.($i + 1));
        $cmd_rotacion = true;
      }
    }

    // duplicando la copia inicial
    if(file_exists($cfg->copia_local.'/backup.0')){
      cmd('cp -al '.$cfg->copia_local.'/backup.0 '.$cfg->copia_local.'/backup.1');
      $cmd_rotacion = true;
    }

    if($cmd_rotacion) ln();
    
    if($cfg->fix_rotacion){

      ln('corrigiendo rotación...');
      $cmd_rotacion = false;
      for($i=1; $i<=$cfg->copias; $i++){
        if(file_exists($cfg->copia_local.'/backup.'.$i.'/backup.'.($i - 1))){
          if(!file_exists($cfg->copia_local.'/fix')) cmd('mkdir '.$cfg->copia_local.'/fix');
          cmd('mv '.$cfg->copia_local.'/backup.'.$i.'/backup.'.($i - 1).' '.$cfg->copia_local.'/fix');
          $cmd_rotacion = true;
        }
      }
      if($cmd_rotacion) ln();

      if(file_exists($cfg->copia_local.'/fix')){
        ln('corrigiendo errores de rotación...');
        ln('deberias comprobar los permisos de los ficheros copiados', 'error');
        cmd('rm -rf '.$cfg->copia_local.'/fix');
        ln();
      }

    }

    if($cmd_rotacion) ln();

  }

  ln('copiando...');
  if(!file_exists($cfg->copia_local.'/backup.0')) cmd('mkdir '.$cfg->copia_local.'/backup.0');
  cmd('rsync -a'.((in_array('rv', $opciones))?'v':'').' --delete --password-file='.$cfg->password_file.' rsync://'.$cfg->rsync_usuario.'@'.$cfg->rsync_host.'/* '.$cfg->copia_local.'/backup.0/');
  ln();

  if($cfg->fix_permisos != ''){
    ln('corrigiendo permisos...');
    cmd('chown -R '.$cfg->fix_permisos.' '.$cfg->copia_local.'/backup.0/');
    cmd('chmod -R g-s '.$cfg->copia_local.'/backup.0/');
    cmd('find '.$cfg->copia_local.'/backup.0/ -type d -exec chmod 775 {} \;');
    cmd('find '.$cfg->copia_local.'/backup.0/ -type f -exec chmod 664 {} \;');
    ln();
  }

  if(isset($GLOBALS['log'])){
    ln('eliminando logs antiguos');
    cmd('find '.dirname($GLOBALS['log']).' -type f -name *.log -mtime +'.$cfg_global->caducidad_logs.' -exec rm {} \\;');
    ln();
  }
  
  $t_fin = time();
  $duracion = $t_fin - $t_inicio;
  ln('marca de tiempo final '.date('Ymd_Gi', $t_fin));
  ln('duración del proceso '.$duracion.'s');
  ln();

  exit(0);