<?

	// База в люом случае подключается
	$db = new mysqli( 'localhost', $g['db'][0], $g['db'][1], $g['db'][2] );
	

// Получает город по id
	function get_city( $id ){
		global $ua, $g, $db;
		
		$res = $db -> query( "SELECT * FROM `cty` WHERE `id` = $id" );
		
		if( !$res -> num_rows ) return false;
		
		return $res -> fetch_array( MYSQLI_ASSOC );
		
	}
		
// Все звонки по этому номеру
	function call_byNum( $phn ){
		global $db;
		
		$res = $db -> query( "SELECT * FROM `calls` WHERE `o` = '{$phn}'" );
		
		if( !$res -> num_rows ) return false;
		
		while( $r = $res -> fetch_array( MYSQLI_ASSOC ) ) $a[] = $r;
		
		return $a;
		
	}
		
// Информация о звонке
	function get_call( $uuid ){
		global $ua, $g, $db;
		
		$res = $db -> query( "SELECT * FROM `calls` WHERE `uuid` = '{$uuid}'" );
		
		if( !$res -> num_rows ) return false;
		
		return $res -> fetch_array( MYSQLI_ASSOC );
		
	}
		
// Отображает ( редактирует ) заявку
	function order(){
		global $ua, $g, $db;
		
		// Получает заявку
		$r = get_orders( 'order' )[0]; $qar = json_decode( $r['jso'], true );
		
		// Получает все звонки по этому номеру
		$na = call_byNum( get_call( $r['uuid'] )['o'] );
		
		// Отправляет, если не ваша заявка
		if( $g['u']['id'] != $r['oid'] ) $disa = true;
		
		// Получает город
		$ca = get_city( $r['cty'] ); $r['cty'] = "{$ca['cty']} {$ca['id']}";
		
		// Получает скрипт для этого шлюза // преобразует к нормальному виду
		foreach( json_decode( file_get_contents( "{$g['u']['crm']}.json" ),true ) as $k => $a ) if( $a[0] == $r['tid'] ) break;
		foreach( $a as $k => $v ) if( is_numeric( $v[0] ) ) $qa[$v[0]] = $v[1]; $g['fa'] += $qa;
		
		// Удалит все тех. поля, которые не выводим
		foreach( $r as $k => $v ) if( !isset( $g['fa'][$k] ) ) unset( $r[$k] );
		
		// Добавит вопросы
		$r = $r + $qar;
		
		// Вывод полей
		$g['body'] .= "<h3>Заявка №{$ua[1]}</h3><span class='text-secondary'>Можно отредактировать!</span></div><form method='post' action='/update_order'>\n\n";
		
		// fa - массив вопросов - r - массив ответов
		$i = 0; foreach( $r as $k => $v ){ $i++;
			
			$g['body'] .= "\n<div class='row py-2 click' data-target='in{$i}'>\n\t<div class='col-12 col-md-6'>{$g['fa'][$k]}\n\t</div>\n\t<div class='col-12 col-md-6'>\n\t\t<input " . ( $disa ? 'disabled' : NULL ) . " id='in{$i}' class='form-control' name='{$k}' type='text' value='{$r[$k]}' " . ( $k == 'cty' ? "data-ajax='cty'" : '' ) . " />" . ( $k == 'cty' ? "<p class='sresalt'></a><br /></p>" : NULL ) . "\n\t</div>\n</div>\n";
			
		}
		
		// Аудио, которые есть по этому номеру
		$s = NULL; foreach( $na as $k => $v ) $s .= "<p class='mt-3'>" . date( 'd.m.Y H:i', $v['uts'] ) . " ( {$v['dura']} сек )<br />" . ( !empty( $v['url'] ) ? "<audio controls=''><source src='{$v['url']}' type='audio/mpeg'></audio>" : NULL ) . "</p>";
		
		// Заверщение
		$g['body'] .= $disa ? NULL : "<div class='my-2'><input type='hidden' value='{$ua[1]}' name='id'><button class='btn btn-primary' type='submit'>Сохранить</button></form>";
		$g['body'] .= $s;
		
		include_once( 'bootstrap.php' );
		
	}
		
