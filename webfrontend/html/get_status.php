<?php
$watchstate_file		= "/tmp/Icon-Watchdog-state.txt";
if ( is_file($watchstate_file) ) 
{
	// Unblock after max. running time of 45 s w/o state changes 
	if ( ( time() - filemtime( $watchstate_file ) ) > (45) ) 
	{
		file_put_contents($watchstate_file, "");
	}
	readfile($watchstate_file);
}
else
{
	file_put_contents($watchstate_file, "");
	echo "";
}
