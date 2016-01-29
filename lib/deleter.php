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
class Deleter {

  /*
  * delete the Images from the Database
  * @param  array jsonArray: holds the Imagedatas in an Array
  * @param  object connectionUpgradeServer: holds Mysql-Object
  *
  * return String
  */
  public function deleteImages($jsonArray,$connectionUpgradeServer,$mysqlObject){
    foreach($jsonArray as $key => $element){// the Datas from tt_content and delete wrong images
      // check if the image is in sys_file_reference
      $select = "*";
      $where = "uid_foreign = " . $element['uid'];
      $table = "sys_file_reference";
      $resultCheckSysFileReference = $mysqlObject->select($connectionUpgradeServer,$select,$table,$where);
      // check if we have a Result
      if((is_object($resultCheckSysFileReference) AND ($resultCheckSysFileReference->num_rows > 0))){
        $rowCheckSysFileReference = $resultCheckSysFileReference->fetch_assoc();
        // Now take uid_local and search in sys_file
        $select = "uid,pid,name,identifier";
        $where = "uid=" . $rowCheckSysFileReference['uid_local'];
        $table = "sys_file";
        $resultCheckSysFile = $mysqlObject->select($connectionUpgradeServer,$select,$table,$where);
        if(is_object($resultCheckSysFile)){//is there an object
          if($resultCheckSysFile->num_rows > 0){// do we have a row
            $rowCheckSysFile = $resultCheckSysFile->fetch_assoc();
            // now check if the name from sys_file is different to the name from element, then delete record in sys_file_reference
            if($rowCheckSysFile['name'] != $element['image']){// are the images different
              $where = "uid_foreign = " . $element['uid'] . " AND uid_local = " . $rowCheckSysFile['uid'];
              $table = "sys_file_reference";
              $mysqlObject->deleteRow($connectionUpgradeServer,'sys_file_reference',$where);
              $logText .= date('Y-m-d:h:m:s') . " Habe Datensatz mit der uid_foreign: "  . $element['uid'] . " und der uid_local " . $rowCheckSysFile['uid'] . " in Tabelle sys_file_reference gelöscht.\n";
            }else if(($rowCheckSysFile['name'] == $element['image']) && ($rowCheckSysFileReference['uid_foreign'] == $element['uid'])){// check if the names are the same
               $where = "uid_foreign = " . $element['uid'] . " AND name = " . $rowCheckSysFile['name'];
               $table = "sys_file_reference";
               $mysqlObject->deleteRow($connectionUpgradeServer,'sys_file_reference',$where);
               $logText .= date('Y-m-d:h:m:s') . " Habe Datensatz mit der uid_foreign: "  . $element['uid'] . " und der Namen " . $rowCheckSysFile['name'] . " in Tabelle sys_file_reference gelöscht.\n";
            }
          }
        }
      }
    }// END delete loop
    return $logText;
  }// END function
}