// Достанет все заявки
	function get_orders( $o = NULL ){
		global $ua, $g, $db;
		
		// Для пользователя на главном экране
		if( $o == 'by_user' ) $q = "SELECT * FROM `orders` WHERE `oid` = '{$g['u']['id']}' ORDER by `id` DESC";
		
		// Когда просматриваем ордер
		if( $o == 'order' ) $q = "SELECT * FROM `orders` WHERE `id` = '{$ua[1]}'";
		
		// Для поиска заполненных заявок, если опять звонит
		if( preg_match( '/^\+\d{5,}$/', $o ) ) $q = "SELECT * FROM `orders` WHERE `call` = '{$o}' ORDER by `id` DESC";
		
		$res = $db -> query( $q );
		
		if( !$res -> num_rows ) return false;
		
		while( $r = $res -> fetch_array( MYSQLI_ASSOC ) ) $a[] = $r;
		
		return $a;
		
	}
		
// Обновляет заявку
	function update_order(){
		global $ua, $g, $db;
		
		// Переопределил, чтобы быстрее
		$r = $_POST;
		
		// Город по дурацки ))
		$r['cty'] = preg_match( '/.+(\d{4})$/', $r['cty'] , $m ) ? $m[1] : 0;
		
		// Ответы на вопросы
		foreach( $r as $k => $v ) if( is_numeric( $k ) ) $q[$k] = $v;
		
		// Заношу в базу
		$q = "UPDATE `orders` SET `jso` = '" . json_encode( $q, JSON_UNESCAPED_UNICODE ) . "', `cty` = {$r['cty']}, `price` = " . preg_replace( '/\D/', '', $r['price'] ) . ", `phn` = " . ( $r['phn'] ? "'{$r['phn']}'" : 'NULL'  ) . ", `nme` = '{$r['nme']}', `txt` = '{$r['txt']}' WHERE `id` = {$r['id']}";
		
		$db -> query( $q );
		
		// Переадресует обратно
		header( "Location: /?{$r['id']}" );
		
		exit;
		
	}
	
// Сохраняет заявку
	function save_call(){
		global $ua, $g, $db;
		
		// Цена заявки
		$_POST['price'] = preg_replace( '/\D/', '', $_POST['q'][$_POST['price']['num']] ) * $_POST['price']['val'];
		
		// Город по дурацки ))
		$_POST['cty'] = preg_match( '/.+(\d{4})$/', $_POST['cty'] , $m ) ? $m[1] : 0;
		
		// Собираю данные в один массив для удобства
		$r = array_merge( get_call( $_POST['uuid'] ), $_POST ); $r['q'] = json_encode( $r['q'], JSON_UNESCAPED_UNICODE );
		
		// print_r( $r ); exit;
		
		// Заношу в базу
		$db -> query( "INSERT INTO `orders` ( `crm`, `oid`, `uuid`, `uts`, `jso`, `cty`, `tid`, `price`, `call`, `phn`, `nme`, `txt` ) VALUES ( {$r['crm']}, {$g['u']['id']}, '{$r['uuid']}', '{$_SERVER['REQUEST_TIME']}', '{$r['q']}', {$r['cty']}, {$r['tid']}, {$r['price']}, '{$r['call']}', " . ( $r['phn'] ? "'{$r['phn']}'" : 'NULL'  ) . ", '{$r['nme']}', '{$r['txt']}' );" );
		
		// Переадресует обратно
		header( "Location: /?saved_order={$db -> insert_id}" );
		
		exit;
		
	}
	
