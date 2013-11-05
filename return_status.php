<?php

if(file_exists('status.ini'))
	echo intval(file_get_contents('status.ini'));

?>