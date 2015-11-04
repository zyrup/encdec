<?php
system('clear');

if (!isset($argv[1])) {
	die("no argument given\n");
}

Encdec::init();

if ($argv[1] == "key") {
	$key = Encdec::generateKey();
	echo "Generated key:\n";
	echo $key;
	echo "\n";
	$pathName = Encdec::$settings->keyDir.Encdec::$settings->keyFile;
	if(!file_exists($pathName)){
		Encdec::saveFile($pathName, $key);
		echo "Saved as '".$pathName."'";
	}else{
		echo "Could not save file. File already exists.";
	}
	echo "\n";
} else if ($argv[1] == "enc") {
	echo Encdec::encryptProcess();
	echo "\n";
} else if ($argv[1] == "dec") {
	echo Encdec::decryptProcess();
	echo "\n";
}

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
			'addMax'			     => 112,
			'defaultKeyLength' => 255
		];
	}

	public static function saveFile($path, $data) {
		$file = fopen($path, "w") or die("Unable to open file");
		fwrite($file, $data);
		fclose($file);
	}

	public static function generateKey() {
		// $keyLength = length of to-encrypt datastring would be very high security
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
		foreach($string as $k => $char){
			$num = ord($char) + ord($key[$keyUnit]);
			if($num <= self::$settings->addMax) {
				$num = abs($num - self::$settings->addMax);
			}
			$secretString .= chr($num);
			$keyUnit++;
			if($keyUnit == $keyLength){
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
		foreach($string as $k => $char){
			$num = ord($char);
			if($num <= self::$settings->addMax) {
				$num = abs($num - self::$settings->addMax) - ord($key[$keyUnit]);
			}else{
				$num -= ord($key[$keyUnit]);
			}
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
		if(file_exists($keyPathName)){
			$key = file_get_contents($keyPathName);
		}else{
			$key = self::generateKey();
			self::saveFile($keyPathName, $key);
			echo "Key generated and saved as '".$keyPathName."'";
		}

		// encrypt files
		$dataDir = self::$settings->dataDir;
		if (!file_exists($dataDir)) {
			die("Path " . $dataDir . " does not exist\n");
		}
		$files = scandir($dataDir);
		$files = array_slice($files, 2); // remove . & ..
		$toEncrypt = array();
		foreach ($files as $k => $file) {
			if ($file[0] == ".") {
				// can't put in json format
				continue;
			}
			$fileData = file_get_contents($dataDir.$file);
			$fileData = (object) [
				'fileName' => $file,
				'string' => $fileData
			];
			array_push($toEncrypt, $fileData);
		}
		$toEncrypt = json_encode($toEncrypt);
		$encryptedString = self::encrypt($toEncrypt, $key);

		// options
		if (self::$settings->encTask == "save") {
			// save file lokal
			$pathName = self::$settings->encDir;
			if (!file_exists($pathName)) {
				die("Path " . $pathName . " does not exist\n");
			}
			$pathName = $pathName.self::$settings->encFile;
			self::saveFile($pathName, $encryptedString);

		}else if (self::$settings->encTask == "send") {
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

		$toDecrypt = json_decode(self::decrypt(file_get_contents($stringPathName), $key));
		if(empty($toDecrypt)){
			die("No data to decrypt avaiable.\n");
		}
		foreach($toDecrypt as $k => $file){
			$pathName = $pathName.$file->fileName;
			self::saveFile($pathName, $file->string);
		}

		echo "Decryption done.";
	}
}
