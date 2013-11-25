<?php
error_reporting(0);
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