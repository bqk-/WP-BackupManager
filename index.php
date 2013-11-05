<?php
/**
 * Plugin Name: Backup Manager
 * Plugin URI: http://www.google.com/
 * Description: Manage backup and restore wordpress
 * Version: 1.0
 * Author: Thibault Miclo
 * Author URI: http://75.103.83.152/~thibault/
 * License: No License
 */
define('TIME_FACTOR',2);
require_once(dirname(__FILE__).'/monitor.class.php');
error_reporting(E_ALL);
$backupManager=new BackupManager();
class BackupManager 
{
	public function __construct(){
		add_action( 'admin_enqueue_scripts', array($this,'bm_init'));
		add_action( 'admin_menu', array($this,'bm_register_menu_page'));
		if($this->notReady())
		{
			$this->bm_check_requirements();
			if(!$this->bm_get_wproot())
				die('Unable to locate wp-config.php file, create a config file, look at config-sample.php in the plugin directory.');
			$f=fopen(dirname(__FILE__).'/config.php','w+');
			fwrite($f,'<?php /*Auto generated config*/ define(\'ROOT_DIR\',\''.$this->bm_get_wproot().'\'); include ROOT_DIR.\'/wp-config.php\'; ?>');
			fclose($f);
		}
		if(!get_option('bm_path') && empty($_POST['path'])) {
			define('ROOT_DIR', $this->bm_get_wproot());
			add_action('admin_notices',array($this,'bm_config_file_missing') );
		}
		else
		{
			define('ROOT_DIR', get_option('bm_path'));
		}
		add_action('core_upgrade_preamble', array($this,'bm_main'));
	}
	private function bm_get_wproot()
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
	private function notReady()
	{
		if(!file_exists(dirname(__FILE__).'/config.php'))
			return true;
		else
			return false;
	}
	public function bm_config_file_missing() {
		echo '<div class="nice_box"><div class="close" onclick="close_parent_box(this);">X</div>Backup manager plugin isn\'t configured and could not work as expected, please configure it on the <a href="admin.php?page=backup-manager-settings">settings page</a>.</div>';
	}	
	public function bm_register_menu_page(){
	    add_menu_page( "Backup Manager", "Backup Manager", "add_users", "backup-manager", array($this,"bm_manage_submenu"), plugins_url( "backup-manager/backup.png" ) );
	    add_submenu_page("backup-manager", "Backup Manager", "Manage", "add_users", "backup-manager", array($this,"bm_manage_submenu"));
	    add_submenu_page("backup-manager", "Backup Manager Settings", "Settings", "add_users", "backup-manager-settings", array($this,"bm_settings_submenu"));
	}

	public function bm_init() {
		$plugin_url=plugins_url().'/backup-manager';
		wp_enqueue_style( "stylesheet", $plugin_url.'/style.css');
		wp_enqueue_style( "stylesheet", $plugin_url.'/button.css');
		wp_enqueue_script( "script", $plugin_url.'/script.js');
		wp_enqueue_script( "script", $plugin_url.'/basiccalendar.js');

		add_filter( 'cron_schedules', array($this,'bm_cron_add'));
	}

	public function bm_cron_add( $schedules ) {
	 	$schedules['weekly'] = array(
	 		'interval' => 604800,
	 		'display' =>'Every 7 days'
	 	);
	 	$schedules['monthly'] = array(
	 		'interval' => 2592000,
	 		'display' => 'Every 30 days'
	 	);
	 	$schedules['biweekly'] = array(
	 		'interval' => 1209600,
	 		'display' => 'Every 14 days'
	 	);
	 	return $schedules;
	}

