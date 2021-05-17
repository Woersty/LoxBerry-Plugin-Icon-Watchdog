#!/usr/bin/perl

##########################################################################
# Modules
##########################################################################
use LoxBerry::System;
use LoxBerry::Log;
my $watchstate_tmp 				= "/tmp"."/"."Icon-Watchdog-state.txt";
my $log 						= LoxBerry::Log->new ( name => 'Icon-Watchdog (CronJob)' ); 
my %ERR 						= LoxBerry::System::readlanguage();
LOGSTART $ERR{'LOGGING.LOG_018_CRON_CALLED'};
# Complete rededign - from now it's PHP and not Perl anymore
my $output_string = `ps -ef | grep "$lbphtmldir/watch.php"|grep -v grep |wc -l 2>/dev/null`;
if ( -f $watchstate_tmp && int $output_string eq 0 )
{
	$data="";
	open my $fh, '<', $watchstate_tmp or LOGERR $ERR{'ERRORS.ERR_014_PROBLEM_WITH_STATE_FILE'};
	my $data = do { local $/; <$fh> };
	close $fh;
	if ( $data ne "-" )
	{
		notify( $lbpplugindir, $ERR{'GENERAL.MY_NAME'}, $ERR{'ERRORS.ERR_015_ERR_STATE_FILE_REINIT'}." ".$data,1);
		LOGWARN $ERR{'ERRORS.ERR_015_ERR_STATE_FILE_REINIT'}." ".$data;
		open(my $fh, '>', $watchstate_tmp) or exit;
		print $fh "-";
		close $fh;
	}
}
my $which = 0;
$which = @ARGV[1] if (@ARGV[1]);
system ("/usr/bin/php -f $lbphtmldir/watch.php ".@ARGV[0]." $which >/dev/null 2>&1 &" );
# Wait a second and check if PHP process is there
sleep 1;
my $output_string = `ps -ef | grep "$lbphtmldir/watch.php"|grep -v grep |wc -l 2>/dev/null`;
if ( int $output_string == 0 ) 
{
	notify( $lbpplugindir, $ERR{'GENERAL.MY_NAME'}, $ERR{'ERRORS.ERR_012_UNABLE_TO_INITIATE_CHECK'},1);
	LOGERR $ERR{'ERRORS.ERR_012_UNABLE_TO_INITIATE_CHECK'}; 
}
LOGEND ""; 
