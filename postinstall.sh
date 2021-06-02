#!/bin/sh
echo "<INFO> Extract example icons"
tar --skip-old-files -xzvf $ARGV5/data/plugins/$ARGV3/svg/svgs.tgz 
rm -f $ARGV5/data/plugins/$ARGV3/svg/svgs.tgz 
exit 0