	private function bm_check_requirements() {
		if(is_writable(ABSPATH)) {
			if(!file_exists('../backups')) {
				if(!mkdir('../backups',0777))
					return 'Unable to create "<strong>backups</strong>" directory in wordpress root folder.<br />Create it and try again!';
			}
			if(!file_exists('../database')) {
				if(!mkdir('../database',0777))
					return 'Unable to create "<strong>database</strong>" directory in wordpress root folder.<br />Create it and try again!';
			}
			$zip=new ZipArchive;
			$name=bloginfo('name').date('Y-m-d_H-i-s',time()).'.zip';
			$res=$zip->open('../backups/'.$name,ZipArchive::CREATE);
			if($res) {
				if(!$zip->addFile('../wp-content/plugins/backup-manager/style.css','style.css'))
					return 'Unable to add test file style.css from backup-manager root folder.';
				$zip->close();
				$path='../backups/'.$name;
				if(!unlink($path))
					return 'Unable to delete test zip archive, permission denied.';

				return 'Ok !';
			}
			else
				return 'Unable to create ZipArchive, make sure the "<strong>backups</strong>" folder is writable!<br />(0777 permissions on linux)';
		}
		else
			return 'Root direcoty isn\'t writable, create <strong>"backups"</strong> folder on it and try again!';
	}

	private function backupName($name) {
		$date=substr($name, strlen(ROOT_DIR)+16,-4);
		list($date,$time)=explode('_',$date);
		list($y,$m,$d)=array_map('intval',explode('-',$date));
		list($h,$i,$s)=array_map('intval',explode('-',$time));
		return date('l jS \of F Y h:i:s A',mktime($h,$i,$s,$m,$d,$y));
	}

	private function listFolderFiles($dir) { 
	    $ffs = @scandir($dir,-1); 
	    $nothing=true;
	    $output = '<table>';
	    foreach($ffs as $ff) { 
	        if($ff != '.' && $ff != '..') { 
	        	$nothing=false;
	        	if(!is_dir($dir.'/'.$ff)) 
	                $output.= '<tr>
	            					<td style="width:70%">'.backupName(trim($dir.'/'.$ff,'./')).'</td>
	            					<td style="width:20%;font-weight:bold;text-align:right;">'.Monitor::HumanSize(filesize(trim($dir.'/'.$ff,'./'))).'</td>
	            					<td class="bm_restore_button" onclick="bm_launch_restore(\''.substr($dir.'/'.$ff,strlen(ROOT_DIR)+16,-4).'\',this);">Restore</td>
	            				</tr>';
	        }  
	    } 
	    if($nothing)
	    	$output.= '<tr><td>No backup available... (make sure your path is correct in the settings)</td></tr>';
	    $output.='</table>';
	    return $output;
	} 

	private function bm_sum_filesize($path) {
	    $ffs = scandir($path); 
	    $sum=0;
	    foreach($ffs as $ff){ 
	        if($ff != '.' && $ff != '..' && $ff != 'backups' && $ff!= 'database')
	            if(!is_dir($path.'/'.$ff))
	            	$sum+=filesize($path.'/'.$ff);
	            else
	            	$sum+=$this->bm_sum_filesize($path.'/'.$ff); 
	    } 
	    return $sum;
	}

	private function bm_sum_nbfiles($path) {
	    $ffs = scandir($path); 
	    $sum=0;
	    foreach($ffs as $ff){ 
	        if($ff != '.' && $ff != '..' && $ff != 'backups' && $ff!= 'database')
	            if(!is_dir($path.'/'.$ff))
	            	$sum++;
	            else
	            	$sum+=$this->bm_sum_nbfiles($path.'/'.$ff); 
	    } 
	    return $sum;
	}

