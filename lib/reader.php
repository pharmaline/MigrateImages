<?php
/*
Reader Class for CheckImages
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
 * Reader Class for CheckImages
 */
class Reader {
  /**
   * @var ini_array the array with all the configurationdatas
   */
  public function __construct($ini_array,$mysqlObject,$pathToLogfile){
    $this->pathUpload = $ini_array['pathUpload'];
    $this->pathMigrated = $ini_array['pathMigrated'];
    $this->ini_array = $ini_array;
    $this->mysql = $mysqlObject;
    $this->pathToLogfile = $pathToLogfile;
  }

  /**
   * read the Datas from Table
   * @param array iniArray
   * @param string pathToLogfile
   * @param object $mysqlObject
   */
  public function readDatas() {
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
            // loop through the Result from tt_content
            while ($row = $resultLiveServer->fetch_assoc()) {
              // get the Page Title from pages
              $table = 'pages';
              $select = 'title';
              $where = 'uid = ' . $row['pid'];
              $resultLiveServerPages = $this->mysql->select($connectionLiveserver,$select,$table, $where);
              $rowPages = $resultLiveServerPages->fetch_assoc();
              $row['pagetitle'] = '\'' . utf8_encode($rowPages['title']) . '\'';
/*
              //$row['pagetitle'] = utf8_encode($rowPages['title']);
              echo '<br>pagetitle:';
              var_dump($row['pagetitle']);
              echo '<br>pid:';
              var_dump($row['pid']);
              echo '<br>';
*/

              //$row['pagetitle'] = 'Donnerstag, 10.9.2014';
              // is there a DAM Image? row['image'] has to be NULL cause there could be a normal tt_content Image
              if(($row['tx_damttcontent_files'] == 1) AND ($row['image'] == '')){
                // hier eine Funktion bauen $tt_contentUid,$tablename,$mysqlObject,$connection

                // read the DAM Image from tt_content
                $rowDam = $this->readDamImage($row['uid'],'tt_content',$this->mysql,$connectionLiveserver);
                $rowDam['pid'] = $row['pid'];
                $rowDam['pagetitle'] = '\'' . utf8_encode($rowPages['title']) . '\'';
                // TODO prüfe das arrayResult ob wir noch mehr Infos brauchen
                // check if we have a Record
                if($rowDam != ''){
                  $arrayResult[] = $rowDam;
                }
                // clear $rowDam
                $rowDam = array();
              }
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
    if($jsonResult){
      $jsonHandle = fopen($this->pathToLogfile . $this->ini_array['filename'],'w') or die('unable to open file' . $this->ini_array['filename']);
      // write the json file
      fwrite($jsonHandle,$jsonResult);
      fclose($jsonHandle);
      echo 'Datei ' . $this->ini_array['filename'] . ' in ' . $this->pathToLogfile . ' geschrieben<br/>';
      echo 'Kopieren Sie das Verzeichnis nun in die T3 6.2 Installation';
    }else{
      echo 'Fehler beim umwandeln in einen Jsondatei!';
    }


  }// end function

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
    $where = 'tablenames=\'' . $tablename . '\' and uid_foreign=' . $tt_contentUid;
    $select = '*';
    $resultDamRef = $mysqlObject->select($connection,$select,$table,$where);
    $rowDamRef = $resultDamRef->fetch_assoc();
    // do we have a Record in tx_dam_mm_ref
    if($rowDamRef){
      // now look into the tx_dam Table for the Image Informations
      $table = 'tx_dam';
      $where = 'uid=' . $rowDamRef['uid_local'] . ' AND file_mime_type=\'image\'';
      $select = 'uid,tstamp,crdate,cruser_id,sorting,file_name,file_path,hidden,deleted';
      $resultDam = $this->mysql->select($connection,$select,$table,$where);
      $rowDam = $resultDam->fetch_assoc();
    }

    return $rowDam;
  }//end function

  /*
  * check if Image is in sys_file
  *
  * @param  array     array
  *
  * return String
  */
  public function listArray($array){
    foreach($array as $key => $value){
      $returnString .= $key . ': ' . $value . '<br/>';
    }
    return $returnString;
  }// end function

  /*
  * list an Array left the Key right the Value for better reading
  *
  * @param  array     array
  *
  * return String
  */
  public function isElementInTable($connection,$select,$table,$where){
    $result = $this->mysql->select($connection,$select,$table,$where);
    if($resultSysFile->num_rows == 0){// no image in sys_file
      $isInTable = FALSE;
    }else {
      $isInTable = TRUE;
    }
    return $isInTable;
  }// end function
}
