<?php
/*
Helper Class for CheckImages
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
 * Helper Class for CheckImages
 */
class Helper {

    /*
    * read an Image from DAM
    *
    * @param  int       tt_contentUid: the Uid from tt_content
    * @param  string    tablename: the name of the Table, e.g. tt_content, tt_news...
    * @param  object    mysqlObject: the Mysql Object
    * @param  resource  connection: the Connection Resource for the Mysql Object
    *
    * return Array
    */
    public function readDamImage($tt_contentUid,$tablename,$mysqlObject,$connection){
      // now look into the tx_dam_mm_ref Table for the Reference Informations
      $table = 'tx_dam_mm_ref';
      $where = 'tablenames=' . $tablename . ' and uid_foreign=' . $tt_contentUid;
      $select = '*';
      $resultDamRef = $mysqlObject->select($connection,$select,$table,$where);

      $rowDamRef = $resultDamRef->fetch_assoc();
      // now look into the tx_dam Table for the Image Informations
      $table = 'tx_dam';
      $where = 'uid=' . $rowDamRef['uid_local'];
      $select = '*';
      $resultDam = $this->mysql->select($connectionLiveserver,$select,$table,$where);
      $rowDam = $resultDam->fetch_assoc();
      return $rowDam;
    }
    public function writeDatas() {
      // connect to the upgradeserver
      $connectionUpgradeServer = $this->mysql->connect($this->ini_array['upgradeserver']);
      // read the JSON File
      $jsonFile = file($this->pathToLogfile.$this->ini_array['filename']);
      $jsonArray = json_decode($jsonFile[0],true);
      // delete the wrong images
      $logTextDelete = $this->deleteImages($jsonArray,$connectionUpgradeServer);
      $returnArray = $this->writeImages($jsonArray,$connectionUpgradeServer);
      $logTextWrite = $returnArray['logText'];
      $linkListe = $returnArray['linkListe'];
      if($logTextWrite){
        $logTextWrite .= date('Y-m-d:h:m:s') . " Ich habe " . $counter . " Datensätze geschrieben\n";
        // schreibe die Logdatei
        $logHandle = fopen($this->pathToLogfile . 'logWrite.txt','w') or die('unable to open Logfile');
        fwrite($logHandle,$logTextWrite);
        fclose($logHandle);
        echo '<br>Fertig. Du findest die Logdatei f&uuml;r gespeicherte Datens&auml;tze unter: ' . $this->pathToLogfile . 'logWrite.txt';
        echo '<br/>Hier die Linkliste:<br/>';
        echo $linkListe;
      }
      if($logTextDelete){
        // schreibe die Logdatei
        $logHandle = fopen($this->pathToLogfile . 'logDelete.txt','w') or die('unable to open Logfile');
        fwrite($logHandle,$logTextDelete);
        fclose($logHandle);
        echo '<br>Fertig. Du findest die Logdatei f&uuml;r gel&ouml;schte Datens&auml;tze unter: ' . $this->pathToLogfile . 'logDelete.txt';
      }
    }

