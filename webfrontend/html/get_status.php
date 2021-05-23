<?php
$watchstate_file		= "/tmp/Icon-Watchdog-state.txt";
if ( is_file($watchstate_file) )
{
	readfile($watchstate_file);
}
else
{
	echo "";
}
