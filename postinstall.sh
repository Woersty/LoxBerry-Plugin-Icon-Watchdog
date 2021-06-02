#!/bin/sh
ARGV3=$3 # Third argument is Plugin installation folder
ARGV5=$5 # Fifth argument is Base folder of LoxBerry

echo "<INFO> Extract example icons"
/bin/tar --skip-old-files -xzvf $ARGV5/data/plugins/$ARGV3/svg/svgs.tgz -C  $ARGV5/data/plugins/$ARGV3/svg
/bin/rm -f $ARGV5/data/plugins/$ARGV3/svg/svgs.tgz 

exit 0
