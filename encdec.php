<?php

if (!isset($argv[1])) {
  die("no argument given\n");
}

Encdec::init();

if ($argv[1] == "key") {
  $key = Encdec::generateKey();
  echo "Generated key:\n";
  echo $key;
  $pathName = Encdec::$settings->keyDir.Encdec::$settings->keyFile;
  if(!file_exists($pathName)){
    Encdec::saveFile($pathName, $key);
    echo "Saved as '".$pathName."'";
  }else{
    echo "Could not save file. File already exists.";
  }
} else if ($argv[1] == "enc") {
  Encdec::encryptProcess();
} else if ($argv[1] == "dec") {
  Encdec::decryptProcess();
}
echo "\n";

class Encdec {
  public static $settings;

  public static function init() {
    self::$settings = (object) [
      'keyFile'          => 'key',
      'keyDir'           => '',
      'dataDir'          => 'data/',
      'encTask'          => 'save',
      'encDir'           => 'enc/',
      'encFile'          => 'enc',
      'decDir'           => 'dec/',
      'decFile'          => 'dec',
      'sendUrl'          => 'insert-url.here',
      'addMax'           => 444, // must be higher than any possible sum
      'defaultKeyLength' => 255
    ];
  }

  public static function readFileAndDirectoryArray($array, $dir) {
    $subFiles = array();
    foreach ($array as $k => $sub) {
      $pathName = $dir.$sub->fileName;
      if ($sub->string) {
        self::saveFile($pathName, $sub->string);
      } else if ($sub->files) {
        $pathName = $pathName."/";
        if (!file_exists($pathName)) {
          mkdir($pathName);
        }
        $subFiles = $sub->files;
        self::readFileAndDirectoryArray($subFiles, $pathName);
      }
    }
  }

  public static function readFileAndDirectory($dir) {
    // http://www.php.net/manual/en/function.readdir.php#87733
    $listDir = array();
    if ($handler = opendir($dir)) {
      while (($sub = readdir($handler)) !== false) {
        if ($sub != "." && $sub != ".." && $sub != ".DS_Store") {
          if (is_file($dir."/".$sub)) {
            $fileData = file_get_contents($dir."/".$sub);
            $fileData = (object) [
              'fileName' => $sub,
              'string' => $fileData
            ];
            $listDir[] = $fileData;
          } elseif(is_dir($dir."/".$sub)) {
            $fileData = (object) [
              'fileName' => $sub,
              'files' => self::readFileAndDirectory($dir."/".$sub)
            ];
            $listDir[$sub] = $fileData;
          }
        }
      }
      closedir($handler);
    }
    return $listDir;
  }

  public static function saveFile($path, $data) {
    $file = fopen($path, "w") or die("Unable to open file");
    fwrite($file, $data);
    fclose($file);
  }

  public static function generateKey() {
    // $keyLength = length of to-encrypt datastring would be high security
    $keyLength = self::$settings->defaultKeyLength;
    $key = "";
    for ($i = 0; $i<$keyLength; $i++) {
      $key .= chr(rand(1, 100));
    }
    return $key;
  }

  public static function encrypt($string, $key) {
    $string = str_split($string);
    $key = str_split($key);
    $secretString = "";
    $keyUnit = 0;
    $keyLength = count($key);
    foreach ($string as $k => $char) {
      $num = abs((ord($char) + ord($key[$keyUnit])) - self::$settings->addMax);
      $secretString .= chr($num);
      $keyUnit++;
      if ($keyUnit == $keyLength) {
        $keyUnit = 0;
      }
    }
    return $secretString;
  }

  public static function decrypt($string, $key) {
    $string = str_split($string);
    $key = str_split($key);
    $message = "";
    $keyUnit = 0;
    $keyLength = count($key);
    foreach ($string as $k => $char) {
      $num = abs(ord($char) - self::$settings->addMax) - ord($key[$keyUnit]);
      $message .= chr($num);
      $keyUnit++;
      if($keyUnit == $keyLength){
        $keyUnit = 0;
      }
    }
    return $message;
  }

  public static function encryptProcess() {

    // get key
    $keyPathName = self::$settings->keyDir.self::$settings->keyFile;
    if (file_exists($keyPathName)) {
      $key = file_get_contents($keyPathName);
    } else {
      $key = self::generateKey();
      self::saveFile($keyPathName, $key);
      echo "Key generated and saved as '".$keyPathName."'";
    }

    // encrypt files
    $dataDir = self::$settings->dataDir;
    if (!file_exists($dataDir)) {
      die("Path " . $dataDir . " does not exist\n");
    }
    $dataDir = rtrim($dataDir, "/");
    $files = self::readFileAndDirectory($dataDir);
    $files = json_encode($files);
    $encryptedString = self::encrypt($files, $key);

    // options
    if (self::$settings->encTask == "save") {
      // save file lokal
      $pathName = self::$settings->encDir;
      if (!file_exists($pathName)) {
        die("Path " . $pathName . " does not exist\n");
      }
      $pathName = $pathName.self::$settings->encFile;
      self::saveFile($pathName, $encryptedString);

    } else if (self::$settings->encTask == "send") {
      // send file with POST
      // post max size default 2MB?
      $toSend = 'data='.$encryptedStrings;
      $ch = curl_init();
      curl_setopt($ch, CURLOPT_URL, self::$settings->sendUrl);
      curl_setopt($ch, CURLOPT_POST, 1);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $toSend);
      curl_exec($ch);
    }

    echo "Encryption done.";
    
  }

  public static function decryptProcess() {
    // get key
    $keyPathName = self::$settings->keyDir.self::$settings->keyFile;
    $stringPathName = self::$settings->encDir.self::$settings->encFile;
    if(!file_exists($keyPathName)){
      die('no key avaiable');
    }
    $key = file_get_contents($keyPathName);
    $pathName = self::$settings->decDir;
    if (!file_exists($pathName)) {
      die("Path " . $pathName . " does not exist\n");
    }

    $files = json_decode(self::decrypt(file_get_contents($stringPathName), $key));

    if (empty($files)) {
      die("No data to decrypt avaiable.\n");
    }

    self::readFileAndDirectoryArray($files, $pathName);

    echo "Decryption done.";
  }
}