// Входящий звонок
	function call_i(){
		global $ua, $g, $db;
		
		// Получает звонок
		if( !$r = get_call( $ua[1] ) ) exit( 'err_04' );
		
		// Получает скрипт для этого шлюза
		$a = json_decode( file_get_contents( "{$g['u']['crm']}.json" ),true );
		
		// Список всех скриптов
		$ss = NULL; foreach( $a as $k => $v ) if( !empty( $v[1] ) ) $ss .= "<a href='/call_i/{$r['uuid']}?script={$k}'>{$v[1]}</a> / "; $ss .= "<a href='/call_i/{$r['uuid']}?script=other'>Другое</a>";
		
		// Применение допскрипта
		$scr = isset( $_REQUEST['script'] ) ? $_REQUEST['script'] : $r['g'];
		
		// Если такой шлюз есть в списке
		if( isset( $a[$scr] ) ){
			
			// Если у него маркер same // Тему оставит
			if( $a[$r['g']][2][0] == 'same' ){
				
				$tid = $a[$r['g']][0];
				$a = $a[$a[$r['g']][2][1]];
				$a[0] = $tid;
				
			} else {
				
				$a = $a[$scr];
				
			}
			
		} else {
			
			// Скрипта с таким шлюзом нет
			exit( 'err_05' );
			
		}
		
		// Вывод опросника
		$q = NULL; $i = 0; foreach( $a as $k => $v ){ if( $k < 2 ) continue; $i++;
			
			// Формула прайса
			if( $v[2][1] == 'price' ) $price = "<input type='hidden' name='price[val]' value='{$v[2][2]}' /><input type='hidden' name='price[num]' value='{$v[0]}' />";
			
			// Теги, если строка начинается с #
			$sub_text = "<small class='text-success none'>{$v[2][0]}</small>";
			
			if( preg_match( '/(.*)#(.+)/', $v[2][0], $m ) ){
				
				$sub_text = "<small class='text-success ok'>{$m[1]}";
				$ta = explode( " ", $m[2] ); foreach( $ta as $w ) $sub_text .= "<a class='in_tag'>{$w}</a>, "; $sub_text .= "</small>";
				
			}
			
			$q .= "\n<div class='row py-2 click' data-target='in{$i}'>\n\t<div class='col-12 col-md-6'><b>{$v[1]}</b><br />{$sub_text}\n\t</div>\n\t<div class='col-12 col-md-6'>\n\t\t<input id='in{$i}' class='form-control-lg' name='" . ( is_numeric( $v[0] ) ? "q[{$v[0]}]" : $v[0] ) . "' type='text' " . ( $v[0] == 1 ? "autofocus" : NULL ) . " " . ( $v[3] == 'ajax' ? "data-ajax='{$v[0]}'" : NULL ) . " />{$price}" . ( $v[3] == 'ajax' ? "<p class='sresalt'></a><br /></p>" : NULL ) . "\n\t</div>\n</div>";
			
		}
		
		// Скрытые
		$q .= "<input type='hidden' name='call' value='{$r['o']}' /><input type='hidden' name='tid' value='{$a[0]}' /><input type='hidden' name='uuid' value='{$r['uuid']}' /><input type='hidden' name='src' value='{$src}' />";
		
		// Получает все звонки по этому номеру
		if( $na = call_byNum( $r['o'] )  ) $s = NULL; foreach( $na as $k => $v ) $s .= "<p class='mt-3'>" . date( 'd.m.Y H:i', $v['uts'] ) . " ( {$v['dura']} сек )<br />" . ( !empty( $v['url'] ) ? "<audio controls=''><source src='{$v['url']}' type='audio/mpeg'></audio>" : NULL ) . "</p>";
		
		// Если по этому номеру тел. уже были заявки
		$so = ''; if( $ora = get_orders( $r['o'] ) ){ $so = "<p>😳 <b class='text-danger'>Внимание: по этому номеру есть заявки:</b><br />"; foreach( $ora as $k => $v ) $so .= "<a target='_blank' href='/order/{$v['id']}'>№{$v['id']} от " . date( 'd.m.Y H:i', $v['uts'] ) . "</a>"; $so .= "</p>"; }
		
		$g['body'] = preg_replace( [ '/-ORDERS-/', '/-SSS-/', '/-NME-/', '/-QUIZ-/', '/-THEME-/', '/-INTO-/', '/-FROM-/', '/-HIST-/' ], [ $so, $ss, "[{$g['u']['name']}]", $q, "[{$a[1]}]", $r['g'], $r['o'], $s ], file_get_contents( 'inc/tm_call_i.html' ) );
		
		// print_r( $g ); print_r( $ss ); print_r( $a ); print_r( $r ); exit;
		
		include_once( 'bootstrap.php' );
		
	}
		