    /*
    * write the Images into the Database.
    *
    *
    */
    protected function writeImages($jsonArray,$connectionUpgradeServer){
    	$linkListe = '';
      foreach($jsonArray as $key => $element){// the Datas from tt_content and write the records
        $cmdFind = "find " . $this->pathUpload . " -name " . $element['image'];
        // for testing only pid 77 { ["uid"]=> string(3) "537" ["pid"]=> string(2) "77" ["image"]=> string(14) "'_D4M8688.jpg'" }
        $resultShell = shell_exec($cmdFind);
        $resultShell = trim($resultShell);
        if($resultShell){
          // copy the image to fileadmin/_migrated/pics/ and use this for the identifier
          $cmdCopy = 'cp ' . $resultShell . ' ' . $this->pathMigrated;
          $resultShell = shell_exec($cmdCopy);
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
          $logText .= 'Konnte Datei: ' . $element['image'] . ' unter ' . $this->pathUpload . ' nicht finden! PID=' . $element['pid'] . '\n';
        }
        $fields = $this->ini_array['upgradeserver']['tables'][0]['fields'];
        //TODO check if the image is already in sys_file with this uid
        $select = 'uid';
        $where = 'name=\'' . $element['image'] . '\'';
        $table = 'sys_file';
        $resultSysFileCheckImage = $this->mysql->select($connectionUpgradeServer,$select,$table,$where);
        // if there is no image in sys_file then insert the record
        if($resultSysFileCheckImage->num_rows == 0){
          //insert the row
          $insert = $this->mysql->insertRow($connectionUpgradeServer,'sys_file',$fields,$insertString);
          if($insert){
            $logText .= date('Y-m-d:h:m:s') . " Habe Datei: " . $elementSysFile['identifier'] . " pid:".$elementSysFile['pid']." in Tabelle sys_file geschrieben.\n";
          }else{
            echo  "Fehler beim Insert in Tabelle sys_file!!!\n" . $insertString . "\n";
            die();
          }
        }
        // after inserting the records into sys_file get the $row['uid']
        $select = 'uid,pid,name,identifier';
        $where = 'name=\'' . $element['image'] . '\'';
        $table = 'sys_file';
        $resultSysFile = $this->mysql->select($connectionUpgradeServer,$select,$table,$where);
        $row = $resultSysFile->fetch_assoc();
        // check if the image is NOT in sys_file_reference
        $where = 'sys_file_reference.uid_local=' . $row['uid'] . ' AND sys_file.name=\'' . $element['image'] . '\'';
        $select = 'sys_file_reference.uid';
        $table = 'sys_file_reference,sys_file';
        $resultCheckImageInSysfilereference = $this->mysql->select($connectionUpgradeServer,$select,$table,$where);
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
          $insert = $this->mysql->insertRow($connectionUpgradeServer,'sys_file_reference',$fields,$insertString);
          if($insert){
              $logText .= date('Y-m-d:h:m:s') . " Habe Datei: " . $elementSysFile['identifier'] . " in Tabelle sys_file_reference geschrieben.\n";
              $logText .= date('Y-m-d:h:m:s') . " Insert Values: " . $insertString . " in Tabelle sys_file_reference geschrieben.\n";
          }else{
            echo 'Fehler beim Insert in Tabelle sys_file_reference!!!' . $insertString . '\n';
            die();
          }
          $counter++;
          $url = $this->ini_array['upgradeserver']['url'] . 'index.php?id=' . $element['pid'];
          $linkListe .= '<a href="'. $url .'">Link mit pid:' . $element['pid'] . '</a><br/>';
        }

      }// END insert Loop
      $returnArray = array('logText' => $logText, 'linkListe' => $linkListe);
      return $returnArray;
    }

    /*
    */
    protected function deleteImages($jsonArray,$connectionUpgradeServer){
      foreach($jsonArray as $key => $element){// the Datas from tt_content and delete wrong images
		/*
	      // delete all Records in sys_file_reference where the uid_foreign=$element['uid']
	      // we will fill these Records new
	      // Example 3-2_Oekologisch_280x160.jpg on pid 101n uid_foreign 314
	      $where = "uid_foreign = " . $element['uid'];
	      $table = "sys_file_reference";
      	$this->mysql->deleteRow($connectionUpgradeServer,$table,$where);
		*/

        // check if the image is in sys_file_reference
        $select = "*";
        $where = "uid_foreign = " . $element['uid'];
        $table = "sys_file_reference";
        $resultCheckSysFileReference = $this->mysql->select($connectionUpgradeServer,$select,$table,$where);
        $rowCheckSysFileReference = $resultCheckSysFileReference->fetch_assoc();
        // Now take uid_local and search in sys_file
        $select = "uid,pid,name,identifier";
        $where = "uid=" . $rowCheckSysFileReference['uid_local'];
        $table = "sys_file";
        $resultCheckSysFile = $this->mysql->select($connectionUpgradeServer,$select,$table,$where);
        if(is_object($resultCheckSysFile)){//is there an object
          if($resultCheckSysFile->num_rows > 0){// do we have a row
            $rowCheckSysFile = $resultCheckSysFile->fetch_assoc();
            // now check if the name from sys_file is different to the name from element, then delete record in sys_file_reference
            if($rowCheckSysFile['name'] != $element['image']){// are the images different
              $where = "uid_foreign = " . $element['uid'] . " AND uid_local = " . $rowCheckSysFile['uid'];
              $table = "sys_file_reference";
              $this->mysql->deleteRow($connectionUpgradeServer,'sys_file_reference',$where);
              $logText .= date('Y-m-d:h:m:s') . " Habe Datensatz mit der uid_foreign: "  . $element['uid'] . " und der uid_local " . $rowCheckSysFile['uid'] . " in Tabelle sys_file_reference gelöscht.\n";
            }else if(($rowCheckSysFile['name'] == $element['image']) && ($rowCheckSysFileReference['uid_foreign'] == $element['uid'])){// check if the names are the same
            	 $where = "uid_foreign = " . $element['uid'] . " AND name = " . $rowCheckSysFile['name'];
            	 $table = "sys_file_reference";
            	 $this->mysql->deleteRow($connectionUpgradeServer,'sys_file_reference',$where);
            	 $logText .= date('Y-m-d:h:m:s') . " Habe Datensatz mit der uid_foreign: "  . $element['uid'] . " und der Namen " . $rowCheckSysFile['name'] . " in Tabelle sys_file_reference gelöscht.\n";
            }
          }
        }

      }// END delete loop
      return $logText;
    }
    /**
     * read the Datas from Table
     * @param array iniArray
     * @param string pathToLogfile
     * @param object $mysqlObject
     */
    public function readDatas() {
      // copy the params
/*
      $liveServerParams = $ini_array['liveserver'];
      $this->ini_array['upgradeserver'] = $ini_array['upgradeserver'];
      */
      // connect to the LiveServer
      $connectionLiveserver = $this->mysql->connect($this->ini_array['liveserver']);
      // normaly we have only one table "tt_content".
      foreach($this->ini_array['liveserver']['tables'] as $key => $value){
        if($value['table'] == "tt_content"){
		  // get the Datas from the LiveServer
          $resultLiveServer = $this->mysql->select($connectionLiveserver,$value['select'],$value['table'], $value['where'].$value['additionalWhere']);
          // get the Configuration for sys_file
          foreach($this->ini_array['upgradeserver']['tables'] as $key1 => $value1){
            // connect to the upgradeserver
            $connectionUpgradeServer = $this->mysql->connect($this->ini_array['upgradeserver']);
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
                    $resultSysFile = $this->mysql->select($connectionUpgradeServer,$value1['select'],$value1['table'],$value1['where']);
                    $rowSysFile = $resultSysFile->fetch_assoc();
                    if($resultSysFile->num_rows == 0){// no image in sys_file
                      $arrayResult[] = $row;
                    }else {// maybe there is an image in sys_file, but not in sys_file_reference
                      $where = 'uid_local = ' . $rowSysFile['uid'];
                      $table = 'sys_file_reference';
                      $resultSysFileReference = $this->mysql->select($connectionUpgradeServer,$value1['select'],$table,$where);
                      if($resultSysFileReference->num_rows == 0){// no image in sys_file
                        $arrayResult[] = $row;
                      }
                    }
                  }

                }else{
                  // build the where clause

                  $value1['where'] = "name = " . "'" . $row['image'] . "'";
                  // check if the image is in sys_file
                  $resultSysFile = $this->mysql->select($connectionUpgradeServer,$value1['select'],$value1['table'],$value1['where']);
                  $rowSysFile = $resultSysFile->fetch_assoc();
                  if($resultSysFile->num_rows == 0){// no image in sys_file
                    $arrayResult[] = $row;
                  }else {// maybe there is a image in sys_file, but not in sys_file_reference
                    $where = 'uid_local = ' . $rowSysFile['uid'];
                    $table = 'sys_file_reference';
                    $resultSysFileReference = $this->mysql->select($connectionUpgradeServer,$value1['select'],$table,$where);
                    if($resultSysFileReference->num_rows == 0){// no image in sys_file
                      $arrayResult[] = $row;
                    }
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
      $jsonResult = '';
      $jsonResult = json_encode($arrayResult, JSON_FORCE_OBJECT);
      $jsonHandle = fopen($this->pathToLogfile . $this->ini_array['filename'],'w') or die('unable to open file' . $this->ini_array['filename']);
      // write the json file
      fwrite($jsonHandle,$jsonResult);
      fclose($jsonHandle);
      echo 'Datei ' . $this->ini_array['filename'] . ' in ' . $this->pathToLogfile . ' geschrieben<br/>';
      echo 'Kopieren Sie das Verzeichnis nun in die T3 6.2 Installation';
    }// end function
}
?>
