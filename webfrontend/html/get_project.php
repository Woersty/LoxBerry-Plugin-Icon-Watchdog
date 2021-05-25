<?php
// LoxBerry Icon-Watchdog Plugin
// Christian Woerstenfeld - git@loxberry.woerstenfeld.de

// Include System Lib
require_once "loxberry_system.php";
require_once "loxberry_log.php";

$plugin_config_file 	= $lbpconfigdir."/Icon-Watchdog.cfg";        # Plugin config
$logfileprefix			= LBPLOGDIR."/Icon-Watchdog_Project_Downloader_";
$logfilesuffix			= ".txt";
$logfilename			= $logfileprefix.date("Y-m-d_H\hi\ms\s_",time()).rand(0, 1000).$logfilesuffix;
$L						= LBSystem::readlanguage("language.ini");

$params = [
    "name" => $L["LOGGING.LOG_001_LOGFILE_NAME"],
    "filename" => $logfilename,
    "addtime" => 1];

$log = LBLog::newLog ($params);
LOGSTART ($L["Icon-Watchdog.INF_0102_PROJECT_DOWNLOAD_REQUEST"]);

// Error Reporting 
error_reporting(E_ALL);     
ini_set("display_errors", false);
ini_set("log_errors", 1);

$msno = "";
if ( !isset($_REQUEST["ms"]) ) 
{
	$result["ms0"] = array("ms" => 0,"success" => false, "error" => true, "message" => $L["ERRORS.ERR_056_MS_ID_MISSING"]); 
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode($result, JSON_UNESCAPED_SLASHES);
	$log->LOGTITLE($L["ERRORS.ERR_056_MS_ID_MISSING"]);
	LOGERR ($L["ERRORS.ERR_056_MS_ID_MISSING"]);
	LOGEND ("");
	exit;
}
else
{
	$msno = intval($_REQUEST["ms"]);
	$log->LOGTITLE("MS#".$msno." ".$L["Icon-Watchdog.INF_0102_PROJECT_DOWNLOAD_REQUEST"]);
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
				LOGDEB ($L["LOGGING.LOG_009_CONFIG_PARAM"]." ".$config_line[0]."=".$plugin_cfg[$config_line[0]]);
			}
		  }
		}
	  }
	  fclose($plugin_cfg_handle);
	}
	else
	{
		LOGWARN ($L["ERRORS.ERR_002_ERROR_READING_CFG"]);
		LOGEND ("");
		exit;
	}

	# Check if Plugin is disabled
	if ( $plugin_cfg["IWD_USE"] == "on" || $plugin_cfg["IWD_USE"] == "1" )
	{
		LOGINF ($L["LOGGING.LOG_010_PLUGIN_ENABLED"]);
	}
	else
	{
		$log->LOGTITLE($L["LOGGING.LOG_011_PLUGIN_DISABLED"]);
		LOGINF ($L["LOGGING.LOG_011_PLUGIN_DISABLED"]);
		LOGEND ("");
		exit;
	}

	if ( $plugin_cfg["MS_MONITOR_CB".$msno] == "on" || $plugin_cfg["MS_MONITOR_CB".$msno] == "1" )
	{
		LOGOK ("MS#".$msno." ".$L["Icon-Watchdog.INF_0005_MS_MONITORING_ENABLED"]);
	}
	else
	{
		$log->LOGTITLE("MS#".$msno." ".$L["Icon-Watchdog.INF_0006_MS_MONITORING_DISABLED"]);
		LOGINF ("MS#".$msno." ".$L["Icon-Watchdog.INF_0006_MS_MONITORING_DISABLED"]);
		LOGEND ("");
		exit;
	}

	$project_files = glob("$lbpdatadir/project/ms_$msno/".$L["GENERAL.PREFIX_CONVERTED_FILE"]."*.Loxone", GLOB_NOSORT);
	if ( isset($project_files) )
	{
		usort($project_files, function($a, $b) {return filemtime($b) - filemtime($a);});
		if (isset($project_files[0]))
		{
			if ( is_readable($project_files[0]) && isset($_REQUEST["getfile"]) )
			{
				if(false !== ($handler = fopen($project_files[0], 'r')))
				{
					header('Content-Description: File Transfer');
					header('Content-Type: application/octet-stream');
					header('Content-Disposition: attachment; filename='.basename($project_files[0]));
					header('Content-Transfer-Encoding: binary');
					header('Expires: 0');
					header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
					header('Pragma: public');
					header('Content-Length: ' . filesize($project_files[0])); 
					//Send the content in chunks

					$chunk_size = 4096;
					ignore_user_abort();
					while ($chunk = fread($handler, $chunk_size)) 
					{
						echo $chunk;
					}
				}
				$log->LOGTITLE("MS#".$msno." ".$L["Icon-Watchdog.INF_0105_PROJECT_DOWNLOAD_OK"]);
				LOGINF ("MS#".$msno." ".$L["Icon-Watchdog.INF_0105_PROJECT_DOWNLOAD_OK"]);
				LOGEND ("");
				exit;
			}
			$projectfilelink = basename($project_files[0]);
			$result["ms$msno"] = array(
				"ms" => $msno,
				"projectfilelink" => $projectfilelink,
				"error" => false,
				"success" => true,
				"message" => "MS#".$msno." ".$L["Icon-Watchdog.INF_0103_PROJECT_LINK_OK"]." ".$projectfilelink
			);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_SLASHES);
			LOGOK ("MS#".$msno." ".$L["Icon-Watchdog.INF_0103_PROJECT_LINK_OK"]." ".$projectfilelink);
			$log->LOGTITLE("MS#".$msno." ".$L["Icon-Watchdog.INF_0103_PROJECT_LINK_OK"]." ".$projectfilelink);
			LOGEND ("");
			exit;
		}
		else
		{
			$result["ms$msno"] = array(
					"ms" => $msno,
					"success" => false,
					"error" => true,
					"message" => "MS#".$msno." ".$L["ERRORS.ERR_043_GET_NO_PROJECT_DATA"].$msno
				);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_SLASHES);
			LOGERR ("MS#".$msno." ".$L["ERRORS.ERR_043_GET_NO_PROJECT_DATA"].$msno);
			$log->LOGTITLE("MS#".$msno." ".$L["ERRORS.ERR_043_GET_NO_PROJECT_DATA"].$msno);
			LOGEND ("");
			exit;
		}
	}
	else
	{
		$result["ms$msno"] = array(
					"ms" => $msno,
					"success" => false,
					"error" => true,
					"message" => "MS#".$msno." ".$L["ERRORS.ERR_042_NO_PROJECT_FILE"]
				);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($result, JSON_UNESCAPED_SLASHES);
		LOGERR ("MS#".$msno." ".$L["ERRORS.ERR_042_NO_PROJECT_FILE"]);
		$log->LOGTITLE("MS#".$msno." ".$L["ERRORS.ERR_042_NO_PROJECT_FILE"]);
		LOGEND ("");
		exit;
	}
}
exit;
