<?php
/*
Worker Class for MigrateImages Etuf Version
written in PHP


Copyright (C) 2016 Bernd Pier <bernd.pier@pharmaline.de>

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

/**
 * Worker Class for MigrateImages Etuf Version
 */
class Worker {
  /**
   * @var ini_array the array with all the configurationdatas
   */
  public function __construct($ini_array,$mysqlObject,$pathToLogfile){
    $this->pathUpload = $ini_array['pathUpload'];
    $this->pathMigrated = $ini_array['pathMigrated'];
    $this->ini_array = $ini_array;
    $this->mysql = $mysqlObject;
    $this->pathToLogfile = $pathToLogfile;
    $this->liveServerTables = $ini_array['liveserver']['tables'];
    $this->upgradeServerTables = $ini_array['upgradeserver']['tables'];
    $this->pagesConstant = $this->upgradeServerTables['pages']['constant'];
    $this->tt_contentConstant = $this->upgradeServerTables['tt_content']['constant'];
    // clear the logText
    $this->logText = '';
    // clear the counter
    $this->counter = 0;
    // connect to the upgradeserver
    $this->connectionUpgradeServer = $this->mysql->connect($this->ini_array['upgradeserver']);
    // connect to the liveserver
    $this->connectionLiveServer = $this->mysql->connect($this->ini_array['liveserver']);
  }
  /**
   * @var action the the action to perform
   */
  public function migrateImages($action){
    // read the Datas from the Upgradeserver
    // add tt_contentConstant to the Uid from tt_content and pagesConstant to the pid from pages
    // so we only select the Elements from the T3 4.5 Installation
    $select = 'uid,pid,tstamp,crdate,cruser_id,sorting,image,hidden,deleted,tx_damttcontent_files';
    $table = $this->liveServerTables['tt_content']['table'];
    $where = '((CType = \'textpic\' OR CType = \'image\') AND image <> \'\') AND tx_damttcontent_files = 0 AND deleted = 0';
    $resultTtcontentLiveserver = $this->mysql->select($this->connectionLiveServer,$select,$table,$where);
    // this holds the Images from Liveserver tt_content
    //$imagesArray = array();
    while($rowTtcontentLiveserver = $resultTtcontentLiveserver->fetch_assoc()){
      //check if we got more than one Image in image
      $checkImage = strpos($rowTtcontentLiveserver['image'],',');
      // we have more than one image in the CE
      if($checkImage){
        $arrayImage = explode(',',$rowTtcontentLiveserver['image']);
        // loop through the Images and write them to sys_file and sys_file_reference
        foreach ($arrayImage as $key => $image){
          $resultFind = $this->findImage($this->pathUpload,$image);
          $rowTtcontentLiveserver['image'] = $image;
          if($resultFind){
            $this->migrateOneImage($rowTtcontentLiveserver,$resultFind);
          } else {
            echo '<br>Konnte Datei: ' . $image . ' nicht finden.<br>';
          }
        }
        // there is only one Image in CE
      } else {
        $this->migrateOneImage($rowTtcontentLiveserver,$resultFin);
      }
      $url = $this->ini_array['upgradeserver']['url'] . 'index.php?id=' . $rowTtcontentLiveserver['pid'];
      $linkListe .= '<a href="'. $url .'">Link mit pid:' . $rowTtcontentLiveserver['pid'] . '</a><br/>';
    }

    if(($this->logText) AND ($linkListe)){
      echo $linkListe;
      $this->writeLogtext('logText.txt');
      return TRUE;
    }else {
      return FALSE;
    }
  }

