<?php

class CrashFeed
   {
   public static function database($message)
      {
      if(!CrashFeed::isInitialized()) return;
      if(!CrashFeed::$Variables['Enabled']) return;
      $context = debug_backtrace();
      CrashFeed::internal('Database',0,$message,$context[0]['file'],$context[0]['line'],$context);
      }

   public static function debug($message)
      {
      if(!CrashFeed::isInitialized()) return;
      if(!CrashFeed::$Variables['Enabled']) return;
      $context = debug_backtrace();
      CrashFeed::internal('Debug',0,$message,$context[0]['file'],$context[0]['line'],$context);
      }

   public static function exception(Exception $e)
      {
      if(!CrashFeed::isInitialized()) return;
      if(!CrashFeed::$Variables['Enabled']) return;
      $type = CrashFeed::errorType($e->getCode());
      CrashFeed::internal($type,$e->getCode(),$e->getMessage(),$e->getFile(),$e->getLine(),$e->getTrace());
      }

   public static function fatal($message)
      {
      if(!CrashFeed::isInitialized()) return;
      if(!CrashFeed::$Variables['Enabled']) return;
      $context = debug_backtrace();
      CrashFeed::internal('Fatal',0,$message,$context[0]['file'],$context[0]['line'],$context);
      }

   public static function initialize($APIKey,$Version = '1.0')
      {
      if(defined('DISABLE_CRASHFEED') && constant('DISABLE_CRASHFEED') !== false) return;

      CrashFeed::$Variables['APIKey'] = $APIKey;
      CrashFeed::$Variables['Version'] = $Version;
      CrashFeed::setEnabled(true);
      CrashFeed::setErrorLogEnabled(false);
      CrashFeed::setLocalLogEnabled(false);
      CrashFeed::setOnErrorRedirect(false);
      CrashFeed::setPHPEnvironmentEnabled(false);
      CrashFeed::setRemoteLogEnabled(true);
      CrashFeed::setRuntimePropertiesEnabled(false);
      CrashFeed::setStrictEnabled(false);
      CrashFeed::_errorSeverity(0);

      date_default_timezone_set('UTC');

      assert_options(ASSERT_ACTIVE,1);
      assert_options(ASSERT_WARNING,0);
      assert_options(ASSERT_BAIL,0);
      assert_options(ASSERT_QUIET_EVAL,1);

      error_reporting(~0 & ~E_STRICT);
      set_error_handler('CrashFeed::_error');
      set_exception_handler('CrashFeed::exception');
      register_shutdown_function('CrashFeed::_shutdown');
      assert_options(ASSERT_CALLBACK,'CrashFeed::_assert');

      ini_set('display_errors',false);
      }

   public static function isInitialized()
      {
      return isset(CrashFeed::$Variables['APIKey']);
      }

   public static function setErrorLogEnabled($enabled)
      {
      CrashFeed::$Variables['EnableErrorLog'] = $enabled === true;
      }

   public static function setLocalLogEnabled($enabled,$logFile = NULL)
      {
      CrashFeed::$Variables['EnableLocalLog'] = $enabled === true;
      if($logFile == NULL) $logFile = $_SERVER['DOCUMENT_ROOT'] . '/crashfeed.log';
      CrashFeed::$Variables['LocalLogFile'] = $logFile;
      }

   public static function setOnErrorRedirect($URL = false)
      {
      CrashFeed::$Variables['OnErrorRedirect'] = $URL;
      }

   public static function setPHPEnvironmentEnabled($enabled)
      {
      CrashFeed::$Variables['EnablePHPEnvironment'] = $enabled === true;
      }

   public static function setRemoteLogEnabled($enabled)
      {
      CrashFeed::$Variables['EnableRemoteLog'] = $enabled === true;
      }

   public static function setRuntimePropertiesEnabled($enabled)
      {
      CrashFeed::$Variables['EnableRuntimeProperties'] = $enabled === true;
      }

   public static function setStrictEnabled($enabled)
      {
      error_reporting($enabled===true ? ~0 : ~0 & ~E_STRICT);
      CrashFeed::$Variables['EnableStrict'] = $enabled === true;
      }

   public static function _assert($file,$line,$message)
      {
      if(!CrashFeed::isInitialized()) return;
      if(!CrashFeed::$Variables['Enabled']) return;
      $context = debug_backtrace();
      CrashFeed::internal('Assert',0,$message,$file,$line,$context);
      }

   public static function _error($level,$message,$file,$line)
      {
      if(!CrashFeed::isInitialized()) return false;
      if(!CrashFeed::$Variables['Enabled']) return true;
      $DisableDefaultHandler = true;
      if($level == 0) return $DisableDefaultHandler;
      if(!(error_reporting() & $level)) return $DisableDefaultHandler;
      if(CrashFeed::errorSeverity($level) <= CrashFeed::$Variables['Severity']) return $DisableDefaultHandler;
      if(!CrashFeed::$Variables['EnableStrict'] && $level == E_STRICT) return $DisableDefaultHandler;
      $type = CrashFeed::errorType($level);
      CrashFeed::internal($type,$level,$message,$file,$line);
      return $DisableDefaultHandler;
      }

   public static function _errorSeverity($severity)
      {
      CrashFeed::$Variables['Severity'] = $severity;
      }

   public static function _shutdown()
      {
      if(!CrashFeed::isInitialized()) return;
      if(!CrashFeed::$Variables['Enabled']) return;
      if(function_exists('error_get_last'))
         {
         $e = error_get_last();
         if(!is_null($e) && isset($e['type']))
            {
            if(!(error_reporting() & $e['type'])) return;
            if(!CrashFeed::$Variables['EnableStrict'] && $e['type'] == E_STRICT) return;
            $type = CrashFeed::errorType($e['type']);
            CrashFeed::internal($type,$e['type'],$e['message'],$e['file'],$e['line']);
            }
         }
      }

   private static function errorList()
      {
      return Array(0 => Array('E_EXCEPTION','Exception',1),1 => Array('E_ERROR','Error',3),2 => Array('E_WARNING','Warning',2),4 => Array('E_PARSE','Error',3),8 => Array('E_NOTICE','Notice',1),16 => Array('E_CORE_ERROR','Error',3),32 => Array('E_CORE_WARNING','Warning',2),64 => Array('E_COMPILE_ERROR','Error',3),128 => Array('E_COMPILE_WARNING','Warning',2),256 => Array('E_USER_ERROR','Error',3),512 => Array('E_USER_WARNING','Warning',2),1024 => Array('E_USER_NOTICE','Notice',1),2048 => Array('E_STRICT','Strict',1),4096 => Array('E_RECOVERABLE_ERROR','Error',1),8192 => Array('E_DEPRECATED','Deprecated',1),16384 => Array('E_USER_DEPRECATED','Deprecated',1));
      }

   private static function errorLog($type,$trace)
      {
      $date = date('Y-m-d H:i:s');
      error_log("[$date] [$type] $trace"); // . PHP_EOL
      }

   private static function errorName($code)
      {
      $list = CrashFeed::errorList();
      return isset($list[$code]) ? $list[$code][0] : 'Unknown';
      }

   private static function errorSeverity($code)
      {
      $list = CrashFeed::errorList();
      return isset($list[$code]) ? $list[$code][2] : 3;
      }

   private static function errorType($code)
      {
      $list = CrashFeed::errorList();
      return isset($list[$code]) ? $list[$code][1] : 'Unknown';
      }

   private static function internal($type,$level,$message,$file,$line,$context = NULL,$opaque = NULL)
      {
      $trace = $file != '' && $line != 0 ? $message . PHP_EOL . '   in file ' . $file . PHP_EOL . '   on line ' . $line : $message;

      if(CrashFeed::$Variables['EnableErrorLog']) CrashFeed::errorLog($type,$trace);
      if(CrashFeed::$Variables['EnableLocalLog']) CrashFeed::localLog($type,$trace);
      if(CrashFeed::$Variables['EnableRemoteLog']) CrashFeed::remoteLog($type,$level,$message,$file,$line,$context,$trace,$opaque);

      if(CrashFeed::errorSeverity($level) == 3 || $type == 'Database' || $type == 'Fatal')
         {
         if(CrashFeed::$Variables['OnErrorRedirect'] !== false)
            {
            CrashFeed::setEnabled(false);
            $URL = CrashFeed::$Variables['OnErrorRedirect'];
            if(!headers_sent()) header('Location: ' . $URL);
            else echo
               '<html><head>',
               '<script type="text/javascript">window.location.replace("', $URL, '");</script>',
               '<noscript><meta http-equiv="refresh" content="0;url=', $URL, '" /></noscript>',
               '</head><body></body></html>';
            }
         else if(!headers_sent()) header("HTTP/1.0 200 OK");
         exit();
         }
      }

   private static function localLog($type,$trace)
      {
      if($f = @fopen(CrashFeed::$Variables['LocalLogFile'],'a+'))
         {
         $date = date('Y-m-d H:i:s');
         fwrite($f,"[$date] [$type] $trace" . PHP_EOL);
         fclose($f);
         }
      }

   private static function remoteLog($type,$level,$message,$file,$line,$context,$trace,$opaque)
      {
      $extra = Array();
      $extra['Level'] = $level;
      $extra['Message'] = $message;
      $extra['File'] = $file;
      $extra['Line'] = $line;
      if($context != NULL) $extra['Context'] = $context;

      if(CrashFeed::$Variables['EnableRuntimeProperties'])
         {
         if(isset($_COOKIE)) $extra['Cookie'] = $_COOKIE;
         if(isset($_FILES)) $extra['Files'] = $_FILES;
         if(isset($_GET)) $extra['Get'] = $_GET;
         if(isset($_POST)) $extra['Post'] = $_POST;
         if(isset($_REQUEST)) $extra['Request'] = $_REQUEST;
         if(isset($_SERVER)) $extra['Server'] = $_SERVER;
         if(isset($_SESSION)) $extra['Session'] = $_SESSION;

         $inc_files = get_included_files();
         if(count($inc_files)) $extra['Included Files'] = $inc_files;
         $extra['Include Path'] = get_include_path();
         $extra['Response Headers'] = headers_list();
         }
      if(CrashFeed::$Variables['EnablePHPEnvironment'])
         {
         if(function_exists('php_ini_scanned_files') && function_exists('php_ini_loaded_file'))
            {
            $ini = php_ini_scanned_files();
            $ini = str_replace(' ,',',',str_replace(', ',',',str_replace("\n",'',$ini)));
            $ini = $ini != '' ? explode(',',$ini) : Array();
            $ini[] = php_ini_loaded_file();
            $extra['INI Files'] = $ini;
            }

         $extra['Extensions'] = get_loaded_extensions();
         sort($extra['Extensions']);
         $extra['INI Settings'] = ini_get_all();
         $extra['PHPVersion'] = phpversion();
         $extra['SAPI'] = php_sapi_name();
         $extra['System'] = php_uname();
         }

      $request = Array();
      $request['A'] = CrashFeed::$Variables['APIKey'];
      $request['P'] = 7;
      $request['V'] = CrashFeed::$Variables['Version'];
      $request['T'] = $type;
      $request['E'] = $trace;
      $request['X'] = json_encode($extra);
      if($opaque != NULL) $request['O'] = $opaque;

      $ch = curl_init();
      curl_setopt($ch,CURLOPT_CAINFO,dirname(__FILE__) . '/bundle.crt');
      curl_setopt($ch,CURLOPT_FORBID_REUSE,true);
      curl_setopt($ch,CURLOPT_FRESH_CONNECT,true);
      curl_setopt($ch,CURLOPT_SSL_VERIFYHOST,2);
      curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,true);
      curl_setopt($ch,CURLOPT_URL,'https://api.crashfeed.com/rest/event-add.jsp');
      curl_setopt($ch,CURLOPT_POST,1);
      curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($request));
      curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
      $solution = json_decode(curl_exec($ch),true);
      curl_close($ch);

      if(isset($solution['I']))
         {
         if(!CrashFeed::$Variables['EnableErrorLog'] && $solution['E'] == 1) CrashFeed::errorLog($type,$trace);
         if(!CrashFeed::$Variables['EnableLocalLog'] && $solution['L'] == 1) CrashFeed::localLog($type,$trace);
         }
      }

   private static function setEnabled($enabled)
      {
      CrashFeed::$Variables['Enabled'] = $enabled === true;
      }

   private static $Variables = Array();
   }

?>