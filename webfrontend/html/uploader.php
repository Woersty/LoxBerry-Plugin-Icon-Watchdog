<?php
// Include System Lib
ignore_user_abort();
require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "import.php";
$logfileprefix	= LBPLOGDIR."/Icon-Watchdog_uploader_";
$logfilesuffix	= ".txt";
$logfilename	= $logfileprefix.date("Y-m-d_H\hi\ms\s",time()).$logfilesuffix;
$L				= LBSystem::readlanguage("language.ini");

$params = [
    "name" 		=> $L["LOGGING.LOG_022_LOGFILE_UPLOADER_NAME"],
    "filename" 	=> $logfilename,
    "addtime" 	=> 1];

// Error Reporting 
error_reporting(E_ALL);     
ini_set("display_errors", false);
ini_set("log_errors"	, 1);

$log 				= LBLog::newLog ($params);
$date_time_format	= "m-d-Y h:i:s a"; # Default Date/Time format
if (isset($L["GENERAL.DATE_TIME_FORMAT_PHP"])) $date_time_format = $L["GENERAL.DATE_TIME_FORMAT_PHP"];
if (isset($_REQUEST["ms"]))
{
	LOGSTART(str_replace("<ms>",$_REQUEST["ms"],$L["LOGGING.LOG_025_UPLOAD_STARTED"]));
}
else
{
	LOGSTART ($L["GENERAL.TXT_STATUS_WORKING"]);
}

function Translate($string)
{
	// LoxBerry Translation for fancy_file_uploader_helper.php
	global $L;
	$translation = ($L["LANGMAP_PHP.".$string]) ? $L["LANGMAP_PHP.".$string] : $string;
	return $translation;
}

define("CS_TRANSLATE_FUNC", "Translate");
require_once "fancy_file_uploader_helper.php";