// Проверка авторизации
	function check_auth(){
		global $ua, $g, $db;
		
		// По умолчанию не залогинен
		$auth = false;
		
		$g['menu'] = 'СРМ. Работа со звонками!-';
		
		// Логаут
		if( $_SERVER['REQUEST_URI'] == '/logout' ){ logout(); }
		
		// Если авторизуется с формы
		if( $_SERVER['REQUEST_URI'] == '/login' ){
			
			// Проверка логина, тут по хорошему надо кол-во попыток логина считать
			$res = $db -> query( "SELECT * FROM `users` WHERE `eml` = '{$_POST['email']}' AND `pass` = '" . md5( $_POST['password'] ) . "'" );
			
			if( $res -> num_rows ){
				
				$r = $res -> fetch_array( MYSQLI_ASSOC );
					
				unset( $r['pass'] );
				
				setcookie( 'user', json_encode( $r, JSON_UNESCAPED_UNICODE ), $_SERVER['REQUEST_TIME'] + 30*24*60*60, "/" );
				
				header( "Location: /" );
				
				exit;
				
			}
						
		}
		
		// Проверка авторизации
		if( !empty( $_COOKIE['user'] ) ){
			
			$g['u'] = json_decode( $_COOKIE['user'], true );
			
			$auth = true;
			
			// ВНИМАНИЕ: Сделать тему с солькой
			
		}
		
		// Форма авторизации
		if( !$auth ){
			
			$g['title'] = 'Авторизация';
			$g['body'] = 'inc/login_form.html';
			
			include_once( 'bootstrap.php' );
		}
		
		return true;
		
	}
	
// Разработка
	function dev(){
		global $g;
		
		$g['title'] = 'Авторизация';
		$g['body'] = 'inc/template.html';
		unset( $g['menu'] );
		
		include_once( 'bootstrap.php' );
		
	}
	
// Логин / разлогин
	function logout(){
		
		setcookie( 'user', null, -1 );
		
		unset( $_COOKIE['user'] );
		
		header( 'Location: /' );
		
		exit;
		
	}

// Профиль
	function profile(){
		global $g;
		
		$g['title'] = 'Профиль';
		$g['body'] = "<div class='col'><p>Ваш профиль.</p><p><a href='/logout'>Выход</a></p></div>";
		
		unset( $g['menu'] );
		include_once( 'bootstrap.php' );
		
	}

// Начало работы
	function start_work(){
		global $g, $db;
		
		if( isset( $_REQUEST['go'] ) ){
			
			// Переупакую куки
			setcookie( 'user', null, -1 ); unset( $_COOKIE['user'] );
			
			// Добавлю время стопа смены
			$g['u']['shift_stop'] = $_SERVER['REQUEST_TIME'] + 4 * 60 * 60 + 10 * 60;
			
			setcookie( 'user', json_encode( $g['u'], JSON_UNESCAPED_UNICODE ), $_SERVER['REQUEST_TIME'] + 30*24*60*60, "/" );
			
			header( "Location: /" );
			
		}
		
		$g['title'] = "Начало работы!";
		$g['body'] = "inc/start_work.html";
		
		unset( $g['menu'] );
		
		include_once( 'bootstrap.php' );
		
	}

// Главный экран
	function main_screen(){
		global $g;
		
		// На гланом экране:
		// Показать звонки
		// Показать заявки с сайтов
		// Показать скрипты и возражения
		// $a = json_decode( file_get_contents( "{$g['u']['crm']}.json" ),true );
		
		// Достанет заявки этого оператора
		$s = NULL; if( $a = get_orders( 'by_user' ) ) foreach( $a as $r ){
			
			$s .= "<p class='small'><b class='badge bg-secondary mx-1'>140</b> <a href='/order/{$r['id']}' class='text-info'>Заявка №{$r['id']}</a><br /><span class='text-secondary'>" . date( 'd.m H:i', $r['uts'] ) . "</span></p>\n";
			
		}
		
		// print_r( $r );
		// print_r( $a );
		
		$g['title'] = 'CRM ' . $g['u']['crm'];
		$g['body'] = preg_replace( '/-YORS-/', $s, file_get_contents( 'inc/template.html' ) );
		
		include_once( 'bootstrap.php' );
		
	}

// Разбор ЧПУ + редирект для прелоадов
	function human_url(){
		global $ua;
		
		// Разбираю ЧПУ в массив
		if( !empty( $_REQUEST['p'] ) ) $ua = explode( "/", rtrim( $_REQUEST['p'], '/' ) ); if( empty( $ua[0] ) ) $ua[0] = 'main_screen';
		
		// Если есть функция без необходимости загрузки страницы, подключаю
		if( function_exists( $ua[0] ) ) $ua[0]();
		
		exit;
	}

?>