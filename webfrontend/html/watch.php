<?php
// LoxBerry Icon-Watchdog Plugin
// Christian Woerstenfeld - git@loxberry.woerstenfeld.de

// Header output
header('Content-Type: application/json; charset=utf-8');

// Calculate running time
$start =  microtime(true);	

// Include System Lib
require_once "loxberry_system.php";
require_once "loxberry_log.php";

$plugin_config_file 	= $lbpconfigdir."/Icon-Watchdog.cfg";        # Plugin config
$workdir_data			= $lbpdatadir."/workdir";                    # Working directory, on RAM-Disk by default due to $workdir_tmp
$workdir_tmp			= "/tmp/Icon-Watchdog";                      # The $workdir_data folder will be linked to this target
$savedir_path 			= $lbpdatadir."/images";                     # Directory to hold latest version of the images.zip
$zipdir_path 			= $lbpdatadir."/zip";                        # Directory to hold svg images to be in the zip

$minimum_free_workdir	= 134217728;                                 # In Bytes. Let minumum 128 MB free on workdir (RAMdisk in $workdir_tmp by default)
$watchstate_file		= "/tmp/Icon-Watchdog-state.txt";			 # State file on RAMdisk, do not change!
$cloud_requests_file	= "/tmp/cloudrequests.txt";       		 # Request file on RAMdisk, do not change!
$logfileprefix			= LBPLOGDIR."/Icon-Watchdog_Watcher_";
$logfilesuffix			= ".txt";
$logfilename			= $logfileprefix.date("Y-m-d_H\hi\ms\s",time()).$logfilesuffix;
$L						= LBSystem::readlanguage("language.ini");
$logfiles_to_keep		= 10;									     # Number of logfiles to keep (also done by LoxBerry Core /sbin/log_maint.pl)
$resultarray 			= array();

#Prevent blocking / Recreate state file if missing or older than 60 min
touch($watchstate_file);
if ( is_file($watchstate_file) ) 
{
	if ( ( time() - filemtime( $watchstate_file ) ) > (60 * 60) ) 
	{
		file_put_contents($watchstate_file, "");
	}
}
else
{
	file_put_contents($watchstate_file, "");
}

if ( ! is_file($watchstate_file) ) debug(__line__,$L["ERRORS.ERR_014_PROBLEM_WITH_STATE_FILE"],3);

$params = [
    "name" => $L["LOGGING.LOG_001_LOGFILE_NAME"],
    "filename" => $logfilename,
    "addtime" => 1];

$log = LBLog::newLog ($params);
$date_time_format       = "m-d-Y h:i:s a";						 # Default Date/Time format
if (isset($L["GENERAL.DATE_TIME_FORMAT_PHP"])) $date_time_format = $L["GENERAL.DATE_TIME_FORMAT_PHP"];
LOGSTART ($L["LOGGING.LOG_002_CHECK_STARTED"]);
$log->LOGTITLE($L["LOGGING.LOG_002_CHECK_STARTED"]);

// Error Reporting 
error_reporting(E_ALL);     
ini_set("display_errors", false);         //todo
ini_set("log_errors", 1);
$summary			= array();
$at_least_one_error	= 0;
$at_least_one_warning = 0;
function debug($line,$message = "", $loglevel = 7)
{
	global $L, $plugindata, $summary, $miniserver,$msno,$plugin_cfg,$at_least_one_error,$at_least_one_warning,$logfilename;
	if ( $plugindata['PLUGINDB_LOGLEVEL'] >= intval($loglevel) )  
	{
		$message = preg_replace('/["]/','',$message); // Remove quotes => https://github.com/mschlenstedt/Loxberry/issues/655
		$raw_message = $message;
		if ( $plugindata['PLUGINDB_LOGLEVEL'] >= 6 && $L["ERRORS.LINE"] != "" ) $message .= " ".$L["ERRORS.LINE"]." ".$line;
		if ( isset($message) && $message != "" ) 
		{
			switch ($loglevel)
			{
			    case 0:
			        // OFF
			        break;
			    case 1:
			    	$message = "<ALERT>".$message;
			        LOGALERT  (         $message);
					array_push($summary,$message);
			        break;
			    case 2:
			    	$message = "<CRITICAL>".$message;
			        LOGCRIT   (         $message);
					array_push($summary,$message);
			        break;
			    case 3:
			    	$message = "<ERROR>".$message;
			        LOGERR    (         $message);
					array_push($summary,$message);
			        break;
			    case 4:
			    	$message = "<WARNING>".$message;
			        LOGWARN   (         $message);
					array_push($summary,$message);
			        break;
			    case 5:
			    	$message = "<OK>".$message;
			        LOGOK     (         $message);
			        break;
			    case 6:
			    	$message = "<INFO>".$message;
			        LOGINF   (         $message);
			        break;
			    case 7:
			    default:
			    	$message = $message;
			        LOGDEB   (         $message);
			        break;
			}
			if ( isset($msno) )
			{
				$msi = "MS#".$msno." ".$miniserver['Name'];
			}
			else
			{
				$msi = "";
			}
			if ( $loglevel == 4 ) 
			{
				$at_least_one_warning = 1;
				$search  = array('<WARNING>');
				$replace = array($L["LOGGING.NOTIFY_LOGLEVEL4"]);
				$notification = array (
				"PACKAGE" => LBPPLUGINDIR,
				"NAME" => $L['GENERAL.MY_NAME']." ".$msi,
				"MESSAGE" => str_replace($search, $replace, $raw_message),
				"SEVERITY" => 4,
				"LOGFILE"	=> $logfilename);
				if ( $plugin_cfg["IWD_USE_NOTIFY"] == "on" || $plugin_cfg["IWD_USE_NOTIFY"] == "1" ) notify_ext ($notification);
				return;
			}
			if ( $loglevel <= 3 ) 
			{
				$at_least_one_error = 1;
				$search  = array('<ALERT>', '<CRITICAL>', '<ERROR>','<WARNING>');
				$replace = array($L["LOGGING.NOTIFY_LOGLEVEL1"],$L["LOGGING.NOTIFY_LOGLEVEL2"],$L["LOGGING.NOTIFY_LOGLEVEL3"],$L["LOGGING.NOTIFY_LOGLEVEL4"]);
				$notification = array (
				"PACKAGE" => LBPPLUGINDIR,
				"NAME" => $L['GENERAL.MY_NAME']." ".$msi,
				"MESSAGE" => str_replace($search, $replace, $raw_message),
				"SEVERITY" => 3,
				"LOGFILE"	=> $logfilename);
				if ( $plugin_cfg["IWD_USE_NOTIFY"] == "on" ||$plugin_cfg["IWD_USE_NOTIFY"] == "1" ) notify_ext ($notification);
				return;
			}
		}
	}
	return;
}

// Plugindata
$plugindata = LBSystem::plugindata();

// Plugin version
debug(__line__,"Version: ".LBSystem::pluginversion(),6);

if ($plugindata['PLUGINDB_LOGLEVEL'] > 5 && $plugindata['PLUGINDB_LOGLEVEL'] <= 7) debug(__line__,$L["Icon-Watchdog.INF_LOGLEVEL_WARNING"]." ".$L["LOGGING.LOGLEVEL".$plugindata['PLUGINDB_LOGLEVEL']]." (".$plugindata['PLUGINDB_LOGLEVEL'].")",4);

$plugin_cfg_handle = @fopen($plugin_config_file, "r");
if ($plugin_cfg_handle)
{
  while (!feof($plugin_cfg_handle))
  {
    $line_of_text = fgets($plugin_cfg_handle);
    if (strlen($line_of_text) > 3)
    {
      $config_line = explode('=', $line_of_text);
      if ($config_line[0])
      {
      	if (!isset($config_line[1])) $config_line[1] = "";
        
        if ( $config_line[1] != "" )
        {
	        $plugin_cfg[$config_line[0]]=preg_replace('/\r?\n|\r/','', str_ireplace('"','',$config_line[1]));
    	    debug(__line__,$L["LOGGING.LOG_009_CONFIG_PARAM"]." ".$config_line[0]."=".$plugin_cfg[$config_line[0]]);
    	}
      }
    }
  }
  fclose($plugin_cfg_handle);
}
else
{
  debug(__line__,$L["ERRORS.ERR_002_ERROR_READING_CFG"],4);
  $default_config  = "[IWD]\r\n";
  $default_config .= "CLOUDDNS=dns.loxonecloud.com";
  $default_config .= "VERSION=$version\r\n";
  $default_config .= "IWD_USE=off\r\n";
  $default_config .= "IWD_USE_NOTIFY=off\r\n";
  $default_config .= "WORKDIR_PATH=/tmp/Icon-Watchdog\r\n";
  file_put_contents($plugin_config_file,$default_config);
  debug(__line__,$L["LOGGING.LOG_016_CREATE_CONFIG_OK"],5);
}

# Check if Plugin is disabled
if ( $plugin_cfg["IWD_USE"] == "on" || $plugin_cfg["IWD_USE"] == "1" )
{
    // Warning if Loglevel > 5 (OK)
	debug(__line__,$L["LOGGING.LOG_010_PLUGIN_ENABLED"],5);
}
else
{
	$runtime = microtime(true) - $start;
	sleep(3); // To prevent misdetection in createmsbackup.pl
	$log->LOGTITLE($L["LOGGING.LOG_011_PLUGIN_DISABLED"]);
	LOGINF ($L["LOGGING.LOG_011_PLUGIN_DISABLED"]);
	$result["ms"] = array("success" => false,"error" => $L["LOGGING.LOG_011_PLUGIN_DISABLED"],"errorcode" => "LOG_011_PLUGIN_DISABLED");
	echo json_encode($result, JSON_UNESCAPED_SLASHES);
	LOGEND ("");
	exit(1);
}

// Read language info
debug(__line__,count($L)." ".$L["LOGGING.LOG_003_NB_LANGUAGE_STRINGS_READ"],6);

// Logfile-Check
$logfiles = glob($logfileprefix."*".$logfilesuffix, GLOB_NOSORT);
if ( count($logfiles) > $logfiles_to_keep )
{
	usort($logfiles,"sort_by_mtime");
	$log_keeps = $logfiles;
	$log_keeps = array_slice($log_keeps, 0 - $logfiles_to_keep, $logfiles_to_keep);			
	debug(__line__,str_ireplace("<number>",$logfiles_to_keep,$L["LOGGING.LOG_004_LOGFILE_CHECK"]),6);

	foreach($log_keeps as $log_keep) 
	{
		debug(__line__," -> ".$L["LOGGING.LOG_005_LOGFILE_KEEP"]." ".$log_keep,7);
	}
	unset($log_keeps);
	
	if ( count($logfiles) > $logfiles_to_keep )
	{
		$log_deletions = array_slice($logfiles, 0, count($logfiles) - $logfiles_to_keep);
	
		foreach($log_deletions as $log_to_delete) 
		{
			debug(__line__," -> ".$L["LOGGING.LOG_006_LOGFILE_DELETE"]." ".$log_to_delete,6);
			unlink($log_to_delete);
		}
		unset($log_deletions);
	}
}

if ( is_file($watchstate_file) )
{
	if ( file_get_contents($watchstate_file) != "" )
	{
		debug(__line__,$L["ERRORS.ERR_001_CHK_RUNNING"],6);
		sleep(3);
		$log->LOGTITLE($L["ERRORS.ERR_001_CHK_RUNNING"]);
		LOGINF ($L["ERRORS.ERR_001_CHK_RUNNING"]);
		$result["ms"] = array("success" => false,"error" => $L["ERRORS.ERR_001_CHK_RUNNING"],"errorcode" => "ERR_001_CHK_RUNNING");
		echo json_encode($result, JSON_UNESCAPED_SLASHES);
		LOGEND ("");
		exit(1);
	}
}

// Read Miniservers
debug(__line__,$L["LOGGING.LOG_007_READ_MINISERVERS"]);

# If no miniservers are defined, return NULL
$miniservers = LBSystem::get_miniservers();
if (!$miniservers ) 
{
	debug(__line__,$L["ERRORS.ERR_003_NO_MINISERVERS_CONFIGURED"],3);
	$runtime = microtime(true) - $start;
	sleep(3); // To prevent misdetection 
	file_put_contents($watchstate_file, "");
	$log->LOGTITLE($L["ERRORS.ERR_004_DOWNLOAD_ABORTED_WITH_ERROR"]);
	LOGERR ($L["ERRORS.ERR_000_EXIT"]." ".$runtime." s");
	$result["ms"] = array("success" => false,"error" => $L["ERRORS.ERR_003_NO_MINISERVERS_CONFIGURED"],"errorcode" => "ERR_003_NO_MINISERVERS_CONFIGURED");
	echo json_encode($result, JSON_UNESCAPED_SLASHES);
	LOGEND ("");
	exit(1);
}

