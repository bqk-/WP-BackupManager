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


bm_mail_notif('Backup has been stopped !');
@unlink(dirname(__FILE__).'/status.ini');
echo 'something'; 