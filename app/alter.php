<?php
/*
*Dieses Script muss nach dem Upgrade Wizard ausgeführt werden.
* 1. installiere das Script in die T3 4.7 Installation
* 2. setzte $readTheDatas auf TRUE und $writeTheDatas auf FALSE
* 3. trage die Konfigurationsdaten in config.json ein, sowohl die Daten für den 4.7 als auch die für den 6.2 Server
* 4. ruf das Script im Browser auf. An dieser Stelle werden die Daten in die .json Datei geschrieben die du in config.json definierst hast
* 5. kopiere den Ordner auf die T3 6.2 Installation
* 6. ändere $readTheDatas auf FALSE und $writeTheDatas auf TRUE
* 7. ruf das Script im Browser auf
* 8. nun werden die Daten aus der .json Datei in die DB geschrieben
* 9. leere den typo3temp/Cache: rm -rf typo3temp/Cache
*/
ini_set('display_errors', 'On');
error_reporting(E_ALL);
class Alter{
  function main(){
    require (../lib/mysql.php);
    $mysql = new Mysql();
    $table = 'tt_content';
    $sqlTtContent = 'ALTER TABLE tt_content ADD COLUMN tx_mcgooglesitemap_objective int(11) DEFAULT NULL';
    $res = $this->addColumn($table,$sqlTtContent);
    var_dump($res);
    die();
  }

}
