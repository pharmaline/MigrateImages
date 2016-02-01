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
class CheckImages{
  function main(){
    // make the HTML Code
    echo '<p>Lesen m&uuml;ssen wir die Daten nicht unbedingt. Wir holen sie uns direkt aus der DB der T3 4.5/4.7 Installation</p>';
    echo '<form method="GET" action="controller.php">
    <button type="submit" name="action" value="read">lese die Daten</button>
    </form>';
    echo '<p>Bevor du irgendetwas machst sei sicher, da&szlig; du die DB der T3 4.5/4.7 Installation mit dem Script "Change45" bearbeitet hast.</p>';
    echo '<p>Au&szlig;erdem musst du danach die Tabellen tt_content und pages dumpen und in die T3 6.2 DB importieren.</p>';
    echo 'Daf&uuml;r muss tt_content und pages strukturell verändert werden, damit der Import klappt.<br/>';
    echo '<a href="../lib/Struktur_pages_62_erweitert.html" target="_blank">Struktur f&uuml;r Pages</a><br/>';
    echo '<a href="../lib/Struktur_tt_content_62_erweitert.html" target="_blank">Struktur f&uuml;r TT_Content</a><br/>';
    echo 'Klick auf den Button und das Script macht die Erweiterungen für Etuf.<br/>Bei anderen Installationen muss du h&auml;ndisch ran.<br/>';
    echo '<form method="GET" action="controller.php">
    <button type="submit" name="action" value="alter_table_45">füge Column in tt_content,pages hinzu</button>
    </form>';
    echo '<form method="GET" action="controller.php">
    <button type="submit" name="action" value="tt_content">schreibe die TT_Content Daten</button>
    </form>';
    echo '<form method="GET" action="controller.php">
    <button type="submit" name="action" value="tx_dam">schreibe die DAM Daten</button>
    </form>';
    echo '<form method="GET" action="controller.php">
    <button type="submit" name="action" value="tt_news">schreibe die TT-News Daten</button>
    </form>';
  }

}



// create the Object
$checkImages = new CheckImages();
$checkImages->main();
// ONLY FOR DEVELOPMENT



