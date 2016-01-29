<?php
/*
PHP REST Server: A HTTP REST interface to Mysql with mysqli Interface
written in PHP

mysql.php :: MySQL database adapter
Copyright (C) 2014 Christian Platt <christian.platt@pharmaline.de>

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

/* $id$ */

/**
 * PHP REST MySQL class
 * MySQL connection class.
 */
class MysqlHelper {

    /**
     * @var resource Database resource
     */
    var $db;

    /**
     * Connect to the database.
     * @param str[] config
     */
    function connect($config) {

      $this->db = new mysqli(
			$config['server'],
			$config['username'],
			$config['password'],
			$config['database']
			);
		if($this->db->connect_errno){
			echo "Failed to connect to MySQL: " . $this->db->connect_error;
			exit;
		}

        return $this->db;
    }

    /**
     * Close the database connection.
     */
    function close($connection) {
        mysqli_close($connection);
    }


    /**
     * Get the columns in a table.
     * @param str table
     * @return resource A resultset resource
     */
    function getColumns($table) {
        return mysqli_query(sprintf('SHOW COLUMNS FROM %s', $table), $this->db);
    }

    /**
     * Get a row from a table.
     * @param str table
     * @param str where
     * @return resource A resultset resource
     */
    function getRow($table, $where) {
        return mysqli_query(sprintf('SELECT * FROM %s WHERE %s', $table, $where));
    }
    /**
     * Get a row from a table.
     * @param str table
     * @param str where
     * @return resource A resultset resource
     */
    function select($connection, $select, $table, $where) {
/*
      if($table == "pages"){
        var_dump("SELECT " . $select . " FROM " . $table . " WHERE " . $where);
        echo "<br>";
      }

      if($table == "tt_content"){
        echo "sql query select:<br>";
        var_dump("SELECT " . $select . " FROM " . $table . " WHERE " . $where);
        echo "<br>";
      }
      */
/*
if($table == "tx_dam"){
  echo "sql query select:<br>";
  var_dump("SELECT " . $select . " FROM " . $table . " WHERE " . $where);
  echo "<br>";
}

if($table == "tx_dam_mm_ref"){
  var_dump("SELECT " . $select . " FROM " . $table . " WHERE " . $where);
  echo "<br>";
}
      var_dump("SELECT " . $select . " FROM " . $table . " WHERE " . $where);
      echo "<br>";

if($table == "tt_content"){
  var_dump("SELECT " . $select . " FROM " . $table . " WHERE " . $where);
  echo "<br>";
}
*/


      try {
        $result = mysqli_query($connection, "SELECT " . $select . " FROM " . $table . " WHERE " . $where);
      //  if($table == "tx_dam_mm_ref"){var_dump(var_dump("SELECT " . $select . " FROM " . $table . " WHERE " . $where));}

      } catch (Exception $e) {
        echo 'MySql Fehler: ',  $e->getMessage(), "\n";
      }

        return $result;
    }
    /**
     * Get the rows in a table.
     * @param str primary The names of the primary columns to return
     * @param str table
     * @return resource A resultset resource
     */
    function getTable($primary, $table) {
        return mysqli_query(sprintf('SELECT %s FROM %s', $primary, $table));
    }

    /**
     * Get the tables in a database.
     * @return resource A resultset resource
     */
    function getDatabase() {
        return mysqli_query($this->db,'SHOW TABLES');
    }

    /**
     * Get the primary keys for the request table.
     * @return str[] The primary key field names
     */
    function getPrimaryKeys($table) {
        $resource = $this->getColumns($table);
        $primary = NULL;
        if ($resource) {
            while ($row = $this->row($resource)) {
                if ($row['Key'] == 'PRI') {
                    $primary[] = $row['Field'];
                }
            }
        }
        return $primary;
    }

    /**
     * Update a row.
     * @param str table
     * @param str values
     * @param str where
     * @return bool
     */
    function updateRow($table, $values, $where) {
        return mysqli_query(sprintf('UPDATE %s SET %s WHERE %s', $table, $values, $where));
    }

