<?php
// Include System Lib
ignore_user_abort();
require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "import.php";
$logfileprefix			= LBPLOGDIR."/Icon-Watchdog_uploader_";
$logfilesuffix			= ".txt";
$logfilename			= $logfileprefix.date("Y-m-d_H\hi\ms\s",time()).$logfilesuffix;
$L						= LBSystem::readlanguage("language.ini");

$params = [
    "name" => $L["LOGGING.LOG_022_LOGFILE_UPLOADER_NAME"],
    "filename" => $logfilename,
    "addtime" => 1];

// Error Reporting 
error_reporting(E_ALL);     
ini_set("display_errors", false);
ini_set("log_errors", 1);
//file_put_contents("/tmp/.htaccess", "php_value upload_max_filesize 20M\nphp_value post_max_size 20M");
$log = LBLog::newLog ($params);
$date_time_format       = "m-d-Y h:i:s a";						 # Default Date/Time format
if (isset($L["GENERAL.DATE_TIME_FORMAT_PHP"])) $date_time_format = $L["GENERAL.DATE_TIME_FORMAT_PHP"];
if ( isset($_REQUEST["ms"]) )
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

// Depending on your server, you might have to use $_POST instead of $_REQUEST.
	if (isset($_REQUEST["action"]) && $_REQUEST["action"] === "fileuploader" && isset($_REQUEST["ms"]) )
	{
		$ms=intval($_REQUEST["ms"]);
		$log->LOGTITLE(str_replace("<ms>",$ms,$L["LOGGING.LOG_025_UPLOAD_STARTED"]));
		header("Content-Type: application/json; charset=UTF-8");
		if ($ms) 
		{
			$allowedexts = array(
				"svg" => true,
				"loxone" => true,
			);
		 
			$files = FancyFileUploaderHelper::NormalizeFiles("files");
			if (!isset($files[0]))  $result = array("success" => false, "error" => $L["ERRORS.ERR_018_BAD_INPUT"], "errorcode" => "bad_input");
			else if (!$files[0]["success"])  $result = $files[0];
			else if (!isset($allowedexts[strtolower($files[0]["ext"])]))
			{
				$result = array(
					"success" => false,
					"error" => str_ireplace('<allowed_ext>',implode(", ", array_keys($allowedexts)),$L["ERRORS.ERR_016_ERR_INVALID_FILEEXT"]),
					"errorcode" => "invalid_file_ext"
				);
			}
			else
			{
				// For chunked file uploads, get the current filename and starting position from the incoming headers.
				$name = FancyFileUploaderHelper::GetChunkFilename();
				if ($name !== false)
				{
					$startpos = FancyFileUploaderHelper::GetFileStartPosition();
	 
					// [Do stuff with the file chunk.]
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
							LOGINF  ("<INFO>".str_ireplace('<file>',$files[0]["name"],$L["LOGGING.LOG_020_UPLOAD_SUCCESS"])." [".$files[0]["name"]."]");
							LOGINF  ("<INFO>".$L["LOGGING.LOG_023_IMPORT_STARTED"]);
							$project = import_loxone_project("$lbpdatadir/project/ms_$ms/".$files[0]["name"],$ms);
							LOGINF  ("<INFO>".$L["LOGGING.LOG_024_IMPORT_DONE"]);
							error_reporting(E_ALL  & ~E_NOTICE ); 
							if ( $project['error'] )
							{
								$result = array(
								"success" => false,
								"error" => $project['error'],
								"errorcode" => $project['errorcode']
								);
							}
							else
							{
								sleep(1);
								file_put_contents("$lbpdatadir/project/ms_$ms/".$L["GENERAL.PREFIX_JSON_FILE"].$ms.".json", $project['json']);
								file_put_contents("$lbpdatadir/project/ms_$ms/".$L["GENERAL.PREFIX_CONVERTED_FILE"].$files[0]["name"], $project['xml']);
								chmod("$lbpdatadir/project/ms_$ms/".$L["GENERAL.PREFIX_CONVERTED_FILE"].$files[0]["name"], 0666);
								chmod("$lbpdatadir/project/ms_$ms/".$L["GENERAL.PREFIX_JSON_FILE"].$ms.".json", 0666);
								$result = array(
									//"project_as_json" => $project['json'],
									//"project_as_pretty" => $project['pretty'],
									"success" => true,
									"message" => str_ireplace('<file>',$files[0]["name"],$L["LOGGING.LOG_020_UPLOAD_SUCCESS"])
								);
							}
							error_reporting(E_ALL ); 

						}					
						else
						{
							$result = array(
								"success" => false,
								"error" => $L["ERRORS.ERR_017_ERR_CREATE_UPLOAD_DIR"],
								"errorcode" => "create_upload_dir"
							);
						}
						
					}
					else if ( strtolower($files[0]["ext"]) == "svg" )
					{
						if (!is_dir("$lbpdatadir/zip/ms_$ms/"))
						{
							@mkdir("$lbpdatadir/zip/ms_$ms/", 0777, true);
						}
						
						if (is_dir("$lbpdatadir/zip/ms_$ms/"))
						{
							rename($files[0]["file"], "$lbpdatadir/zip/ms_$ms/".strtolower($files[0]["name"]));
							LOGINF  ("<INFO>".str_ireplace('<file>',$files[0]["name"],$L["LOGGING.LOG_020_UPLOAD_SUCCESS"])." [".$files[0]["name"]."]");
						
							$result = array(
								"success" => true,
								"message" => str_ireplace('<file>',$files[0]["name"],$L["LOGGING.LOG_020_UPLOAD_SUCCESS"])
							);
						}					
						else
						{
							$result = array(
								"success" => false,
								"error" => $L["ERRORS.ERR_017_ERR_CREATE_UPLOAD_DIR"],
								"errorcode" => "create_upload_dir"
							);
						}
					}
					else
					{	
							$result = array(
							"success" => false,
							"error" => $L["ERRORS.ERR_044_ERROR_IN_UPLOADER"],
							"errorcode" => "uploader_error_1_".strtolower($files[0]["ext"])
							);
					}
				}
			}
		}
		else		
		{
			$result = array(
				"success" => false,
				"error" => "Invalid MS fixme",
				"errorcode" => "invalid_file_ext"
				);
		}
	}
	else
	{
			$result = array(
			"success" => false,
			"error" => $L["ERRORS.ERR_044_ERROR_IN_UPLOADER"],
			"errorcode" => "uploader_error_2"
		);
	}	
echo json_encode($result, JSON_UNESCAPED_SLASHES);
if (isset($result["error"]))
{
	LOGERR ($result["error"]);
	$log->LOGTITLE($result["error"]);
}
else
{
	LOGOK(str_replace("<ms>",$ms,$L["LOGGING.LOG_026_UPLOAD_DONE"]));
	$log->LOGTITLE(str_replace("<ms>",$ms,$L["LOGGING.LOG_026_UPLOAD_DONE"]));
}
LOGEND();
exit();
