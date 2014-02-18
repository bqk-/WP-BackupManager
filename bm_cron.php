<?php
/**
 * Cron backup for BackupManager
 * Use url/bm_cron.php?type=X
 * with X =
 * - 1 => backup files only
 * - 2 => backup database only
 * - 3 => backup both
 */

function bm_get_wproot()
{
    $base = dirname(__FILE__);
        $path = false;
    if(@file_exists(dirname(dirname($base))."/wp-load.php"))
        $path = dirname(dirname($base));
       else
        if (@file_exists(dirname(dirname(dirname($base)))."/wp-load.php"))
            $path = dirname(dirname(dirname($base)));
        else
            $path = false;

    if ($path != false)
        $path = str_replace("\\", "/", $path);
    return $path;
}
require(bm_get_wproot() . '/wp-load.php');
define('ROOT_DIR',get_option('bm_path')); 
define('WP_USE_THEMES', false);
global $wp, $wp_query, $wp_the_query, $wp_rewrite, $wp_did_header;
error_reporting(0);
set_time_limit(0);
function notAlreadyInBackup() {
    $ffs = @scandir(ROOT_DIR.'/backups/');
    if(is_array($ffs))
    foreach($ffs as $ff)
        if($ff != '.' && $ff != '..') 
            if(substr($ff,-3)!='zip' && substr($ff,-3)!='ini')
                return false; 
   return true;
}
function zipFolderFiles($path,$zip) { 
    $ffs = @scandir($path);
    foreach($ffs as $ff){ 
        if($ff != '.' && $ff != '..' && $ff != 'backups' && $ff != 'database') 
            if(!is_dir($path.'/'.$ff)) {
                $zip->addFile($path.'/'.$ff,substr($path.'/'.$ff,strlen(ROOT_DIR)+1));
            }
            else
                if(!zipFolderFiles($path.'/'.$ff,$zip))
                    return false;
    } 
    return true;
} 

function backup_databse($filename) {
    $dsn = 'mysql:dbname='.DB_NAME.';host='.DB_HOST;
    $db = new PDO($dsn, DB_USER, DB_PASSWORD);
    $tables = array();
    foreach($db->query('SHOW TABLES') as $row)
    {
        $tables[] = $row[0];
    }
    $return='';
    //cycle through
    foreach($tables as $table)
    {
        $result = $db->query('SELECT * FROM `'.$table.'`');
        $num_fields = $result->columnCount();
        $return.= 'DROP TABLE IF EXISTS `'.$table.'`;';
        $row2 = $db->query('SHOW CREATE TABLE `'.$table.'`')->fetch(PDO::FETCH_NUM);
        $return.= "\n\n".$row2[1].";\n\n";
        
        for ($i = 0; $i < $num_fields; $i++) 
        {
            while($row=$result->fetch(PDO::FETCH_NUM))
            {
                $return.= 'INSERT INTO `'.$table.'` VALUES(';
                for($j=0; $j<$num_fields; $j++) 
                {
                    $row[$j] = addslashes($row[$j]);
                    $row[$j] = str_replace("\n","\\n",$row[$j]);
                    if (isset($row[$j])) { $return.= '\''.$row[$j].'\'' ; } else { $return.= '\'\''; }
                    if ($j<($num_fields-1)) { $return.= ','; }
                }
                $return.= ");\n";
            }
        }
        $return.="\n\n\n";
    }
    //save file
    $handle = fopen($filename,'w+');
    fwrite($handle,$return);
    fclose($handle);

    return true;
}

function backupName2($name) {
    $date=substr($name,6);
    list($date,$time)=explode('_',$date);
    list($y,$m,$d)=array_map('intval',explode('-',$date));
    list($h,$i,$s)=array_map('intval',explode('-',$time));
    return date('l jS \of F Y h:i:s A',mktime($h,$i,$s,$m,$d,$y));
}

if(notAlreadyInBackup())
        if(isset($_GET['type'])&&intval($_GET['type'])>0)
            if(intval($_GET['type'])==3){
                $zip=new ZipArchive;
                $name='backup'.date('Y-m-d_H-i-s',time());
                $res=$zip->open(ROOT_DIR.'/backups/'.$name.'.zip',ZipArchive::CREATE);
                if($res) {
                    if(backup_databse(ROOT_DIR.'/database/'.$name.'.sql')){
                        if(zipFolderFiles(ROOT_DIR,$zip))
                            $zip->close();
                        bm_mail_notif('Backup terminated !');
                    }
                    else
                    {
                        bm_mail_notif('Backup failed ! Error: nodb');
                    }
                }
                else
                {
                     bm_mail_notif('Backup failed ! Error: nozip');
                }
            }
            else if(intval($_GET['type'])==2){

                $name='backup'.date('Y-m-d_H-i-s',time());

                if(backup_databse(ROOT_DIR.'/database/'.$name.'.sql')){
                    bm_mail_notif('Backup terminated !');
                }
                else
                {
                     bm_mail_notif('Backup failed ! Error: nodb');
                }
      
            }
            else {
                $zip=new ZipArchive;
                $name='backup'.date('Y-m-d_H-i-s',time());
                $res=$zip->open(ROOT_DIR.'/backups/'.$name.'.zip',ZipArchive::CREATE);
                if($res) {
                        if(zipFolderFiles(ROOT_DIR,$zip)){
                                $zip->close();
                            bm_mail_notif('Backup terminated !');
                        }
                        else
                             bm_mail_notif('Backup failed ! Error: nozip');
                    }       
                }
                else
                     bm_mail_notif('Backup failed ! Error: nozip');
            }
        else
             bm_mail_notif('Backup failed ! Error: noget');
else
    bm_mail_notif('Backup failed ! Error: alreadybackup');

?>