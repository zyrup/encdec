# encdec
Simple cryptography script in PHP. Partially adapted one-time pad encryption. Made and tested on OS X El Capitan.

##How to use:
Generate 255 signs long key:
```
php encdec.php key
```
Encrypt a folder with data inside:
```
php encdec.php enc
```
Decrypt a folder with data inside:
```
php encdec.php dec
```

##Configure:
There is a $settings variable inside the encdec.php file.

##Thanks to:
Lea Krämer & Lidia Del Rio
