#!/usr/bin/perl

# Copyright 2020 Wörsty (git@loxberry.woerstenfeld.de)
# Licensed under the Apache License, Version 2.0 (the "License");
# you may not use this file except in compliance with the License.
# You may obtain a copy of the License at
# 
#     http://www.apache.org/licenses/LICENSE-2.0
# 
# Unless required by applicable law or agreed to in writing, software
# distributed under the License is distributed on an "AS IS" BASIS,
# WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
# See the License for the specific language governing permissions and
# limitations under the License.

use LoxBerry::System;
use LoxBerry::Web;
use LoxBerry::Log;
#use MIME::Base64;
#use List::MoreUtils 'true','minmax';
use HTML::Entities;
use CGI::Carp qw(fatalsToBrowser);
use CGI qw/:standard/;
use warnings;
use strict;
no  strict "refs"; 

# Variables
my %Config;
my $maintemplatefilename 		= "Icon-Watchdog.html";
my $errortemplatefilename 		= "error.html";
my $helptemplatefilename		= "help.html";
my $languagefile 				= "language.ini";
my $logfilename 				= "Icon-Watchdog_UI.log";
my $pluginconfigfile 			= "Icon-Watchdog.cfg";
my $watchstate_file				= $lbphtmldir."/"."Icon-Watchdog-state.txt";
my $template_title;
my $no_error_template_message	= "<b>Icon-Watchdog:</b> The error template is not readable. We must abort here. Please try to reinstall the plugin.";
my $version 					= LoxBerry::System::pluginversion();
my $helpurl 					= "http://www.loxwiki.eu/display/LOXBERRY/Icon-Watchdog";
my $log 						= LoxBerry::Log->new ( name => 'Icon-Watchdog', filename => $lbplogdir ."/". $logfilename, append => 1 );
my $error_message				= "";
my $ms_id;
my $miniservercount;
my %miniservers;

# Logging
my $plugin = LoxBerry::System::plugindata();

LOGSTART "New admin call."      if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$LoxBerry::System::DEBUG 	= 1 if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$LoxBerry::Web::DEBUG 		= 1 if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
$log->loglevel($plugin->{PLUGINDB_LOGLEVEL});

LOGDEB "Init CGI and import names in namespace R::";
my $cgi 	= CGI->new;
$cgi->import_names('R');

LOGDEB "Get language";
my $lang	= lblanguage();
LOGDEB "Resulting language is: " . $lang;

LOGDEB "Check, if filename for the errortemplate is readable";
stat($lbptemplatedir . "/" . $errortemplatefilename);
if ( !-r _ )
{
	LOGDEB "Filename for the errortemplate is not readable, that's bad";
	$error_message = $no_error_template_message;
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	print $error_message;
	LOGCRIT $error_message;
	LoxBerry::Web::lbfooter();
	LOGCRIT "Leaving Icon-Watchdog Plugin due to an unrecoverable error";
	LOGEND if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
	exit;
}

LOGDEB "Filename for the errortemplate is ok, preparing template";
my $errortemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $errortemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		associate => $cgi,
		%htmltemplate_options,
		debug => 1,
		);
LOGDEB "Read error strings from " . $languagefile . " for language " . $lang;
my %ERR = LoxBerry::System::readlanguage($errortemplate, $languagefile);

LOGDEB "Check, if filename for the maintemplate is readable, if not raise an error";
$error_message = $L{'ERRORS.ERR_MAIN_TEMPLATE_NOT_READABLE'};
stat($lbptemplatedir . "/" . $maintemplatefilename);
&error if !-r _;
LOGDEB "Filename for the maintemplate is ok, preparing template";
my $maintemplate = HTML::Template->new(
		filename => $lbptemplatedir . "/" . $maintemplatefilename,
		global_vars => 1,
		loop_context_vars => 1,
		die_on_bad_params=> 0,
		%htmltemplate_options,
		debug => 1
		);
LOGDEB "Read main strings from " . $languagefile . " for language " . $lang;
my %L = LoxBerry::System::readlanguage($maintemplate, $languagefile);


$maintemplate->param( "LBPPLUGINDIR" , $lbpplugindir);

LOGDEB "Call default page";
if (!-r $lbphtmldir."/".".htaccess") 
{
open my $htaccess, ">", $lbphtmldir . "/" . ".htaccess";
	print $htaccess "php_value upload_max_filesize 20M\r\n";
	print $htaccess "php_value post_max_size 30M\r\n";
close $htaccess;
}
&defaultpage;

#####################################################
# Subs
#####################################################

