<?

	// Заголовки
	header( "Access-Control-Allow-Origin: *" ); header( 'Content-Type: text/plain; charset=utf-8' );
	
	// База
	include_once( 'secret.php' ); $db = new mysqli( 'localhost', $g['db'][0], $g['db'][1], $g['db'][2] );
	
	// Причесываю к маленькому
	$_REQUEST['cty'] = mb_strtolower( urldecode( $_REQUEST['cty'] ) );
	
	// Вытащит все города
	$res = $db -> query( "SELECT * FROM `cty` WHERE `cty` LIKE '%{$_REQUEST['cty']}%' OR `txt` LIKE '%{$_REQUEST['cty']}%' LIMIT 50" );

	if( $res -> num_rows ){
		
		while( $r = $res -> fetch_array( MYSQLI_ASSOC ) )
			
			$s .= "<a class='in_tag' data-replace='ok'>" . preg_replace( '/' . $_REQUEST['cty'] . '/', '<b>$0</b>', $r['cty'] ) . " <small class='text-secondary'>{$r['id']}</small></a><br />";
		
	}
	
	$s = $s ?: "<a class='in_tag' data-replace='ok'><b>Другой</b></a><br />";
	exit( $s );
		
	

?>