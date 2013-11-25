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

if(isset($_GET['file']) && !empty($_GET['file']))
{
	$_GET['file']='backup'.$_GET['file'];
	if(substr($_GET['file'],-3)=='zip')
		if(file_exists(ROOT_DIR.'/backups/'.$_GET['file'])){
			$type='application/zip';
			$file=ROOT_DIR.'/backups/'.$_GET['file'];
		}
		else
			$file='';
	else if(substr($_GET['file'],-3)=='sql')
		if(file_exists(ROOT_DIR.'/database/'.$_GET['file'])){
			$type='application/octet-stream';
			$file=ROOT_DIR.'/database/'.$_GET['file'];
		}
		else
			$file='';
	else
		echo 'Nothing to do here. (1)';
	if(!empty($file)){
		header("Pragma: public");
		header("Expires: 0"); 
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header('Content-Type: '.$type);
    	header("Content-Transfer-Encoding: Binary"); 
    	header("Content-disposition: attachment; filename=\"".$_GET['file']."\""); 
   	 	readfile($file);
	}
	else
		echo 'Nothing to do here. (2)';
}
else
	echo 'Nothing to do here. (3)';