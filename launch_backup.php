<?php
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
	global $end_size, $done;
    $ffs = @scandir($path);
    foreach($ffs as $ff){ 
        if($ff != '.' && $ff != '..' && $ff != 'backups' && $ff != 'database') 
            if(!is_dir($path.'/'.$ff)) {
            	$zip->addFile($path.'/'.$ff,substr($path.'/'.$ff,strlen(ROOT_DIR)+1));
            	if(!file_exists('status.ini'))
            		return false;
            	$done+=filesize($path.'/'.$ff);
            	$percent=round(($done/$end_size)*100);
            	$handle = fopen('status.ini','w+');
				fwrite($handle,$percent);
				fclose($handle);
			}
            else
            	if(!zipFolderFiles($path.'/'.$ff,$zip))
            		return false;
    } 
    return true;
} 

function backup_databse($filename,$size) {
	global $end_size, $done;
	$dsn = 'mysql:dbname='.DB_NAME.';host='.DB_HOST;
	$db = new PDO($dsn, DB_USER, DB_PASSWORD);
	$tables = array();
	$nb_tables=0;
	foreach($db->query('SHOW TABLES') as $row)
	{
		$nb_tables++;
		$tables[] = $row[0];
	}
	$return='';
	$loop_size=intval($size/$nb_tables);
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
		if(!file_exists('status.ini'))
			return false;
    	$done+=$loop_size;
    	$percent=round(($done/$end_size)*100);
        $handle = fopen('status.ini','w+');
		fwrite($handle,$percent);
		fclose($handle);
		$return.="\n\n\n";
	}
	if(!file_exists('status.ini'))
		return false;
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
	if(isset($_GET['size']) && !empty($_GET['size'])) {
		list($files_size,$db_size)=explode('-',$_GET['size']);
		$done=0;
		if(isset($_GET['who'])&&intval($_GET['who'])>0)
			if(intval($_GET['who'])==3){
				$end_size=$files_size+$db_size;
				$zip=new ZipArchive;
				$name='backup'.date('Y-m-d_H-i-s',time());
				$res=$zip->open(ROOT_DIR.'/backups/'.$name.'.zip',ZipArchive::CREATE);
				if($res) {
					$handle = fopen('status.ini','w+');
			    	if(!$handle)
			    	{
			    		bm_mail_notif('Backup failed ! Error: noini');
			    		echo json_encode(array('return'=>'noini'));
			    	}
			    	else {
			    		fwrite($handle,0);
						fclose($handle);
						//echo json_encode(array('launchok'=>TRUE));
						if(backup_databse(ROOT_DIR.'/database/'.$name.'.sql',$db_size)){
							if(zipFolderFiles(ROOT_DIR,$zip))
								$zip->close();
							$handle = fopen('status.ini','w+');
							fwrite($handle,100);
							fclose($handle);
							bm_mail_notif('Backup terminated !');
							echo json_encode(array('return'=>'ok'));
						}
						else
						{
							bm_mail_notif('Backup failed ! Error: nodb');
							echo json_encode(array('return'=>'nodb'));
						}
						
			    	}		
				}
				else
				{
					bm_mail_notif('Backup failed ! Error: nozip');
					echo json_encode(array('return'=>'nozip'));
				}
			}
			else if(intval($_GET['who'])==2){
				$end_size=$db_size;
				$name='backup'.date('Y-m-d_H-i-s',time());
				$handle = fopen('status.ini','w+');
		    	if(!$handle)
		    	{
		    		bm_mail_notif('Backup failed ! Error: noini');
		    		echo json_encode(array('return'=>'noini'));
		    	}
		    	else {
		    		fwrite($handle,0);
					fclose($handle);
					//echo json_encode(array('launchok'=>TRUE));
					if(backup_databse(ROOT_DIR.'/database/'.$name.'.sql',$db_size)){
						$handle = fopen('status.ini','w+');
						fwrite($handle,100);
						fclose($handle);
						bm_mail_notif('Backup terminated !');
						echo json_encode(array('return'=>'ok','name'=>$name,'size'=>Monitor::HumanSize(filesize(ROOT_DIR.'/database/'.$name.'.sql')),'date'=>backupName2($name)));
					}
					else
					{
						bm_mail_notif('Backup failed ! Error: nodb');
						echo json_encode(array('return'=>'nodb'));
					}
		    	}		
			}
			else {
				$end_size=$files_size;
				$zip=new ZipArchive;
				$name='backup'.date('Y-m-d_H-i-s',time());
				$res=$zip->open(ROOT_DIR.'/backups/'.$name.'.zip',ZipArchive::CREATE);
				if($res) {
					$handle = fopen('status.ini','w+');
			    	if(!$handle)
			    		echo json_encode(array('return'=>'noini'));
			    	else {
			    		fwrite($handle,0);
						fclose($handle);
						//echo json_encode(array('launchok'=>TRUE));
						if(zipFolderFiles(ROOT_DIR,$zip)){
								$zip->close();
							$handle = fopen('status.ini','w+');
							fwrite($handle,100);
							fclose($handle);
							bm_mail_notif('Backup terminated !');
							echo json_encode(array('return'=>'ok','name'=>$name,'size'=>Monitor::HumanSize(filesize(ROOT_DIR.'/backups/'.$name.'.zip')),'date'=>backupName2($name)));
						}
						else
						{
							bm_mail_notif('Backup failed ! Error: nozip');
							echo json_encode(array('return'=>'nozip'));
						}
			    	}		
				}
				else
				{
					bm_mail_notif('Backup failed ! Error: nozip');
					echo json_encode(array('return'=>'nozip'));
				}
			}
		else
		{
			bm_mail_notif('Backup failed ! Error: noget');
			echo json_encode(array('return'=>'noget'));
		}
	}
	else
	{
		bm_mail_notif('Backup failed ! Error: noget');
		echo json_encode(array('return'=>'noget'));
	}
else
{
	bm_mail_notif('Backup failed ! Error: alreadybackup');
	echo json_encode(array('return'=>'alreadybackup'));
}