    /**
     * Insert a new row.
     * @param str table
     * @param str names
     * @param str values
     * @return bool
     */
    function insertRow($connection,$table, $names, $values) {
      $error = "";
      // check if names is an array, then convert array to string
      if(is_array($names)){
          $nameString = self::arrayToString($names,",");
      }else{
        $nameString = $names;
      }
      // check if values is an array, then convert array to string
      if(is_array($values)){
          $valueString = self::arrayToString($values,",");
      }else{
        $valueString = $values;
      }

      // build the insert String
      $insertString = "INSERT INTO " . $table . " (" . $nameString . ") VALUES (" . $valueString . ")";


/*

if($table == "pages"){
  var_dump($insertString);
  echo "<br>";
}
      if(strpos($values,'RTEmagicC_1-3_Auszeichnungen-7955_jpg.jpg')){
        echo $insertString;die();
      }
      if($table == "sys_file_reference"){
        var_dump($insertString);
        echo "<br>";
      }

      echo "insertString:<br>";
      var_dump($insertString);
      echo "<br>";


      if($table == "sys_file_reference"){
        var_dump($insertString);
        echo "<br>";
      }
      */
      // try to insert the datas
      try {
        $insert = mysqli_query($connection,$insertString);
      } catch (Exception $e) {
        $error = $e->getMessage();
      }
      if($insert){
          return $insert;
      }else{
        return $error;
      }

    }
    /**
    * converts an array to string seperate each item with a seperator
    * @param array array
    * @parma string seperator
    *
    * @return string
    */
    function arrayToString($array,$seperator){
      $string = "";
      if(is_array($array)){
        foreach($array as $key => $item){
          $string .= $item . $seperator;
        }
      }
      // remove the seperator from the end
      $string = substr($string, 0, -1);
      return $string;
    }


    function tryDBInsert($connection,$insertSQL){
    	try {
    		if(mysqli_query($connection, $insertSQL)){
    			return mysqli_insert_id($connection);
			}else{
	    		$this->dump(mysqli_error($connection));
    		}
    	} catch(exception $e) {
    		$this->dump( 'Caught exception: '.  $e->getMessage());
    	}
    }


    /**
     * Get the columns in a table.
     * @param str table
     * @return resource A resultset resource
     */
    function deleteRow($connection,$table, $where) {
        //return mysql_query(sprintf('DELETE FROM %s WHERE %s', $table, $where));
        $deleteString = "DELETE FROM " . $table . " WHERE " . $where;
/*
        echo "deleteString:<br>";
        var_dump($deleteString);
        echo "<br>";
        */
        try {
          $delete = mysqli_query($connection,$deleteString);
        } catch (Exception $e) {
          echo 'MySql Fehler: ',  $e->getMessage(), "\n";
        }
        return $delete;
    }

    /**
     * Escape a string to be part of the database query.
     * @param str string The string to escape
     * @return str The escaped string
     */
    function escape($string) {
        return mysqli_escape_string($this->db,$string);
    }

    /**
     * Fetch a row from a query resultset.
     * @param resource resource A resultset resource
     * @return str[] An array of the fields and values from the next row in the resultset
     */
    function row($resource) {
        return mysqli_fetch_assoc($this->db,$resource);
    }

    /**
     * The number of rows in a resultset.
     * @param resource resource A resultset resource
     * @return int The number of rows
     */
    function numRows($resource) {
        return mysqli_num_rows($resource);
    }

    /**
     * The number of rows affected by a query.
     * @return int The number of rows
     */
    function numAffected() {
        return mysqli_affected_rows($this->db);
    }

    /**
     * Get the ID of the last inserted record.
     * @return int The last insert ID
     */
    function lastInsertId() {
        return mysqli_insert_id($this->db);
    }


    /**selectRows gets all the records affected by $sql statemant as ARRAY
    *@PARAM char the sql select
    *@RETURN array found records as ARRAY
    */
    function selectRowsAsArray($sql){
    	$rowArray=array();
    	$connection=$this->db;
    	if($result = $connection->query($sql)){
		 	if($hits=$result->num_rows){	//something found? Update Part
		 		while ($row=mysqli_fetch_assoc($result)) {
					//$this->dump(date('d.m.Y h:i:s',$row['validto']));
					//$this->dump(date('d.m.Y h:i:s',time()));
					$rowArray[]=$row;
				}
			}
		}
		return $rowArray;
    }



