<?php

# Get notifications in html format 
require_once "loxberry_system.php";
require_once "loxberry_log.php";
// Read language
$L = LBSystem::readlanguage("language.ini");
$plugin_config_file = $lbpconfigdir."/Icon-Watchdog.cfg";
$params = [
    "name" => $L["LOGGING.LOG_CONFIG_HANDLER"]." "
];
$log = LBLog::newLog ($params);
LOGSTART ($L["LOGGING.LOG_012_CFG_HANDLER_CALLED"]);

// Error Reporting 
error_reporting(E_ALL);     
ini_set("display_errors", false);        
ini_set("log_errors", 1);

$summary			= array();
$output 			= "";

function debug($message = "", $loglevel = 7)
{
	global $L, $plugindata, $summary;
	if ( $plugindata['PLUGINDB_LOGLEVEL'] >= intval($loglevel) )  
	{
		$message = str_ireplace('"','',$message); // Remove quotes => https://github.com/mschlenstedt/Loxberry/issues/655
		switch ($loglevel)
		{
		    case 0:
		        // OFF
		        break;
		    case 1:
		        error_log(          "<ALERT> PHP: ".$message );
				array_push($summary,"<ALERT> PHP: ".$message);
		        break;
		    case 2:
		        error_log(          "<CRITICAL> PHP: ".$message );
				array_push($summary,"<CRITICAL> PHP: ".$message);
		        break;
		    case 3:
		        error_log(          "<ERROR> PHP: ".$message );
				array_push($summary,"<ERROR> PHP: ".$message);
		        break;
		    case 4:
		        error_log(          "<WARNING> PHP: ".$message );
				array_push($summary,"<WARNING> PHP: ".$message);
		        break;
		    case 5:
		        error_log( "<OK> PHP: ".$message );
		        break;
		    case 6:
		        error_log( "<INFO> PHP: ".$message );
		        break;
		    case 7:
		    default:
		        error_log( "PHP: ".$message );
		        break;
		}
	}
	return;
}

// Plugindata
$plugindata = LBSystem::plugindata();
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
    	    LOGINF($L["LOGGING.LOG_009_CONFIG_PARAM"]." ".$config_line[0]."=".$plugin_cfg[$config_line[0]]);
    	}
      }
    }
  }
  fclose($plugin_cfg_handle);
}
else
{
  touch($plugin_config_file);
}

foreach ($_REQUEST as $config_key => $config_value)
{
	$plugin_cfg[strtoupper($config_key)] = $config_value;
}

$plugin_cfg["VERSION"] = LBSystem::pluginversion();

ksort($plugin_cfg);
$plugin_cfg_handle = fopen($plugin_config_file, 'w');

$lbversion = LBSystem::lbversion();
if (version_compare($lbversion, '2.0.2.0') >= 0) 
{
	LOGINF("Version >= 2.0.2.0 (".$lbversion.")");
	LBSystem::get_miniservers();
}
else
{
	LOGINF("Version < 2.0.2.0 (".$lbversion.")");
	LBSystem::read_generalcfg();
}

$ms = $miniservers;

if (!is_array($ms)) 
{
	LOGERR($L["ERRORS.ERR_003_NO_MINISERVERS_CONFIGURED"]);
	LOGEND("");
	die($L["ERRORS.ERR_003_NO_MINISERVERS_CONFIGURED"]);
}
$max_ms = max(array_keys($ms));
if (flock($plugin_cfg_handle, LOCK_EX)) 
{ // exklusive Sperre
    ftruncate($plugin_cfg_handle, 0); // kürze Datei
	fwrite($plugin_cfg_handle, "[IWD]\r\n");
	foreach ($plugin_cfg as $config_key => $config_value)
	{
		if ( filter_var($config_key, FILTER_SANITIZE_NUMBER_INT) > $max_ms )
		{
			# This MS doesn't exists anymore, do not write into config file.
			LOGWARN($L["ERRORS.ERR_007_REMOVE_PARAMETER_FROM_CONFIG"]." ".$config_key . '="' . $config_value);
		}
		else
		{
			LOGINF($L["LOGGING.LOG_013_CONFIG_PARAM_WRITTEN"]. " ". $config_key. "=" . $config_value );
			$written = fwrite($plugin_cfg_handle, $config_key . '="' . $config_value .'"'."\r\n");
			if ( !$written )
				{
					$output .= "show_error('".$L["ERRORS.ERR_008_ERROR_WRITE_CONFIG"]." => ".$config_key."');\n";
					if ( substr(strtolower($config_key),0,7) == "iwd_use" )
					{
						$output .= "$('#".strtolower($config_key)."-button').css('background-color','#FFC0C0');\n";
					}
					else
					{
						$output .= "$('#".strtolower($config_key)."').css('background-color','#FFC0C0');\n";
					}
				}
				else
				{
					if ( substr(strtolower($config_key),0,7) == "iwd_use" )
					{
						$output .= "$('#".strtolower($config_key)."-button').css('background-color','#C0FFC0');\n";
						$output .= "setTimeout( function() { $('#".strtolower($config_key)."-button').css('background-color',''); checkStatus();}, 500);\n";
					}
					else
					{
						$output .= "$('#".strtolower($config_key)."').css('background-color','#C0FFC0');\n";
						$output .= "setTimeout( function() { $('#".strtolower($config_key)."').css('background-color',''); checkStatus();}, 500);\n";
					}
				}
		}
	}
    fflush($plugin_cfg_handle); // leere Ausgabepuffer bevor die Sperre frei gegeben wird
    flock($plugin_cfg_handle, LOCK_UN); // Gib Sperre frei
} 
else 
{
	$output .= "show_error('".$L["ERRORS.ERR_008_ERROR_WRITE_CONFIG"]."');\n";
	LOGERR($L["ERRORS.ERR_008_ERROR_WRITE_CONFIG"]);
}
fclose($plugin_cfg_handle);
$all_interval_used = 0;
foreach ($plugin_cfg as $config_key => $config_value)
{
	#If at least one task is configured, set cronjob
	if ( strpos($config_key, 'MONITOR_ACTIVE_MS_') !== false && intval($config_value) > 0 ) 
	{
		$all_interval_used = 1;
	}
}
#Create Cron-Job
if ( $all_interval_used > 0 )
{
	if ( ! is_link(LBHOMEDIR."/system/cron/cron.hourly/".LBPPLUGINDIR)  )
	{
		@symlink(LBPHTMLAUTHDIR."/bin/watch.pl", LBHOMEDIR."/system/cron/cron.hourly/".LBPPLUGINDIR);
	}
		
	if ( ! is_link(LBHOMEDIR."/system/cron/cron.hourly/".LBPPLUGINDIR) )
	{
		LOGERR($L["ERRORS.ERR_009_ERR_CFG_CRON_JOB"]);	
	}
	else
	{
		LOGINF($L["LOGGING.LOG_014_INFO_CRON_JOB_ACTIVE"]);	
	}
}
else
{
	if ( is_link(LBHOMEDIR."/system/cron/cron.hourly/".LBPPLUGINDIR) )
	{
		unlink(LBHOMEDIR."/system/cron/cron.hourly/".LBPPLUGINDIR) or LOGERR($L["ERRORS.ERR_009_ERR_CFG_CRON_JOB"]);
	}
	LOGINF($L["LOGGING.LOG_015_INFO_CRON_JOB_STOPPED"]);	
}
echo $output;
LOGEND("");
