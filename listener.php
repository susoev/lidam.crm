<?
	
	// Выдает текущие звонки
	
	// Заголовки
	header( "Access-Control-Allow-Origin: *" ); header( 'Content-Type: text/plain; charset=utf-8' );
	
	// Номер СРМки
	if( !is_numeric( $_REQUEST['crm'] ) ) exit( 'out_03' );
	
	// Показывает за последние Х часов, у которых ANS на нуле
	include_once( 'set_up.php' );

	// Пароли
	include_once( 'secret.php' );

	$db = new mysqli( 'localhost', $g['db'][0], $g['db'][1], $g['db'][2] );
	
	// Вытащит все звонки, старше X часов
	$res = $db -> query( "SELECT * FROM `calls` WHERE `crm` = {$_REQUEST['crm']} AND `uts` > " . ( $_SERVER['REQUEST_TIME'] - $g['history'] * 60 * 60 ) . " ORDER by `uts` DESC" );
	
	if( !$res -> num_rows ) exit( 'err' );
	
	while( $r = $res -> fetch_array( MYSQLI_ASSOC ) ){
		
		// Исходящие не показываю
		// if( $r['from'] < 900 ) continue;
		
		// Отвеченный вызов
		$r['color'] = 'light';
		
		// Сейчас идет вызов
		if( !$r['i'] && !$r['dura'] ){
			
			$r['color'] = 'success';
			
			// Если перезвон на наш основной
			// if( $r['into'] == '79675551721' ) $r['color'] = 'info';
			
		}
		
		// Кто то ответил, сейчас говорит
		if( $r['i'] && !$r['dura'] ) $r['color'] = 'warning';
		
		// Для пропущеных так же добавит время пропуска
		if( !$r['i'] && $r['dura'] ){
			
			$r['color'] = 'secondary';
			
			// Прошло времени с момента звонка
			$delay = $_SERVER['REQUEST_TIME'] - $r['uts'];
			
			// Покажет в секундах
			if( $delay < 60 ) $r['delay'] = "{$delay} c.";
				
			else {
				
				$r['delay'] = ceil( $delay / 60 );
				
				// В часах
				if( $r['delay'] > 61 ){
					
					$r['delay'] = "> " . ceil( $r['delay'] / 60 ) . " ч.";
					
				} else {
					
					// В минутах
					$r['delay'] .= " м.";
					
				}
				
			}
			
		}
		
		// Убираю ссылку, не засирать выдачу
		unset( $r['url'] );
		
		$j[] = $r;
		
	}

	// Дебаг = вывод в виде архива
	if( isset( $_REQUEST['debug'] ) ){ print_r( $j ); exit; }

	exit( json_encode( $j ) );
	
?>