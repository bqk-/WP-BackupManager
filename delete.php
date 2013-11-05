<?php
error_reporting(0);
include 'config.php';

if(isset($_GET['file'])&&!empty($_GET['file']))
{
	$type=substr($_GET['file'],-3);
	if($type=='sql')
		@unlink(ROOT_DIR.'/database/backup'.$_GET['file']);
	else if($type=='zip')
		@unlink(ROOT_DIR.'/backups/backup'.$_GET['file']);
	else if($type=='ini') {
		@unlink(ROOT_DIR.'/backups/'.$_GET['file']);
		@unlink(ROOT_DIR.'/database/'.$_GET['file']);
	}
	
	if($type!='ini'&&$type!='sql'&&$type!='zip')
		echo 'error';
	else
		echo 'success';
}
else
	echo 'error';


?>