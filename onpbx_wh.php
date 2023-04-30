<?
	
	// Принимает хуки звонков от онлайн пбх
	
	// Входящая строка
	parse_str( file_get_contents( 'php://input' ), $a );
	
	// Внимание, времянка для тестов
	$a = json_decode( '{"event":"call_user_start","domain":"pbx16568.onpbx.ru","direction":"inbound","uuid":"5da83ad2-21b3-4dc9-976f-2c504085d0d1","origin":"sip","caller":"+79220432678","callee":"101","from_domain":"","to_domain":"","gateway":"79675553153","date":"1681633059"}', true );
	
	// Выход если непонятная внешка или нет СРМ системы
	if( empty( $a ) ) exit( 'out_00' ); if( !is_numeric( $_REQUEST['crm'] ) ) exit( 'out_03' );
	
	print_r( $a );
	
	// Временна история, складываю звонки
	// file_put_contents( "tmp_calls/{$a['uuid']}.txt", json_encode( $a, JSON_UNESCAPED_UNICODE ) . "\n", FILE_APPEND );
	
	// Остаемся, если завершенный исходящий РАЗГОВОР более 10 секунд
	// if( ( $a['direction'] == 'outbound' ) && ( $a['event'] == 'call_end' ) && ( $a['dialog_duration'] > 5 ) ) $exit = false;

	// Разрешенные номера
	include_once( 'secret.php' );
	
	// Выход если внутренние звонки
	if( !$a['gateway'] ) exit( 'out_01' );
	
	// Если номер в разрешенных
	if( !isset( $in_a[$a['gateway']] ) ) exit( 'out_02' );
	
	// Если всё ок, подключаю базу
	include_once( 'set_up.php' ); $db = new mysqli( 'localhost', $g['db'][0], $g['db'][1], $g['db'][2] );


// 1.1 Вход. Пошел вызов на пользователей
	if( $a['event'] == 'call_user_start' ){
		
		// Заносит в базу
		$db -> query( "INSERT INTO `calls` ( `uuid`, `uts`, `crm`, `g`, `o`, `d` ) VALUES ( '{$a['uuid']}', '{$a['date']}', {$_REQUEST['crm']}, '{$a['gateway']}', '{$a['caller']}', 'i' );" );
		exit( 'done:call_user_start' );
		
	}

// 1.2  Вход. Звонок отвечен, есть кто ответил, но пока нет УРЛ
	if( $a['event'] == 'call_answered' ){
		
		$db -> query( "UPDATE `calls` SET `i` = '{$a['callee']}' WHERE `uuid` = '{$a['uuid']}';" );
		exit( 'done:call_answered' );
		
	}
	
// 1.3 Вход. Поговорили. Звонок завершен: есть продолжительность, есть УРЛ
	if( $a['event'] == 'call_end' ){
		
		$db -> query( "UPDATE `calls` SET `dura` = {$a['dialog_duration']}, `url` = '{$a['download_url']}' WHERE `uuid` = '{$a['uuid']}';" );
		exit( 'done:call_end' );
		
	}

// 1.4 Пропущеный. Звонок пропущен: есть продолжительность, но нет АНС
	if( $a['event'] == 'call_missed' ){
		
		$db -> query( "UPDATE `calls` SET `dura` = {$a['call_duration']} WHERE `uuid` = '{$a['uuid']}';" );
		exit( 'done:call_missed' );
		
	}
	
// Исх. Поговорили. Звонок завершен: есть продолжительность, есть УРЛ
	if( ( $a['direction'] == 'outbound' ) && ( $a['event'] == 'call_end' ) ){
		
		$db -> query( "UPDATE `calls` SET `ans` = '{$a['callee']}', `dura` = {$a['call_duration']}, `url` = '{$a['download_url']}' WHERE `uuid` = '{$a['uuid']}';" );
		
	}
	
// Исх. Пошел вызов
	if( ( $a['direction'] == 'outbound' ) && ( $a['event'] == 'call_start' ) ){
		
		// Заносит в базу
		$db -> query( "INSERT INTO `calls` ( `uuid`, `uts`, `into`, `ans`, `from`, `out` ) VALUES ( '{$a['uuid']}', '{$a['date']}', '{$a['gateway']}', '{$a['caller']}', '+{$a['callee']}', 1 );" );
		
	}
	


?>