<?php
class Controller {
  var $pathToScript;
  var $pathToLogfile;
  var $logText;
  var $counter;
  var $path;
  var $ini_string;
  var $ini_array;
  //var $myMysql;

  function __construct(){
    // init Variable
    // this is the Path to this Script
    $this->pathToScript = str_replace('controller.php', '', $_SERVER['SCRIPT_FILENAME']);
    // this is the Path to the Logfile
    $this->pathToLogfile = str_replace('app/','',$this->pathToScript) . 'log/';
    $this->logText = '';
    $this->counter = 0;
    //$this->path = '../uploads/pics';
    $this->ini_string = file_get_contents('../config/main.json');

    $this->ini_array = json_decode($this->ini_string, TRUE);

    $this->filename = $this->ini_array['filename'];
    // init the MySql Helper class
    require ('../lib/mysql.php');
    $this->mysql = new MysqlHelper();
    require('../lib/helper.php');
    $this->helper = new Helper($this->ini_array,$this->mysql,$this->pathToLogfile);
  }
  function main(){
    $action = $_GET['action'];
    if ($action == 'read') {
      require('../lib/reader.php');
      $this->reader = new Reader($this->ini_array,$this->mysql,$this->pathToLogfile);
      $this->reader->readDatas();
      //$this->helper->readDatas();
    } else if ($action == 'tt_content') {
      require('worker.php');
      $this->worker = new Worker($this->ini_array,$this->mysql,$this->pathToLogfile,$action);
      $this->worker->migrateImagesTtContent();
      //$this->helper->writeDatas();
    } else if ($action == 'tx_dam') {
      require('worker.php');
      $this->worker = new Worker($this->ini_array,$this->mysql,$this->pathToLogfile,$action);
      $this->worker->migrateImagesTxDam();

      //$this->helper->writeDatas();
    } else if ($action == 'tt_news') {
      require('worker.php');
      $this->worker = new Worker($this->ini_array,$this->mysql,$this->pathToLogfile,$action);
      $this->worker->migrateImagesTtNews();
    } else if ($action == 'tt_news_dam') {
      require('worker.php');
      $this->worker = new Worker($this->ini_array,$this->mysql,$this->pathToLogfile,$action);
      $this->worker->migrateImagesTtNewsDam();
    } else if ($action == 'alter_table_45') {
      require('alter.php');
      $this->alter = new Alter();
      $this->alter->main();
    }

    echo '<form method="GET" action="main.php">
      <input type="submit" name="back" value="back" />
      </form>';
  }// end function
}// end class
$controller = new Controller();
$controller->main();
die();
?>
