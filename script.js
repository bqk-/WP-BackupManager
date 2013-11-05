function bm_close_warning_box() {
	document.getElementById('warning-update').style.display='none';
	document.getElementById('background-update').style.display='none';
}

function bm_show_backups() {
	var e=document.getElementById('listBackups');
	if(e.style.display=='none')
		e.style.display='block';
	else
		e.style.display='none';
}
var timer=0;
var end_size=0;
function bm_launch_backup(something) {
	var who;
	var sizes=document.getElementById('backup_size').innerHTML.split('-');
	var db_size=sizes[1];
	var files_size=sizes[0];

	if(typeof something === "undefined") {
        who=3;
        end_size=db_size+files_size;
        var e=document.getElementById('bm_bar_wrapper');
		e.innerHTML='<span id="bm_green_bar"></span><span id="bm_gray_bar"></span><span id="bm_percent">0 %</span>';
		e.innerHTML+='<button type="button" id="bm_cancel_backup" onclick="bm_cancel_backup(timer);">Cancel</button>';
	}
    else {
    	who=parseInt(something);
    	var f=document.createElement('tr');
		var td1=document.createElement('td');
		var td2=document.createElement('td');
		var td3=document.createElement('td');
		var td4=document.createElement('td');
		var td5=document.createElement('td');
		td1.style.width='50%';
		td1.id='bm_bar_wrapper';
		td1.innerHTML='<span id="bm_green_bar"></span><span id="bm_gray_bar"></span><span id="bm_percent">0 %</span>';
		td2.style.fontWeight='bold';
		td2.innerHTML='-';
		td2.style.textAlign='right';
		td3.innerHTML='-';
		td3.style.textAlign='center';
		td4.innerHTML='-';
		td4.style.textAlign='center';
		td5.innerHTML='-';
		td5.style.textAlign='center';
		f.appendChild(td1);
		f.appendChild(td2);
		f.appendChild(td3);
		f.appendChild(td4);
		f.appendChild(td5);
    }
	
    if(who==2) {
    	end_size=db_size;
    	document.getElementById('bm_title_db').parentNode.insertBefore(f,document.getElementById('bm_title_db').nextSibling);
    }
    else if (who == 1) {
    	end_size=files_size;
    	document.getElementById('bm_title').parentNode.insertBefore(f,document.getElementById('bm_title').nextSibling);
    }

	timer=setInterval(bm_refresh_percent,500);
	var xmlhttp;
  	xmlhttp=new XMLHttpRequest();
  	var d = new Date();
	var start = d.getTime();
  	xmlhttp.open("GET",'../wp-content/plugins/backup-manager/launch_backup.php?size='+document.getElementById('backup_size').innerHTML+'&who='+who,true);
	xmlhttp.send();
	xmlhttp.onreadystatechange=function()
	{
	  	if(xmlhttp.readyState==4 && xmlhttp.status==200)
	    {
	    	bm_end_backup();
	    	var xms=xmlhttp.responseText;
	    	var response=JSON.parse(xmlhttp.responseText || "null");
	    	var e=document.getElementById('bm_bar_wrapper');
	    	switch(response['return']) {
	    		case 'alreadybackup':
	    			e.innerHTML='A backup is already launched, wait the end of this one.';
	    		break;

	    		case 'noget':
	    			e.innerHTML='No $_GET received by the server, try again.';
	    		break;

	    		case 'nozip':
	    			e.innerHTML='Unable to create the zip archive.';
	    		break;

	    		case 'nodb':
	    			e.innerHTML='The database backup failed.';
	    		break;

	    		case 'ok':
	    			document.getElementById('bm_green_bar').style.width='100%';
			    	document.getElementById('bm_gray_bar').style.width='0%';
			       	document.getElementById('bm_percent').innerHTML='Done !';
			       	if(who == 3) {
			       		var f=document.createElement('p');
			       		f.innerHTML='<strong>Backup completed !</strong>';
	    		   		document.getElementById('bm_cancel_backup').parentNode.appendChild(f);
	    		   		document.getElementById('bm_cancel_backup').parentNode.removeChild(document.getElementById('bm_cancel_backup'));
	    		   	}
	    		   	else if(who<=2)
	    		   	{
	    		   		e.id='';
	    		   		td1.innerHTML=response['date'];
	    		   		td1.className='bm_bar';
	    		   		td2.innerHTML=response['size'];
	    		   		td3.innerHTML='<button onclick="'+(who==1?'bm_launch_restore':'bm_restore_db')+'(\''+response['name'].substr(6)+'\',this.parentNode);" class="button">Restore</button>';
	    		   		td4.innerHTML='<a href="#" class="button icon trash danger" onclick="bm_delete(\''+response['name'].substr(6)+(who == 1 ? '.zip':'.sql')+'\',this)">Delete</a>';
	    		   		td5.innerHTML='<button onclick="bm_launch_download(\''+response['name'].substr(6)+(who == 1 ? '.zip':'.sql')+'\');" class="button">Download</button>';
	    		   	}
			    break;

	    		default:
	    			e.innerHTML='Error : '+ response['return'];
	    		break;
			}
	    }
	};
	
}

