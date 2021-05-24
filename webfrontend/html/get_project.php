<?php
// LoxBerry Icon-Watchdog Plugin
// Christian Woerstenfeld - git@loxberry.woerstenfeld.de

// Include System Lib
require_once "loxberry_system.php";
require_once "loxberry_log.php";

$logfileprefix			= LBPLOGDIR."/Icon-Watchdog_Project_Downloader_";
$logfilesuffix			= ".txt";
$logfilename			= $logfileprefix.date("Y-m-d_H\hi\ms\s",time()).$logfilesuffix;
$L						= LBSystem::readlanguage("language.ini");

$params = [
    "name" => $L["LOGGING.LOG_001_LOGFILE_NAME"],
    "filename" => $logfilename,
    "addtime" => 1];

$log = LBLog::newLog ($params);
LOGSTART ($L["Icon-Watchdog.INF_0102_PROJECT_DOWNLOAD_REQUEST"]);
$log->LOGTITLE($L["Icon-Watchdog.INF_0102_PROJECT_DOWNLOAD_REQUEST"]);

// Error Reporting 
error_reporting(E_ALL);     
ini_set("display_errors", true);
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
	$project_files = glob("$lbpdatadir/project/ms_$msno/".$L["GENERAL.PREFIX_CONVERTED_FILE"]."*.Loxone", GLOB_NOSORT);
	if ( isset($project_files) )
	{
		usort($project_files, function($a, $b) {return filemtime($b) - filemtime($a);});
		if ($project_files[0])
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
					while(false !== ($chunk = fread($handler,4096)))
					{
						echo $chunk;
					}
				}
				exit;
			}
			$projectfilelink = basename($project_files[0]);
			$result["ms$msno"] = array(
				"ms" => $msno,
				"projectfilelink" => $projectfilelink,
				"error" => false,
				"success" => true,
				"message" => $L["Icon-Watchdog.INF_0103_PROJECT_LINK_OK"]." ".$projectfilelink
			);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_SLASHES);
			LOGOK ($L["Icon-Watchdog.INF_0103_PROJECT_LINK_OK"]);
			$log->LOGTITLE($L["Icon-Watchdog.INF_0103_PROJECT_LINK_OK"]);
			LOGEND ("");
			exit;
		}
		else
		{
			$result["ms$msno"] = array(
					"ms" => $msno,
					"success" => false,
					"error" => true,
					"message" => $L["ERRORS.ERR_043_GET_NO_PROJECT_DATA"].$msno
				);
			header('Content-Type: application/json; charset=utf-8');
			echo json_encode($result, JSON_UNESCAPED_SLASHES);
			LOGERR ($L["ERRORS.ERR_043_GET_NO_PROJECT_DATA"]);
			$log->LOGTITLE($L["ERRORS.ERR_043_GET_NO_PROJECT_DATA"].$msno);
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
					"message" => $L["ERRORS.ERR_042_NO_PROJECT_FILE"]
				);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($result, JSON_UNESCAPED_SLASHES);
		LOGERR ($L["ERRORS.ERR_042_NO_PROJECT_FILE"]);
		$log->LOGTITLE($L["ERRORS.ERR_042_NO_PROJECT_FILE"]);
		LOGEND ("");
		exit;
	}
}
exit;