  protected function migrateOneImage($rowTtContent,$resultFind)
  {
    // get the extension of the image from $elementSysFile['image']
    $startPoint = strripos ($rowTtcontentLiveserver['image'],'.');
    $extension = substr($rowTtcontentLiveserver['image'],$startPoint);
    $extension = strtolower($extension);
    if($extension == '.jpg'){
        $mimeType = 'Image/jpeg';
    }else{
        $mimeType = 'Image/'.str_replace('.','',$extension);
    }
    $this->copyImage($this->pathMigrated,$resultFind);
    $this->logText .= "Habe das Bild: " . $image . " nach " . $this->pathMigrated . " kopiert\n";

    $select = 'uid';
    $where = 'name=\'' . $rowTtContent['image'] . '\'';
    $table = 'sys_file';
    $resultSysFile = $this->mysql->select($this->connectionUpgradeServer,$select,$table,$where);
    // get the identifier and the name from row['image']
    $identifier = str_replace('../../','',$this->pathMigrated . $rowTtContent['image']);

    //$name = $rowTtContent['image'];
    // start with the insertString
    // pid,tstamp,creation_date,extension,mime_type,name,identifier.
    $insertStringSysFile = '\'' . $rowTtContent['pid'] . '\',\'' . $rowTtContent['tstamp'] . '\',\'' . $rowTtContent['tstamp'] . '\',\'' . $extension . '\',\'' . $mimeType . '\',\'' . $rowTtContent['image'] . '\',\'' . $identifier . '\'';
    // if there is no image in sys_file then insert the record
    if($resultSysFile->num_rows == 0){
      //insert the row into sys_file
      $insert = $this->mysql->insertRow($this->connectionUpgradeServer,$this->upgradeServerTables['sys_file']['table'],$this->upgradeServerTables['sys_file']['fields'],$insertStringSysFile);
      if($insert){
        $this->logText .= date('Y-m-d:h:m:s') . " Habe Datei: " . $identifier . " pid:" . $rowTtContent['pid']." in Tabelle " . $this->upgradeServerTables['sys_file']['table'] . " geschrieben.\n";
        $this->counter++;
      }else{
        echo  "line 128: Fehler beim Insert in Tabelle " . $this->upgradeServerTables['sys_file']['table'] . "!!!\n" . $insertStringSysFile . "\n";
        die();
      }
    }
    // now insert the Image into sys_file_reference
    // get the uid from sys_file
    $rowSysFile = $resultSysFile->fetch_assoc();
    $insertTableSysFileReference = $this->upgradeServerTables['sys_file_reference']['table'];
    // pid,uid_local,uid_foreign,hidden,tablenames,fieldname,table_local
    $insertFieldsSysFileReference = $this->upgradeServerTables['sys_file_reference']['fields'];
    $insertStringSysFileReference = '\'' . $rowTtContent['pid'] . '\',\'' . $rowSysFile['uid'] . '\',\'' . $rowTtContent['uid'] . '\',\'' . $rowTtContent['hidden'] . '\',\'tt_content\',\'image\',\'' . $this->upgradeServerTables['sys_file_reference']['table'] . '\'';
    //insert the row into sys_file_reference
    $insert = $this->mysql->insertRow($this->connectionUpgradeServer,$insertTableSysFileReference,$insertFieldsSysFileReference,$insertStringSysFileReference);
    if($insert){
      $this->logText .= date('Y-m-d:h:m:s') . " Habe Datei: " . $identifier . " pid:" . $rowTtContent['pid']." in Tabelle " . $insertTableSysFileReference . " geschrieben.\n";
      $this->counter++;
    }else{
      echo  'line 145: Fehler beim Insert in Tabelle '. $insertTableSysFileReference . '!!!\n' . $insertStringSysFileReference . '\n';
      die();
    }
  }
  /*
  * write the Logtext into a file
  *
  *
  *
  */
  protected function writeLogtext($filename)
  {
    $this->logTextWrite .= date('Y-m-d:h:m:s') . " Ich habe " . $this->counter . " Datensätze geschrieben\n";
    // schreibe die Logdatei
    $logHandle = fopen($this->pathToLogfile . $filename,'w') or die('unable to open Logfile');
    fwrite($logHandle,$this->logTextWrite);
    fclose($logHandle);
    echo '<br>Fertig. Du findest die Logdatei f&uuml;r gespeicherte Datens&auml;tze unter: ' . $this->pathToLogfile . $filename;
  }

  /* copy an Image to Path
  *
  * @param  string: path
  * @param  string: image
  *
  * return String
  */
  function copyImage($path,$resultShell){
    $cmdCopy = 'cp ' . $resultShell . ' ' . $path;
    $resultShell = shell_exec($cmdCopy);
    if($resultShell){
      return $resultShell;
    }
  }