if ( isset($_REQUEST["action"]) && $_REQUEST["action"] === "fileuploader" && isset($_REQUEST["ms"]) )
{
	$ms=intval($_REQUEST["ms"]);
	$log->LOGTITLE(str_replace("<ms>",$ms,$L["LOGGING.LOG_025_UPLOAD_STARTED"]));
	if ($ms) 
	{
		$allowedexts = array(
			"svg" 		=> true,
			"loxone" 	=> true,
		);
	 
		$files = FancyFileUploaderHelper::NormalizeFiles("files");
		if (!isset($files[0]))  $result = array("success" => false, "error" => $L["ERRORS.ERR_018_BAD_INPUT"], "errorcode" => "bad_input");
		else if (!$files[0]["success"])  $result = $files[0];
		else if (!isset($allowedexts[strtolower($files[0]["ext"])]))
		{
			$result = array(
				"success"	=> false,
				"error" 	=> str_ireplace('<allowed_ext>',implode(", ", array_keys($allowedexts)),$L["ERRORS.ERR_016_ERR_INVALID_FILEEXT"]),
				"errorcode" => "invalid_file_ext"
			);
		}
		else
		{
			// No chunked file uploads
			$name = FancyFileUploaderHelper::GetChunkFilename();
			if ($name !== false)
			{
					$result = array(
					"success" 	=> false,
					"error" 	=> str_replace("<code>",3,$L["ERRORS.ERR_044_ERROR_IN_UPLOADER"]),
					"errorcode" => "uploader_error_3");
			}
			else
			{
				if ( strtolower($files[0]["ext"]) == "loxone" )
				{
					if (!is_dir("$lbpdatadir/project/ms_$ms/"))
					{
						@mkdir("$lbpdatadir/project/ms_$ms/", 0777, true);
					}
					if (is_dir("$lbpdatadir/project/ms_$ms/"))
					{
						rename($files[0]["file"], "$lbpdatadir/project/ms_$ms/".$files[0]["name"]);
						LOGINF  ("<INFO> MS#$ms ".str_ireplace('<file>',$files[0]["name"],$L["LOGGING.LOG_020_UPLOAD_SUCCESS"]));
						LOGINF  ("<INFO> MS#$ms ".$L["LOGGING.LOG_023_IMPORT_STARTED"]);
						$project = import_loxone_project("$lbpdatadir/project/ms_$ms/".$files[0]["name"],$ms);
						LOGINF  ("<INFO> MS#$ms ".$L["LOGGING.LOG_024_IMPORT_DONE"]);
						error_reporting(E_ALL  & ~E_NOTICE ); 
						if ( $project['error'] )
						{
							$result = array(
							"success" 	=> false,
							"error" 	=> $project['error'],
							"errorcode" => $project['errorcode']);
						}
						else
						{
							file_put_contents("$lbpdatadir/project/ms_$ms/".$L["GENERAL.PREFIX_JSON_FILE"].$ms.".json", $project['json']);
							file_put_contents("$lbpdatadir/project/ms_$ms/".$L["GENERAL.PREFIX_CONVERTED_FILE"].$files[0]["name"], $project['xml']);
							chmod("$lbpdatadir/project/ms_$ms/".$L["GENERAL.PREFIX_CONVERTED_FILE"].$files[0]["name"], 0666);
							chmod("$lbpdatadir/project/ms_$ms/".$L["GENERAL.PREFIX_JSON_FILE"].$ms.".json", 0666);
							$result = array(
								"success" => true,
								"message" => str_ireplace('<file>',$files[0]["name"],$L["LOGGING.LOG_020_UPLOAD_SUCCESS"]));
						}
						error_reporting(E_ALL ); 
					}					
					else
					{
						$result = array(
							"success" 	=> false,
							"error" 	=> $L["ERRORS.ERR_017_ERR_CREATE_UPLOAD_DIR"],
							"errorcode" => "create_upload_dir");
					}
				}
				else if ( strtolower($files[0]["ext"]) == "svg" )
				{
					if (!is_dir("$lbpdatadir/svg"))
					{
						@mkdir("$lbpdatadir/svg", 0777, true);
					}
					if (is_dir("$lbpdatadir/svg"))
					{
						$targetfile = file_get_contents($files[0]["file"]);
						if ( strpos($targetfile, 'viewBox="0 0 32 32"') || strpos($targetfile, "viewBox='0 0 32 32'") )
						{
							// Valid viewBox found. Accept upload.
							$targetfile = str_ireplace(array("script","link"),array("",""),$targetfile);
							file_put_contents("$lbpdatadir/svg/".strtolower($files[0]["name"]),$targetfile);
							$result = array(
								"success" => true,
								"message" => str_ireplace('<file>',strtolower($files[0]["name"]),$L["LOGGING.LOG_020_UPLOAD_SUCCESS"])
							);
						}
						else
						{
							$result = array(
							"success" 	=> false,
							"error" 	=> $L["ERRORS.ERR_059_INVALID_VIEWBOX"],
							"errorcode" => "invalid_viewbox");
						}
					}					
					else
					{
						$result = array(
							"success" 	=> false,
							"error" 	=> $L["ERRORS.ERR_017_ERR_CREATE_UPLOAD_DIR"],
							"errorcode" => "create_upload_dir");
					}
				}
				else
				{	
						$result = array(
						"success" 	=> false,
						"error" 	=> str_replace("<code>",1,$L["ERRORS.ERR_044_ERROR_IN_UPLOADER"]),
						"errorcode" => "uploader_error_1_".strtolower($files[0]["ext"]));
				}
			}
		}
	}
	else		
	{
		$result = array(
			"success" 	=> false,
			"error" 	=> $L["ERRORS.ERR_056_MS_ID_MISSING"],
			"errorcode" => "invalid_ms_id");
	}
}
else	
{
		$result = array(
		"success" 	=> false,
		"error" 	=> str_replace("<code>",2,$L["ERRORS.ERR_044_ERROR_IN_UPLOADER"]),
		"errorcode" => "uploader_error_2");
}

(isset($_REQUEST["ms"]))?$msinfo="MS#".$_REQUEST["ms"]." ":$msinfo="";

header("Content-Type: application/json; charset=UTF-8");
echo json_encode($result, JSON_UNESCAPED_SLASHES);
if (isset($result["error"]))
{
	LOGERR("<ERROR> $msinfo".$result["error"]);
	$log->LOGTITLE($result["error"]);
}
else
{
	LOGOK("<OK> $msinfo".$result["message"]);
	$log->LOGTITLE($msinfo.$result["message"]);
}
LOGEND();
exit;
