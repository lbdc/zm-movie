<?php
//
// Functions
//
// main() main function loading data from zoneminder sql database and diplay movie options
// load_movies() load existing movies and shows download/status information 
// load_Camera() populates the dropdown list and min/max timestamps for each camera
// Make_Movie() makes the movie by creating an input text files list of events 
// and encodes the movie using avconv (ffmpeg)
// In 'Alarm' mode, alarm events are padded with a buffer and assembled into one continuous alarm movie
// In 'All' mode all events are assmebled into one continuous movie
//
// Uses deep storage
//
// Due to bulk frames in zoneminder, you should set buffers to twice the time bulk value 
// to ensure the min/max times are respected. At default settings, we're talking about seconds. 
// To minimize this effect reduce the bulk frame settings. 
//
// Ensure the web folder is writable by www-data or the movie created will stay in temporary folder
// Note: If using arguments the execute the PHP script, files created may not be overwritable by www-data
//
// Zoneminder Constants
//
function main()
{
// Read from etc/zm/zm.conf (ubuntu) or etc/zm.conf (centos)
if(file_exists("/etc/zm/zm.conf")) {
	$config = parse_ini_file('/etc/zm/zm.conf');}
else if(file_exists("/etc/zm.conf")) {
	$config = parse_ini_file('/etc/zm.conf');}
else { echo "No zoneminder configuration zm.conf found";}
//
define('ZM_HOST', $config['ZM_DB_HOST']);
define('ZMUSER', $config['ZM_DB_USER']);
define('ZMPASS', $config['ZM_DB_PASS']);
define('ZM_DB', $config['ZM_DB_NAME']);
//
// Set target folder for movies generated, defaults to folder where script resides
define('PATH_TARGET',getcwd());
//
// Get DIR_EVENTS from database
$con=mysqli_connect(ZM_HOST,ZMUSER, ZMPASS, ZM_DB);
$result = mysqli_query($con, "SELECT Value FROM Config WHERE Name = 'ZM_DIR_EVENTS'");
while($row = mysqli_fetch_assoc($result)) {
	$Dir_Events=end($row);
}
mysqli_close($con);
define('PATH_EVENT', $config['ZM_PATH_WEB'].'/'.$Dir_Events);
//
// Arguments required if using function as command line
//
// load cameras from database
	$mon_event=Load_Camera();
//
// HTML section
//
echo '<html>';
echo '<head>';
echo '</head>';
echo '<body>';
echo '<style> table, tr, td { border: 1px solid black; border-collapse: collapse; border-spacing: 5px; padding-right: 5px; padding-left: 5px;} </style>';
// HTML Camera Dropdown list
echo '<form name="Camera" action="" method="GET">';
echo '<select id="Camera" name="Camera" onChange="this.form.submit()">';
echo '<option value="Select">Select Camera</option>';
foreach ($mon_event as $k => $v) {
       	echo '<option value="' . $k . '">'. $v['Name'] . '</option>';
}
echo '</select>';
echo '<input type="hidden" name="form" value="1">';
echo '</form>';
if(isset($_GET['form']) && $_GET['form'] == 1) {
	$c = $_GET['Camera'];
}
else {$c="";}
//
// HTML Option Table
echo '<form name="mmovie" action="" method="GET">';
echo '<table>';
echo '<tr>' . '<td>' . 'Camera Selected ' . '</td>' . '<td>' . '<input type = text value="' . $mon_event[$c]['Name'] . '" name="Name" readonly>' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Camera Id ' . '</td>' . '<td>' . '<input type = text value="' . $mon_event[$c]['Id'] . '" name="Id" readonly>' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Video Start Time ' . '</td>' . '<td>' . '<input type = text value="' . $mon_event[$c]['Starttime'] . '" name="Starttime">' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Video End Time ' . '</td>' . '<td>' . '<input type = text value="' . $mon_event[$c]['Endtime'] . '" name="Endtime">' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Buffers (seconds)' . '</td>' . '<td>' . '<input type="number" name="Buffer" max="60" min="0" step="5" value="30">' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Frames' . '</td>' . '<td>' . '<select name="Frames"> <option value="Alarm">Alarm</option> <option value="All">All</option></select>' . '</td>' . '</tr>';
echo '</table><br>';
//
echo "encoder parameters";
echo '<table>';
echo '<tr>' . '<td>' . 'Codec' . '</td>' . '<td>' . '<select name="Codec"><option value="x264">x264</option></select>' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'CRF' . '</td>' . '<td>' . '<input type="number" name="CRF" max="51" min="0" value="23">'.'</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Profile' . '</td>' . '<td>' . '<select name="Profile"><option value="Baseline">Baseline</option><option value="Main" SELECTED>Main</option><option value="High">High</option></select>' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Preset' . '</td>' . '<td>' . '<select name="Preset"><option value="Veryslow">Veryslow</option><option value="Slow">Slow</option><option value="Medium">Medium</option><option value="Fast" SELECTED>Fast</option><option value="Faster">Faster</option><option value="Veryfast">Veryfast</option><option value="Superfast">Superfast</option><option value="Ultrafast">Ultrafast</option></select>' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Speed' . '</td>' . '<td>' . '<input type="number" name="Speed" max="50" min="1" step="1" value="10">' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Video Size<sup>*Camera</sup>' . '</td>' . '<td>' . '<select name="Size"> <option value="' . $mon_event[$c]['Size'] . '">' . $mon_event[$c]['Size'] . '*</option><option value="1920:1080">1920x1080</option> <option value="1280:720">1280x720</option><option value="640:480">640x480</option><option value="320:240">320x240</option></select>' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Filename' . '</td>' . '<td>' . '<input type = text value="' . $mon_event[$c]['Name'] . '_movie" name="Filename">' . '</td>' . '</tr>';
echo '<tr>' . '<td>' . 'Extension' . '</td>' . '<td>' . '<select name="Ext"><option value="mp4">mp4</option><option value="mkv">mkv</option>' . '</td>' . '</tr>';
echo '</table><br>';
echo '<input type="hidden" name="index" value="' . $c . '">';
echo '<input type="hidden" name="form" value="2">';
echo '<input type="submit" value="Make Movie">';
echo '</form>';
if(isset($_GET['form']) && $_GET['form'] == 2) {
	$c = $_GET['Camera'];
}
//
// POST data from user input
//
if (isset($_GET['form']) && $_GET['form'] == 2) {
	$MonitorId = $_GET['Id'];
	$Frametype = $_GET['Frames'];
	$Speed = $_GET['Speed'];
	$Buffer = $_GET['Buffer'];
	$Encoder = $_GET['Encoder'];
	$Profile = $_GET['Profile'];
	$Preset = $_GET['Preset'];
	$CRF = $_GET['CRF'];
	$Starttime = $_GET['Starttime'];
	$Endtime = $_GET['Endtime'];
	$Codec = $_GET['Codec'];
	$Size = $_GET['Size'];
	$Filename = $_GET['Filename'].".".$_GET['Ext'];
	$index=$_GET['index'];
	if(strtotime($Starttime) >= strtotime($mon_event[$index]['Starttime']) && strtotime($Endtime) <= strtotime($mon_event[$index]['Endtime']) && strtotime($Endtime) >  strtotime($Starttime))
	{
		Make_Movie($MonitorId,$Starttime,$Endtime,$Buffer,$Speed,$Frametype,$Codec,$Size,$Filename,$Profile,$Preset,$CRF);
	}
	else
	{
		echo "<script type='text/javascript'>alert('Error: Verify your dates');history.go(-1);</script>";
	}
}
} # end of function main (no indent)
function load_movies() 
{
// ajax update table interval script
echo '<script>
var refreshId = setInterval(function() {refresh_table(); }, 5000); 
var iterations=0;
refresh_table();
function refresh_table()
{
	iterations++;
	if (iterations >=500) {
		clearInterval(refreshId);
		alert("Timeout...Please refresh page to continue");
	}
        var xmlhttp;
        if (window.XMLHttpRequest)
        {// code for IE7+, Firefox, Chrome, Opera, Safari
                xmlhttp=new XMLHttpRequest();
        }
        else
        {// code for IE6, IE5
                xmlhttp=new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange=function()
        {
                if (xmlhttp.readyState==4 && xmlhttp.status==200)
                {
                        document.getElementById("progressDiv").innerHTML=xmlhttp.responseText;
                }
        }
        xmlhttp.open("GET","zm_movie_cam_avconv_03.php?q=refresh_table",true);
        xmlhttp.send();
}

</script>';
//
// Show existing movies for download
// Get movies from folder and store them into array with corresponding supporting file
foreach(glob('*.{mkv,mp4}', GLOB_BRACE) as $value) {
	$movie_files[] = $value; 
	$movie_txt[]=basename($value,pathinfo($value,PATHINFO_EXTENSION))."txt";
	$movie_log[]=basename($value,pathinfo($value,PATHINFO_EXTENSION))."log"; 
	$movie_progress[]=basename($value,pathinfo($value,PATHINFO_EXTENSION))."progress"; 
}
//
// Make HTML table
echo '<form name="files"  method="GET">';
echo '<div id="progressDiv">';
echo '<table>';
echo '<tr><td></td><td>Movie Name</td><td>Status</td><td>Index</td><td>-%-</td><td>Size<sup>Est<sup></td><td>Log</td></tr>';
// Iterate through all movie files found and create table
for ($x=0; $x<count($movie_files) ;$x++) {
	// Get pid of encoder process from log and verify if there is a file creation in progress
	$movie_pid=preg_grep("(PID)", file($movie_log[$x]));
	$pid[$x]=explode(' ', array_values($movie_pid)[0])[2];
	if(!empty(posix_getsid($pid[$x]))) { 
		$enc_status[$x]="Encoding"; }
	else {
		$enc_status[$x]="Completed";
		$pid[$x]=""; }
	echo '<tr>';
	// Display movie files
	echo '<td><input type="radio" name="movie_index" value="'.$x.'"></td>';
	echo '<td><a href="'.$movie_files[$x].'">'.$movie_files[$x].'</a></td>';
	echo '<td>'.$enc_status[$x].'</td>';
	// Display text files
	echo '<td><a href="'.$movie_txt[$x].'">List</a></td>';
	$progress[$x]=intval(file($movie_progress[$x])[0])/(intval(exec("wc -l $movie_txt[$x]")))*100;
	echo '<td>'.round($progress[$x]).'</td>';
	$size=number_format(filesize($movie_files[$x])/1048576/$progress[$x]*100,1);	
	if($size<=15 || $progress[$x] > 90) {  
		echo '<td>'.round(number_format(filesize($movie_files[$x])/1048576/$progress[$x]*100,1), 1).' MB</td>'; }
	else {
		echo '<td>'.round(number_format(filesize($movie_files[$x])/1048576/$progress[$x]*100,1),-1).' MB</td>'; }
	// Display Log files
	echo '<td><a href="'.$movie_log[$x].'">Log</a></td>';
	echo '</tr>';
}


echo '</table>';
echo '<input type="submit" name="Kill/Del" value="Kill/Del">';
echo '</div>';
echo '</form>';
// Movie is killed first, then deleted if requested again
if(isset($_GET['Kill/Del'])) {
	$y=$_GET['movie_index'];
	if($pid[$y] > 0) {
		// kill process
		$arg = 'ps -o pid --no-headers --ppid '.$pid[$y]; 
		exec("$arg", $kill);	
		exec("kill $pid[$y]");	
		foreach($kill as $value) {
			exec("kill $value"); }
	}
	else {
		// deleting files
		unlink($movie_files[$y]);
		unlink($movie_txt[$y]);
		unlink($movie_log[$y]);
		unlink($movie_progress[$y]);
	}
	unset($_GET);
	unset($_REQUEST);
	$page = $_SERVER['PHP_SELF'];
	echo '<script type="text/javascript">';
	refresh_table();
	echo '</script>';	
}

} # end of function load_movies
echo '</body>';
echo '</html>';


// Function load_camera()
// Load cameras from zoneminder mysql database
// and return data to browser
function Load_Camera()
{
	$con=mysqli_connect(ZM_HOST,ZMUSER, ZMPASS, ZM_DB);
	if (mysqli_connect_errno()) {
		echo "Failed to connect to MySQL: " . mysqli_connect_error();
	}
	//
	// The query can be combined but works fine as is
	//
	$result = mysqli_query($con,"SELECT Id, Name, Width, Height from Monitors");
	while($row = mysqli_fetch_assoc($result)) {
		$mon_name[]=$row;
	}
	for($i = 0; $i < count($mon_name); $i++) {
		$j = $mon_name[$i]['Id'];
		$result = mysqli_query($con,"SELECT MonitorId, min(Starttime), max(Endtime) from Events where MonitorId=$j");
		while($row = mysqli_fetch_assoc($result)) {
			$mon_event[]=$row;
		}
	}
	// Don't like paranthesis in variables
	$i=0;
	foreach($mon_event as &$name) {
		$name['Id'] = $mon_name[$i]['Id'];
		$name['Name'] = $mon_name[$i]['Name'];
		$name['Size'] = $mon_name[$i]['Width'] . ':' . $mon_name[$i]['Height'];
		$name['Starttime'] = $name['min(Starttime)'];
		$name['Endtime'] = $name['max(Endtime)'];
		unset($name['min(Starttime)']);
		unset($name['max(Endtime)']);
		$i++;
 	}
	mysqli_close($con);
	return $mon_event;
}
//
// Function Make_Movie()
//
function Make_Movie($MonitorId,$Starttime,$Endtime,$Buffer,$Speed,$Frametype,$Codec,$Size,$Filename,$Profile,$Preset,$CRF)
{
// Parse filename extension
$Filename_base=basename($Filename, ".".pathinfo($Filename,PATHINFO_EXTENSION)); 
// open log file
	$path_target = PATH_TARGET;
	$zm_movie_log = fopen("$path_target/$Filename_base.log","w") or die(' Unable to open log file');
// Is ffmpeg or avconv installed
	exec("which avconv",$ffmpeg);
	if (empty($ffmpeg)) {
		exec("which ffmpeg",$ffmpeg);
		if (empty($ffmpeg)) {
			fwrite($zm_movie_log,"fmpeg or avconv not found!".PHP_EOL);
			die("ffmpeg or avconv not found!".PHP_EOL);
		}
	}
// Get DIR_EVENTS from database
// open database
	echo "Starting Movie";
	fwrite($zm_movie_log,"Starting Movie: $Filename Id:$MonitorId".PHP_EOL."Start:$Starttime".PHP_EOL."End:$Endtime".PHP_EOL."Buffer:$Buffer".PHP_EOL."Speed: $Speed".PHP_EOL."Frames:$Frametype".PHP_EOL."Codec:$Codec".PHP_EOL."Size:$Size".PHP_EOL."Profile: $Profile".PHP_EOL."Preset: $Preset".PHP_EOL."CRF: $CRF".PHP_EOL);
	$con=mysqli_connect(ZM_HOST,ZMUSER,ZMPASS,ZM_DB);
	if (mysqli_connect_errno()) {
	        echo "Failed to connect to MySQL: " . mysqli_connect_error();
		fwrite($zm_movie_log,"Failed to connect to MySQL".PHP_EOL);
	}
	// Get bulk frame interval needed to expand them (used later)
	$result = mysqli_query($con, "SELECT Value FROM Config WHERE Name = 'ZM_BULK_FRAME_INTERVAL'");
	while($row = mysqli_fetch_assoc($result)) {
	        $Bulk_frame_interval=end($row);
        }
	// Calculate FPS of Camera (required for IP cams). Done in SQL. Use first EventId. Assumed Constant.
	$result = mysqli_query($con, "SELECT round(Frames/Length) FROM Events WHERE MonitorId=$MonitorId AND StartTime>= '$Starttime' limit 1");
	while($row = mysqli_fetch_assoc($result)) {
	        $fps=end($row);
	}
	// Get significant digits for naming files 'EVENT_IMAGE_DIGITS'
	$result = mysqli_query($con, "SELECT Value FROM Config WHERE Name = 'ZM_EVENT_IMAGE_DIGITS'");
	while($row = mysqli_fetch_assoc($result)) {
	        $Event_image_digits=end($row);
        }
	// Store initial Starttime for naming video later
	$Video_start = $Starttime;
	// open file for dumping frames path to be used as input to mencoder (or ffmpeg)
	$list1 = fopen("$path_target/$Filename_base.txt","w") or die(' Unable to open tmp file');
	if ($Frametype == 'Alarm') {
		// Find all events with first/last timestamps of consecutive alarm frames between requested time for requested monitor
		$result = mysqli_query($con,"SELECT EventId, FrameId, Type, TimeStamp, StartTime, EndTime, MonitorId FROM Frames, Events WHERE Frames.Type='Alarm' AND Events.Id=Frames.EventId AND Frames.TimeStamp >= '$Starttime' AND Frames.Timestamp <= '$Endtime' AND MonitorId=$MonitorId");
		$FrameId_Before = 0;
		$FrameData_Before = array('EventId','FrameId','Type','TimeStamp','StartTime','EndTime','MonitorId');
		$FrameData_Before = array_fill_keys($FrameData_Before,'');
		$i=0;
		$j=0;
		$Alarm_Start = 0;
		while($row = mysqli_fetch_assoc($result)) {
		        $FrameId = intval($row['FrameId']);
		        $FrameData = $row;
		        if ( $FrameId_Before + 1 != $FrameId) {
		                $j=$i+1;
		                $Alarm_list[$i]['EndTime'] = $FrameData_Before['TimeStamp'];
		                $Alarm_list[$j]['StartTime'] = $FrameData['TimeStamp'];
		                $Alarm_list[$j]['PathTime'] = $FrameData['StartTime'];
		                $Alarm_list[$j]['FrameId'] = $FrameId;
		                $Alarm_list[$i]['EventId'] = $FrameData_Before['EventId'];
		                $Alarm_list[$i]['MonitorId'] = $FrameData_Before['MonitorId'];
		                $i++;}
		        $FrameId_Before = $FrameId;
		        $FrameData_Before = $FrameData;
		}
		// Dump last end alarm frame in array to complete the new array
		$Alarm_list[$i]['EndTime'] = $FrameData['TimeStamp'];
		$Alarm_list[$i]['EventId'] = $FrameData['EventId'];
		$Alarm_list[$i]['MonitorId'] = $FrameData['MonitorId'];
		// Debugging purposes
		// var_dump($Alarm_list);
		//
		// Iterate through each alarm and add buffer time before and after discrete alarm events
		for($i = 1; $i < count($Alarm_list); $i++) {
		        $NewEndtime = strtotime($Alarm_list[$i]['EndTime'])+$Buffer;
		        $NewStartTime = strtotime($Alarm_list[$i]['StartTime'])-$Buffer;
		        $Alarm_list[$i]['EndTime'] = date('Y-m-d H:i:s', $NewEndtime);
		        $Alarm_list[$i]['StartTime'] = date('Y-m-d H:i:s', $NewStartTime);
		}
		// Eliminate overlap of alarms (if applicable) by moving start time of next alarm event to end time of previous
		for($i = 1; $i < count($Alarm_list)-1; $i++) {
		        if ($Alarm_list[$i+1]['StartTime'] < $Alarm_list[$i]['EndTime']) {
		                $NewStarttime = strtotime($Alarm_list[$i]['EndTime'])+1;
		                $Alarm_list[$i+1]['StartTime'] = date('Y-m-d H:i:s', $NewStarttime);
		        }
		}
		fwrite($zm_movie_log,"Distinct alarm events found: ".count($Alarm_list).PHP_EOL);
		// Debugging purposes
		// var_dump($Alarm_list);
	}
	// If 'All' argument is used for generating the movie simply substitute initial time arguments for one big event (all frames between times)
	// Add a buffer to account for any bulk frames before/after requested time
	else if ($Frametype == 'All') {
		$Buffer=$Bulk_frame_interval/$fps;
		$NewEndtime = strtotime($Endtime)+$Buffer;
		$NewStartTime = strtotime($Starttime)-$Buffer;
		$Alarm_list[1]['EndTime'] = date('Y-m-d H:i:s', $NewEndtime);
		$Alarm_list[1]['StartTime'] = date('Y-m-d H:i:s', $NewStartTime);
		$Alarm_list[1]['MonitorId'] = $MonitorId;
	// Must set additional index for counter to work below
	        $Alarm_list[2]['StartTime'] = '';
	        $Alarm_list[2]['EndTime'] = '';
	        $Alarm_list[2]['MonitorId'] = '';
	}
	// Iterate through each alarm event and dump path and image name to input file for movie maker
	// For 'All' frames this is simply one event
	$last_row='';
	$last_row_type='';
	for($j = 1; $j < count($Alarm_list); $j++)
	{
	        $EventStartTime = $Alarm_list[$j]['StartTime'];
	        $EventEndTime = $Alarm_list[$j]['EndTime'];
	        $MonitorId = $Alarm_list[$j]['MonitorId'];
	        $result = mysqli_query($con,"SELECT EventId, FrameId, Type, StartTime, MonitorId FROM Frames, Events WHERE Events.Id=Frames.EventId AND Frames.TimeStamp >= '$EventStartTime' AND Frames.TimeStamp <= '$EventEndTime' AND MonitorId=$MonitorId");
	        while($row = mysqli_fetch_assoc($result))
	        {
	                $DP = date_parse($row['StartTime']);
	                $yy=substr($DP['year'],2);
	                $yy=sprintf("%02d",$yy);
	                $mm=sprintf("%02d",$DP['month']);
	                $dd=sprintf("%02d",$DP['day']);
	                $hh=sprintf("%02d",$DP['hour']);
	                $min=sprintf("%02d",$DP['minute']);
	                $ss=sprintf("%02d",$DP['second']);
		// Write frames to file
		// Expand bulk frames if present
		// Pad event name to match zoneminder setting
	                if ($row['FrameId'] - $last_row == 1)
	                {
				$FrameId = str_pad($row['FrameId'],$Event_image_digits,'0',STR_PAD_LEFT);
	                        $Event = PATH_EVENT."/".$MonitorId."/".$yy."/".$mm."/".$dd."/".$hh."/".$min."/".$ss."/".$FrameId."-capture.jpg".PHP_EOL;
	                        fwrite($list1,$Event);
	                }
	                else if (($row['FrameId'] - $last_row) > 1 AND ($row['FrameId'] - $last_row) <= $Bulk_frame_interval)
	                {
	                        for ($i=$last_row+1; $i <= $row['FrameId']; $i++)
	                        {
					$FrameId = str_pad($i,$Event_image_digits,'0',STR_PAD_LEFT);
				        $Event = PATH_EVENT."/".$MonitorId."/".$yy."/".$mm."/".$dd."/".$hh."/".$min."/".$ss."/".$FrameId."-capture.jpg".PHP_EOL;
	                                fwrite($list1,$Event);
	                        }
	                }
	                $last_row=$row['FrameId'];
	        }
	}
	fclose($list1);
	// Make movie. Option to use mencoder of ffmpeg (avconv)
	// Get Camera Name
	$result = mysqli_query($con, "SELECT Name FROM Monitors WHERE Id=$MonitorId");
	while($row = mysqli_fetch_assoc($result)) {
	        $Mon_Name=end($row);
	        }
	mysqli_close($con);
	// Calculate requested speed of movie
	$fps =$fps*$Speed;
	// set parameters
	$date1=explode(" ",$Video_start);
	$video_file=$Filename;
	$Extension=pathinfo($Filename);
	$Size=explode(":",$Size);
	$Width=$Size[0];
//
	$encoder_param = "x=0; while read CMD; do cat \$CMD; x=$((x+1)); echo \$x > ".PATH_TARGET."/".$Filename_base.".progress;  done < ".PATH_TARGET."/".$Filename_base.".txt | " .$ffmpeg[0] . " -r ".$fps." -f image2pipe -vcodec mjpeg -i - -profile:v ".$Profile." -preset:v ".$Preset." -threads 0 -crf ".$CRF. " -vf scale=" . $Width. ":-1 " .PATH_TARGET."/".$video_file." -y";

	fwrite($zm_movie_log,$encoder_param.PHP_EOL);
//
	if(filesize(PATH_TARGET."/".$Filename_base.".txt") > 0) {
		$pid=exec("($encoder_param) > /dev/null & echo $!");
		fwrite($zm_movie_log,"PID = ".$pid.PHP_EOL); }
	else {
		echo "No events found";
		fwrite($zm_movie_log,"No events found".PHP_EOL);
	}
	fwrite($zm_movie_log,"---------------------------------------".PHP_EOL);
	fclose($zm_movie_log);
	// Clear GET data and reload page to clear POST
	if(isset($_GET) || isset($_REQUEST)) {
		unset($_GET);
		unset($_REQUEST);
		header('Location: ' . $_SERVER['PHP_SELF']);
	}
}
if($_REQUEST['q'] == "refresh_table") {
        load_movies(); }
else {
        main(); 
	      load_movies();}
?>
