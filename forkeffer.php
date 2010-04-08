#!/usr/local/bin/php
<?php

define( '_VERSION', '$Rev: 3226 $' );

$checkPath = realpath( array_pop( $argv ) );

if( !$checkPath ) {
	printHelp();
	exit;
}

$options = parseOptions( array(
	'verbose'	=> false,
	'recursive' => false,
	'dry-run'	=> false
), $argv );


clean( $checkPath, $options );

function clean( $path, $options )
{
	$dir = dir( $path );
	printline( 'Checking '.$path );
	
	while( ($entity = $dir->read()) !== false ) {
		if( $entity == '.' || $entity == '..' ) {
			continue;
		}

		if( $options['recursive'] && is_dir( $path.'/'.$entity ) ) {
			clean( $path.'/'.$entity, $options );
		} elseif( is_file( $path.'/'.$entity ) && (
			substr( $entity, 0, 2 ) == '._' ||
			$entity == '.DS_Store' ) ) {
		
			$pri = false;
			if( $options['dry-run'] ) {
				$pri = true;
			}
			printline( 'Removing '.$path.'/'.$entity, $pri );
			
			if( !$options['dry-run'] ) {
				unlink( $path.'/'.$entity );
			}
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
ForkEffer - Remove MacOS fork and meta files
(i.e. .DS_Store and _foo.bar)
Version: {$v}

NOTE: zephyr.php can automatically remove file forks

Example:

    ./zephyr/forkeffer.php -n path/

    Check files in path/ but do not remove
    
    
Options:
-h --help             Help
-n --dry-run          Determine files to be removed but to not rem,ove
-v --verbose          Verbose output
-r                    Scan recursively


END;
}