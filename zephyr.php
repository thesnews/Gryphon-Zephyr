#!/usr/local/bin/php
<?php

define( '_VERSION', '$Rev$' );

$path = dirname( __FILE__ );

if( !file_exists( $path.'/libs/spyc.php' ) ) {
	printline( 'SPYC lib not found' );
	exit;
}

if( !file_exists( $path.'/libs/packer.php' ) ) {
	printline( 'Packer lib not found' );
	exit;
}

include_once( $path.'/libs/spyc.php' );
include_once( $path.'/libs/packer.php' );

if( count( $argv ) <= 1 ) {
	printHelp();
	exit;
}

$options = parseOptions( array(
	'config'	=> false,
	'dry-run'	=> false,
	'verbose'	=> false,
	'forkeffer' => false
), $argv );


if( !$options['config'] ) {
	printline( 'No config value given', true );
	printHelp();
	exit;
}


$configFile = $path.'/'.$options['config'].'.zephyr.yaml';

if( !file_exists( $configFile ) ) {
	printline( "Could not find config file:\n".
		"zephyr/".$options['config'].'.zephyr.yaml', true );
	exit;
}

$config = Spyc::YAMLLoad( $configFile );

if( !$config['server'] ) {
	printline( "No server specified", true );
}

$packedFiles = array();
if( !$options['dry-run'] && !empty( $config['compress'] ) ) {
	foreach( $config['compress'] as $file ) {
		if( !file_exists( $file ) || !is_writable( $file ) ) {
			printline( "Could not open ".$file );
			continue;
		}
		
		$contents = file_get_contents( $file );
		
		$packedFiles[$file] = $contents;
		
		if( substr( $file, strlen( $file ) - 3 ) == '.js' ) {
			$p = new JavaScriptPacker( $contents );
			$str = $p->pack();
		} elseif( substr( $file, strlen( $file ) - 4 ) == '.css' ) {
			$str = packCSS( $contents );
		} else {
			
			printline( "Could not pack ".$file );
			continue;
		}
	
		file_put_contents( $file, $str );
	}
}

$baseCommand = 'rsync -av';
if( $options['dry-run'] ) {
	$baseCommand .= 'n';
}

if( $config['port'] ) {
	$baseCommand .= "e 'ssh -p ".$config['port']."'";
} else {
	$baseCommand .= 'e ssh';
}

$serverPrefix = $config['username'].'@'.$config['server'].':';

$ignoreSCM = '';
if( $config['ignore-scm'] ) {
	$ignoreSCM = ' --cvs-exclude';
}

foreach( $config['sync'] as $item ) {

	$localPath = $item['local'];
	$remotePath = $item['remote'];
	if( !is_dir( $localPath ) ) {
		printline( $localPath." not found. Skipping" );
		continue;
	}
	
	if( $options['forkeffer'] ) {
		$ffCmd = 'php '.$path.'/libs/forkeffer.php '.$localPath;
		printline( "Effing forks" );
		exec( $ffCmd );
	}
	
	if( substr( $localPath, strlen( $localPath )-1 ) != '/' ) {
		$localPath .= '/';
	}

	if( substr( $remotePath, strlen( $remotePath )-1 ) != '/' ) {
		$remotePath .= '/';
	}
	
	$excludes = " --exclude='.DS_Store' --exclude='.git'";
	
	if( $item['exclude'] ) {
		$excludes .= ' --exclude='.implode( ' --exclude=', $item['exclude'] );
	}
	
	$delete = '';
	if( array_key_exists( 'delete', $item ) && $item['delete'] == 'true' ) {
		$delete = ' --delete';
	}
	
	$cmd = $baseCommand.$excludes.$ignoreSCM.$delete.' '.$localPath.' '.
		$serverPrefix.$remotePath;
		
	passthru( $cmd );
//	echo $cmd."\n\n";

}

if( empty( $packedFiles ) ) {
	exit;
}

foreach( $packedFiles as $path => $contents ) {
	file_put_contents( $path, $contents );
}

function printline( $str, $priority = false )
{
	global $options;
	
	if( $priority || $options['verbose'] ) {
		echo $str."\n";
	}
}

function parseOptions( $opts, $argv )
{
	foreach( $argv as $val ) {
		if( $val && ($val == '--help' || $val == '-h' ) ) {
			printHelp();
			exit;
		}
		
		$val = explode( '=', $val );
		switch( $val[0] ) {
			case '--config':
				$opts['config'] = $val[1];
				break;
			case '--dry-run':
			case '-n':
				$opts['dry-run'] = true;
				break;
			case '--verbose':
			case '-v':
				$opts['verbose'] = true;
				break;
			case '--forkeffer':
				$opts['forkeffer'] = true;
		}
	}
	
	return $opts;
}

function printHelp()
{
	$v = _VERSION;
echo <<<END
Zephyr - Gryphon Deployment Script
Version: {$v}

Example:

    ./zephyr/zephyr.php --config=statenews

    Run Zephyr with the information from zephyr/statenews.zephyr.yaml
    
    
Options:
-h --help      Help
   --config    The config file to use
-n --dry-run   Test the deploy, print changed files but don't actually do
               anything.
-v --verbose   Verbose output
   --forkeffer Scan local directories and remove file forks and meta info
               (i.e. ._foo and .DS_Store)


END;
}

function packCSS( $str )
{

	//comments
	$str = preg_replace( '!//[^\n\r]+!', '', $str );

	//new lines, multiple 
	$str = preg_replace( '/[\r\n\t\s]+/s', ' ', $str );

	// more comments
	$str = preg_replace( '#/\*.*?\*/#', '', $str );

	//spaces before
	$str = preg_replace( '/[\s]*([\{\},;:])[\s]*/', '\1', $str );

	//spaces in the begining
	$str = preg_replace( '/^\s+/', '', $str );
	
	return $str;

}

?>