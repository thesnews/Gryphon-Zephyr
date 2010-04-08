#!/usr/local/bin/php
<?php

define( '_VERSION', '$Rev: 3226 $' );

$path = dirname( __FILE__ );

if( !file_exists( $path.'/libs/packer.php' ) ) {
	printline( 'Packer lib not found' );
	exit;
}

include_once( $path.'/libs/packer.php' );

$checkPath = realpath( array_pop( $argv ) );

if( !$checkPath ) {
	printHelp();
	exit;
}

$options = parseOptions( array(
	'javascript'=> true,
	'css'		=> true,
	'verbose'	=> false,
	'recursive' => false,
	'append'	=> false,
	'dry-run'	=> false
), $argv );

if( !is_dir( $checkPath ) || !is_writable( $checkPath ) ) {
	printline( $checkPath.' is not a valid path', true );
	exit;
}

if( $options['recursive'] ) {
	printline( 'Running in recursive mode.' );
}
if( $options['append'] ) {
	printline( 'Running in append mode.' );
}
if( $options['javascript'] ) {
	printline( 'Will attempt to compress javascript files.' );
}
if( $options['css'] ) {
	printline( 'Will attempt to compress CSS files.' );
}
if( $options['dry-run'] ) {
	printline( 'Dry-run mode. No changes will be written.' );
}

scanDirectory( $checkPath, $options );

function scanDirectory( $path, $options )
{
	$dir = dir( $path );
	
	while( ($entity = $dir->read()) !== false ) {
		if( strpos( $entity, '.' ) === 0 ) {
			continue;
		}
		
		printline( 'Scanning '.$path.'/'.$entity.'...' );
		
		if( is_file( $path.'/'.$entity ) ) {
			$contents = false;
			$out = false;
			if( $options['javascript'] &&
				substr( $entity, strlen( $entity ) - 3 ) == '.js' ) {
				
				$contents = file_get_contents( $path.'/'.$entity );
				$p = new JavaScriptPacker( $contents );
				$out = $p->pack();
			} elseif( $options['css'] &&
				substr( $entity, strlen( $entity ) - 4 ) == '.css' ) {

				$out = packCSS( $contents );
			}
		
			if( !$out ) {
				continue;
			}
			
			$outFile = $path.'/'.$entity;
			if( $options['append'] ) {
				$ext = substr( $entity, strrpos( $entity, '.' ) + 1 );
				$outFile = $path.'/'.substr( $entity, 0,
					strrpos( $entity, '.' ) ).'.compressed.'.$ext;
			}
			
			if( $options['dry-run'] ) {
				printline( 'Compress '.$path.'/'.$entity, true );
			} else {
				printline( 'Compress '.$path.'/'.$entity );
				file_put_contents( $outFile, $out );
			}
			
		} elseif( $options['recursive'] && is_dir( $path.'/'.$entity ) ) {
			scanDirectory( $path.'/'.$entity, $options );
		}
	}
	
	$dir->close();
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
			case '--append':
			case '-a':
				$opts['append'] = true;
				break;
			case '--skip-css':
				$opts['css'] = false;
				break;
			case '--skip-javascript':
				$opts['javascript'] = false;
				break;
			case '-r':
				$opts['recursive'] = true;
				break;
			case '--verbose':
			case '-v':
				$opts['verbose'] = true;
				break;
			case '--dry-run':
			case '-n':
				$opts['dry-run'] = true;
				break;
		}
	}
	
	return $opts;
}

function printHelp()
{
	$v = _VERSION;
echo <<<END
Pack - Pack Javascript and CSS files
Version: {$v}

NOTE: zephyr.php will automatically pack files for transport to a deployment
server leaving the original files untouched. You should only use pack.php
to pack individual files.

Example:

    ./zephyr/pack.php --skip-css path/

    Pack javascript only found in path/
    
    
Options:
-h --help             Help
-n --dry-run          Determine files to compress do not compress files
-v --verbose          Verbose output
-r                    Scan recursively
-a --append           Append '.compressed' to processed files
   --skip-css         Do not process CSS files
   --skip-javascript  Do not procss Javascript files


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
