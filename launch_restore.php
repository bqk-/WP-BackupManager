<?php
error_reporting(0);
include 'config.php';
set_time_limit(0);

function plop($dir) {
   if (is_dir($dir)) {
     $objects = scandir($dir);
     foreach ($objects as $object) {
       if ($object != "." && $object != "..") {
         if (filetype($dir."/".$object) == "dir") plop($dir."/".$object); else unlink($dir."/".$object);
       }
     }
     reset($objects);
     rmdir($dir);
   }
 }

function emptydir($dir) {
   if (is_dir($dir)) {
     $objects = scandir($dir);
     foreach ($objects as $object) {
       if ($object != "." && $object != ".." && $object != 'backups' && $object != 'database') {
         if (filetype($dir."/".$object) == "dir") plop($dir."/".$object); else unlink($dir."/".$object);
       }
     }
     reset($objects);
   }
 }
 

 
 
if(file_exists(ROOT_DIR.'/backups/status.ini')) //Restore in progress, don't do anything or break everything
	exit(json_encode(array('return'=>'alreadyrestore')));

if(isset($_GET['file']) && !empty($_GET['file'])) {
	//only folder not affected, we will store progress in it
	$f=fopen(ROOT_DIR.'/backups/status.ini','w+');
	fwrite($f,0);
	$path_zip=ROOT_DIR.'/backups/backup'.$_GET['file'].'.zip';
	$zip=new ZipArchive;
	$res=$zip->open($path_zip);
	if($res) {
        $handle = fopen(ROOT_DIR.'/backups/status.ini','w+');
		fwrite($handle,10);
		fclose($handle);
		emptydir(ROOT_DIR);
		$handle = fopen(ROOT_DIR.'/backups/status.ini','w+');
		fwrite($handle,40);
		fclose($handle);
		$zip->extractTo(ROOT_DIR.'/');
		$zip->close();
		$handle = fopen(ROOT_DIR.'/backups/status.ini','w+');
		fwrite($handle,100);
		fclose($handle);
		//unlink(ROOT_DIR.'/backups/status.ini');
		echo json_encode(array('return'=>'ok'));
	}
	else
		echo json_encode(array('return'=>'nozip'));
}
else
	echo json_encode(array('return'=>'nofile'));

?>