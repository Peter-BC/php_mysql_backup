<?php

// +----------------------------------------------------------------------
// | Mysql backup and restore
// +----------------------------------------------------------------------
// | Author: Peter
// +----------------------------------------------------------------------
// | GitHub:https://github.com/Peter-BC/php_mysql_backup
// | Youtube:
// +----------------------------------------------------------------------
// | I hope my code is helpful for you.
// +----------------------------------------------------------------------

set_time_limit(0); //no running time limit
ignore_user_abort(1);// close browser still running

define('APP_PATH', realpath(dirname(__FILE__)));
include 'database.php';

$bk = new mysql_backup;

//export ------------------------------
$bk->export();
//end export---------------------------


//import ---------------------------------------------
$time = '20230114-205059';
$time = strtotime(str_replace('-', '', $time));
//$bk->import($time);
//end import------------------------------------------

class mysql_backup {

  public $config;

  public function __construct() {
    $this->config = [
      'path' => APP_PATH. './backup/',// backup path default ./backup
      'part' => '20971520',//backup file volume size  unit B, 20M
      'compress' => '1', //if use compress
      'level' => '9', //compress level 1-9, 1 is lowest, 9 is highest

      'database.hostname' => '127.0.0.1',//mysql server
      'database.hostport' => '3306', //mysql port
      'database.user' => '',  //mysql user
      'database.password' => '', //mysql password
      'database.database' => '', //database name
    ];
  }


  public  function import($time = 0)
  {
      if ($time === 0) exitr('Param error！');

      // initialize
      $name  = date('Ymd-His', $time) . '-*.sql*';
      $path  = realpath($this->config['path']) . DIRECTORY_SEPARATOR . $name;
      //echo $path;exit;
      $files = glob($path);
      $list  = array();
      echo 'Restore start, all count'. count($files). "。。。。。\n";
      foreach($files as $name){
          $basename = basename($name);
          $match    = sscanf($basename, '%4s%2s%2s-%2s%2s%2s-%d');
          $gz       = preg_match('/^\d{8,8}-\d{6,6}-\d+\.sql.gz$/', $basename);
          $list[$match[6]] = array($match[6], $name, $gz);
      }
      ksort($list);

      // check file
      $last = end($list);
      if(count($list) === $last[0]){
          foreach ($list as $key => $item) {
            echo "Restore NO. {$key} file...\n";

              $Database = new Database($item, $this->config);
              $start = $Database->import(0);

              // loop import
              while (0 !== $start) {
                  if (false === $start) { // 出错

                      echo 'Restore error!';
                  }
                  $start = $Database->import($start[0]);
              }
              echo "Restore {$key} file OK...\n";
          }

          echo 'Restore done!';
          exit;
      } else {
        echo 'Restore error!';
        exit;
      }
  }



  public function export($ids = null, $start = 0)
  {
      $time = time();

      // backup file name
      $file = array(
          'name' => date('Ymd-His', $time),
          'part' => 1,
      );

      $Database = new Database($file, $this->config);
      $tables = $Database->getTables();


      if ( !empty($tables) && is_array($tables)) {

          // initialize
          $path = $this->config['path'];
          //echo $path;exit;
          if(!is_dir($path)){
              mkdir($path, 0755, true);
          }



          // Check backup process is unique
          $lock = "{$this->config['path']}backup.lock";

          if(is_file($lock)){

              echo "Please delete backup.lock！\n";exit;
          } else {
              // create file
              file_put_contents($lock, $time);
          }

          // check writeable

          is_writeable($this->config['path']) || $this->error('backup folder can not  write ');


          // Create backup file

          if(false !== $Database->create()){
              // backup table
              foreach ($tables as $table) {
                echo "Backup {$table} start。。。。\n";
                  $start = $Database->backup($table, $start);
                  while (0 !== $start) {
                      if (false === $start) { // 出错
                          $this->error('Backup error!');
                      }
                      $start = $Database->backup($table, $start[0]);
                  }
                  echo "Backup {$table} done\n";
              }

              // when done, delete lock file
              unlink($lock);
              // done
              echo "Backup done!\n";exit;
          } else {

              echo "Init error, can not create Backup file\n";exit;
          }
      } else {

          echo "no tables? \n";exit;
      }
  }
}
