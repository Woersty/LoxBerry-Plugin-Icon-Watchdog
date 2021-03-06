#!/bin/bash
ARGV0=$0 # Zero argument is shell command
ARGV1=$1 # First argument is temp folder during install
ARGV2=$2 # Second argument is Plugin-Name for scipts etc.
ARGV3=$3 # Third argument is Plugin installation folder
ARGV4=$4 # Forth argument is Plugin version
ARGV5=$5 # Fifth argument is Base folder of LoxBerry
shopt -s dotglob

echo "<INFO> Moving back existing config files"
/bin/mv -v /tmp/$ARGV1\_upgrade/config/* $ARGV5/config/plugins/$ARGV3/

echo "<INFO> Moving back existing log files"
/bin/mv -v /tmp/$ARGV1\_upgrade/log/* $ARGV5/log/plugins/$ARGV3/

echo "<INFO> Moving back existing compare files"
/bin/mv -v $ARGV5/data/plugins/tmp_data_iwd/* $ARGV5/data/plugins/$ARGV3/

echo "<INFO> Remove temporary folders"
/bin/rm -rf /tmp/$ARGV1\_upgrade
/bin/rm -rf $ARGV5/data/plugins/tmp_data_iwd

exit 0
