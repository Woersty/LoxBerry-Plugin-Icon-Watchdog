<?php
// LoxBerry Icon-Watchdog Plugin
// Christian Woerstenfeld - git@loxberry.woerstenfeld.de
// Include System Lib
ignore_user_abort();
require_once "loxberry_system.php";
require_once "loxberry_log.php";

$plugin_config_file 	= $lbpconfigdir."/Icon-Watchdog.cfg";        # Plugin config
$logfileprefix			= LBPLOGDIR."/Icon-Watchdog_SVG_View";
$logfilesuffix			= ".txt";
$logfilename			= $logfileprefix.$logfilesuffix;
$L						= LBSystem::readlanguage("language.ini");

$params = [
    "name" 		=> $L["LOGGING.LOG_026_LOGFILE_NAME_SVG_VIEWER"],
    "filename" 	=> $logfilename,
    "addtime" 	=> 1];

// Error Reporting 
error_reporting(E_ALL);     
ini_set("display_errors", false);
ini_set("log_errors", 1);

(isset($_REQUEST["ms"]))?$msinfo="MS#".$_REQUEST["ms"]." ":$msinfo="";

$log = LBLog::newLog ($params);
LOGSTART ($msinfo.$L["Icon-Watchdog.INF_0110_SVG_VIEW_REQUEST"]);
$log->LOGTITLE($msinfo.$L["Icon-Watchdog.INF_0110_SVG_VIEW_REQUEST"]);

if ( isset($_REQUEST["base64svgfilename"]) && isset($_REQUEST["delete_icon"]) )
{
	$svgname = basename(utf8_encode(base64_decode($_REQUEST["base64svgfilename"])));
	if ( unlink("$lbpdatadir/svg/".$svgname ) )
	{
		$result = array(
				"success" => true,
				"error" => false,
				"refresh" => $svgname,
				"message" => str_replace(array("<file>"),array($svgname),$L["Icon-Watchdog.INF_0117_SVG_DELETION_OK"]));
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($result, JSON_UNESCAPED_SLASHES);
		LOGOK ($msinfo.str_replace(array("<file>"),array($svgname),$L["Icon-Watchdog.INF_0117_SVG_DELETION_OK"]));
		$log->LOGTITLE($msinfo.str_replace(array("<file>"),array($svgname),$L["Icon-Watchdog.INF_0117_SVG_DELETION_OK"]));
		LOGEND ("");
		exit;
	}
	else
	{
			$result = array(
		"success" => false,
		"error" => true,
		"message" => $msinfo.str_replace(array("<file>"),array($svgname),$L["Icon-Watchdog.ERR_062_SVG_DELETION_FAILED"]));
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($result, JSON_UNESCAPED_SLASHES);
		LOGWARN ($msinfo.str_replace(array("<file>"),array($svgname),$L["Icon-Watchdog.ERR_062_SVG_DELETION_FAILED"]));
		$log->LOGTITLE($msinfo.str_replace(array("<file>"),array($svgname),$L["Icon-Watchdog.ERR_062_SVG_DELETION_FAILED"]));
		LOGEND ("");
		exit;
	}
}

if ( isset($_REQUEST["svgfilename"]) && isset($_REQUEST["ms"]) && isset($_REQUEST["U"]) && isset($_REQUEST["Title"]) )
{
	$ms = intval($_REQUEST["ms"]);
	if (!is_dir("$lbpdatadir/zip/ms_$ms"))
	{
		@mkdir("$lbpdatadir/zip/ms_$ms", 0777, true);
	}
	if (is_dir("$lbpdatadir/zip/ms_$ms"))
	{
		if ( copy("$lbpdatadir/svg/".basename($_REQUEST["svgfilename"]), "$lbpdatadir/zip/ms_$ms/".strtolower(basename($_REQUEST["U"])).".svg") )
		{
			$result = array(
					"success" => true,
					"error" => false,
					"refresh" => "svg_icon_".$ms."_".strtolower(basename($_REQUEST["U"])),
					"refresh_data" => base64_encode(file_get_contents("$lbpdatadir/zip/ms_$ms/".strtolower(basename($_REQUEST["U"])).".svg")),
					"message" => str_replace(array("<file>","<title>","<id>"),array($_REQUEST["svgfilename"],utf8_encode(base64_decode($_REQUEST["Title"])),$_REQUEST["U"]),$L["Icon-Watchdog.INF_0112_SVG_ASSIGNMENT_OK"]));
			echo json_encode($result, JSON_UNESCAPED_SLASHES);
			LOGOK ($msinfo.str_replace(array("<file>","<title>","<id>"),array($_REQUEST["svgfilename"],utf8_encode(base64_decode($_REQUEST["Title"])),$_REQUEST["U"]),$L["Icon-Watchdog.INF_0112_SVG_ASSIGNMENT_OK"]));
			$log->LOGTITLE($msinfo.str_replace(array("<file>","<title>","<id>"),array($_REQUEST["svgfilename"],utf8_encode(base64_decode($_REQUEST["Title"])),$_REQUEST["U"]),$L["Icon-Watchdog.INF_0112_SVG_ASSIGNMENT_OK"]));
			LOGEND ("");
			exit;
		}
	}
	$result = array(
		"success" => false,
		"error" => true,
		"message" => $L["ERRORS.ERR_061_SVG_ASSIGNMENT_FAILED"]
	);
	echo json_encode($result, JSON_UNESCAPED_SLASHES);
	LOGERR ($msinfo.$L["ERRORS.ERR_061_SVG_ASSIGNMENT_FAILED"]);
	$log->LOGTITLE($msinfo.$L["ERRORS.ERR_061_SVG_ASSIGNMENT_FAILED"]);
	LOGEND ("");
	exit;
}	

$svg_files = glob("$lbpdatadir/svg/*.svg");
if ( isset($svg_files) )
{
	$i = 0;
	$svgs = array();
	foreach ( $svg_files as $svg_file )
	{
		$i++;
		if ( is_readable($svg_file) )
		{
			array_push($svgs,array ( "name" => basename($svg_file), "image" => base64_encode( file_get_contents($svg_file) ) ) );
		}
	}
	if ( $i > 0 )
	{
		header('Content-Type: application/json; charset=utf-8');
		$result["svg"] = array(
				"svg_data" => $svgs,
				"success" => true,
				"error" => false, 
				"message" => str_replace("<number>",$i,$L["Icon-Watchdog.INF_0111_SVG_VIEW_PROCESSED"])
			);
		echo json_encode($result, JSON_UNESCAPED_SLASHES);
		LOGOK ($msinfo.str_replace("<number>",$i,$L["Icon-Watchdog.INF_0111_SVG_VIEW_PROCESSED"]));
		$log->LOGTITLE($msinfo.str_replace("<number>",$i,$L["Icon-Watchdog.INF_0111_SVG_VIEW_PROCESSED"]));
		LOGEND ("");
		exit;
	}
}
$result["svg"] = array(
			"svg_data" => array(),
			"success" => false,
			"error" => true, 
			"message" => $L["ERRORS.ERR_060_NO_SVG_TO_BROWSE"]
		);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($result, JSON_UNESCAPED_SLASHES);
LOGERR ($msinfo.$L["ERRORS.ERR_060_NO_SVG_TO_BROWSE"]);
$log->LOGTITLE($msinfo.$L["ERRORS.ERR_060_NO_SVG_TO_BROWSE"]);
LOGEND ("");
exit;