$ms = $miniservers;
if (!is_array($ms)) 
{
	debug(__line__,$L["ERRORS.ERR_003_NO_MINISERVERS_CONFIGURED"],3);
	$runtime = microtime(true) - $start;
	sleep(3); // To prevent misdetection 
	file_put_contents($watchstate_file, "");
	$log->LOGTITLE($L["ERRORS.ERR_004_DOWNLOAD_ABORTED_WITH_ERROR"]);
	LOGERR ($L["ERRORS.ERR_000_EXIT"]." ".$runtime." s");
	$result["ms"] = array("success" => false,"error" => $L["ERRORS.ERR_003_NO_MINISERVERS_CONFIGURED"],"errorcode" => "ERR_003_NO_MINISERVERS_CONFIGURED");
	echo json_encode($result, JSON_UNESCAPED_SLASHES);
	LOGEND ("");
	exit(1);
}
else
{
	debug(__line__,count($ms)." ".$L["LOGGING.LOG_008_MINISERVERS_FOUND"],5);
}

// Init Array for files to save
$curl = curl_init() or debug(__line__,$L["ERRORS.ERR_019_ERROR_INIT_CURL"],3);
curl_setopt($curl, CURLOPT_RETURNTRANSFER	, true);
curl_setopt($curl, CURLOPT_HTTPAUTH			, constant("CURLAUTH_ANY"));
curl_setopt($curl, CURLOPT_CUSTOMREQUEST	, "GET");
curl_setopt($curl, CURLOPT_TIMEOUT			, 600);
curl_setopt($curl, CURLOPT_SSL_VERIFYPEER	, 0);
curl_setopt($curl, CURLOPT_SSL_VERIFYSTATUS	, 0);
curl_setopt($curl, CURLOPT_SSL_VERIFYHOST   , 0);

// Process all miniservers
set_time_limit(0);

