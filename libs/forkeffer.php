<?php

$path = $argv[1];

$path = realpath( $path );

if( !is_readable( $path ) ) {
	echo $path." does not exist\n";
	exit;
}

clean( $path );

function clean( $path )
{
	$dir = dir( $path );
	while( ($entity = $dir->read()) !== false ) {
		if( $entity == '.' || $entity == '..' ) {
			continue;
		}
		
		if( is_dir( $path.'/'.$entity ) ) {
			clean( $path.'/'.$entity );
		} elseif( is_file( $path.'/'.$entity ) && (
			substr( $entity, 0, 2 ) == '._' ||
			$entity == '.DS_Store' ) ) {
		
			unlink( $path.'/'.$entity );
			
			echo '.';
		}
	
	}
	
	$dir->close();
}

echo "\n";
?>