/*
$myMysql = new mysql;
// read the Datas from Typo3 4.7 Installation
if($readTheDatas){
	//get the Datas from the Liveserver
	// for noweda we don`t need to look for DAM because there are no DAM Files
	// Note that the files have to take place in uploads/pics/ for CE
  // copy the params
  $liveServerParams = $ini_array['liveserver'];
  $localServerParams = $ini_array['localserver'];
  // connect to the LiveServer
  $connectionLiveServer = $myMysql->connect($liveServerParams);
  // normaly we have only one table "tt_content".
  foreach($liveServerParams['tables'] as $key => $value){
    if($key == "tt_content"){
      // get the Configuration for sys_file
      foreach($localServerParams['tables'] as $key1 => $value1){
        // get the Datas from the LiveServer
        $resultLiveServer = $myMysql->select($connectionLiveServer,$value['select'],$value['table'], $value['where'].$value['additionalWhere']);
        // connect to the LocalServer
        $connectionLocalServer = $myMysql->connect($localServerParams);
        if($value1['table'] == "sys_file"){
          while ($row = $resultLiveServer->fetch_assoc()) {
            //check if $row['image'] holds more than one image
            if(strpos($row['image'],",")){
              // explode the images into an array
              $imageArray = explode(",",$row['image']);
              foreach($imageArray as $value2){
                $row['image'] = $value2;
                // get the imagename for the check
                //$value1['where'] = "identifier = " . "'" . $row['image'] . "' uid_foreign = " . $row['uid'] . "'";
                $value1['where'] = "name = " . "'" . $row['image'] . "'";
                // check if the image is in sys_file
                $resultLocalServer = $myMysql->select($connectionLocalServer,$value1['select'],$value1['table'],$value1['where']);
                if($resultLocalServer->num_rows == 0){// no image in sys_file
                  $arrayResult[] = $row;
                }
              }
            }else{
              // get the imagename for the check
              $value1['where'] = "name = " . "'" . $row['image'] . "'";
              // check if the image is in sys_file
              $resultLocalServer = $myMysql->select($connectionLocalServer,$value1['select'],$value1['table'],$value1['where']);
              if($resultLocalServer->num_rows == 0){// no image in sys_file
                $arrayResult[] = $row;
              }
            }
          }
        }else {// any other table than sys_file so break
          break;
        }
      }
    }else {// any other table than tt_content so break
      break;
    }
  }
	// create the json file
  $jsonResult = "";
  $jsonResult = json_encode($arrayResult, JSON_FORCE_OBJECT);
	$jsonHandle = fopen($filename,"w") or die("unable to open file" . $filename);
	// write the json file
	fwrite($jsonHandle,$jsonResult);
	fclose($jsonHandle);
	echo "Datei " . $filename . " in " . $pathToScript . " geschrieben<br/>";
	echo "Kopieren Sie das Verzeichnis nun in die T3 6.2 Installation und &auml;ndern Sie in der config.json write auf TRUE und read auf FALSE !";
}
// write the Datas into Typo3 6.2 Installation, read them from the file
if($writeTheDatas){
	// connect to the Localserver
  $connectionLocalserver = $myMysql->connect($ini_array['localserver']);
	// read the JSON File
	$jsonFile = file($filename);
	$jsonArray = json_decode($jsonFile[0],true);
  foreach($jsonArray as $key => $element){// the Datas from tt_content and delete wrong images
    $cmdFind = "find " . $path . " -name " . $element['image'];
    // for testing only pid 77 { ["uid"]=> string(3) "537" ["pid"]=> string(2) "77" ["image"]=> string(14) "'_D4M8688.jpg'" }
    $resultShell = shell_exec($cmdFind);
    $resultShell = trim($resultShell);
    $element['identifier'] = str_replace('../','',$resultShell);
    $fields = array('pid','name','identifier');
    $values = "";
    $insertString = "";
    // check if the image is in sys_file_reference
    $select = "*";
    $where = "uid_foreign = " . $element['uid'];
    $table = "sys_file_reference";
    $resultCheckSysFileReference = $myMysql->select($connectionLocalserver,$select,$table,$where);
    $rowCheckSysFileReference = $resultCheckSysFileReference->fetch_assoc();
    // Now take uid_local and search in sys_file
    $select = "uid,pid,name,identifier";
    $where = "uid=" . $rowCheckSysFileReference['uid_local'];
    $table = "sys_file";
    $resultCheckSysFile = $myMysql->select($connectionLocalserver,$select,$table,$where);
    if($resultCheckSysFile->num_rows > 0){
        $rowCheckSysFile = $resultCheckSysFile->fetch_assoc();
        // now check if the name from sys_file is different to the name from element, then delete record in sys_file_reference
        if($rowCheckSysFile['name'] != $element['image']){
          $where = "uid_foreign = " . $element['uid'] . " AND uid_local = " . $rowCheckSysFile['uid'];
          $table = "sys_file_reference";
          $myMysql->deleteRow($connectionLocalserver,'sys_file_reference',$where);
          $logText .= date('Y-m-d') . " Habe Datensatz mit der uid_foreign: "  . $element['uid'] . " und der uid_local " . $rowCheckSysFile['uid'] . " in Tabelle sys_file_reference gelöscht.\n";
        }
    }
  }
  foreach($jsonArray as $key => $element){// the Datas from tt_content and write the records
    $cmdFind = "find " . $path . " -name " . $element['image'];
    // for testing only pid 77 { ["uid"]=> string(3) "537" ["pid"]=> string(2) "77" ["image"]=> string(14) "'_D4M8688.jpg'" }
    $resultShell = shell_exec($cmdFind);
    $resultShell = trim($resultShell);
    $element['identifier'] = str_replace('../','',$resultShell);
    //TODO bei mehreren Bildern wird nur eins in die Datenbank geschrieben.
    // Lösung Löschen in eigene Schleife dann Schleife für Insert
    // DB neu einelesen unde testen
    // copy $element to $elementSysFile
    $elementSysFile = $element;
    // delete the uid, we don`t need it here.
    array_shift($elementSysFile);
    foreach($elementSysFile as $key => $value){
      $search = "'";
      if((!strpos($value,$search) AND (strpos($value,$search) != 1))){//check if value is wrapped with ''
        $insertString .= "'" . trim($value) . "',";
      }
    }
    // remove the seperator from the end
    $insertString = substr($insertString, 0, -1);
    //insert the row TODO wegen Test deaktiviert

    $insert = $myMysql->insertRow($connectionLocalserver,'sys_file',$fields,$insertString);
    //$insert = true;
    if($insert){
      $logText .= date('Y-m-d') . " Habe Datei: " . $elementSysFile['identifier'] . " in Tabelle sys_file geschrieben.\n";
    }else{
      $logText .= "Fehler beim Insert in Tabelle sys_file!!!" . $insertString . "\n";
    }

    // get the $row['uid']
    $select = "uid,pid,name,identifier";
    $where = "name='" . $element['image'] . "'";
    $resultSysFile = $myMysql->select($connectionLocalserver,$select,'sys_file',$where);
    $row = $resultSysFile->fetch_assoc();
    // prepare the insert string
    $insertString = "";
    // uid_local is the uid from sys_file
    // uid_foreign ist the uid from tt_content
    // tablenames is tt_content
    // table_local is sys_file
    // fieldname is image
    $fields = array('pid','uid_local','uid_foreign','tablenames','fieldname','table_local');
    $insertString = "'" . $elementSysFile['pid'] . "','" . $row['uid'] . "','" . $element['uid'] . "','tt_content','image','sys_file'";
    // insert record in sys_file_reference

    $insert = $myMysql->insertRow($connectionLocalserver,'sys_file_reference',$fields,$insertString);
    if($insert){
        $logText .= date('Y-m-d') . " Habe Datei: " . $elementSysFile['identifier'] . " in Tabelle sys_file_reference geschrieben.\n";
    }else{
      $logText .= "Fehler beim Insert in Tabelle sys_file_reference!!!" . $insertString . "\n";
    }

    $counter++;
  }

  if($logText){
    $logText .= date('Y-m-d') . " Ich habe " . $counter . " Datensätze geschrieben";
  }else{
    $logText = date('Y-m-d') . " Ich habe keinen Datensatz geschrieben.";
  }
  // schreibe die Logdatei
  $logHandle = fopen("log.txt","w") or die("unable to open Logfile");
  fwrite($logHandle,$logText);
  fclose($logHandle);
  echo "Fertig. Du findest die Logdatei unter: " . $pathToScript;
}
  function jsonError(){
  	switch(json_last_error_msg()){
  		case JSON_ERROR_NONE:
  		        return ' - Keine Fehler';
  		    break;
  		case JSON_ERROR_DEPTH:
  		        return ' - Maximale Stacktiefe überschritten';
  		    break;
  		case JSON_ERROR_STATE_MISMATCH:
  		        return ' - Unterlauf oder Nichtübereinstimmung der Modi';
  		    break;
  		case JSON_ERROR_CTRL_CHAR:
  		        return ' - Unerwartetes Steuerzeichen gefunden';
  		    break;
  		case JSON_ERROR_SYNTAX:
  		        return ' - Syntaxfehler, ungültiges JSON';
  		    break;
  		case JSON_ERROR_UTF8:
  		        return ' - Missgestaltete UTF-8 Zeichen, möglicherweise fehlerhaft kodiert';
  		    break;
  		default:
  		        return ' - Unbekannter Fehler';
  		    break;
  	}
  }
    */