	private function bm_evaluate() {
		global $wpdb;
		$monitor=new Monitor;
		$monitor->StartTimer();
		$nb_files=$this->bm_sum_nbfiles(ABSPATH);
		$size=$this->bm_sum_filesize(ABSPATH);
		$testsize=intval($size/1000); // size of the file to be created.
		$fp = fopen('somefile.txt', 'w+'); // open in write mode.
		$k=0;
		while($k<$testsize) {
			fwrite($fp,chr(mt_rand(1,126)));
			$k++;
		}
		fclose($fp); // close the file.
		$zip=new ZipArchive;
		$name='perf_evaluate.zip';
		$zip->open($name,ZipArchive::CREATE);
		$zip->addFile('somefile.txt');
		$zip->close();
		unlink($name);
		unlink('somefile.txt');
		$temp=new SaneDb($wpdb);
		$myrows = $wpdb->get_results( 'SELECT sum( data_length + index_length ) / 1024 / 1024 AS "db_size" 
	FROM information_schema.TABLES WHERE table_schema="'.$temp->getDbName().'"' );
		$monitor->StopTimer();
		$time=$monitor->GetElapsedTime();
		$time=$time*1000;
		return array('nb'=>$nb_files,'size_files'=>$size,'size_db'=>intval($myrows[0]->db_size*1000000),'time'=>TIME_FACTOR*($time+($myrows[0]->db_size*1000)),'dbtime'=>$myrows[0]->db_size*1000);
	}

	public function bm_main() {
		echo '<div id="warning-update">
			<div class="close" onclick="bm_close_warning_box();">X</div>';
		
			$requirements=$this->bm_check_requirements();
			if($requirements==='Ok !') {
				$monitor=array();
				$monitor=$this->bm_evaluate();
				echo '<div id="bm_overlay"></div>';
		    	echo '<p>
		    		Before doing any update you should consider backing-up your installation!<br />
		    		To backup your installation, just click on this button :<br />
		    		<div id="bm_bar_wrapper"><button class="button" id="bm_launch_button" onclick="bm_launch_backup();">Complete backup</button></div><br /><br />
		    		Estimated time : '.Monitor::HumanTime($monitor['time']).' | '.$monitor['nb'].' files | <span id="backup_size" style="display:none;">'.$monitor['size_files'].'-'.$monitor['size_db'].'</span>'.Monitor::HumanSize($monitor['size_files']+$monitor['size_db']).'<br />
		    		</p>';
			}
			else
				echo '<p>Your system isn\'t configured properly for the backup manager plugin.<br /><br />
			    		Error: '.$requirements.'</p>';
		

		 echo '</div>
		 <div id="background-update" onclick="bm_close_warning_box();"></div>';
	}


	private function listBackups() { 
		$dir=ROOT_DIR.'/backups/';
	    $ffs = @scandir($dir,-1); 
	    $nothing=true;
	    $output = '<table>';
	    $output .= '<tr id="bm_title">
	    				<th>Date</th>
	    				<th>Size</th>
	    				<th>Restore</th>
	    				<th>Delete</th>
	                    <th>Download</th>
	    			</tr>';
	    if($ffs)
	    foreach($ffs as $ff) { 
	        if($ff != '.' && $ff != '..') { 
	        	$nothing=false;
	        	if(!is_dir($dir.'/'.$ff) && substr($dir.'/'.$ff,-3)=='zip') 
	                $output.= '<tr>
	            				<td style="width:50%">'.$this->backupName(trim($dir.'/'.$ff,'./')).'</td>
	            				<td style="font-weight:bold;text-align:right;">'.Monitor::HumanSize(filesize(trim($dir.$ff,'./'))).'</td>
	            				<td style="text-align:center;"><button onclick="bm_launch_restore(\''.substr($dir.'/'.$ff,strlen(ROOT_DIR)+16,-4).'\',this.parentNode);" class="button">Restore</button></td>
	            				<td style="text-align:center;"><a href="#" class="button icon trash danger" onclick="bm_delete(\''.substr($dir.'/'.$ff,strlen(ROOT_DIR)+16).'\',this)">Delete</a></td>
	            				<td style="text-align:center;"><button onclick="bm_launch_download(\''.substr($dir.'/'.$ff,strlen(ROOT_DIR)+16).'\');" class="button">Download</button></td>
	                            </tr>';
	        }  
	    } 
	    if($nothing)
	    	$output.= '<tr><td>No backup available... (make sure your path is correct in the settings)</td></tr>';
	    $output.='</table>';
	    return $output;
	} 
	private function listDatabases() { 
		$dir=ROOT_DIR.'/database/';
	    $ffs = @scandir($dir,-1); 
	    $nothing=true;
	    $output = '<table>';
	    $output .= '<tr id="bm_title_db">
	    				<th>Date</th>
	    				<th>Size</th>
	    				<th>Restore</th>
	    				<th>Delete</th>
	                    <th>Download</th>
	    			</tr>';
	    if($ffs)
	    foreach($ffs as $ff) { 
	        if($ff != '.' && $ff != '..') { 
	        	$nothing=false;
	        	if(!is_dir($dir.'/'.$ff) && substr($dir.'/'.$ff,-3)=='sql') 
	                $output.= '<tr>
	            				<td style="width:50%">'.$this->databaseName(trim($dir.'/'.$ff,'./')).'</td>
	            				<td style="font-weight:bold;text-align:right;">'.Monitor::HumanSize(filesize(trim($dir.$ff,'./'))).'</td>
	            				<td style="text-align:center;"><button onclick="bm_restore_db(\''.substr($dir.'/'.$ff,strlen(ROOT_DIR)+17,-4).'\',this.parentNode);" class="button icon loop">Restore</button></td>
	            				<td style="text-align:center;"><a href="#" class="button icon trash danger" onclick="bm_delete(\''.substr($dir.'/'.$ff,strlen(ROOT_DIR)+17).'\',this)">Delete</a></td>
	            				<td style="text-align:center;"><button onclick="bm_launch_download(\''.substr($dir.'/'.$ff,strlen(ROOT_DIR)+17).'\');" class="button">Download</button></td>
	                            </tr>';
	        }  
	    } 
	    if($nothing)
	    	$output.= '<tr><td>No backup available... (make sure your path is correct in the settings)</td></tr>';
	    $output.='</table>';
	    return $output;
	} 
	private function databaseName($name) {
		$date=substr($name, strlen(ROOT_DIR)+17,-4);
		list($date,$time)=explode('_',$date);
		list($y,$m,$d)=array_map('intval',explode('-',$date));
		list($h,$i,$s)=array_map('intval',explode('-',$time));
		return date('l jS \of F Y h:i:s A',mktime($h,$i,$s,$m,$d,$y));
	}

	private function cleanStatus() {
		@unlink(ROOT_DIR.'/database/status.ini');
		@unlink(ROOT_DIR.'/backups/status.ini');
	}

	private function bm_add_cron_backup($start,$recurrence,$type) {
		$sche=wp_get_schedules();
		if(in_array($type,$sche))
			switch($type){
				case 1:
					wp_schedule_event($start,$recurrence,'bm_launch_db');
				break;
				case 2:
					wp_schedule_event($start,$recurrence,'bm_launch_files');
				break;
				case 3:
					wp_schedule_event($start,$recurrence,'bm_cron_backup');
				break;
				default:
				break;
			}
	}

	public function bm_manage_submenu() {
	        $this->cleanStatus();
	        $monitor=$this->bm_evaluate();
	        echo '<div class="wrap">';
	        echo '<div id="bm_overlay"></div>';
	        echo '<h2>Backup Manager - Manage backups</h2>';
	        echo '<h3>Files backups <a href="#" onclick="bm_launch_backup(1);" class="add-new-h2" id="bm_launch_button">Backup files</a> (~'.Monitor::humanTime($monitor['time']-$monitor['dbtime']).' for '.Monitor::humanSize($monitor['size_files']).')</h3>';
	        echo $this->listBackups();

	        echo '<h3>Database backups <a href="#" onclick="bm_launch_backup(2);" class="add-new-h2" id="bm_launch_button_db">Backup database</a> (~'.Monitor::humanTime($monitor['dbtime']).' for '.Monitor::humanSize($monitor['size_db']).')</h3>';
	        echo $this->listDatabases();
	        echo '</div>';
	        echo '<span id="backup_size" style="display:none;">'.$monitor['size_files'].'-'.$monitor['size_db'].'</span>';
	}
	public function bm_settings_submenu() {
		if(isset($_POST['path']) || isset($_POST['email'])){
			$error=false;
			if(!empty($_POST['path'])){
				if(strlen($_POST['path'])==strrpos($_POST['path'],'/')+1)
					$_POST['path']=substr($_POST['path'], 0,-1);
				if(file_exists($_POST['path'])){
					update_option('bm_path',$_POST['path']);
				}
				else
					$error.='Invalid path...<br />';
			}

			if(!empty($_POST['email'])) {
				$nb=intval($_POST['email']);
				if($nb===2)
					update_option('bm_email_config',2);
				else if($nb===1) {
					$emails=array_map('strtolower',explode(',',$_POST['custom_emails']));
					$mail_error=false;
					foreach($emails as $mail) if(!preg_match('#^[a-z0-9._-]+@[a-z0-9._-]{2,}\.[a-z]{2,4}$#', $mail))
						$mail_error.='Address <strong>'.$mail.'</strong> is invalid.<br />';
					if(!$mail_error) {
						update_option('bm_custom_emails',implode(',',$emails));
						update_option('bm_email_config',1);
					}
					else
						$error.=$mail_error;
				}
				else
					update_option('bm_email_config',0);
			}

			if($error)
				echo '<div class="nice_box redbg"><div class="close" onclick="close_parent_box(this);">X</div>'.$error.'</div>';
			
 			}
 		$path=get_option('bm_path');
 		if(!$path)
 			$path=ROOT_DIR;
 		$email=get_option('admin_email');
 		$custom_emails=get_option('bm_custom_emails');
 		if(!$custom_emails)
 			$custom_emails='custom@mail.com,custom2@mail.fr,...';
 		$email_config=intval(get_option('bm_email_config'));
		echo '<div class="wrap">';
	    echo '<h2>Backup Manager Options</h2>';
	    echo '<fieldset><legend>Settings</legend>';
	    	echo '<form method="POST" action="">
		    		<div class="block-settings">
		    			<div class="left-settings">
		    				Absolute path to the folder you want to backup (/var/www/mywebsite - C:/xampp/htdocs/mywebsite)
		    			</div>
		    			<div class="right-settings">
		    			  <input type="text" size="44" value="'.$path.'" name="path" />
		    			</div>
		    		</div>
	    		<hr />';
	    	echo '<!--<div class="block-settings">
		    			<div class="left-settings">
		    				Notify me when my backup is over
		    			</div>
		    			<div class="right-settings">
			    			<input type="radio" name="email" '.($email_config==2 ? 'checked="checked"':'').' value="2">
			    				Email notification to admin email address : <strong>'.$email.'</strong><br />
			    			<input type="radio" name="email" '.($email_config==1 ? 'checked="checked"':'').'value="1">
			    				Email notification to custom email addresses: <input type="text" size="44" value="'.$custom_emails.'" name="custom_emails" /><br />
			    			<input type="radio" name="email" '.($email_config==0 ? 'checked="checked"':'').'value="0">
			    				No notification
		    			</div>
		    		</div>
	    			<hr />
	    			<div class="block-settings">
		    			<div class="left-settings">
		    				Number of files to keep 
		    			</div>
		    			<div class="right-settings">
		    				<input type="text" value="" name="nb_db" size="4" /> Database <br />
		    				<input type="text" value="" name="nb_files" size="4" /> Files<br />
		    			</div>
		    		</div>
		    		<div class="block-settings">
		    			<div class="left-settings">
		    				<a href="http://en.wikipedia.org/wiki/Cron" target="_blank">Cron</a> backups
		    			</div>
		    			<div class="right-settings">';
		    	$cron='<select name="cron_type">
		    				<option value="3">Both</option>
		    				<option value="2">Database</option>
		    				<option value="1">Files</option>
		    			</select>
		    			<select name="cron_recur">';
		    	foreach($arr=wp_get_schedules() as $k=>$v)
		    		echo '<option value="'.$k.'">'.$v['display'].'</option>';
		    		echo '</select>';
		    		echo 'First occurence (mm/dd/yyyy) <input type="text" name="cron_day" /><span id="cron_day"></span> <input type="text" name="cron_time" />
		    			';
		    		echo '<script type="text/javascript">

							var todaydate=new Date()
							var curmonth=todaydate.getMonth()+1 //get current month (1-12)
							var curyear=todaydate.getFullYear() //get current year

							document.getElementById(\'cron_day\').innerHTML=buildCal(curmonth ,curyear, "main", "month", "daysofweek", "days", 1);
						</script>';
					echo '</div>-->
		    		</div>
	    			<input type="submit" value="Update" />
	    			</form>';
	    	echo '';
	    echo '</fieldset>';
	    echo '</div>';
	}

}
class SaneDb
{
    private $_oDb;

    public function __construct(wpdb $oDb)
    {
        $this->_oDb = $oDb;
    }
    public function getDbName() { return $this->_oDb->dbname;     }
}
function bm_backupdb($filename) {
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
	//cycle through
	foreach($tables as $table)
	{
		$result = $db->query('SELECT * FROM `'.$table.'`');
		$num_fields = $result->columnCount();
		$return.= 'DROP TABLE `'.$table.'`;';
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

	$handle = fopen($filename,'w+');
	fwrite($handle,$return);
	fclose($handle);
}
function bm_zipFolderFiles($path) { 
	$ffs = @scandir($path);
    foreach($ffs as $ff){ 
        if($ff != '.' && $ff != '..' && $ff != 'backups' && $ff != 'database') 
            if(!is_dir($path.'/'.$ff)) {
            	$zip->addFile($path.'/'.$ff,substr($path.'/'.$ff,strlen(ROOT_DIR)+1));
			}
            else
            	zipFolderFiles($path.'/'.$ff,$zip);
    } 
}
function bm_launch_db(){
	bm_cron_backup(2);
}
function bm_launch_files() {
	bm_cron_backup(1);
}
function bm_cron_backup($type=3) {
	switch($type){
		case 1:
			$zip=new ZipArchive;
			$name='backup'.date('Y-m-d_H-i-s',time());
			$res=$zip->open(ROOT_DIR.'/backups/'.$name.'.zip',ZipArchive::CREATE);
			if($res) {
				zipFolderFiles(ROOT_DIR,$zip);
					if(!$zip->close())
						bm_mail_error('Cron was not able to save the ZipArchive, no idea why.');
			}
			else
				bm_mail_error('Cron was not able to create the ZipArchive, maybe a permissions issue.');
		break;
		case 2:
			if(!backup_databse(ROOT_DIR.'/database/'.$name.'.sql'))
				bm_mail_error('Cron was not able to create the .sql file, maybe a permissions issue.');
		break;
		case 3:
			$zip=new ZipArchive;
			$name='backup'.date('Y-m-d_H-i-s',time());
			$res=$zip->open(ROOT_DIR.'/backups/'.$name.'.zip',ZipArchive::CREATE);
			if($res) {
				//echo json_encode(array('launchok'=>TRUE));
				if(backup_databse(ROOT_DIR.'/database/'.$name.'.sql',$db_size)){
					zipFolderFiles(ROOT_DIR,$zip);
					if(!$zip->close())
						bm_mail_error('Cron was not able to save the ZipArchive, have you enough space on your disk ?');
				}
				else
					bm_mail_error('Cron was not able to create the .sql file, maybe a permissions issue.');
			}
			else
				bm_mail_error('Cron was not able to create the ZipArchive, maybe a permissions issue.');
		break;

		default:
		break;
	}
}

function bm_mail_error($msg)
{
	$headers = 'From: miclo.thibault@gmail.com' . "\r\n" .
     'Reply-To: miclo.thibault@gmail.com' . "\r\n" .
     'X-Mailer: PHP/' . phpversion();
	if(get_option('bm_email_config') == 1)
		mail(get_option('bm_custom_emails'),'BackupManager Cron Error',$msg,$headers);
	else
		mail(get_option('admin_email'),'BackupManager Cron Error',$msg,$headers);
}

?>