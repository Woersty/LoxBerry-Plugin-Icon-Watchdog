<?php
// Include System Lib
require_once "loxberry_system.php";
require_once "loxberry_log.php";
require_once "import.php";
$logfileprefix			= LBPLOGDIR."/Icon-Watchdog_";
$logfilesuffix			= ".txt";
$logfilename			= $logfileprefix.date("Y-m-d_H\hi\ms\s",time()).$logfilesuffix;
$L						= LBSystem::readlanguage("language.ini");

$params = [
    "name" => $L["LOGGING.LOG_001_LOGFILE_NAME"],
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
LOGSTART ($L["LOGGING.LOG_002_CHECK_STARTED"]);
$log->LOGTITLE("UPLOADER");

function Translate($string)
{
	// LoxBerry Translation for fancy_file_uploader_helper.php
	global $L;
	$translation = ($L["LANGMAP_PHP.".$string]) ? $L["LANGMAP_PHP.".$string] : $string;
	return $translation;
}
define("CS_TRANSLATE_FUNC", "Translate");
require_once "fancy_file_uploader_helper.php";

function ModifyUploadResult(&$result, $filename, $name, $ext, $fileinfo)
{
	// Add more information to the result here as necessary (e.g. a URL to the file that a callback uses to link to or show the file).
}
/*
	$options = array(
		"allowed_exts" => array("jpg", "png"),
		"filename" => __DIR__ . "/" . $id . ".{ext}",
//		"result_callback" => "ModifyUploadResult"
	);
*/
	//FancyFileUploaderHelper::HandleUpload("files", $options);

// Depending on your server, you might have to use $_POST instead of $_REQUEST.
	if (isset($_REQUEST["action"]) && $_REQUEST["action"] === "fileuploader" && isset($_REQUEST["ms"]) )
	{
		header("Content-Type: application/json; charset=UTF-8");
		$ms=intval($_REQUEST["ms"]);
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
					if (!is_dir("$lbpdatadir/ms_$ms/"))
					{
						@mkdir("$lbpdatadir/ms_$ms/", 0777, true);
					}
					
					if (is_dir("$lbpdatadir/ms_$ms/"))
					{
						rename($files[0]["file"], "$lbpdatadir/ms_$ms/".$files[0]["name"]);
						LOGINF  ("<INFO>".str_ireplace('<file>',$files[0]["name"],$L["LOGGING.LOG_020_UPLOAD_SUCCESS "]));
						$project = import_loxone_project("$lbpdatadir/ms_$ms/".$files[0]["name"]);
						file_put_contents("$lbpdatadir/ms_$ms/Convert_".$files[0]["name"], $project['xml']);
						file_put_contents("$lbpdatadir/ms_$ms/JSON_".$files[0]["name"], $project['json']);
						chmod("$lbpdatadir/ms_$ms/Convert_".$files[0]["name"], 0666);
						chmod("$lbpdatadir/ms_$ms/JSON_".$files[0]["name"], 0666);
						$result = array(
							"project_as_json" => $project['json'],
							"project_as_pretty" => $project['pretty'],
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
			}
		}
		else		
		{
			$result = array(
				"success" => false,
				"error" => str_ireplace('<allowed_ext>',implode(", ", array_keys($allowedexts)),$L["ERRORS.ERR_016_ERR_INVALID_FILEEXT"]),
				"errorcode" => "invalid_file_ext"
				);
		}
		echo json_encode($result, JSON_UNESCAPED_SLASHES);
		LOGEND ();
		exit();
	}	