sub defaultpage 
{
	stat($lbpconfigdir . "/" . $pluginconfigfile);
	if (!-r _ || -z _ ) 
	{
		$error_message = $L{'ERRORS.ERR_010_ERROR_CREATE_CONFIG_DIRECTORY'};
		mkdir $lbpconfigdir unless -d $lbpconfigdir or &error; 
		$error_message = $L{'ERRORS.ERR_011_ERROR_CREATE_CONFIG_FILE'};
		open my $configfileHandle, ">", $lbpconfigdir . "/" . $pluginconfigfile or &error;
			print $configfileHandle "[IWD]\r\n";
			print $configfileHandle "CLOUDDNS=dns.loxonecloud.com\r\n";
			print $configfileHandle "VERSION=$version\r\n";
			print $configfileHandle "IWD_USE=off\r\n";
			print $configfileHandle "IWD_USE_NOTIFY=off\r\n";
			print $configfileHandle "WORKDIR_PATH=/tmp/Icon-Watchdog\r\n";
		close $configfileHandle;
		$error_message = $L{'LOGGING.LOG_016_CREATE_CONFIG_OK'};
		&error; 
	}

	# Get plugin config
	my $plugin_cfg 		= new Config::Simple($lbpconfigdir . "/" . $pluginconfigfile);
	$plugin_cfg 		= Config::Simple->import_from($lbpconfigdir . "/" . $pluginconfigfile,  \%Config);
	$error_message      = $L{'ERRORS.ERR_005_ERROR_READING_CFG'}. "<br>" . Config::Simple->error() if (Config::Simple->error());
	&error if (! %Config);

	# Get through all the config options
	LOGDEB "Plugin config read.";
	if ( $plugin->{PLUGINDB_LOGLEVEL} eq 7 )
	{
		foreach (sort keys %Config) 
		{ 
			LOGDEB "Plugin config line => ".$_."=".$Config{$_}; 
		} 
	}
	LOGDEB "Sub defaultpage";
	LOGDEB "Set page title, load header, parse variables, set footer, end";
	$template_title = $L{'Icon-Watchdog.MY_NAME'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$maintemplate->param( "LOGO_ICON"		, get_plugin_icon(64) );
	$maintemplate->param( "HTTP_HOST"		, $ENV{HTTP_HOST});
	$maintemplate->param( "HTTP_PATH"		, "/plugins/" . $lbpplugindir);
	$maintemplate->param( "VERSION"			, $version);
	$maintemplate->param( "LOGFILE"			, $lbplogdir . "/" . $logfilename);
	$maintemplate->param( "PLUGINDB_LOGLEVEL", $plugin->{PLUGINDB_LOGLEVEL});
	$maintemplate->param( "PLUGINDB_MD5_CHECKSUM", $plugin->{PLUGINDB_MD5_CHECKSUM});
	$maintemplate->param( "IWD_USE"			, "off");
	$maintemplate->param( "IWD_USE"			, $Config{"IWD.IWD_USE"}) if ( $Config{"IWD.IWD_USE"} ne "" );
	$maintemplate->param( "IWD_USE_NOTIFY"	, "off");
	$maintemplate->param( "IWD_USE_NOTIFY"	, $Config{"IWD.IWD_USE_NOTIFY"}) if ( $Config{"IWD.IWD_USE_NOTIFY"} ne "" );
	$maintemplate->param( "STATUS"			=>  $L{'Icon-Watchdog.PLACEHOLDER_STATUS'});

	my $langmap;
	foreach (keys %L)
	{
		if (substr($_,0,8) eq "LANGMAP." )
		{
			$langmap = $langmap . "\n".'"'.substr($_,8).'":"'.$L{$_}.'",';
		}
	}
	$maintemplate->param( "LANGMAP.LANGMAP"	=>  $langmap);

	# All Miniservers
	my %miniservers = LoxBerry::System::get_miniservers();
	if ( ! %miniservers ) 
	{
		LOGERR $L{'ERRORS.ERR_003_NO_MINISERVERS_CONFIGURED'};
	}
	else
	{
		$miniservercount = keys %miniservers;
		my @template_row;
		for ($ms_id = 1; $ms_id<=$miniservercount; $ms_id++) 
		{ 
		my @row;
		my %row;
			 LOGDEB "Miniserver $ms_id Name => ".$miniservers{$ms_id}{'Name'};
			 LOGDEB "Miniserver $ms_id IP   => ".$miniservers{$ms_id}{'IPAddress'};
			
			my %ms;
			$ms{Name} 			= $miniservers{$ms_id}{'Name'};
			$ms{IPAddress} 		= $miniservers{$ms_id}{'IPAddress'};
			$ms{PreferHttps} 	= $miniservers{$ms_id}{'PreferHttps'};
			$ms{icontable_IconPlace}	= '<center><table style="display:none;" class="icontable IconPlace ms'.$ms_id.' " id="icontable_IconPlace_'.$ms_id.'"><thead><tr><th>'.$L{'GENERAL.TXT_TABLE_ICON'}.'</th><th>'.$L{'GENERAL.TXT_TABLE_UNIQUEID'}.'</th><th>'.$L{'GENERAL.TXT_TABLE_TITLE'}.'</th></tr></thead><tbody></tbody></table></center>';
			$ms{icontable_IconCat}		= '<center><table style="display:none;" class="icontable IconCat ms'.$ms_id.' "   id="icontable_IconCat_'.$ms_id.'"><thead><tr><th>'.$L{'GENERAL.TXT_TABLE_ICON'}.'</th><th>'.$L{'GENERAL.TXT_TABLE_UNIQUEID'}.'</th><th>'.$L{'GENERAL.TXT_TABLE_TITLE'}.'</th></tr></thead><tbody></tbody></table></center>';
			$ms{icontable_IconState}	= '<center><table style="display:none;" class="icontable IconState ms'.$ms_id.' " id="icontable_IconState_'.$ms_id.'"><thead><tr><th>'.$L{'GENERAL.TXT_TABLE_ICON'}.'</th><th>'.$L{'GENERAL.TXT_TABLE_UNIQUEID'}.'</th><th>'.$L{'GENERAL.TXT_TABLE_TITLE'}.'</th></tr></thead><tbody></tbody></table></center>';
			
			if ( $ms{PreferHttps} eq "1" )
			{
				$ms{Port} 		= $miniservers{$ms_id}{'PortHttps'};
			}
			else
			{
				$ms{Port} 		= $miniservers{$ms_id}{'Port'};
			}

			foreach my $ms_parameter_to_process ('MS_MONITOR_CB')
			{
				LOGDEB "$ms_parameter_to_process: ".int($Config{'IWD.'.$ms_parameter_to_process . $ms_id});
				if ( int($Config{'IWD.'.$ms_parameter_to_process . $ms_id}) eq 1 ) 
				{
					$ms{$ms_parameter_to_process} = 1; 
					$ms{$ms_parameter_to_process. "_script"} = '$("#'.$ms_parameter_to_process . '_checkbox'.$ms_id .'").prop("checked", 1);';
				}
				else
				{
					$ms{$ms_parameter_to_process} = 0; 
					$ms{$ms_parameter_to_process. "_script"} = '$("#'.$ms_parameter_to_process . '_checkbox'.$ms_id .'").prop("checked", 0);';
				}
				$ms{$ms_parameter_to_process. "_script"}  = $ms{$ms_parameter_to_process. "_script"} . '
				$("#'.$ms_parameter_to_process . '_checkbox'.$ms_id.'").on("change", function(event) 
				{ 
					if ( $("#'.$ms_parameter_to_process . '_checkbox'.$ms_id.'").is(":checked") ) 
					{ 
						$("#'.$ms_parameter_to_process . $ms_id.'").val(1); 
						$("label[for=\''.$ms_parameter_to_process . '_checkbox'.$ms_id.'\']" ).removeClass( "ui-checkbox-off" ).addClass( "ui-checkbox-on" );
					} 
					else 
					{ 
						$("#'.$ms_parameter_to_process . $ms_id.'").val(0); 
						$("label[for=\''.$ms_parameter_to_process . '_checkbox'.$ms_id.'\']" ).removeClass( "ui-checkbox-on" ).addClass( "ui-checkbox-off" );
					}
				});
				$("#'.$ms_parameter_to_process . '_checkbox' .$ms_id.'").trigger("change");';
				LOGDEB "Set special parameter " . $ms_parameter_to_process . $ms_id;

			}
	
			push @{ $row{'MSROW'} }					, \%ms;
					$row{'MSID'} 					= $ms_id;
					

			push(@template_row, \%row);
		}	
		$maintemplate->param("TEMPLATE_ROW" => \@template_row);
	}	
    $maintemplate->param("HTMLPATH" => "/plugins/".$lbpplugindir."/");

    our $imgpath = "http://".$ENV{'HTTP_HOST'} . $ENV{'SCRIPT_NAME'}; 
		$imgpath =~ s/admin\///ig;
		$imgpath =~ s/index.cgi//ig;
    $maintemplate->param( "IMGPATH" , $imgpath);
	
    print $maintemplate->output();
	LoxBerry::Web::lbfooter();
	LOGDEB "Leaving Icon-Watchdog Plugin normally";
	LOGEND if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
	exit;
}

sub error 
{
	LOGDEB "Sub error";
	LOGERR $error_message;
	LOGDEB "Set page title, load header, parse variables, set footer, end with error";
	$template_title = $L{'ERRORS.MY_NAME'} . " - " . $L{'ERRORS.ERR_TITLE'};
	LoxBerry::Web::lbheader($template_title, $helpurl, $helptemplatefilename);
	$errortemplate->param('ERR_MESSAGE'		, $error_message);
	$errortemplate->param('ERR_TITLE'		, $ERR{'ERRORS.ERR_TITLE'});
	$errortemplate->param('ERR_BUTTON_BACK' , $ERR{'ERRORS.ERR_BUTTON_BACK'});
	print $errortemplate->output();
	LoxBerry::Web::lbfooter();
	LOGDEB "Leaving Icon-Watchdog Plugin with an error";
	LOGEND if $plugin->{PLUGINDB_LOGLEVEL} eq 7;
	exit;
}