   	/**
	* Inserts or updates dataArray into Database
	*
	*@PARAM varchar name of table to update
	*@PARAM varchar where for update
	*@PARAM ARRAY	data to insert/updates
	*@RETURN boolean
	*/
	function insertOrUpdateTable($table,$where_clause,$data){
		$uid='';
		//$where_clause='where forecast_date="'.$data['forecast_date'].'" AND id="'.$data['id'].'" AND forecast_name="'.$data['forecast_name'].'"';
		$connection=$this->db;

		$sql='select uid from '.$table." ".$where_clause;	//works with t3 uid based tables

		if($result = $connection->query($sql)){
		 	if($hits=$result->num_rows){	//something found? Update Part
		 		while($obj = $result->fetch_object()){  //get Resulst
					$uid=$obj->uid;	//get the uid of table
					$where='uid='.$obj->uid.'';
					$sql=$this->prepareUpdateSQL($where,$table,$data);
					if($result = $connection->query($sql)){
						return $uid;
					}else{
						echo "<br>".$connection->error."<br>".$sql;	//error output when inserting or updateing
						return false;
					}
				}
			}else{	//insert Part
				$sql=$this->prepareInsertSQL($table,$data);
				if($result = $connection->query($sql)){
					//$this->dump($connection->insert_id);
					return true;
				}else{
					echo "<br>".$connection->error."<br>".$sql;	//error output when inserting or updateing
					return false;
				}
			}
		}else{
			echo "<br>".$connection->error."<br>";	//error output
			return false;
		}

		//insert
	}

	/**
	* compute the inser SQL from source
	*@PARAM		Varchar name of Table
	*@PARAM 	ARRAY 	of key=>value pairs
	*RETURN 	VARCHAR
	*/
	function prepareInsertSQL($table,$inserts){
		foreach ($inserts as $key=>$value) {
		    $value=mysqli_real_escape_string($this->db,$value);
		    $inserts[$key]=$value;
		}
		$values =array_values($inserts);
		$keys = array_keys($inserts);

    	$sql='INSERT INTO `'.$table.'` (`'.implode('`,`', $keys).'`) VALUES ("'.implode('","', $values).'")';

		return $sql;
	}

	/**
	* compute the inser SQL from source
	*@PARAM		Varchar name of Table
	*@PARAM 	ARRAY 	of key=>value pairs
	*RETURN 	VARCHAR
	*/
	function prepareUpdateSQL($where,$table,$inserts){
		$values = array_values($inserts);
    	$keys = array_keys($inserts);
    	$update='';

    	foreach ($inserts as $key=>$value) {
			if($this->is_utf8($value)){
				$value=utf8_decode($value);
			}
		    $value=mysqli_real_escape_string($this->db,$value);
		    $update.="`".$key."`='".$value."', ";
		}
		$update=substr($update,0,strlen($update)-2);
    	$sql='UPDATE `'.$table.'` SET '.$update.' where '.$where;

		return $sql;
	}


	/**
     * Returns true if $string is valid UTF-8 and false otherwise.
     *
     * @param [mixed] $string     string to be tested
     * @return boolean
     */
    function is_utf8($string) {

        // From http://w3.org/International/questions/qa-forms-utf-8.html
        return preg_match('%^(?:
              [\x09\x0A\x0D\x20-\x7E]            # ASCII
            | [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
            |  \xE0[\xA0-\xBF][\x80-\xBF]        # excluding overlongs
            | [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
            |  \xED[\x80-\x9F][\x80-\xBF]        # excluding surrogates
            |  \xF0[\x90-\xBF][\x80-\xBF]{2}     # planes 1-3
            | [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
            |  \xF4[\x80-\x8F][\x80-\xBF]{2}     # plane 16
        )*$%xs', $string);

    }


       function dump($var){
		$height='';	//height: {$height}
		echo "<pre style=\"border: 1px solid #000; overflow: auto; margin: 0.5em;\">";
		var_dump($var);
		echo "</pre>\n";
	}

}
?>
