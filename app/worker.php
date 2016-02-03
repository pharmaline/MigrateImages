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
  public function __construct($ini_array,$mysqlObject,$pathToLogfile,$action){
    $this->pathUpload = $ini_array['pathUpload'];
    $this->pathMigrated = $ini_array['pathMigrated'];
    $this->ini_array = $ini_array;
    $this->mysql = $mysqlObject;
    $this->pathToLogfile = $pathToLogfile;
    $this->liveServer = $ini_array['liveserver'];
    $this->upgradeServer = $ini_array['upgradeserver'];
    $this->upgradeServerAction = $ini_array['upgradeserver']['action'][$action];
    $this->pagesConstant = $this->liveServer['tables']['pages']['constant'];
    $this->tt_contentConstant = $this->liveServer['tables']['tt_content']['constant'];
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
  public function migrateImagesTtNews(){
    $select = $this->upgradeServerAction['tables']['tt_news']['select'];
    $where = $this->upgradeServerAction['tables']['tt_news']['where'];
    $table = $this->upgradeServerAction['tables']['tt_news']['table'];
    var_dump($this->upgradeServerAction);
    $resultTtNewsLiveserver = $this->mysql->select($this->connectionLiveServer,$select,$table,$where);
    // loop through the Records and migrate the Images
    while($rowTtNewsLiveserver = $resultTtNewsLiveserver->fetch_assoc()){
      // get the uid from tx_dam_mm_ref
      $select = $this->upgradeServerAction['tables']['tx_dam_mm_ref']['select'];
      $table = $this->upgradeServerAction['tables']['tx_dam_mm_ref']['table'];
      $where = str_replace('###uid_foreign###',$rowTtNewsLiveserver['uid'],$this->upgradeServerAction['tables']['tx_dam_mm_ref']['where']);
        echo "where<br>";
        var_dump($where);
    }
    die();
  }
  /**
   * @var action the the action to perform
   */
  public function migrateImagesTxDam(){
    // read the Datas from the Upgradeserver
    // add tt_contentConstant to the Uid from tt_content and pagesConstant to the pid from pages
    // so we only select the Elements from the T3 4.5 Installation
    $select = $this->upgradeServerAction['tables']['tt_content']['select'];
    $table = $this->upgradeServerAction['tables']['tt_content']['table'];
    $where = $this->upgradeServerAction['tables']['tt_content']['where'] . $this->upgradeServerAction['tables']['tt_content']['additionalWhere'];
    $resultTtcontentLiveserver = $this->mysql->select($this->connectionLiveServer,$select,$table,$where);
    // this holds the Images from Liveserver tt_content
    $imagesArray = array();
    $keyArrayImage = 0;
    while($rowTtcontentLiveserver = $resultTtcontentLiveserver->fetch_assoc()){
      // now look into tx_dam_mm_ref with the uid from tt_content
      // if you get more than 1 Record then there are more then 1 Images in this CE
      $select = '*';
      $table = 'tx_dam_mm_ref';
      $where = 'uid_foreign = \'' . $rowTtcontentLiveserver['uid'] . '\'' . ' AND ident = \'tx_damttcontent_files\'';
      $resultTxDamMmRefLiveserver = $this->mysql->select($this->connectionLiveServer,$select,$table,$where);

      if($resultTxDamMmRefLiveserver->num_rows > 1){ // more than 1 Image
        while ($rowTxDamMmRef = $resultTxDamMmRefLiveserver->fetch_assoc()) {
          // fetch the Imagename from tx_dam
          $table = 'tx_dam';
          $select = 'file_name,file_path';
          $where = 'uid = \'' . $rowTxDamMmRef['uid_local'] . '\'';
          $resultTxDamLiveserver = $this->mysql->select($this->connectionLiveServer,$select,$table,$where);
          if($resultTxDamLiveserver->num_rows >= 1){
            $rowTxDam = $resultTxDamLiveserver->fetch_assoc();
            // set the this->uploadPath
            $arrayImage[$keyArrayImage]['file_name'] = $rowTxDam['file_name'];
            $arrayImage[$keyArrayImage]['pathUpload'] = '../../' . $rowTxDam['file_path'];
            $keyArrayImage++;
          }
        }
        $checkImage = TRUE;
      } else if($resultTxDamMmRefLiveserver->num_rows == 1){// only 1 Image
        $rowTxDamMmRef = $resultTxDamMmRefLiveserver->fetch_assoc();
        // fetch the Imagename from tx_dam
        $table = 'tx_dam';
        $select = 'file_name,file_path';
        $where = 'uid = \'' . $rowTxDamMmRef['uid_local'] . '\'';
        $resultTxDamLiveserver = $this->mysql->select($this->connectionLiveServer,$select,$table,$where);
        if($resultTxDamLiveserver->num_rows == 1){//TODO brauch ich die Abfrage? Ich bekomme ja eh nur ein Bild zurück!!
          $rowTxDam = $resultTxDamLiveserver->fetch_assoc();
          // set the this->uploadPath
          $rowTtcontentLiveserver['image'] = $rowTxDam['file_name'];
          $rowTtcontentLiveserver['pathUpload'] = '../../' . $rowTxDam['file_path'];
        }
        $checkImage = FALSE;
      }
      // we have more than one image in the CE
      if($checkImage){
        // loop through the Images and write them to sys_file and sys_file_reference
        foreach ($arrayImage as $key => $item){
          $rowTtcontentLiveserver['image'] = $item['file_name'];
          $resultFind = $this->findImage($item['pathUpload'],$rowTtcontentLiveserver['image']);
          if($resultFind){
            $this->migrateOneImage($rowTtcontentLiveserver,$item['pathUpload']);
          } else {
            echo '<br>line 114: Konnte Datei: ' . $rowTtcontentLiveserver['image'] . ' nicht finden. Pfad: ' . $item['pathUpload'] . '<br>';
            echo '<br>Das Bild liegt auf der Seite mit der Pid: ' . $rowTtcontentLiveserver['pid'] . '. Bitte manuell pr&uuml;fen.<br>';
          }
          // clear the Array
          $arrayImage = array();
        }
        // there is only one Image in CE
      } else {
        $resultFind = $this->findImage($rowTtcontentLiveserver['pathUpload'],$rowTtcontentLiveserver['image']);
        if($resultFind){
            $this->migrateOneImage($rowTtcontentLiveserver,$rowTtcontentLiveserver['pathUpload']);
        }else {
          echo '<br>line 125: Konnte Datei: ' . $rowTtcontentLiveserver['image'] . ' nicht finden. Pfad: ' . $rowTtcontentLiveserver['pathUpload'] . '<br>';
          echo '<br>Das Bild liegt auf der Seite mit der Pid: ' . $rowTtcontentLiveserver['pid'] . '. Bitte manuell pr&uuml;fen.<br>';
        }

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

  /**
   * @var action the the action to perform
   */
  public function migrateImagesTtContent(){
    // read the Datas from the Upgradeserver
    // add tt_contentConstant to the Uid from tt_content and pagesConstant to the pid from pages
    // so we only select the Elements from the T3 4.5 Installation
    $select = $this->liveServerTables['tt_content']['select'];
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
            $this->migrateOneImage($rowTtcontentLiveserver,$this->pathUpload);
          } else {
            echo '<br>Line 164: Konnte Datei: ' . $image . ' nicht finden. Pfad: ' . $this->pathUpload . '<br>';
            echo '<br>line 139: Das Bild liegt auf der Seite mit der Pid: ' . $rowTtcontentLiveserver['pid'] . '. Bitte manuell pr&uuml;fen.<br>';
          }
        }
        // there is only one Image in CE
      } else {
        $this->migrateOneImage($rowTtcontentLiveserver,$this->pathUpload);
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

  protected function migrateOneImage($rowTtContent,$pathUpload) {
    // get the extension of the image from $elementSysFile['image']
    $startPoint = strripos ($rowTtContent['image'],'.');
    $extension = substr($rowTtContent['image'],$startPoint + 1);// without the .
    $extension = strtolower($extension);
    if($extension == 'jpg'){
        $mimeType = 'Image/jpeg';
    }else{
        $mimeType = 'Image/'.str_replace('.','',$extension);
    }
    $this->copyImage($this->pathMigrated,$pathUpload,$rowTtContent['image']);
    $this->logText .= "Habe das Bild: " . $image . " nach " . $this->pathMigrated . " kopiert\n";
    $select = 'uid';
    $where = 'name=\'' . $rowTtContent['image'] . '\'';
    $table = 'sys_file';
    $resultSysFile = $this->mysql->select($this->connectionUpgradeServer,$select,$table,$where);
    // get the identifier and the name from row['image']
    $identifier = str_replace('../../','',$this->pathMigrated . $rowTtContent['image']);
    // start with the insertString
    // pid,tstamp,creation_date,extension,mime_type,name,identifier.
    $insertStringSysFile = '\'' . $rowTtContent['pid'] . '\',\'' . $rowTtContent['tstamp'] . '\',\'' . $rowTtContent['tstamp'] . '\',\'' . $extension . '\',\'' . $mimeType . '\',\'' . $rowTtContent['image'] . '\',\'' . $identifier . '\'';
    // if there is no image in sys_file then insert the record
    if($resultSysFile->num_rows == 0){
      //insert the row into sys_file
      $insert = $this->mysql->insertRow($this->connectionUpgradeServer,$this->upgradeServerAction['tables']['sys_file']['table'],$this->upgradeServerAction['tables']['sys_file']['fields'],$insertStringSysFile);
      if($insert){
        $this->logText .= date('Y-m-d:h:m:s') . " Habe Datei: " . $identifier . " pid:" . $rowTtContent['pid']." in Tabelle " . $this->upgradeServerAction['tables']['sys_file']['table'] . " geschrieben.\n";
        $this->counter++;
      }else{
        echo  "line 128: Fehler beim Insert in Tabelle " . $this->upgradeServerAction['tables']['sys_file']['table'] . "!!!\n" . $insertStringSysFile . "\n";
        die();
      }
    }
    // search again in sys_file to get the uid from the new Element
    $resultSysFile = $this->mysql->select($this->connectionUpgradeServer,$select,$table,$where);
    // now insert the Image into sys_file_reference
    $rowSysFile = $resultSysFile->fetch_assoc();
    $insertTableSysFileReference = $this->upgradeServerAction['tables']['sys_file_reference']['table'];
    // pid,uid_local,uid_foreign,hidden,tablenames,fieldname,table_local
    $insertFieldsSysFileReference = $this->upgradeServerAction['tables']['sys_file_reference']['fields'];
    $insertStringSysFileReference = '\'' . $rowTtContent['pid'] . '\',\'' . $rowSysFile['uid'] . '\',\'' . $rowTtContent['uid'] . '\',\'' . $rowTtContent['hidden'] . '\',\'tt_content\',\'image\',\'' . $this->upgradeServerAction['tables']['sys_file']['table'] . '\',\'' . $rowTtContent['imagecaption'] . '\'';
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
  function copyImage($path,$pathUpload,$image){
    $cmdCopy = 'cp ' . $pathUpload . '\'' . $image . '\' ' . $path;
    $resultShell = shell_exec($cmdCopy);
    if($resultShell){
      return $resultShell;
    }
  }// END function

  /* find an Image in Path
  *
  * @param  string: path
  * @param  string: image
  *
  * return String
  */
  private function findImage($path,$image){
    $cmdFind = "find " . $path . " -name '" . $image . "'";
    // for testing only pid 77 { ["uid"]=> string(3) "537" ["pid"]=> string(2) "77" ["image"]=> string(14) "'_D4M8688.jpg'" }
    $resultShell = shell_exec($cmdFind);
    $resultShell = trim($resultShell);
    if($resultShell){
      return $resultShell;
    }
  }// END function

}