$at_least_one_save = 0;
$saved_ms=array();
$problematic_ms=array();
array_push($summary,"<HR> ");
ksort($ms);
$connection_data_returncode0 = 0;
$randomsleep = 1;
$known_for_today = 0;
$all_cloudrequests = 0;
for ( $msno = 1; $msno <= count($ms); $msno++ ) 
{
	$miniserver = $ms[$msno];
	$prefix = ($miniserver['PreferHttps'] == 1) ? "https://":"http://";
	$port   = ($miniserver['PreferHttps'] == 1) ? $miniserver['PortHttps']:$miniserver['Port'];
	$log->LOGTITLE($L["Icon-Watchdog.INF_0001_DOWNLOAD_STARTED_MS"]." #".$msno." (".$miniserver['Name'].")");
	if (isset($argv[2])) 
	{
		if ( intval($argv[2]) == $msno )
		{
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0002_MANUAL_DOWNLOAD_SINGLE_MS"]." ".$msno."/".count($ms)." => ".$miniserver['Name'],5);
		}
		else if ( intval($argv[2]) == 0)
		{
			// No single manual save
		}
		else
		{
			// Single manual save but not the MS we want
			continue;	
		}
	}
	if (isset($_REQUEST['ms'])) 
	{
		if ( preg_replace('/[^0-9]/', '', $_REQUEST['ms']) == $msno )
		{
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0002_MANUAL_DOWNLOAD_SINGLE_MS"]." ".$msno."/".count($ms)." => ".$miniserver['Name'],5);
		}
		else if ( preg_replace('/[^0-9]/', '', $_REQUEST['ms']) == 0)
		{
			// No single manual save
		}
		else
		{
			// Single manual save but not the MS we want
			continue;	
		}
	}

	file_put_contents($watchstate_file,str_ireplace("<MS>",$msno." (".$miniserver['Name'].")",$L["Icon-Watchdog.INF_0003_STATE_RUN"]));
    debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0004_PROCESSING_MINISERVER"]." ".$msno."/".count($ms)." => ".$miniserver['Name'],5);
	$filetree["name"] 		= array();
	$filetree["size"] 		= array();
	$filetree["time"] 		= array();
	$save_ok_list["name"] 	= array();
	$save_ok_list["size"] 	= array();
	$save_ok_list["time"] 	= array();
	$percent_done 			= "100";
	$percent_displ 			= "";
	$finalstorage			= $lbpdatadir."/ms_$msno";
	$backups_to_keep		= 7;
	$ms_subdir				= "";
	$ms_monitor				= 0;
	if ( isset($plugin_cfg["MS_SUBDIR".$msno]) ) $ms_subdir	= "/".$plugin_cfg["MS_SUBDIR".$msno];
	if ( isset($plugin_cfg["MS_MONITOR_CB".$msno]) ) $ms_monitor = $plugin_cfg["MS_MONITOR_CB".$msno];
	//$bkpfolder 				= str_pad($msno,3,0,STR_PAD_LEFT)."_".$miniserver['Name'];
	$bkpfolder = "ms_".$msno;
	
	$last_save 				= "";
	#Manual Backup Button on Admin page
	$manual_check = 0;
	if (isset($argv[1])) 
	{
		if ( $argv[1] == "manual" )
		{
			$manual_check = 1;
		}
	}
		if ( $ms_monitor === "1" )
		{
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0005_MS_MONITORING_ENABLED"],5);
		}
		else
		{
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0006_MS_MONITORING_DISABLED"],5);
			continue;
		}
	
	//$workdir_tmp = $plugin_cfg["WORKDIR_PATH"];

	debug(__line__,$L["Icon-Watchdog.INF_0008_CLEAN_WORKDIR_TMP"]." ".$workdir_tmp);
	create_clean_workdir_tmp($workdir_tmp);
	if (!realpath($workdir_tmp)) 
	{
		debug(__line__,$L["ERRORS.ERR_021_PROBLEM_WITH_WORKDIR"],3);
		$runtime = microtime(true) - $start;
		sleep(3); // To prevent misdetection in createmsbackup.pl
		file_put_contents($watchstate_file, "");
		$log->LOGTITLE($L["ERRORS.ERR_004_DOWNLOAD_ABORTED_WITH_ERROR"]);
		LOGERR ($L["ERRORS.ERR_000_EXIT"]." ".$runtime." s");
		$result["ms".$msno] = array("success" => false,"error" => $L["ERRORS.ERR_021_PROBLEM_WITH_WORKDIR"],"errorcode" => "ERR_021_PROBLEM_WITH_WORKDIR");
		echo json_encode($result, JSON_UNESCAPED_SLASHES);
		LOGEND ("");
		exit(1);
	}
	
	debug(__line__,$L["Icon-Watchdog.INF_0009_DEBUG_DIR_FILE_LINK_EXISTS"]." -> ".$workdir_data);
	if ( is_file($workdir_data) || is_dir($workdir_data) || is_link( $workdir_data ) )
	{
		debug(__line__,$L["Icon-Watchdog.INF_0010_DEBUG_YES"]." -> ".$L["Icon-Watchdog.INF_0012_DEBUG_IS_LINK"]." -> ".$workdir_data);
		if ( is_link( $workdir_data ) )
		{
			debug(__line__,$L["Icon-Watchdog.INF_0010_DEBUG_YES"]." -> ".$L["Icon-Watchdog.INF_0015_DEBUG_CORRECT_TARGET"]." -> ".$workdir_data." => ".$workdir_tmp);
			if ( readlink($workdir_data) == $workdir_tmp )
			{
				debug(__line__,$L["Icon-Watchdog.INF_0016_WORKDIR_IS_SYMLINK"]); 
				# Everything in place => ok!
			}
			else
			{
				debug(__line__,$L["Icon-Watchdog.INF_0011_DEBUG_NO"]." -> ".$L["Icon-Watchdog.INF_0017_DEBUG_DELETE_SYMLINK"]." -> ".$workdir_data);
				unlink($workdir_data);
				debug(__line__,$L["Icon-Watchdog.INF_0018_DEBUG_CREATE_SYMLINK"]." -> ".$workdir_data ."=>".$workdir_tmp);
				symlink ($workdir_tmp, $workdir_data);
			}
		}
		else
		{
			debug(__line__,$L["Icon-Watchdog.INF_0011_DEBUG_NO"]." -> ".$L["Icon-Watchdog.INF_0014_DEBUG_IS_DIR"]." -> ".$workdir_data);
			if (is_dir($workdir_data))
			{
				debug(__line__,$L["Icon-Watchdog.INF_0010_DEBUG_YES"]." -> ".$L["Icon-Watchdog.INF_0019_DEBUG_DIRECTORY_DELETE"]." -> ".$workdir_data);
				rrmdir($workdir_data);			
			}
			else
			{
				debug(__line__,$L["Icon-Watchdog.INF_0011_DEBUG_NO"]." -> ".$L["Icon-Watchdog.INF_0013_DEBUG_IS_FILE"]." -> ".$workdir_data);
				if (is_file($workdir_data))
				{
					debug(__line__,$L["Icon-Watchdog.INF_0010_DEBUG_YES"]." -> ".$L["Icon-Watchdog.INF_0020_DEBUG_DELETE_FILE"]." -> ".$workdir_data);
					unlink($workdir_data);
				}
				else
				{
					debug(__line__,"Oh no! You should never read this",2);
				}
			}
			debug(__line__,$L["Icon-Watchdog.INF_0018_DEBUG_CREATE_SYMLINK"]." -> ".$workdir_data ."=>".$workdir_tmp);
			symlink($workdir_tmp, $workdir_data);
		}
	} 
	else
	{
		debug(__line__,$L["Icon-Watchdog.INF_0011_DEBUG_NO"]." -> ".$L["Icon-Watchdog.INF_0018_DEBUG_CREATE_SYMLINK"]." -> ".$workdir_data ."=>".$workdir_tmp);
		symlink($workdir_tmp, $workdir_data);
	} 
	if (readlink($workdir_data) == $workdir_tmp)
	{
		chmod($workdir_tmp	, 0777);
		chmod($workdir_data	, 0777);
		debug(__line__,$L["Icon-Watchdog.INF_0021_SET_WORKDIR_AS_SYMLINK"]." (".$workdir_data.")",6); 
	}
	else
	{
		debug(__line__,$L["ERRORS.ERR_022_CANNOT_SET_WORKDIR_AS_SYMLINK"],3);
		$runtime = microtime(true) - $start;
		sleep(3); // To prevent misdetection in createmsbackup.pl
		file_put_contents($watchstate_file, "");
		$log->LOGTITLE($L["ERRORS.ERR_004_DOWNLOAD_ABORTED_WITH_ERROR"]);
		LOGERR ($L["ERRORS.ERR_000_EXIT"]." ".$runtime." s");
		$result["ms".$msno] = array("success" => false,"error" => $L["ERRORS.ERR_022_CANNOT_SET_WORKDIR_AS_SYMLINK"],"errorcode" => "ERR_022_CANNOT_SET_WORKDIR_AS_SYMLINK");
		echo json_encode($result, JSON_UNESCAPED_SLASHES);
		LOGEND ("");
		exit(1);
	}
	// Define and create save directories base folder
	if (is_file($savedir_path))
	{
		debug(__line__,$L["Icon-Watchdog.INF_0013_DEBUG_IS_FILE"]." -> ".$L["Icon-Watchdog.INF_0010_DEBUG_YES"]." -> ".$L["Icon-Watchdog.INF_0020_DEBUG_DELETE_FILE"]." -> ".$savedir_path);
		unlink($savedir_path);
	}
	if (!is_dir($savedir_path))
	{
		debug(__line__,$L["Icon-Watchdog.INF_0014_DEBUG_IS_DIR"]." -> ".$L["Icon-Watchdog.INF_0011_DEBUG_NO"]." -> ".$L["Icon-Watchdog.INF_0022_DEBUG_DIRECTORY_CREATE"]." -> ".$savedir_path);
		$resultarray = array();
		@exec("mkdir -v -p ".$savedir_path." 2>&1",$resultarray,$retval);
	}
	if (!is_dir($savedir_path))
	{
		debug(__line__,$L["ERRORS.ERR_023_CREATE_DOWNLOAD_BASE_FOLDER"]." ".$savedir_path." (".join(" ",$resultarray).")",3); 
		$runtime = microtime(true) - $start;
		sleep(3); // To prevent misdetection in createmsbackup.pl
		file_put_contents($watchstate_file, "");
		$log->LOGTITLE($L["ERRORS.ERR_004_DOWNLOAD_ABORTED_WITH_ERROR"]);
		LOGERR ($L["ERRORS.ERR_000_EXIT"]." ".$runtime." s");
		$result["ms".$msno] = array("success" => false,"error" => $L["ERRORS.ERR_023_CREATE_DOWNLOAD_BASE_FOLDER"],"errorcode" => "ERR_023_CREATE_DOWNLOAD_BASE_FOLDER");
		echo json_encode($result, JSON_UNESCAPED_SLASHES);
		LOGEND ("");
		exit(1);
	}
	debug(__line__,$L["Icon-Watchdog.INF_0023_DOWNLOAD_FOLDER_OK"]." (".$savedir_path.")",6); 

	//Check Connection in case of Cloud DNS
	if ( $miniserver['UseCloudDNS'] == "on" || $miniserver['UseCloudDNS'] == "1" ) 
	{
		//Check for earlier Cloud DNS requests on RAM Disk
		touch($cloud_requests_file); // Touch file to prevent errors if inexistent
		$checkurl = "http://".$plugin_cfg["CLOUDDNS"]."/?getip&snr=".$miniserver['CloudURL']."&json=true";
		$max_accepted_dns_errors 	= 10;
		$dns_errors 				= 0;
		do 
		{
			sleep(1);
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0024_GET_CLOUD_CONNECTION_DATA"]." => ".$miniserver['Name']);
			$connection_data_returncode = get_connection_data($checkurl);
			/* Possible connection_data_error codes:
				0 = ok
				1 = Other error, retry
				2 = Too many errors, stop retrying
				3 = Too much errors for today
				4 = Port not open / Remote connect disabled
				5 = Error init cURL, stop retrying
				6 = Error 403/405, stop retrying
			*/
			if ( $connection_data_returncode == 1)
			{
				// Code 1 will repeat the loop until $max_accepted_dns_errors is reached
				// 1 = Other error, retry
				$dns_errors++;
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0025_CLOUD_DNS_FAIL"]." (#$dns_errors/$max_accepted_dns_errors)",6);
			}
			if ( $dns_errors > $max_accepted_dns_errors ) 
			{
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_024_TOO_MANY_CLOUD_DNS_FAILS"]." (#$dns_errors/$max_accepted_dns_errors) ".$miniserver['Name']." ".curl_error($curl),3);
				$connection_data_returncode = 2; 
			}
		} while ($connection_data_returncode == 1);
		
		if ( $connection_data_returncode >= 2 ) 
		{
			create_clean_workdir_tmp($workdir_tmp);
			file_put_contents($watchstate_file,"");
			array_push($summary,"<HR> ");
			if ( $connection_data_returncode != 3 ) array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
			continue;
		}
	}
	else
	{
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0026_CLOUD_DNS_NOT_USED"]." => ".$miniserver['Name']." @ ".$miniserver['IPAddress'],5);
	}

	if ( $miniserver['IPAddress'] == "" ) 
	{
		debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_025_MS_CONFIG_NO_IP"],3);
		array_push($summary,"<HR> ");
		array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
		curl_close($curl_dns);
		$connection_data_returncode = 1;
		return $connection_data_returncode;
	}
	else
	{
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0028_MS_IP_HOST_PORT"]."=".$miniserver['IPAddress'].":".$port,6);
	}

	curl_setopt($curl, CURLOPT_USERPWD, $miniserver['Credentials_RAW']);
	$url = $prefix.$miniserver['IPAddress'].":".$port."/dev/cfg/ip";
	curl_setopt($curl, CURLOPT_URL, $url);
	sleep(5);
	if(curl_exec($curl) === false)
	{
		debug(__line__,"MS#".$msno." ".$url);
		debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_026_ERROR_READ_LOCAL_MS_IP"]." ".$miniserver['Name']." ".curl_error($curl),3);
		create_clean_workdir_tmp($workdir_tmp);
		file_put_contents($watchstate_file,"");
		array_push($summary,"<HR> ");
		array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
		continue;
	}	
	else
	{   $local_ip = [];
		$read_line= curl_multi_getcontent($curl) or $read_line = ""; 
		if(preg_match("/.*dev\/cfg\/ip.*value.*\"(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\".*$/i", $read_line, $local_ip))
		{
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0029_LOCAL_MS_IP"]." ".$local_ip[1],6);
		}
		else
		{
			debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_026_ERROR_READ_LOCAL_MS_IP"]." ".$url." => ".nl2br(htmlentities($read_line)),3);
			create_clean_workdir_tmp($workdir_tmp);
			file_put_contents($watchstate_file,"");
			array_push($summary,"<HR> ");
			array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
			continue;
		}
	}
	
	$url = $prefix.$miniserver['IPAddress'].":".$port."/dev/cfg/version";
	curl_setopt($curl, CURLOPT_URL, $url);
	if(curl_exec($curl) === false)
	{
		debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_027_ERROR_READ_LOCAL_MS_VERSION"]." ".curl_error($curl),3);
		create_clean_workdir_tmp($workdir_tmp);
		file_put_contents($watchstate_file,"");
		array_push($summary,"<HR> ");
		array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
		continue;
	}	
	else
	{ 
		$read_line= curl_multi_getcontent($curl) or $read_line = ""; 
		if(preg_match("/.*dev\/cfg\/version.*value.*\"(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})\".*$/i", $read_line, $ms_version))
		{
			$ms_version_dir = str_pad($ms_version[1],2,0,STR_PAD_LEFT).str_pad($ms_version[2],2,0,STR_PAD_LEFT).str_pad($ms_version[3],2,0,STR_PAD_LEFT).str_pad($ms_version[4],2,0,STR_PAD_LEFT);
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0030_LOCAL_MS_VERSION"]." ".$ms_version[1].".".$ms_version[2].".".$ms_version[3].".".$ms_version[4]." => ".$ms_version_dir,6);
		}
		else
		{
			debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_027_ERROR_READ_LOCAL_MS_VERSION"]." ".curl_error($curl),3);
			create_clean_workdir_tmp($workdir_tmp);
			file_put_contents($watchstate_file,"");
			array_push($summary,"<HR> ");
			array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
			continue;
		}
	}

	//$bkpdir 	= $backup_file_prefix.trim($local_ip[1])."_".date("YmdHis",time())."_".$ms_version_dir;
	//debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0031_CREATE_DOWNLOADFOLDER"]." ".$bkpdir." + ".$bkpfolder,6);
	$bkpdir     ="";
    // Set root dir to /web/ and read it
	$folder = "/web/";
	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0033_READ_DIRECTORIES_AND_FILES"]." ".$folder,6);
	$filetree = read_ms_tree($folder);

	$full_backup_size = array_sum($filetree["size"]);
	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0034_BUILDING_FILELIST_COMPLETED"]." ".count($filetree["name"]),6);

	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0047_COMPARING_FILES"],6);
	if (!is_dir($savedir_path."/".$bkpfolder))
	{
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0014_DEBUG_IS_DIR"]." -> ".$L["Icon-Watchdog.INF_0011_DEBUG_NO"]." -> ".$L["Icon-Watchdog.INF_0022_DEBUG_DIRECTORY_CREATE"]." -> ".$savedir_path."/".$bkpfolder);
		$resultarray = array();
		@exec("mkdir -v -p ".$savedir_path."/".$bkpfolder." 2>&1",$resultarray,$retval);
	}
	if (!is_dir($savedir_path."/".$bkpfolder))
	{
		debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0024_CREATE_BACKUP_SUB_FOLDER"]." ".$savedir_path."/".$bkpfolder." (".join(" ",$resultarray).")",3); 
		create_clean_workdir_tmp($workdir_tmp);
		file_put_contents($watchstate_file,"");
		array_push($summary,"<HR> ");
		array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
		continue;
	}

	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0023_DOWNLOAD_FOLDER_OK"]." (".$savedir_path."/".$bkpfolder.")"); 
	$filestosave = 0;	
	foreach (getDirContents($savedir_path."/".$bkpfolder) as &$file_on_disk) 
	{
		$short_name = str_replace($savedir_path."/".$bkpfolder, '', $file_on_disk);
		
		if ($short_name != "" ) 
		{
			$key_in_filetree = array_search($short_name,$filetree["name"],true);
		}
		else
		{
			$key_in_filetree = false;
		}
		if ( !($key_in_filetree === false) )
		{
			if ( $filetree["size"][$key_in_filetree] == filesize($file_on_disk) && $filetree["time"][$key_in_filetree] == filemtime($file_on_disk) )
			{
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0044_COMPARE_FOUND_REMOVE_FROM_LIST"]." (".$short_name.")",6); 
				unset($filetree["name"][$key_in_filetree]);
		    	unset($filetree["size"][$key_in_filetree]);
		    	unset($filetree["time"][$key_in_filetree]);
				$filestosave++;	
			}
			else
			{
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0045_COMPARE_FOUND_DIFFER_KEEP_LIST"]." (".$short_name.")\nMS <=> LB ".$filetree["name"][$key_in_filetree]." <=> ".$short_name."\nMS <=> LB ".$filetree["size"][$key_in_filetree]." <=> ".filesize($file_on_disk)." Bytes \nMS <=> LB ".date("M d H:i",$filetree["time"][$key_in_filetree])." <=> ".date("M d H:i",filemtime($file_on_disk)),6);
				//unlink($file_on_disk);
				$filestosave++;	
			}
		}
		else
		{
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_030_COMPARE_NOT_ON_MS_ANYMORE"]." (".$short_name.") ".filesize($file_on_disk)." Bytes [".filemtime($file_on_disk)."]",1);
				unlink($file_on_disk);
		}
	}

	$estimated_size = array_sum($filetree["size"])/1024;
	if (is_link($workdir_tmp) )
	{
		$workdir_space   = disk_free_space(readlink($workdir_tmp))/1024;
	}
	else
	{
		$workdir_space   = disk_free_space($workdir_tmp)/1024;
	}	
	$free_space		= ($workdir_space - $estimated_size);
	debug(__line__,"MS#".$msno." ".str_ireplace("<free_space>",round($free_space,1),str_ireplace("<workdirbytes>",round($workdir_space,1),str_ireplace("<downloadsize>",round($estimated_size,1),$L["Icon-Watchdog.INF_0036_CHECK_FREE_SPACE_IN_WORKDIR"]))),5);
	if ( $free_space < $minimum_free_workdir/1024 )
	{
		debug(__line__,"MS#".$msno." ".str_ireplace("<free_space>",round($free_space,1),str_ireplace("<workdirbytes>",round($workdir_space,1),str_ireplace("<downloadsize>",round($estimated_size,1),$L["ERRORS.ERR_028_NOT_ENOUGH_FREE_SPACE_IN_WORKDIR"]))),2);
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0008_CLEAN_WORKDIR_TMP"]." ".$workdir_tmp);
		create_clean_workdir_tmp($workdir_tmp);
		file_put_contents($watchstate_file,"");
		array_push($summary,"<HR> ");
		array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
		continue;
	}
	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0034_BUILDING_FILELIST_COMPLETED"]." ".count($filetree["name"]),6);
	
	$curl_save = curl_init();

	if ( !$curl_save )
	{
		debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0002_ERROR_INIT_CURL"],3);
		create_clean_workdir_tmp($workdir_tmp);
		file_put_contents($watchstate_file,"");
		array_push($summary,"<HR> ");
		array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
		continue;
	}
	curl_setopt($curl_save, CURLOPT_HTTPAUTH, constant("CURLAUTH_ANY"));

	$crit_issue=0;
	if ( count($filetree["name"]) > 0 )
	{
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0053_START_DOWNLOAD"],5);
		// Calculate download time
		$start_dwl =  microtime(true);	
 		foreach( $filetree["name"] as $k=>$file_to_save)
		{
			$path = dirname($file_to_save);
			if (!is_dir($workdir_tmp."/".$bkpfolder.$path))
			{
				$resultarray = array();
				@exec("mkdir -v -p ".$workdir_tmp."/".$bkpfolder.$path." 2>&1",$resultarray,$retval);
			}
			if (!is_dir($workdir_tmp."/".$bkpfolder.$path)) 
			{
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0007_PROBLEM_CREATING_BACKUP_DIR"]." ".$workdir_tmp."/".$bkpfolder.$path." (".join(" ",$resultarray).")",3);
				$crit_issue=1;
				break;
			}
			$fp = fopen ($workdir_tmp."/".$bkpfolder.$file_to_save, 'w+');
			
			if (!isset($fp))
			{
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_032_PROBLEM_CREATING_FILE"]." ".$workdir_tmp."/".$bkpfolder.$file_to_save,3);
				$crit_issue=1;
				break;
			}
			$url = $prefix.$miniserver['IPAddress'].":".$port."/dev/fsget".$file_to_save;
			usleep(50000);
			$curl_save_issue=0;
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0054_READ_FROM_WRITE_TO"]." ( $file_to_save )",6);
			debug(__line__,"MS#".$msno." ".$url ." => ".$workdir_tmp."/".$bkpfolder.$file_to_save); 
			$curl_save = curl_init(str_replace(" ","%20",$url));
			curl_setopt($curl_save, CURLOPT_USERPWD				, $miniserver['Credentials_RAW']);
			curl_setopt($curl_save, CURLOPT_NOPROGRESS			, 1);
			curl_setopt($curl_save, CURLOPT_FOLLOWLOCATION		, 1);
			curl_setopt($curl_save, CURLOPT_CONNECTTIMEOUT		, 600); 
			curl_setopt($curl_save, CURLOPT_TIMEOUT				, 600);
			curl_setopt($curl_save, CURLOPT_SSL_VERIFYPEER		, 0);
			curl_setopt($curl_save, CURLOPT_SSL_VERIFYSTATUS	, 0);
			curl_setopt($curl_save, CURLOPT_SSL_VERIFYHOST		, 0);
			curl_setopt($curl_save, CURLOPT_FILE, $fp) or $curl_save_issue=1;

			if ( $curl_save_issue == 1 )
			{
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_032_PROBLEM_CREATING_FILE"]." ".$workdir_tmp."/".$bkpfolder.$file_to_save." ".curl_error($curl),3);
				$crit_issue=1;
				break;
			}
			$data 	= curl_exec($curl_save);
			$code	= curl_getinfo($curl_save,CURLINFO_RESPONSE_CODE);
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0056_SERVER_RESPONSE"]." ".$code);
			if ( $code != 200 )
			{
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0057_DOWNLOAD_SERVER_RESPONSE_NOT_200"],6);
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0058_DATA_BEFORE_REFRESH"]." ".$url,6);

				//Check Connection in case of Cloud DNS if file download failed
				if ( $miniserver['UseCloudDNS'] == "on" || $miniserver['UseCloudDNS'] == "1" ) 
				{
					//Check for earlier Cloud DNS requests on RAM Disk
					touch($cloud_requests_file); // Touch file to prevent errors if inexistent
					$checkurl = "http://".$plugin_cfg["CLOUDDNS"]."/?getip&snr=".$miniserver['CloudURL']."&json=true";
					$max_accepted_dns_errors 	= 10;
					$dns_errors 				= 0;
					do 
					{
						sleep(1);
						debug(__line__,"MS#".$msno." Call function get_connection_data => ".$miniserver['Name']);
						$connection_data_returncode = get_connection_data($checkurl);
						/* Possible connection_data_error codes:
							0 = ok
							1 = Other error, retry
							2 = Too many errors, stop retrying
							3 = Too much errors for today
							4 = Port not open / Remote connect disabled
							5 = Error init cURL
							6 = Error 403/405, stop retrying
						*/
						if ( $connection_data_returncode == 1)
						{
							// Code 1 will repeat the loop until $max_accepted_dns_errors is reached
							// 1 = Other error, retry
							$dns_errors++;
							debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0025_CLOUD_DNS_FAIL"]." (#$dns_errors/$max_accepted_dns_errors)",6);
						}
						if ( $dns_errors > $max_accepted_dns_errors ) 
						{
							debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_024_TOO_MANY_CLOUD_DNS_FAILS"]." (#$dns_errors/$max_accepted_dns_errors) ".$miniserver['Name']." ".curl_error($curl),3);
							$connection_data_returncode = 2; 
						}
					} while ($connection_data_returncode == 1);
					
					if ( $connection_data_returncode >= 2 ) 
					{
						create_clean_workdir_tmp($workdir_tmp);
						file_put_contents($watchstate_file,"");
						array_push($summary,"<HR> ");
						if ( $connection_data_returncode != 3 ) array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
						continue;
					}
				}

				curl_close($curl_save); 
				sleep(2);
				$url = $prefix.$miniserver['IPAddress'].":".$port."/dev/fsget".$file_to_save;
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0059_DATA_AFTER_REFRESH"]." ".$url,6);
				usleep(50000);
				$curl_save_issue=0;
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0054_READ_FROM_WRITE_TO"]." ( $file_to_save )",6);
				debug(__line__,"MS#".$msno." ".$url ." => ".$workdir_tmp."/".$bkpfolder.$file_to_save,7); 
				$curl_save = curl_init(str_replace(" ","%20",$url));
				curl_setopt($curl_save, CURLOPT_USERPWD				, $miniserver['Credentials_RAW']);
				curl_setopt($curl_save, CURLOPT_NOPROGRESS			, 1);
				curl_setopt($curl_save, CURLOPT_FOLLOWLOCATION		, 1);
				curl_setopt($curl_save, CURLOPT_CONNECTTIMEOUT		, 600); 
				curl_setopt($curl_save, CURLOPT_TIMEOUT				, 600);
				curl_setopt($curl_save, CURLOPT_SSL_VERIFYPEER		, 0);
				curl_setopt($curl_save, CURLOPT_SSL_VERIFYSTATUS	, 0);
				curl_setopt($curl_save, CURLOPT_SSL_VERIFYHOST		, 0);
				curl_setopt($curl_save, CURLOPT_FILE, $fp) or $curl_save_issue=1;

				if ( $curl_save_issue == 1 )
				{
					debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_032_PROBLEM_CREATING_FILE"]." ".$workdir_tmp."/".$bkpfolder.$file_to_save." ".curl_error($curl),3);
					$crit_issue=1;
					break;
				}
				$data 	= curl_exec($curl_save);
				$code	= curl_getinfo($curl_save,CURLINFO_RESPONSE_CODE);
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0161_SERVER_RESPONSE"]." ".$code);
				if ( $code != 200 )
				{
					debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0070_TOO_MANY_DOWNLOAD_ERRORS"],3);
					create_clean_workdir_tmp($workdir_tmp);
					file_put_contents($watchstate_file,"");
					array_push($summary,"<HR> ");
					array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
					continue;
				}

			}
			if ( filesize($workdir_tmp."/".$bkpfolder.$file_to_save)  != $filetree["size"][array_search($file_to_save,$filetree["name"],true)] && filesize($workdir_tmp."/".$bkpfolder.$file_to_save) != 122 )
			{
				if ( preg_match("/\/sys\/rem\//i", $file_to_save) )
				{
					debug(__line__,"MS#".$msno." ".str_ireplace("<file>",$bkpfolder.$file_to_save,str_ireplace("<dwl_size>",filesize($workdir_tmp."/".$bkpfolder.$file_to_save),str_ireplace("<ms_size>",$filetree["size"][array_search($file_to_save,$filetree["name"],true)],$L["ERRORS.ERR_035_DIFFERENT_FILESIZE"]))),6);
				}
				else
				{
					debug(__line__,"MS#".$msno." ".str_ireplace("<file>",$bkpfolder.$file_to_save,str_ireplace("<dwl_size>",filesize($workdir_tmp."/".$bkpfolder.$file_to_save),str_ireplace("<ms_size>",$filetree["size"][array_search($file_to_save,$filetree["name"],true)],$L["ERRORS.ERR_035_DIFFERENT_FILESIZE"]))),6);
				}
				sleep(.1); 
				$LoxURL  = $prefix.$miniserver['IPAddress'].":".$port."/dev/fslist".dirname($filetree["name"][array_search($file_to_save,$filetree["name"],true)]);
				curl_setopt($curl_save, CURLOPT_URL, $LoxURL);
				curl_setopt($curl_save, CURLOPT_RETURNTRANSFER, 1); 
				$read_data = curl_exec($curl_save);
				curl_setopt($curl_save, CURLOPT_RETURNTRANSFER, 0); 
				$read_data = trim($read_data);
				$read_data_line = explode("\n",$read_data);
				$base = basename($filetree["name"][array_search($file_to_save,$filetree["name"],true)]);
				foreach ( array_filter($read_data_line, function($var) use ($base) { return preg_match("/\b$base\b/i", $var); }) as $linefound )
				{
					preg_match("/^-\s*(\d*)\s([a-zA-z]{3})\s(\d{1,2})\s(\d{1,2}:\d{1,2})\s(.*)$/i", $linefound, $filename);
					if ($filename[1] == 0)
					{
						debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0014_ZERO_FILESIZE"]." ".$folder.$filename[5]." (".$filename[1]." Bytes)",5);
					}
					else
					{
						debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0074_EXTRACTED_NAME_FILE"]." ".$folder.$filename[5]." (".$filename[1]." Bytes)",6);
						$filetree["size"][array_search($file_to_save,$filetree["name"],true)] = $filename[1];
					}
				}
				curl_setopt($curl_save, CURLOPT_FILE, $fp) or $curl_save_issue=1;
				$data = curl_exec($curl_save);
			}

			if ( $data === FALSE)
			{
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0096_DOWNLOAD_FAILED_RETRY"]." ".$url ." => ".$workdir_tmp."/".$bkpfolder.$file_to_save,6); 
				sleep(.1);
				curl_setopt($curl_save, CURLOPT_FILE, $fp) or $curl_save_issue=1;
				$data = curl_exec($curl_save);
			}

			fclose ($fp); 
			
			if ( filesize($workdir_tmp."/".$bkpfolder.$file_to_save)  != $filetree["size"][array_search($file_to_save,$filetree["name"],true)] && filesize($workdir_tmp."/".$bkpfolder.$file_to_save) != 122 )
			{
				if (  preg_match("/\/log\/remoteconnect/i", $file_to_save) )
				{
					debug(__line__,"MS#".$msno." ".str_ireplace("<file>",$bkpfolder.$file_to_save,str_ireplace("<dwl_size>",filesize($workdir_tmp."/".$bkpfolder.$file_to_save),str_ireplace("<ms_size>",$filetree["size"][array_search($file_to_save,$filetree["name"],true)],$L["ERRORS.ERR_035_DIFFERENT_FILESIZE"]))),6);
				}
				else
				{
					debug(__line__,"MS#".$msno." ".str_ireplace("<file>",$bkpfolder.$file_to_save,str_ireplace("<dwl_size>",filesize($workdir_tmp."/".$bkpfolder.$file_to_save),str_ireplace("<ms_size>",$filetree["size"][array_search($file_to_save,$filetree["name"],true)],$L["ERRORS.ERR_035_DIFFERENT_FILESIZE"]))),4);
				}
			}
			if ( $data === FALSE )
			{
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0009_CURL_SAVE_FAILED"]." ".$workdir_tmp."/".$bkpfolder.$file_to_save." ".curl_error($curl_save),4);
			}
			else
			{
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0055_DOWNLOAD_SUCCESS"]." ".$url ." => ".$workdir_tmp."/".$bkpfolder.$file_to_save,6); 
				// Set file time to guessed value read from miniserver
				if (touch($workdir_tmp."/".$bkpfolder.$file_to_save, $filetree["time"][array_search($file_to_save,$filetree["name"],true)]) === FALSE )
				{
					debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_033_FILETIME_ISSUE"]." ".$workdir_tmp."/".$bkpfolder.$file_to_save,4);
				}
				if ( filesize($workdir_tmp."/".$bkpfolder.$file_to_save) < 255 )
				{
					$read_data = file_get_contents($workdir_tmp."/".$bkpfolder.$file_to_save);
					if(stristr($read_data,'<html><head><title>error</title></head><body>') === FALSE && $read_data != "") 
					{
						# Content small but okay
					}
					else
					{
						{
							debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_034_CURL_GET_CONTENT_FAILED"]." ".$file_to_save." [".curl_error($curl_save).$read_data."]",4); 
							continue;
						}
					}
				}
				array_push($save_ok_list["name"], $file_to_save);
				array_push($save_ok_list["size"], filesize($workdir_tmp."/".$bkpfolder.$file_to_save));
				array_push($save_ok_list["time"], filemtime($workdir_tmp."/".$bkpfolder.$file_to_save));
				
				if ( filesize($workdir_tmp."/".$bkpfolder.$file_to_save)  != $filetree["size"][array_search($file_to_save,$filetree["name"],true)])
				{
					debug(__line__,"MS#".$msno." ".str_ireplace("<file>",$bkpfolder.$file_to_save,str_ireplace("<dwl_size>",filesize($workdir_tmp."/".$bkpfolder.$file_to_save),str_ireplace("<ms_size>",$filetree["size"][array_search($file_to_save,$filetree["name"],true)],$L["ERRORS.ERR_035_DIFFERENT_FILESIZE"]))),4);
				}
				else
				{
					debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0060_CURL_SAVE_OK"]." ".$workdir_tmp."/".$bkpfolder.$file_to_save." (".filesize($workdir_tmp."/".$bkpfolder.$file_to_save)." Bytes)",6);
				}
				$percent_done = round((count($save_ok_list["name"]) *100 ) / count($filetree["name"]),0);
				file_put_contents($watchstate_file,str_ireplace("<MS>",$msno." (".$miniserver['Name'].")",$L["Icon-Watchdog.INF_0003_STATE_RUN"])." (".$L["Icon-Watchdog.INF_0062_STATE_DOWNLOAD"]." ".$percent_done."%)");
				if ( ! ($percent_done % 5) )
				{
					if ( $percent_displ != $percent_done )
					{
						if ($percent_done <= 95)
						{
						 	debug(__line__,"MS#".$msno." ".str_pad($percent_done,3," ",STR_PAD_LEFT).$L["Icon-Watchdog.INF_0061_PERCENT_DONE"]." (".str_pad(round(array_sum($save_ok_list["size"]),0),strlen(round(array_sum($filetree["size"]),0))," ", STR_PAD_LEFT)."/".str_pad(round(array_sum($filetree["size"]),0),strlen(round(array_sum($filetree["size"]),0))," ", STR_PAD_LEFT)." Bytes) [".str_pad(count($save_ok_list["name"]),strlen(count($filetree["name"]))," ", STR_PAD_LEFT)."/".str_pad(count($filetree["name"]),strlen(count($filetree["name"]))," ", STR_PAD_LEFT)."]",5);
						 	$log->LOGTITLE(str_ireplace("<MS>",$msno." (".$miniserver['Name'].")",$L["Icon-Watchdog.INF_0003_STATE_RUN"])." (".$L["Icon-Watchdog.INF_0062_STATE_DOWNLOAD"]." ".$percent_done."%)");
						}
		 			}
		 			$percent_displ = $percent_done;
				}	
			}
		}

		if ( $crit_issue == 1 )
		{
			create_clean_workdir_tmp($workdir_tmp);
			file_put_contents($watchstate_file,"");
			array_push($summary,"<HR> ");
			array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
			continue;
		}
		
		
		debug(__line__,"MS#".$msno." ".$percent_done.$L["Icon-Watchdog.INF_0061_PERCENT_DONE"]." (".round(array_sum($save_ok_list["size"]),0)."/".round(array_sum($filetree["size"]),0)." Bytes) [".count($save_ok_list["name"])."/".count($filetree["name"])."]",5);
		file_put_contents($watchstate_file,str_ireplace("<MS>",$msno." (".$miniserver['Name'].")",$L["Icon-Watchdog.INF_0003_STATE_RUN"]));
		$log->LOGTITLE(str_ireplace("<MS>",$msno." (".$miniserver['Name'].")",$L["Icon-Watchdog.INF_0003_STATE_RUN"]));
		debug(__line__,"MS#".$msno." ".count($save_ok_list["name"])." ".$L["Icon-Watchdog.INF_0063_DOWNLOAD_COMPLETE"]." (".array_sum($save_ok_list["size"])." Bytes)",5);
		
		if ( (count($filetree["name"]) - count($save_ok_list["name"])) > 0 )
		{	
			debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_036_SOME_FILES_NOT_SAVED"]." ".(count($filetree["name"]) - count($save_ok_list["name"])),4);
			debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_037_SOME_FILES_NOT_SAVED_INFO"]."\n".implode("\n",array_diff($filetree["name"], $save_ok_list["name"])),6);
			////todo
		}
		$runtime_dwl = (microtime(true) - $start_dwl);
		debug(__line__,"MS#".$msno." "."Runtime: ".$runtime_dwl." s");
		if ( round($runtime_dwl,1,PHP_ROUND_HALF_UP) < 0.5 ) $runtime_dwl = 0.5;
		$size_dwl = array_sum($save_ok_list["size"]);
		$size_dwl_kBs = round(  ($size_dwl / 1024) / $runtime_dwl ,2);
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0064_DOWNLOAD_TIME"]." ".secondsToTime(round($runtime_dwl,0,PHP_ROUND_HALF_UP))." s ".$size_dwl." Bytes => ".$size_dwl_kBs." kB/s",5);
	}
	else
	{
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0050_NOSTART_DOWNLOAD"],5);
	}
	curl_close($curl_save); 
	
	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0065_MOVING_TO_SAVE_DIR"]." ".$workdir_tmp."/".$bkpfolder." =>".$savedir_path."/".$bkpfolder);
	if (is_writeable($savedir_path."/".$bkpfolder)) 
	{
		
		$freespace = get_free_space($savedir_path."/".$bkpfolder);
		if ( $freespace < $full_backup_size + 33554432 )
		{
			
			debug (__line__,"MS#".$msno." ".str_ireplace("<free>",formatBytes($freespace,0),str_ireplace("<need>",formatBytes($full_backup_size,0),$L["ERRORS.ERR_031_NOT_ENOUGH_FREE_SPACE"])),2);
			create_clean_workdir_tmp($workdir_tmp);
			file_put_contents($watchstate_file, "");
			array_push($summary,"<HR> ");
			array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
			continue;
		}
		else
		{
			debug (__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0051_ENOUGH_FREE_SPACE"]." ".formatBytes($freespace),5);
		}
		$fileformat = "UNCOMPRESSED";
		$fileformat_extension = "";

		debug (__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0088_MOVING_DOWNLOADED_FILE"]." ".$savedir_path."/".$bkpfolder,6);
		rmove($workdir_tmp."/".$bkpfolder, $savedir_path."/".$bkpfolder);
		debug (__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0089_REMOVING_WORKDIR"]." ".$workdir_tmp."/".$bkpfolder,6);
		rrmdir($workdir_tmp."/".$bkpfolder);
		debug (__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0090_BUILD_LIST_OF_KNOWN_FILES"]." (".$zipdir_path.'/ms_'.$msno.'/)',6);
		$existing_files_for_zip = glob($zipdir_path.'/ms_'.$msno.'/*', GLOB_NOSORT);

		$zip = new ZipArchive;
		debug (__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0091_OPEN_IMAGES_ZIP"]." (".$zipdir_path.'/ms_'.$msno.'/)',6);
		if ($zip->open($savedir_path.'/'.$bkpfolder.'/web/images.zip') === TRUE) 
		{
			debug (__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0010_DEBUG_YES"]);
			if ( !is_dir($zipdir_path.'/ms_'.$msno.'/') )
			{
				@mkdir($zipdir_path.'/ms_'.$msno.'/', 0777, true);
			}
			$to_extract = array();
			for ($idx = 0; $idx < $zip->numFiles; $idx++) 
			{
				$file_in_zip = $zipdir_path.'/ms_'.$msno.'/'.$zip->getNameIndex($idx);
				if ( array_keys($existing_files_for_zip, $file_in_zip) )
				{
					debug(__line__,"MS#".$msno." ".str_ireplace("<file>",$file_in_zip,$L["Icon-Watchdog.INF_0086_FILE_KNOWN_DO_NOT_EXTRACT"]));
				}
				else
				{
					array_push($to_extract, basename($file_in_zip));
					debug(__line__,"MS#".$msno." ".str_ireplace("<file>",$file_in_zip,$L["Icon-Watchdog.INF_0087_FILE_UNKNOWN_EXTRACT"]),6);
				}
			}

			if ( count($to_extract) === 0 )
			{
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0094_NO_UNKNOWN_IMAGES_IN_ZIP"],5);
			}
			else
			{
				debug(__line__,"MS#".$msno." ".str_ireplace("<number_of_files_to_extract>",count($to_extract),$L["Icon-Watchdog.INF_0093_NBR_EXTRACT_IMAGES_FROM_ZIP"]),5);
				if ($zip->extractTo($zipdir_path.'/ms_'.$msno.'/', $to_extract))
				{
					debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0092_EXTRACT_IMAGES_FROM_ZIP"],6);
				}
				else
				{
					debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_039_CANT_EXTRACT_IMAGES_FROM_ZIP"],3);
				}
			}
			$zip->close();
		}
		else
		{
			debug (__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0011_DEBUG_NO"],6);
			debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_040_OPEN_IMAGES_ZIP"],3);
			continue;
		}	

		if ( $crit_issue == 1 )
		{
			create_clean_workdir_tmp($workdir_tmp);
			file_put_contents($watchstate_file,"");
			array_push($summary,"<HR> ");
			array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
			continue;
		}
		
			$nbr_saved = count($filetree["size"]);
			switch ($nbr_saved) 
			{
		    	case "0":
					$fileinf = $L["Icon-Watchdog.INF_0075_NO_FILE_CHANGED"];
					break;
		    	case "1":
					$fileinf = $L["Icon-Watchdog.INF_0076_FILE_CHANGED"]." ".formatBytes(array_sum($filetree["size"]));
					break;
				default:
					$fileinf = $nbr_saved." ".$L["Icon-Watchdog.INF_0077_FILES_CHANGED"]." ".formatBytes(array_sum($filetree["size"]));
					break;
			}
			$message = str_ireplace("<NAME>",$miniserver['Name'],str_ireplace("<MS>",$msno,$L["Icon-Watchdog.INF_0066_DOWNLOAD_FROM_MINISERVER_COMPLETED"]))." ".$fileinf;
			debug(__line__,"MS#".$msno." ".$message,5);
/*			$notification = array (
			"PACKAGE" => LBPPLUGINDIR,
			"NAME" => $L['GENERAL.MY_NAME']." ".$miniserver['Name'],
			"MESSAGE" => $message,
			"SEVERITY" => 6,
			"LOGFILE"	=> $logfilename);
			if ( $plugin_cfg["IWD_USE_NOTIFY"] == "on" || $plugin_cfg["IWD_USE_NOTIFY"] == "1" ) 
			{
				@notify_ext ($notification);
			}
*/
			array_push($summary,"MS#".$msno." "."<OK> ".$message);
	}
	else
	{
		debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_041_STORAGE_NOT_WRITABLE"]." ".$finalstorage,3);
		create_clean_workdir_tmp($workdir_tmp);
		file_put_contents($watchstate_file,"");
		array_push($summary,"<HR> ");
		array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
		continue;

	}
	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0008_CLEAN_WORKDIR_TMP"]." ".$workdir_tmp);
	create_clean_workdir_tmp($workdir_tmp);
	file_put_contents($watchstate_file,$L["Icon-Watchdog.INF_0037_CHECK_COMPLETED_MS"]." #".$msno." (".$miniserver['Name'].")");
	@system("php -f ".dirname($_SERVER['PHP_SELF']).'/ajax_config_handler.php LAST_SAVE'.$msno.'='.$last_save_stamp.' >/dev/null 2>&1');
	$at_least_one_save = 1;
	array_push($summary,"<HR> ");
	array_push($saved_ms," #".$msno." (".$miniserver['Name'].")");
	$log->LOGTITLE($L["Icon-Watchdog.INF_0037_CHECK_COMPLETED_MS"]." #".$msno." (".$miniserver['Name'].")");


	////////////////
	/// Check Part
	////////////////
	$ProjectSerial = "none";
	require_once "import.php";
	$project_files = glob("$lbpdatadir/project/ms_$msno/".$L["GENERAL.PREFIX_CONVERTED_FILE"]."*.Loxone", GLOB_NOSORT);
	usort($project_files, function($a, $b) {return filemtime($b) - filemtime($a);});
	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0096_USING_PROJECT_FILE"]." ".$project_files[0],5);
	
	if ($project_files[0])
	{
		$project = import_loxone_project($project_files[0],$msno);

		if ( isset($project['error']) )
		{
			$result["ms$msno"] = array(
			"ms" => $msno,
			"project_as_json" => "",
			"success" => false,
			"error" => $project['error'],
			"errorcode" => $project['errorcode'],
			"Serial"    => $project['Serial']
			);
			debug(__line__,$project['error'],3);
			$ProjectSerial = $project['Serial'];
			continue;
		}
		
		debug(__line__,$L["LOGGING.LOG_021_PROJECT_ANALYZE_OK"],5);
		$result["ms$msno"] = array(
			"ms" => $msno,
			"project_as_json" => $project['json'],
			//"pretty" => $project['pretty'],
			"error" => false,
			"success" => true,
			"message" => $L["LOGGING.LOG_021_PROJECT_ANALYZE_OK"],
			"Serial"    => $project['Serial']

		);
		$ProjectSerial = $project['Serial'];
	}
	else
	{
		$result["ms$msno"] = array(
			"ms" => $msno,
			"success" => false,
			"error" => $L["ERRORS.ERR_042_NO_PROJECT_FILE"],
			"errorcode" => "no_project_file",
			"Serial"    => "none"

		);
		debug(__line__,$L["ERRORS.ERR_042_NO_PROJECT_FILE"],4);
	}	

	generate_images_zip($msno);

	// Upload to MS
	// Check serial
	$serial="000000000000";
	$MAC  = $prefix.$miniserver['IPAddress'].":".$port."/dev/cfg/mac";
	$curl_mac = curl_init(str_replace(" ","%20",$MAC));
	curl_setopt($curl_mac, CURLOPT_USERPWD				, $miniserver['Credentials_RAW']);
	curl_setopt($curl_mac, CURLOPT_NOPROGRESS			, 1);
	curl_setopt($curl_mac, CURLOPT_FOLLOWLOCATION		, 1);
	curl_setopt($curl_mac, CURLOPT_CONNECTTIMEOUT		, 10); 
	curl_setopt($curl_mac, CURLOPT_TIMEOUT				, 10);
	curl_setopt($curl_mac, CURLOPT_SSL_VERIFYPEER		, 0);
	curl_setopt($curl_mac, CURLOPT_SSL_VERIFYSTATUS		, 0);
	curl_setopt($curl_mac, CURLOPT_SSL_VERIFYHOST		, 0);
	curl_setopt($curl_mac, CURLOPT_HEADER				, 0);  
	curl_setopt($curl_mac, CURLOPT_RETURNTRANSFER		, true);
	if ( !$curl_mac )
	{
		debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0002_ERROR_INIT_CURL"],4);
		debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_051_XML_MAC_FAILED"],4);
	}
	else
	{
		$response = curl_exec($curl_mac);  
		debug(__line__,"MS#".$msno." URL: $MAC => Response: ".htmlentities($response)."\n");
		curl_close($curl_mac);

		if ( $response )
		{
			$mac_response = simplexml_load_string ( $response, "SimpleXMLElement" ,LIBXML_NOCDATA | LIBXML_NOWARNING );
			if ($mac_response === false) 
			{
				$errors = libxml_get_errors();
				$importerrors = array();
				foreach ($errors as $error) 
				{
					report_xml_error($msno, $error, explode("\n",$response));
				}
				libxml_clear_errors();
			}
			else
			{
				if ( isset($mac_response["value"][0]) )
				{
					$serial	= strtoupper(str_replace(":","",$mac_response["value"][0]));
				}
			}
		}
		debug(__line__,"MS#".$msno." Project-Serial: ".$ProjectSerial);
		debug(__line__,"MS#".$msno." Miniserver-Serial: ".$serial);
		if ( $serial == "000000000000" || $ProjectSerial == "none" ) 
		{	
			debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_051_XML_MAC_FAILED"],4);
		}
		else
		{
			if ( $ProjectSerial == $serial )
			{
				debug(__line__,"MS#".$msno." ".str_replace(array("<ms>","<serial>"),array($msno,$serial),$L["Icon-Watchdog.INF_0099_MS_SERIAL_OK"]),6);
				$downloaded_zipmd5 = "";
				if ( is_readable ( "/tmp/ms".$msno."_images.zip.md5" ) )
				{
					$downloaded_zipmd5 = file_get_contents ("/tmp/ms".$msno."_images.zip.md5");	
				}
				if ( is_readable ( $savedir_path."/ms_".$msno."/web/images.zip" ) )
				{
					$current_zipmd5 = md5_file ($savedir_path."/ms_".$msno."/web/images.zip");
					debug(__line__,"MS#".$msno." MD5: ".$downloaded_zipmd5 ."<>".$current_zipmd5);

					if ( $downloaded_zipmd5 == $current_zipmd5 )
					{
						debug(__line__,"MS#".$msno." ".str_replace(array("<ms>","<serial>"),array($msno,$serial),$L["Icon-Watchdog.INF_0100_ZIP_NOT_CHANGED"]),5);
					}
					else
					{
						// File to be uploaded not there, skip.
						
						// Do FTP only for matched Serial
						
						if ( $miniserver['UseCloudDNS'] == "on" || $miniserver['UseCloudDNS'] == "1" ) 
						{
							debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0041_CLOUD_DNS_USED"]." => ".$miniserver['Name'],6);
							$ftpport = (isset($miniserver["CloudURLFTPPort"]))?$miniserver["CloudURLFTPPort"]:21;
						}
						else
						{
							$ftpport = (isset($plugin_cfg["FTPPort".$msno]))?$plugin_cfg["FTPPort".$msno]:21;
						}	
					
						$file = $savedir_path."/ms_".$msno."/web/images.zip";
						$remote_file = '/web/images.zip';

						// Verbindung aufbauen
						$conn_id = ftp_connect($miniserver['IPAddress'],$ftpport,10);
						if ( !$conn_id )
						{
							debug(__line__,$L["ERRORS.ERR_048_FTP_CONNECT_FAILED"]." => Miniserver #".$msno."@".$miniserver['IPAddress'].":".$ftpport,4);
						}
						else
						{
							// Login mit Benutzername und Passwort
							$login_result = ftp_login($conn_id, $miniserver['Admin_RAW'], $miniserver['Pass_RAW']);
							if ( !$login_result )
							{
								debug(__line__,str_replace("<user>",$miniserver['Admin_RAW'],$L["ERRORS.ERR_049_FTP_LOGIN_FAILED"])." => Miniserver #".$msno."@".$miniserver['IPAddress'].":".$ftpport,4);
								debug(__line__,"Miniserver #".$msno." Login ".$miniserver['Admin_RAW']." and Password ".$miniserver['Pass_RAW']." for ".$miniserver['IPAddress']." at Port ".$ftpport);
							}
							else
							{
								// Schalte passiven Modus ein
								ftp_pasv($conn_id, true);
								ftp_set_option($conn_id, FTP_TIMEOUT_SEC, 60);
								// Lade eine Datei hoch
								if (ftp_put($conn_id, $remote_file, $file, FTP_BINARY)) 
								{
									debug(__line__,str_replace(array("<file>","<ms>"),array($file,$msno),$L["Icon-Watchdog.INF_0098_FTP_UPLOAD_DONE"]),5);
									@file_put_contents ("/tmp/ms".$msno."_images.zip.md5", md5_file ($savedir_path."/ms_".$msno."/web/images.zip"));
								}
								else 
								{
									debug(__line__,str_replace(array("<file>","<ms>"),array($file,$msno),$L["ERRORS.ERR_050_FTP_UPLOAD_FAILED"]),4);
								}
							}
							// Verbindung schließen
							ftp_close($conn_id);
						}
					}
				}
				else			
				{
					// File to be uploaded not there, skip.
					debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_052_UPLOAD_FILE_PROBLEM"],4);
				}	
			}
			else
			{
				debug(__line__,"MS#".$msno." ".str_replace(array("<ms>","<serial>","<projectserial>"),array($msno,$serial,$ProjectSerial),$L["ERRORS.ERR_053_MS_SERIAL_MISMATCH"]),4);
			}
		}
	}
}

if ( $msno > count($ms) ) { $msno = ""; };
array_push($summary," ");
debug(__line__,$L["Icon-Watchdog.INF_0067_ALL_COMPLETE"],5);

if ( count($saved_ms) > 0 )
{
	$str_part_saved_ms = " <font color=green>".join(", ",$saved_ms)."</font>";
}
else
{
	$str_part_saved_ms = " ";
}
if ( count($problematic_ms) > 0 )
{
	
	$str_part_problematic_ms = " ".$L["Icon-Watchdog.INF_0068_DOWNLOAD_COMPLETED_MS_FAIL"]." <font color=red>".join(", ",$problematic_ms)."</font>";
}
else
{
	$str_part_problematic_ms = "";
}

if ( count($saved_ms) > 0 )
{
	$log->LOGTITLE($L["Icon-Watchdog.INF_0038_CHECK_FINISHED"]." ".$L["Icon-Watchdog.INF_0037_CHECK_COMPLETED_MS"].$str_part_saved_ms.$str_part_problematic_ms);
}
else
{
	$log->LOGTITLE($L["Icon-Watchdog.INF_0038_CHECK_FINISHED"]." ".$L["Icon-Watchdog.INF_0078_DOWNLOAD_COMPLETED_NO_MS"].$str_part_problematic_ms);
}

curl_close($curl); 
debug(__line__,$L["Icon-Watchdog.INF_0019_DEBUG_DIRECTORY_DELETE"]." -> ".$workdir_tmp);
rrmdir($workdir_tmp);

$runtime = microtime(true) - $start;

if ( count($summary) > 2 )
{
	error_log($L["Icon-Watchdog.INF_9999_SUMMARIZE_ERRORS"]);
	foreach ($summary as &$errors) 
	{
		if ( ! preg_match("/<HR>/i", $errors) && $errors != " " )
		{
			error_log($errors);
		}
	}
}


$err_html = "";

foreach ($summary as &$errors) 
{
	debug("Summary:\n".htmlentities($errors));
	$errors = nl2br($errors);
	if ( preg_match("/<INFO>/i", $errors) )
	{
		$err_html .= "<br><span style='color:#000000; background-color:#DDEFFF'>".$errors."</span>";
	}
	else if ( preg_match("/<OK>/i", $errors) )
	{
		$err_html .= "<br><span style='color:#000000; background-color:#D8FADC'>".$errors."</span>";
	}
	else if ( preg_match("/<WARNING>/i", $errors)  )
	{ 
		$err_html .= "<br><span style='color:#000000; background-color:#FFFFC0'>".$errors."</span>";
	}
	else if ( preg_match("/<ERROR>/i", $errors)  )
	{
		$err_html .= "<br><span style='color:#000000; background-color:#FFE0E0'>".$errors."</span>";
	}
	else if ( preg_match("/<CRITICAL>/i", $errors)  )
	{
		$err_html .= "<br><span style='color:#000000; background-color:#FFc0c0'>".$errors."</span>";
	}
	else if ( preg_match("/<ALERT>/i", $errors)  )
	{
		$err_html .= "<br><span style='color:#ffffff; background-color:#0000a0'>".$errors."</span>";
	}
	else
	{
		$err_html .= "<br>".$errors;
	}
}
#$err_html 	 = preg_replace('/\\n+/i','',$err_html);
#$err_html 	 = preg_replace('/\\r+/i','',$err_html);
$err_html 	 = preg_replace('/\s\s+/i',' ',$err_html);
$err_html 	 = preg_replace('/<HR>\s<br>+/i','<HR>',$err_html);
$err_html	 = preg_replace("/(<HR>)\\1+/", "$1", $err_html);
if (str_replace(array('<ALERT>', '<CRITICAL>','<ERROR>'),'', $err_html) != $err_html)
{
	$at_least_one_error = 1;
}
else if (str_replace(array('<WARNING>'),'', $err_html) != $err_html)
{
	$at_least_one_warning = 1;
}


if ( !$result )
{
	$result["ms"] = array("success" => false,"error" => $L["ERRORS.ERR_046_ERR_UNKNOWN"],"errorcode" => "ERR_046_ERR_UNKNOWN");
}
echo json_encode($result, JSON_UNESCAPED_SLASHES);
sleep(3); // To prevent misdetection
file_put_contents($watchstate_file, "");
LOGEND("");
exit;

// Functions

function get_connection_data($checkurl)
{
	global $different_cloudrequests,$connection_data_returncode,$all_cloudrequests,$known_for_today,$miniserver,$L,$msno,$workdir_tmp,$watchstate_file,$summary,$problematic_ms,$port,$prefix,$log,$date_time_format,$plugin_cfg,$cfg,$cloud_requests_file,$connection_data_returncode0,$manual_check,$randomsleep;
	$connection_data_returncode	= 0;
	if ( $miniserver['UseCloudDNS'] == "on" || $miniserver['UseCloudDNS'] == "1" ) 
	{
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0041_CLOUD_DNS_USED"]." => ".$miniserver['Name'],6);
		if ( $miniserver['CloudURL'] == "" )
		{
			debug(__line__,"MS#".$msno." ".$L["ERROR.ERR_029_PROBLEM_READING_CLOUD_DNS_ADDR"]." => ".$miniserver['Name'],5);
		}
		if ( isset($checkurl) ) 
		{
			$sleep_start = time();
			$sleep_end = $sleep_start + 2;
			$sleep_until = date($date_time_format,$sleep_end);
			debug(__line__,"MS#".$msno." (".$miniserver['Name'].") ".str_ireplace("<wait_until>",$sleep_until,$L["Icon-Watchdog.INF_0040_SLEEP_BEFORE_SENDING_NEXT_CLOUD_DNS_QUERY"]),5);
			$wait_info_string = "MS#".$msno." (".$miniserver['Name'].") ".str_ireplace("<wait_until>",$sleep_until,str_ireplace("<time>",secondsToTime($sleep_end - time()),$L["Icon-Watchdog.INF_0039_TIME_TO_WAIT"]));
			file_put_contents($watchstate_file,$wait_info_string);
			$log->LOGTITLE($wait_info_string);
			sleep(2);
		}
		if ( date("i",time()) == "00" || date("i",time()) == "15" || date("i",time()) == "30" || date("i",time()) == "45" )
		{ 
			debug(__line__,"MS#".$msno." (".$miniserver['Name'].") ".$L["Icon-Watchdog.INF_0143_WAIT_FOR_RESTART"],6);
			sleep(5); // Fix for Loxone Cloud restarts at 0, 15, 30 and 45
		}
		if ( ($miniserver['UseCloudDNS'] == "on" ||$miniserver['UseCloudDNS'] == "1") && $randomsleep == 1 && $manual_check != 1 )
		{
			$randomsleep = random_int(2,300);
			$sleep_start = time();
			$sleep_end = $sleep_start + $randomsleep;
			$sleep_until = date($date_time_format,$sleep_end);
			$wait_info_string = "MS#".$msno." (".$miniserver['Name'].") ".str_ireplace("<time>",$sleep_until." ($randomsleep s)",$L["Icon-Watchdog.INF_0079_RANDOM_SLEEP"]);
			debug(__line__,$wait_info_string,6);
			file_put_contents($watchstate_file,$wait_info_string);
			$log->LOGTITLE($wait_info_string);
			sleep($randomsleep);
		} 
		
		$cloud_requests_json_array = json_decode(file_get_contents($cloud_requests_file),true);
		$known_for_today = 0;
		$all_cloudrequests = 0;
		if ($cloud_requests_json_array)
		{
			$key = array_search(strtolower($miniserver['CloudURL']), array_column($cloud_requests_json_array, 'cloudurl'));
			if ($key !== FALSE)
			{
				if ( substr($cloud_requests_json_array[$key]["date"],0,8) == date("Ymd",time()) )
				{
					$cloud_requests_json_array[$key]["requests"]++; 
					$known_for_today = 1;
				}
				else
				{
					$cloud_requests_json_array[$key]["requests"] = 1; 
				}
				debug(__line__,"MS#".$msno." (".$miniserver['Name'].") ".str_ireplace("<no>",$cloud_requests_json_array[$key]["requests"],$L["Icon-Watchdog.INF_0080_CLOUD_DNS_REQUEST_DATA_MS_FOUND"]),6);
			}
			else
			{
				debug(__line__,"MS#".$msno." (".$miniserver['Name'].") ".$L["Icon-Watchdog.INF_0150_CLOUD_DNS_REQUEST_DATA_MS_NOT_FOUND"],6);
				unset ($cloud_request_array_to_push);
				$cloud_request_array_to_push['msno'] = $msno;
				$cloud_request_array_to_push['date'] = date("Ymd",time());
				$cloud_request_array_to_push['cloudurl'] = strtolower($miniserver['CloudURL']);
				$cloud_request_array_to_push['requests'] = 1;
				array_push($cloud_requests_json_array, $cloud_request_array_to_push);
			}
		}
		else
		{
			debug(__line__,"MS#".$msno." (".$miniserver['Name'].") ".$L["Icon-Watchdog.INF_0151_CLOUD_DNS_REQUEST_DATA_NOT_FOUND"],6);
			$cloud_requests_json_array = array();
			unset ($cloud_request_array_to_push);
			$cloud_request_array_to_push['msno'] = $msno;
			$cloud_request_array_to_push['date'] = date("Ymd",time());
			$cloud_request_array_to_push['cloudurl'] = strtolower($miniserver['CloudURL']);
			$cloud_request_array_to_push['requests'] = 1;
			array_push($cloud_requests_json_array, $cloud_request_array_to_push);
		}
		$cloud_requests_json_array_today = array_map("cloud_requests_today", $cloud_requests_json_array);
		$different_cloudrequests = 0;
		foreach($cloud_requests_json_array_today as $datapacket) 
		{
			if ( intval($datapacket['requests']) > 0 ) 
			{
				$different_cloudrequests++;
				$all_cloudrequests = $all_cloudrequests + intval($datapacket['requests']);
			}
		}
		if ( $known_for_today != 1)
		{
			debug(__line__,"MS#".$msno." ".str_ireplace("<all>",$all_cloudrequests,str_ireplace("<max_different_request>",10,str_ireplace("<different_request>",$different_cloudrequests,$L["Icon-Watchdog.INF_0148_CLOUD_DNS_REQUEST_NUMBER"])))." (".$miniserver['CloudURL'].")",6);
		}
		if ( $different_cloudrequests >= 10 && $known_for_today != 1)
		{
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0066_CLOUDDNS_TOO_MUCH_REQUESTS_FOR_TODAY"]." => ".$miniserver['Name'],5);
				file_put_contents($cloud_requests_file,json_encode($cloud_requests_json_array_today));
				$connection_data_returncode = 3;
				return $connection_data_returncode;
		}
		file_put_contents($watchstate_file,str_ireplace("<MS>",$msno." (".$miniserver['Name'].")",$L["Icon-Watchdog.INF_0003_STATE_RUN"]));
		$log->LOGTITLE(str_ireplace("<MS>",$msno." (".$miniserver['Name'].")",$L["Icon-Watchdog.INF_0003_STATE_RUN"]));
		file_put_contents($cloud_requests_file,json_encode($cloud_requests_json_array_today));

		$curl_dns = curl_init(str_replace(" ","%20",$checkurl));
		curl_setopt($curl_dns, CURLOPT_NOPROGRESS		, 1);
		curl_setopt($curl_dns, CURLOPT_FOLLOWLOCATION	, 0);
		curl_setopt($curl_dns, CURLOPT_CONNECTTIMEOUT	, 600); 
		curl_setopt($curl_dns, CURLOPT_TIMEOUT			, 600);
		curl_setopt($curl_dns, CURLOPT_SSL_VERIFYPEER	, 0);
		curl_setopt($curl_dns, CURLOPT_SSL_VERIFYSTATUS	, 0);
		curl_setopt($curl_dns, CURLOPT_SSL_VERIFYHOST	, 0);
		curl_setopt($curl_dns, CURLOPT_RETURNTRANSFER 	, 1);
		if ( !$curl_dns )
		{
			debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0002_ERROR_INIT_CURL"],3);
			curl_close($curl_dns);
			$connection_data_returncode	= 5;
			return $connection_data_returncode;
		}
		sleep(1);
		curl_exec($curl_dns);
		$response = curl_multi_getcontent($curl_dns); 
		debug(__line__,"MS#".$msno." URL: $checkurl => Response: ".$response."\n");
		$response = json_decode($response,true);
		if (!isset($response["LastUpdated"])) $response["LastUpdated"]="";
		// Possible is for example
		// cmd getip
		// IP xxx.xxx.xxx.xxx
		// IPHTTPS xxx.xxx.xxx.xxx:yyyy
		// Code 403 (Forbidden) 200 (OK)    
		// LastUpdated 2018-03-11 16:52:30
		// PortOpen   		(true/false)
		// PortOpenHTTPS	(true/false)
		// DNS-Status 		registered
		// RemoteConnect 	(true/false)
		$HTTPS_mode 	=	($miniserver['PreferHttps'] == 1) ? "HTTPS":"";
		$code			=	curl_getinfo($curl_dns,CURLINFO_RESPONSE_CODE);
		switch ($code) 
		{
			case "200":
				$RemoteConnect = ( isset($response["RemoteConnect"]) ) ? $response["RemoteConnect"]:"false";
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0081_CLOUD_DNS_QUERY_RESULT"]." ".$miniserver['Name']." => IP: ".$response["IP".$HTTPS_mode]." Code: ".$response["Code"]." LastUpdated: ".$response["LastUpdated"]." PortOpen".$HTTPS_mode.": ".$response["PortOpen".$HTTPS_mode]." DNS-Status: ".$response["DNS-Status"]." RemoteConnect: ".$RemoteConnect,5);
				if ( $response["Code"] == "405" )
				{	
					debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0063_CLOUDDNS_ERROR_405"]." => ".$miniserver['Name'],3);
					debug(__line__,"MS#".$msno." URL: ".$checkurl." => Code ".$code);
					debug(__line__,"MS#".$msno." ".join(" ",$response));
					$connection_data_returncode = 6;
					break;
				}
				if ( $response["Code"] != "200" )
				{
					debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0064_CLOUDDNS_CODE_MISMATCH"]." => ".$miniserver['Name']."\nURL: ".$checkurl." => Code ".$code,4);
					debug(__line__,"MS#".$msno." ".join(" ",$response));
					$connection_data_returncode = 1;
					break;
				}
				$ip_info = explode(":",$response["IP".$HTTPS_mode]);
				$miniserver['IPAddress']=$ip_info[0];
				if (count($ip_info) == 2) 
				{
					$port	= $ip_info[1];
				}
				else 
				{
					$port   = ($miniserver['PreferHttps'] == 1) ? 443:80;
				}
				if ( $response["PortOpen".$HTTPS_mode] != "true" ) 
				{
					debug(__line__,"MS#".$msno." ".str_ireplace("<miniserver>",$miniserver['Name'],$L["ERRORS.ERR_0050_CLOUDDNS_PORT_NOT_OPEN"])." ".$response["LastUpdated"],3);
					$connection_data_returncode = 4;
					break;
				}
				else
				{
					if ( isset($response["RemoteConnect"]) &&  $response["RemoteConnect"] != "true" && $HTTPS_mode == "HTTPS") 
					{
						debug(__line__,"MS#".$msno." ".str_ireplace("<miniserver>",$miniserver['Name'],$L["ERRORS.ERR_0072_CLOUDDNS_REMOTE_CONNECT_NOT_TRUE"])." ".$response["LastUpdated"],3);
						$connection_data_returncode = 4;
						break;
					}
				}
				break;
			case "403":
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0051_CLOUDDNS_ERROR_403"]." => ".$miniserver['Name'],3);
				$connection_data_returncode = 6;
				break;
			case "0":
				if ( $connection_data_returncode0 > 3 )
				{
					debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0065_TOO_MANY_CLOUDDNS_ERROR_0"]." => ".$miniserver['Name'],4);
				}
				else
				{
					debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0062_CLOUDDNS_ERROR_0"]." => ".$miniserver['Name'],5);
					sleep(1);
					$connection_data_returncode0++;
				}
				$connection_data_returncode = 1;
				break;
			case "418":
				debug(__line__,"MS#".$msno." (".$miniserver['Name'].") ".$L["ERRORS.ERR_0053_CLOUDDNS_ERROR_418"],5);
				$connection_data_returncode = 1;
				break;
			case "500":
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0061_CLOUDDNS_ERROR_500"]." => ".$miniserver['Name'],4);
				$connection_data_returncode = 1;
				break;
			default;
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0052_CLOUDDNS_UNEXPECTED_ERROR"]." => ".$miniserver['Name']."\nURL: ".$checkurl." => Code ".$code."\n".join("\n",$response),3);
				$connection_data_returncode = 1;
		}
		if ( $connection_data_returncode >= 1 )
		{
			curl_close($curl_dns);
			return $connection_data_returncode;
		}
		$connection_data_returncode0 = 0;
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0027_CLOUD_DNS_OKAY"],6);
	}
	else
	{
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0026_CLOUD_DNS_NOT_USED"]." => ".$miniserver['Name']." @ ".$miniserver['IPAddress'],5);
	}

	if ( $miniserver['IPAddress'] == "0.0.0.0" ) 
	{
		debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0046_CLOUDDNS_IP_INVALID"]." => ".$miniserver['Name'],3);
		array_push($summary,"<HR> ");
		array_push($problematic_ms," #".$msno." (".$miniserver['Name'].")");
		$connection_data_returncode = 1;
		return $connection_data_returncode;
	}
	$connection_data_returncode = 0;
	return $connection_data_returncode;
}

function formatBytes($size, $precision = 2)
{
	if ( !is_numeric( $size ) || $size == 0 ) return "0 kB";
    $base = log($size, 1024);
    $suffixes = array('', 'kB', 'MB', 'GB', 'TB');   
    return round(pow(1024, $base - floor($base)), $precision) .' '. $suffixes[floor($base)];
}

function recurse_copy($src,$dst,$copied_bytes,$filestosave) 
{ 
	global $L, $copied_bytes,$filestosave,$watchstate_file,$msno,$workdir_tmp, $miniserver,$log,$copyerror;
	if ( $copyerror == 1 ) return false;
    $dir = opendir($src); 
	if ( ! is_dir($dst) )
	{ 
	    debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0022_DEBUG_DIRECTORY_CREATE"]." ".$dst);
		if(!@mkdir($dst))
		{
		    $errors= error_get_last();
        	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.ERR_0067_PROBLEM_CREATING_FINAL_DIR"]." => ".$dst,3);
        	debug(__line__,"MS#".$msno." Code: ".$errors['type']." Info: ".$errors['message']);
        	$copyerror = 1;
        	return false;
		} 
    }
    while(false !== ( $file = readdir($dir)) ) 
    { 
        if (( $file != '.' ) && ( $file != '..' )) 
        { 
            if ( is_dir($src . '/' . $file) ) 
            { 
                recurse_copy($src . '/' . $file,$dst . '/' . $file,$copied_bytes,$filestosave); 
            } 
            else 
            { 
            	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0078_DEBUG_COPY_FILE"]." ".$src . '/' . $file .' => ' . $dst . '/' . $file);
                if(!@copy($src . '/' . $file,$dst . '/' . $file))
				{
				    $errors= error_get_last();
                	debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0044_ERR_COPY_FAIL"]." ".$dst . '/' . $file,3);
                	debug(__line__,"MS#".$msno." Code: ".$errors['type']." Info: ".$errors['message']);
                	$copyerror = 1;
                	return false;
				} 
                $copied_bytes = $copied_bytes + filesize($dst . '/' . $file);
				$filestosave = $filestosave  - 1;
				if ( ! ($filestosave % 10) )
				{
					$stateinfo = " (".$L["Icon-Watchdog.INF_0042_STATE_COPY"]." ".str_pad($filestosave,4," ",STR_PAD_LEFT).", ".$L["Icon-Watchdog.INF_0043_STATE_COPY_MB"]." ".round( $copied_bytes / 1024 / 1024 ,2 )." MB)";
					file_put_contents($watchstate_file,str_ireplace("<MS>",$msno." (".$miniserver['Name'].")",$L["Icon-Watchdog.INF_0003_STATE_RUN"]).$stateinfo);
	                $log->LOGTITLE(str_ireplace("<MS>",$msno." (".$miniserver['Name'].")",$L["Icon-Watchdog.INF_0003_STATE_RUN"]).$stateinfo);
	                debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0079_DEBUG_COPY_PROGRESS"].$stateinfo,6);
				}
            } 
        } 
    }
    closedir($dir); 
return $copied_bytes;
}

class MSbackupZIP 
{ 
  private static function folderToZip($folder, &$zipFile, $exclusiveLength) { 
  	global $L,$summary,$miniserver,$msno;
    $handle = opendir($folder); 
    while (false !== $f = readdir($handle)) { 
      if ($f != '.' && $f != '..') 
      { 
        $filePath = "$folder/$f"; 
        // Remove prefix from file path before add to zip. 
        $localPath = substr($filePath, $exclusiveLength); 
   
        
        if (is_file($filePath)) 
        {
          debug(__line__,"MS#".$msno." "."ZIP: ".$L["Icon-Watchdog.INF_0060_ADD_FILE_TO_ZIP"]." ".$filePath);
          $zipFile->addFile($filePath, $localPath); 
        } 
        elseif (is_dir($filePath)) 
        { 
          // Add sub-directory. 
			debug(__line__,"MS#".$msno." "."ZIP: ".$L["Icon-Watchdog.INF_0059_ADD_FOLDER_TO_ZIP"]." ".$filePath,6);
          	$zipFile->addEmptyDir($localPath); 
          	self::folderToZip($filePath, $zipFile, $exclusiveLength); 
        } 
      } 
    } 
    closedir($handle); 
}

public static function zipDir($sourcePath, $outZipPath) 
  {
  	global $L,$msno;
    $z = new ZipArchive(); 
    $z->open($outZipPath, ZIPARCHIVE::CREATE); 
    self::folderToZip($sourcePath, $z, strlen("$sourcePath/")); 
	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0065_COMPRESS_ZIP_WAIT"],5);
    $z->close(); 
  }

} 

function roundToPrevMin(\DateTime $dt, $precision = 5) 
{ 
    $s = $precision * 60; 
    $dt->setTimestamp($s * floor($dt->getTimestamp()/$s)); 
    return $dt; 
} 

function getDirContents($path) 
{
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    $files = array(); 
    foreach ($rii as $file)
        if (!$file->isDir())
            $files[] = $file->getPathname();
    return $files;
}

function secondsToTime($seconds) 
{
	global $L;
    $dtF = new \DateTime('@0');
    $dtT = new \DateTime("@$seconds");
	if ($seconds > 86400) return $dtF->diff($dtT)->format('%a '.$L["Icon-Watchdog.INF_0082_DAYS"].' %h:%i:%s '.$L["Icon-Watchdog.INF_0083_HOURS"]);
	if ($seconds > 3600)  return $dtF->diff($dtT)->format('%h:%I:%S '.$L["Icon-Watchdog.INF_0083_HOURS"]);
	if ($seconds > 60)    return $dtF->diff($dtT)->format('%i:%S '.$L["Icon-Watchdog.INF_0084_MINUTES"]);
                          return $dtF->diff($dtT)->format('%s '.$L["Icon-Watchdog.INF_0085_SECONDS"]);
}

function read_ms_tree ($folder)
{	
	global $L,$curl,$miniserver,$filetree,$msno,$prefix,$port;
	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0070_FUNCTION"]." read_ms_tree => ".$folder);
	sleep(.1);
	if ( substr($folder,-3) == "/./" || substr($folder,-4) == "/../" || substr($folder,0,6) == "/temp/" ) 
		{
			return;
		}
	$LoxURL  = $prefix.$miniserver['IPAddress'].":".$port."/dev/fslist".$folder;
    debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0071_URL_TO_READ"]." ".$LoxURL);
	curl_setopt($curl, CURLOPT_URL, $LoxURL);
	if(curl_exec($curl) === false)
	{
		debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0004_ERROR_EXEC_CURL"]." ".curl_error($curl),4);
		return;
	}	
	else
	{ 
		$read_data = curl_multi_getcontent($curl) or $read_data = ""; 
		$read_data = trim($read_data);
		if(stristr($read_data,'<html><head><title>error</title></head><body>') === FALSE && $read_data != "") 
		{
			if(stristr($read_data,'Directory empty')) 
			{
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0013_DIRECTORY_EMPTY"].": ".$folder,6);
				return;
			}
			else
			{
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0072_GOT_DATA_FROM_MS"]." ".$read_data);
			}
		}
	}
	foreach(explode("\n",$read_data) as $k=>$read_data_line)
	{
		$read_data_line = trim(preg_replace("/[\n\r]/","",$read_data_line));
		if(preg_match("/^d.*/i", $read_data_line))
		{
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0048_DIRECTORY_FOUND_IGNORE"]." ".$read_data_line);
		}
		else 
		{
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0069_FILE_FOUND"]." ".$read_data_line);
			if(preg_match("/^-\s*(\d*)\s([a-zA-z]{3})\s(\d{1,2})\s(\d{1,2}:\d{1,2})\s(.*)$/i", $read_data_line, $filename))
			{
				/*
				Array $filename[x]
				x=Value
				-------
				1=Size
				2=Month
				3=Day of month
				4=Time
				5=Filename
				*/
				if (preg_match("/^images.zip/i", $filename[5]) && $folder == "/web/" )
				{
					debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0035_IMAGES_ZIP_FOUND"]." ".$filename[5],5);
				}
				else
				{
					debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0046_IGNORING_FILE"]." ".$filename[5],6);
					continue;
				}


				$dtime = DateTime::createFromFormat("M d H:i", $filename[2]." ".$filename[3]." ".$filename[4]);
				$timestamp = $dtime->getTimestamp();
				if ($timestamp > time() )
				{
					// Filetime in future. As Loxone doesn't provide a year 
					// I guess the file was created last year or before and
					// subtract one year from the previously guessed filetime.
					$dtime = DateTime::createFromFormat("Y M d H:i", (date("Y") - 1)." ".$filename[2]." ".$filename[3]." ".$filename[4]);
					$timestamp = $dtime->getTimestamp();
					debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0023_FUTURE_TIMESTAMP"]." ".$folder.$filename[5],6);
				}
				debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0073_FILE_TIMESTAMP"]." ".date("d.m. H:i",$timestamp)." (".$timestamp.") ".$folder.$filename[5],6);
				
				if ($filename[1] == 0)
				{
					debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0014_ZERO_FILESIZE"]." ".$folder.$filename[5]." (".$filename[1]." Bytes)",5);
				}
				else
				{
					debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0074_EXTRACTED_NAME_FILE"]." ".$folder.$filename[5]." (".$filename[1]." Bytes)",6);
					array_push($filetree["name"], $folder.$filename[5]);
					array_push($filetree["size"], $filename[1]);
					array_push($filetree["time"], $timestamp);
				}
			}
			else
			{
				debug(__line__,"MS#".$msno." ".$L["ERRORS.ERR_0006_UNABLE_TO_EXTRACT_NAME"]." ".$read_data_line,4);
			}
		}
  	}
 	return $filetree;
 }

function create_clean_workdir_tmp($workdir_tmp)
{
	global $L,$msno;
	debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0049_DEBUG_DIRECTORY_EXISTS"]." -> ".$workdir_tmp);
	if (is_dir($workdir_tmp))
	{
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0010_DEBUG_YES"]." -> ".$L["Icon-Watchdog.INF_0019_DEBUG_DIRECTORY_DELETE"]." -> ".$workdir_tmp);
		@rrmdir($workdir_tmp);
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0022_DEBUG_DIRECTORY_CREATE"]." -> ".$workdir_tmp);
		@mkdir($workdir_tmp, 0777, true);
	}
	else
	{
		debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0011_DEBUG_NO"]." -> ".$L["Icon-Watchdog.INF_0022_DEBUG_DIRECTORY_CREATE"]." -> ".$workdir_tmp);
		@mkdir($workdir_tmp, 0777, true);
	}
	return;
}

/**
* Recursively move files from one directory to another
*
* @param String $src – Source of files being moved
* @param String $dest – Destination of files being moved
*/
function rmove($src, $dest)
{
	global $savedir_path,$bkpfolder,$L,$msno;
	// If source is not a directory stop processing
	if(!is_dir($src)) 
	{
		return false;
	}
	
	// If the destination directory does not exist create it
	if(!is_dir($dest)) 
	{
		if(!mkdir($dest)) 
		{
        	debug(__line__,"MS#".$msno." ".$L["LOGGING.LOGLEVEL3"].": ".$L["Icon-Watchdog.INF_0022_DEBUG_DIRECTORY_CREATE"]." => ".$dest,3);
			return false;
		}
	}
	
	// Open the source directory to read in files
	$i = new DirectoryIterator($src);
	foreach($i as $f) 
	{
		if($f->isFile()) 
		{
			// Keep filetime
			$dt = filemtime($f->getRealPath());
			debug(__line__,"MS#".$msno." "."Move file and set time ".$f->getRealPath()." => ". date("d.m. H:i",$dt),7);
  			rename($f->getRealPath(), "$dest/" .$f->getFilename());
  			touch("$dest/" . $f->getFilename(), $dt);
		} 
		else if(!$f->isDot() && $f->isDir()) 
		{
			rmove($f->getRealPath(), "$dest/$f");
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0019_DEBUG_DIRECTORY_DELETE"]." -> ".$f->getRealPath());
			rmdir($f->getRealPath());
		}
	}
	if ( $src != $savedir_path."/".$bkpfolder."/" ) 
	{
		if (is_file($src)) 
		{
			debug(__line__,"MS#".$msno." ".$L["Icon-Watchdog.INF_0020_DEBUG_DELETE_FILE"]." -> ".$src);
			unlink($src);
		}
	}
}

function rrmdir($dir) 
{
	global $L,$start,$watchstate_file,$msno;
	if (is_dir($dir)) 
	{
		if ( $msno != "" ) 
		{
				$msinfo = "MS#".$msno." ";
		}
		else
		{
				$msinfo	= "";
		}

		if (!is_writable($dir) ) 
		{
			debug(__line__,$msinfo.$L["ERRORS.ERR_0023_PERMISSON_PROBLEM"]." -> ".$dir,3);
			$runtime = microtime(true) - $start;
			sleep(3); // To prevent misdetection in createmsbackup.pl
			file_put_contents($watchstate_file, "");
			$log->LOGTITLE($L["ERRORS.ERR_004_DOWNLOAD_ABORTED_WITH_ERROR"]);
			LOGERR ($L["ERRORS.ERR_000_EXIT"]." ".$runtime." s");
			LOGEND ("");
			exit(1);
		}
		$objects = scandir($dir);
		foreach ($objects as $object) 
		{
			if ($object != "." && $object != "..") 
			{
				if (filetype($dir."/".$object) == "dir") 
			  	{
			  		rrmdir($dir."/".$object);
				}
			 	else 
			 	{
			 		debug(__line__,$msinfo.$L["Icon-Watchdog.INF_0020_DEBUG_DELETE_FILE"]." -> ".$dir."/".$object);
			 		unlink($dir."/".$object);
			 	}
			}
		}
		reset($objects);
		debug(__line__,$msinfo.$L["Icon-Watchdog.INF_0019_DEBUG_DIRECTORY_DELETE"]." -> ".$dir);
		rmdir($dir);
	}
}

function sort_by_mtime($file1,$file2) 
{
    $time1 = filemtime($file1);
    $time2 = filemtime($file2);
    if ($time1 == $time2) 
    {
        return 0;
    }
    return ($time1 > $time2) ? 1 : -1;
}

function get_free_space ( $path )
{
	$base=dirname($path);
	$free = @exec("if [ -d '".escapeshellcmd($path)."' ]; then df -k --output=avail '".escapeshellcmd($path)."' 2>/dev/null |grep -v Avail; fi");
	if ( $free == "" ) $free = @exec("if [ -d '".escapeshellcmd($base)."' ]; then df -k --output=avail '".escapeshellcmd($base)."' 2>/dev/null |grep -v Avail; fi");
	if ( $free == "" ) $free = "0";
	return $free*1024;
}

function cloud_requests_today($indata)
{
	if ( !isset($indata["date"]) ) return(false);
	if ( substr($indata["date"],0,8) == date("Ymd",time()) ) 
	{
		return($indata);
	}
	else
	{
		return(false);
	}
}
function generate_images_zip($ms)
{
	global $zipdir_path, $savedir_path, $L;
	$zipArchive = new ZipArchive;

	if ( is_readable ( $savedir_path."/ms_".$ms."/web/images.zip" ) )
	{
		@file_put_contents ("/tmp/ms".$ms."_images.zip.md5", md5_file ($savedir_path."/ms_".$ms."/web/images.zip"));
	}
	$zip = new ZipArchive();
	$ret = $zip->open($savedir_path."/ms_".$ms."/web/images.zip", ZipArchive::CREATE|ZipArchive::CREATE);
	if ($ret !== TRUE) {
		debug(__line__,str_replace("<ms>", $ms, $L["ERRORS.ERR_047_CREATE_ZIP_FAILED"])." $ret",4);
	} else {
		$options = array('remove_all_path' => TRUE);
		$zip->addGlob($zipdir_path."/ms_".$ms."/*.{svg,png}", GLOB_BRACE, $options);
		$zip->close();
	}
	return;
}