function bm_refresh_percent(){
	var xmlhttp;
	var done;
	var percent;
  	xmlhttp=new XMLHttpRequest();
  	xmlhttp.open("GET","../wp-content/plugins/backup-manager/return_status.php?t=" + Math.random(),true);
	xmlhttp.send();
	xmlhttp.onreadystatechange=function()
	{
	  	if(xmlhttp.readyState==4 && xmlhttp.status==200)
	    {
	    	done=parseInt(xmlhttp.responseText);
	    	if(done!=0 && done <= 100) {
		    	var green_size=done;
		    	var gray_size=100-green_size;
		    	document.getElementById('bm_green_bar').style.width=green_size+'%';
		    	document.getElementById('bm_gray_bar').style.width=gray_size+'%';
		       	document.getElementById('bm_percent').innerHTML=done+' %';
		     }
	    }
	    //else
	    //	document.getElementById('bm_percent').innerHTML='Canceled';
	};
}

function bm_cancel_backup() {
	document.getElementById('bm_cancel_backup').style.display='none';
	var xmlhttp;
  	xmlhttp=new XMLHttpRequest();
  	xmlhttp.open("GET","../wp-content/plugins/backup-manager/stop_backup.php?t=" + Math.random(),true);
	xmlhttp.send();
	xmlhttp.onreadystatechange=function()
	{
	  	if(xmlhttp.readyState==4 && xmlhttp.status==200)
	    {
	    	document.getElementById('bm_percent').innerHTML='Canceled';
			clearInterval(timer);
	    }
	};

}

function bm_end_backup() {
	var xmlhttp;
  	xmlhttp=new XMLHttpRequest();
  	xmlhttp.open("GET","../wp-content/plugins/backup-manager/stop_backup.php?t"+Math.random(),true);
	xmlhttp.send();
	xmlhttp.onreadystatechange=function()
	{
	  	if(xmlhttp.readyState==4 && xmlhttp.status==200)
	    {
	    	clearInterval(timer);
	    }
	};

}

function bm_transformIntoDiv(elem) {
    var parent = elem.parentNode;
    var newNode = document.createElement("div");
    newNode.id="bm_bar_wrapper";
    parent.replaceChild(newNode,elem);
}

