#!/bin/bash
ARGV0=$0 # Zero argument is shell command
ARGV1=$1 # First argument is temp folder during install
ARGV2=$2 # Second argument is Plugin-Name for scipts etc.
ARGV3=$3 # Third argument is Plugin installation folder
ARGV4=$4 # Forth argument is Plugin version
ARGV5=$5 # Fifth argument is Base folder of LoxBerry

 while :
 do
  if [ -r /tmp/Icon-Watchdog-state.txt ]
  then
    state=`head -c 1 /tmp/Icon-Watchdog-state.txt 2>/dev/null`
    if [[ "$state" = "" ]]  
    then
     echo "<OK> No check job seems in progress. Continue..."
     break
    else
     echo "<INFO> A check job seems in progress. Wait 10 s..."
     cat /tmp/Icon-Watchdog-state.txt
     echo
    fi
    sleep 10
  else
   echo "<OK> No check job running. Continue..."
   break
  fi
done

shopt -s dotglob

echo "<INFO> Backing up existing config files"
mkdir -p /tmp/$ARGV1\_upgrade/config
mv -v $ARGV5/config/plugins/$ARGV3/* /tmp/$ARGV1\_upgrade/config/

echo "<INFO> Backing up existing log files"
mkdir -p /tmp/$ARGV1\_upgrade/log
mv -v $ARGV5/log/plugins/$ARGV3/* /tmp/$ARGV1\_upgrade/log/

echo "<INFO> Backing up existing data files"
mkdir -p $ARGV5/data/plugins/tmp_data_iwd
mv -v $ARGV5/data/plugins/$ARGV3/* $ARGV5/data/plugins/tmp_data_iwd

# Exit with Status 0
exit 0