  /* find an Image in Path
  *
  * @param  string: path
  * @param  string: image
  *
  * return String
  */
  private function findImage($path,$image){
    $cmdFind = "find " . $path . " -name " . $image;
    // for testing only pid 77 { ["uid"]=> string(3) "537" ["pid"]=> string(2) "77" ["image"]=> string(14) "'_D4M8688.jpg'" }
    $resultShell = shell_exec($cmdFind);
    $resultShell = trim($resultShell);
    if($resultShell){
      return $resultShell;
    }
  }

/*
  public function controller() {

    //TODO nur fürs testen
    $stringJson = $this->readJsonArray($jsonArray);

    // delete the wrong images
    require('deleter.php');
    $this->deleter = new Deleter;
    $this->logTextDelete = $this->deleter->deleteImages($jsonArray,$this->connectionUpgradeServer,$this->mysql);

    $returnArray = $this->writeImages($jsonArray,$this->connectionUpgradeServer);
    $this->logTextWrite = $returnArray['logText'];
    $linkListe = $returnArray['linkListe'];
    if($this->logTextWrite){
      $this->logTextWrite .= date('Y-m-d:h:m:s') . " Ich habe " . $this->counter . " Datensätze geschrieben\n";
      // schreibe die Logdatei
      $logHandle = fopen($this->pathToLogfile . 'logWrite.txt','w') or die('unable to open Logfile');
      fwrite($logHandle,$this->logTextWrite);
      fclose($logHandle);
      echo '<br>Fertig. Du findest die Logdatei f&uuml;r gespeicherte Datens&auml;tze unter: ' . $this->pathToLogfile . 'logWrite.txt';
      echo '<br/>Hier die Linkliste:<br/>';
      echo $linkListe;
    }
    if($this->logTextDelete){
      // schreibe die Logdatei
      $logHandle = fopen($this->pathToLogfile . 'logDelete.txt','w') or die('unable to open Logfile');
      fwrite($logHandle,$this->logTextDelete);
      fclose($logHandle);
      echo '<br>Fertig. Du findest die Logdatei f&uuml;r gel&ouml;schte Datens&auml;tze unter: ' . $this->pathToLogfile . 'logDelete.txt';
    }
  }
*/
  /*
  * write the Images into the Database.
  *
  *
  */
  /*
  protected function writeImages($jsonArray){
  	$linkListe = '';
    // define the sql variables for the Pages Table
    $table = 'pages';
    $fields = 'title';
    $select = 'uid';
    foreach($jsonArray as $key => $element){// the Datas from tt_content and write the records
      // find the Images in pathUpload and return the Result
      $resultFind = $this->findImage($this->pathUpload,$element['image']);
      if($resultFind){
        // copy the image to fileadmin/_migrated/pics/
        $this->copyImage($this->pathMigrated,$resultFind);
        //fill the identifier
        $element['identifier'] = str_replace('../../','',$this->pathMigrated . $element['image']);
        // copy $element to $elementSysFile
        $elementSysFile = $element;
        // delete the uid, we don`t need it here.
        array_shift($elementSysFile);
        $insertString = '';
        $search = '\'';
        foreach($elementSysFile as $key => $value){
          if(($key == 'pid') OR ($key == 'tstamp') OR ($key == 'crdate') OR ($key == 'image') OR ($key == 'identifier')){// only these fields
            if((!strpos($value,$search) AND (strpos($value,$search) != 1))){//check if value is wrapped with ''
                $insertString .= "'" . trim($value) . "',";
            }
          }
        }
        // now add the value for fields extension and mime_type
        // get the extension of the image from $elementSysFile['image']
        $startPoint = strripos ($elementSysFile['image'],'.');
        $extension = substr($elementSysFile['image'],$startPoint);
        if($extension == '.jpg'){
            $mimeType = 'Image/jpeg';
        }else{
            $mimeType = 'Image/'.str_replace('.','',$extension);
        }
        $insertString .= '\'' . trim($extension) . '\',\'' . $mimeType . '\'';
      }else{
        $this->logText .= 'Konnte Datei: ' . $element['image'] . ' unter ' . $this->pathUpload . ' nicht finden! PID=' . $element['pid'] . '\n';
      }
      $fields = $this->ini_array['upgradeserver']['tables'][0]['fields'];
      //TODO check if the image is already in sys_file with this uid
      $select = 'uid';
      $where = 'name=\'' . $element['image'] . '\'';
      $table = 'sys_file';
      $resultSysFileCheckImage = $this->mysql->select($this->connectionUpgradeServer,$select,$table,$where);
      // if there is no image in sys_file then insert the record
      if($resultSysFileCheckImage->num_rows == 0){
        //insert the row
        $insert = $this->mysql->insertRow($this->connectionUpgradeServer,$table,$fields,$insertString);
        if($insert){
          $this->logText .= date('Y-m-d:h:m:s') . " Habe Datei: " . $elementSysFile['identifier'] . " pid:".$elementSysFile['pid']." in Tabelle sys_file geschrieben.\n";
        }else{
          echo  'Fehler beim Insert in Tabelle '. $table . '!!!\n' . $insertString . '\n';
          die();
        }
      }
      // after inserting the records into sys_file get the $row['uid']
      $select = 'uid,pid,name,identifier';
      $where = 'name=\'' . $element['image'] . '\'';
      $table = 'sys_file';
      $resultSysFile = $this->mysql->select($this->connectionUpgradeServer,$select,$table,$where);
      $row = $resultSysFile->fetch_assoc();
      // check if the image is NOT in sys_file_reference
      $where = 'sys_file_reference.uid_local=' . $row['uid'] . ' AND sys_file.name=\'' . $element['image'] . '\'';
      $select = 'sys_file_reference.uid';
      $table = 'sys_file_reference,sys_file';
      $resultCheckImageInSysfilereference = $this->mysql->select($this->connectionUpgradeServer,$select,$table,$where);
      if($resultCheckImageInSysfilereference->num_rows == 0){
        // no Image found so insert the Record
        // prepare the insert string
        $insertString = '';
        // uid_local is the uid from sys_file
        // uid_foreign ist the uid from tt_content
        // tablenames is tt_content
        // table_local is sys_file
        // fieldname is image
        $fields = $this->ini_array['upgradeserver']['tables'][1]['fields'];
        $insertString = "'" . $elementSysFile['pid'] . "','" . $row['uid'] . "','" . $element['uid'] . "','" . $element['hidden'] . "','tt_content','image','sys_file'";
        // insert record in sys_file_reference
        $insert = $this->mysql->insertRow($this->connectionUpgradeServer,'sys_file_reference',$fields,$insertString);
        if($insert){
            $this->logText .= date('Y-m-d:h:m:s') . " Habe Datei: " . $elementSysFile['identifier'] . " in Tabelle sys_file_reference geschrieben.\n";
            $this->logText .= date('Y-m-d:h:m:s') . " Insert Values: " . $insertString . " in Tabelle sys_file_reference geschrieben.\n";
        }else{
          echo 'Fehler beim Insert in Tabelle sys_file_reference!!!' . $insertString . '\n';
          die();
        }
        $this->counter++;
        $url = $this->ini_array['upgradeserver']['url'] . 'index.php?id=' . $element['pid'];
        $linkListe .= '<a href="'. $url .'">Link mit pid:' . $element['pid'] . '</a><br/>';
      }

    }// END insert Loop
    $returnArray = array('logText' => $this->logText, 'linkListe' => $linkListe);
    return $returnArray;
  }// END function
*/


  /* for displaying the JsonArray in humanreadable Form
  *
  * @param  array: jsonArray
  *
  * return String
  */
/*
  private function readJsonArray($jsonArray){
    foreach($jsonArray as $value){
      foreach($value as $key1 => $value1){


        if($value['tx_damttcontent_files'] == '1'){
          $string .= $key1 . ': ' . $value1 . '<br/>';
          $string .= 'image: ' . $value['image'] . '<br/>';
          $string .= 'uid: ' . $value['uid'] . '<br/>';
          $string .= 'tx_damttcontent_files: ' . $value['tx_damttcontent_files'] . '<br/>';
          //$string .= 'dam: ' . $value['dam'] . '<br/>';
        }


        $string .= $key1 . ': ' . $value1 . '<br/>';
      }
    }
    return $string;
  }// end function
  */
}