function bm_launch_restore(elem,td) {
	document.getElementById('bm_overlay').style.display='block';
	if(td.parentNode.childNodes[0].className=='bm_bar')
		var e=td.parentNode.childNodes[0];
	else {
		var e=td.parentNode.childNodes[1];
		e.id='bm_bar_wrapper';
	}
	e.innerHTML='<span id="bm_green_bar"></span><span id="bm_gray_bar"></span><span id="bm_percent">0 %</span>';
	var xmlhttp;
  	xmlhttp=new XMLHttpRequest();
  	timer=setInterval(bm_refresh_percent_restore,500);
  	xmlhttp.open("GET",'../wp-content/plugins/backup-manager/launch_restore.php?file='+elem,true);
	xmlhttp.send();
	xmlhttp.onreadystatechange=function()
	{
	  	if(xmlhttp.readyState==4 && xmlhttp.status==200)
	    {
	    	clearInterval(timer);
	    	var xms=xmlhttp.responseText;
	    	var response=JSON.parse(xmlhttp.responseText) || "null";
	    	switch(response['return']) {
	    		case 'alreadyrestore':
	    			e.innerHTML='<strong>A restore is already launched, wait the end of this one.</strong>';
	    		break;

	    		case 'nofile':
	    			e.innerHTML='<strong>No file received by the server, try again.</strong>';
	    		break;

	    		case 'nozip':
	    			e.innerHTML='<strong>Unable to read the zip archive.</strong>';
	    		break;

	    		case 'ok':
	    			e.innerHTML='<strong>Restore completed, the page will be refreshed in 3 seconds or you can just <a href="#" onclick="location.reload();">refresh right now</a>.</strong>';
	    			xmlhttp2=new XMLHttpRequest();
	    			xmlhttp2.open("GET",'../wp-content/plugins/backup-manager/delete.php?file=status.ini',true);
					xmlhttp2.send();
					xmlhttp2.onreadystatechange=function() {setTimeout(function() {location.reload();},3000)};
	    		break;

	    		default:
	    			e.innerHTML='Error : '+ response['return'];
	    		break;
			}
	    }
	};
}
function bm_restore_db(elem,td) {
	document.getElementById('bm_overlay').style.display='block';
	if(td.parentNode.childNodes[0].className=='bar')
		var e=td.parentNode.childNodes[0];
	else {
		var e=td.parentNode.childNodes[1];
		e.id='bm_bar_wrapper';
	}
	e.innerHTML='<span id="bm_green_bar_db"></span><span id="bm_gray_bar_db"></span><span id="bm_percent_db">0 %</span>';
	var xmlhttp;
  	xmlhttp=new XMLHttpRequest();
  	timer=setInterval(bm_refresh_percent_db,500);
  	xmlhttp.open("GET",'../wp-content/plugins/backup-manager/restore_db.php?file='+elem,true);
	xmlhttp.send();
	xmlhttp.onreadystatechange=function()
	{
	  	if(xmlhttp.readyState==4 && xmlhttp.status==200)
	    {
	    	clearInterval(timer);
	    	var xms=xmlhttp.responseText;
	    	var response=JSON.parse(xmlhttp.responseText) || "null";
	    	switch(response['return']) {
	    		case 'alreadyrestore':
	    			e.innerHTML='<strong>A restore is already launched, wait the end of this one.</strong>';
	    		break;

	    		case 'nofile':
	    			e.innerHTML='<strong>No file received by the server, try again.</strong>';
	    		break;

	    		case 'nosql':
	    			e.innerHTML='<strong>Unable to read the sql file.</strong>';
	    		break;

	    		case 'ok':
	    			e.innerHTML='<strong>Restore completed, the page will be refreshed in 3 seconds or you can just <a href="#" onclick="location.reload();">refresh right now</a>.</strong>';
	    			xmlhttp2=new XMLHttpRequest();
	    			xmlhttp2.open("GET",'../wp-content/plugins/backup-manager/delete.php?file=status.ini',true);
					xmlhttp2.send();
					xmlhttp2.onreadystatechange=function() {setTimeout(function() {location.reload();},3000)};
	    		break;

	    		default:
	    			e.innerHTML='Error : '+ response['return'];
	    		break;
			}
	    }
	};
}
function bm_refresh_percent_restore(){
	var xmlhttp;
	var done, total;
  	xmlhttp=new XMLHttpRequest();
  	xmlhttp.open("GET","../backups/status.ini?t=" + Math.random(),true);
	xmlhttp.send();
	xmlhttp.onreadystatechange=function()
	{
	  	if(xmlhttp.readyState==4 && xmlhttp.status==200)
	    {
	    	done=parseInt(xmlhttp.responseText);
	    	if(done==100){
	    		clearInterval(timer);
	    		document.getElementById('bm_overlay').style.display='block';
	    		e.innerHTML='<strong>Restore completed, the page will be refreshed in 3 seconds or you can just <a href="#" onclick="location.reload();">refresh right now</a>.</strong>';
	    		xmlhttp2=new XMLHttpRequest();
	    		xmlhttp2.open("GET",'../wp-content/plugins/backup-manager/delete.php?file=status.ini',true);
				xmlhttp2.send();
				xmlhttp2.onreadystatechange=function() {setTimeout(function() {location.reload();},3000)};
	    	}
	    	var green_size=done;
	    	var gray_size=100-green_size;
	    	document.getElementById('bm_green_bar').style.width=green_size+'%';
	    	document.getElementById('bm_gray_bar').style.width=gray_size+'%';
	       	document.getElementById('bm_percent').innerHTML=done+' %';
	    }
	    //else
	    //	document.getElementById('bm_percent').innerHTML='Canceled';
	};
}
function bm_refresh_percent_db(){
	var xmlhttp;
	var done;
  	xmlhttp=new XMLHttpRequest();
  	xmlhttp.open("GET","../database/status.ini?t=" + Math.random(),true);
	xmlhttp.send();
	xmlhttp.onreadystatechange=function()
	{
	  	if(xmlhttp.readyState==4 && xmlhttp.status==200)
	    {
	    	//alert(xmlhttp.responseText);
	    	done=parseInt(xmlhttp.responseText);
	    	if(done==100)
	    		clearInterval(timer);
	    	var green_size=done;
	    	var gray_size=100-green_size;
	    	document.getElementById('bm_green_bar_db').style.width=green_size+'%';
	    	document.getElementById('bm_gray_bar_db').style.width=gray_size+'%';
	       	document.getElementById('bm_percent_db').innerHTML=done+' %';
	    }
	    //else
	    //	document.getElementById('bm_percent').innerHTML='Canceled';
	};
}
function bm_delete(file,e) {
	var xmlhttp;
  	xmlhttp=new XMLHttpRequest();
  	xmlhttp.open("GET","../wp-content/plugins/backup-manager/delete.php?file="+file,true);
	xmlhttp.send();
	xmlhttp.onreadystatechange=function()
	{
	  	if(xmlhttp.readyState==4 && xmlhttp.status==200)
	    {
	    	var resp=xmlhttp.responseText;
	    	if(resp=='error')
	    		alert('Data error.');
	    	else if(resp=='success') {
	    		var f=e.parentNode.parentNode;
	    		f.parentNode.removeChild(f);
	    	}
	    	else
	    		alert('Error : '+resp);
	    }
	};
}

function bm_launch_download(file) {
	window.open("../wp-content/plugins/backup-manager/download.php?file="+file)
}


function close_parent_box(f) {
	var frame=8;
	var totalTime=600;
	var stepS;
	var e=f.parentNode;
    stepS=totalTime/frame;
	for(var i=1;i<frame;i++)
	{
		setTimeout((function(l){
						return function () {
							e.style.opacity=l;
							};
						})(
							1-((1/frame)*i)
							),i*stepS);
	}
	setTimeout(function(){e.style.opacity=0;e.parentNode.removeChild(e);},totalTime);
}