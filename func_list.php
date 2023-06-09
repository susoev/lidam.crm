<?

// v 1.1 lidam.crm
// CRM система для работы с заявками
// Эта часть обслуживает процесс работы операторов

// База в люом случае подключается
$db = new mysqli( 'localhost', $g['db'][0], $g['db'][1], $g['db'][2] );
	

// Чек с гита на предмет изменений
	function git_check(){
		global $g, $ua;

		// Если это не админ
		if( $g['u']['id'] != 1 ) return false;

		// Хеш, для сверки с гитом
		$f = 'tmp/github.tmp';

		// Создаст файл, если ещё нет
		if( !is_dir( 'tmp' ) ) mkdir( 'tmp' ); if( !is_file( $f ) ) file_put_contents( $f, '' );

		// Если локальный вызов, не из браузера, выдаст напоминание об обновлении
		if( $ua[0] != __FUNCTION__ ){

			// Если обновление более суток
			if( ( ( filectime( $f ) + 86400 ) < $_SERVER['REQUEST_TIME'] ) || !filesize( $f ) ) return true;

			return false;

		}

		// Делает запрос к гиту, возврайщается обычным массивом, для сравнения, делаю в json
		$la = git_load( 'contents/' ); $a = json_encode( $la, JSON_UNESCAPED_UNICODE );
		
		// Сравнит json в хеше и полученный
		if( !$la ) die( 'Err_06:' . __FUNCTION__ );

		// Локальный файл
		$fa = file_get_contents( $f );

		// Если есть изменения
		if( ( $a != $fa ) || isset( $_REQUEST['update'] ) ){

			// Содержимое локального файла
			$fa = json_decode( $fa, true );

			// Лист перезаписи
			$msg = NULL;

			// Иду по гихабу
			foreach( $la as $k => $v ){
				
				// Если папка
				if( $v['type'] == 'dir' ){

					// Создаст если нет
					if( !is_dir( $v['name'] ) ) mkdir( $v['name'] );

					continue;

				}

				// Если файл изменился
				if( $v['sha'] != $fa[$k]['sha'] ){

					// Достанет контент // и перезапишет его
					$ar = git_load( 'contents/' . $v['path'] );
					
					file_put_contents( $v['path'], base64_decode( $ar['content'] ) );

					$msg .= "Обновлен: {$v['path']}<br />\n";
					
				}

			}
			
			// Запишет то что получил от гита в файлик
			file_put_contents( $f, $a );
			
			// Отправит восвояси
			exit( 'me-ok' ); header( "Location: /" . ( $msg ? "?msg=" . urlencode( $msg ) : NULL ) );
			die;

		} else {

			die( 'nothing to change' );

		}
		
	}

// Загрузка с гита // Достанет файлы с гитхаба
	function git_load( $tail ){
		global $g;

		$ch = curl_init(); $a = false;
		
		curl_setopt( $ch, CURLOPT_URL, $g['repo'] . $tail );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, [

			'Accept: application/vnd.github+json',
			'Authorization: Bearer ' . $g['gha'],
			'X-GitHub-Api-Version: 2022-11-28',
			'User-Agent: REQUEST'

		] );
		
		$res = curl_exec( $ch ); curl_close( $ch );

		// Результат
		if( $res ){
			
			$a = json_decode( $res, true );

			foreach( $a as $k => $ar ){

				if( $ar['type'] == 'dir' ) $a = array_merge( $a, git_load( 'contents/' . $ar['path'] ) );

			}
			
		}

		return $a;

	}

// Достанет все заявки по теме. Для запроса конкретной заявки( 10, 198509 ) = ( сервер, номер заявки )
	function text_orders( $srv = 0, $oid = 0 ){
		global $g;
		
		// Соберет все темы по которым можно получать заявки
		foreach( json_decode( file_get_contents( "{$g['u']['crm']}.json" ), true ) as $a ) if( $a[0] > 1000 ) $tar[] = $a[0];
		
		// Отправка запроса на заявки
		$r = file_get_contents(

			$g['svr_out'][0], false, stream_context_create(

				array(

					'http' => array(

						'header'  => "Content-type: application/x-www-form-urlencoded\r\n", 'method'  => 'POST', 'content' => http_build_query( [

							'a' => $tar,
							'crm' => $g['u']['crm'],
							'api_key' => $g['svr_out'][1],
							'oid' => $oid,
							'srv' => $srv

						] )

					)
				)
			)
		);
		
		// Возвращает результат
		return json_decode( $r, true );

	}

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
		$r = get_orders( 'order' )[0]; $qar = json_decode( $r['jso'], true ); if( empty( $r ) ) exit( 'Ошибка. Такой заявки нет!' );
		
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
		$s = NULL; if( !empty( $na ) ) foreach( $na as $k => $v ) $s .= "<p class='mt-3'>" . date( 'd.m.Y H:i', $v['uts'] ) . " ( {$v['dura']} сек )<br />" . ( !empty( $v['url'] ) ? "<audio controls=''><source src='{$v['url']}' type='audio/mpeg'></audio>" : NULL ) . "</p>";
		
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
		$q = "UPDATE `orders` SET `jso` = '" . json_encode( $q, JSON_UNESCAPED_UNICODE ) . "', `cty` = {$r['cty']}, `price` = " . preg_replace( '/\D/', '', $r['price'] ) . ", `phn` = " . ( $r['phn'] ? "'{$r['phn']}'" : 'NULL'  ) . ", `adt` = '{$r['adt']}', `nme` = '{$r['nme']}', `txt` = '{$r['txt']}' WHERE `id` = {$r['id']}";
		
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
		$db -> query( "INSERT INTO `orders` ( `crm`, `oid`, `uuid`, `uts`, `jso`, `cty`, `tid`, `price`, `call`, `phn`, `adt`, `nme`, `txt` ) VALUES ( {$r['crm']}, {$g['u']['id']}, '{$r['uuid']}', '{$_SERVER['REQUEST_TIME']}', '{$r['q']}', {$r['cty']}, {$r['tid']}, {$r['price']}, '{$r['call']}', " . ( $r['phn'] ? "'{$r['phn']}'" : 'NULL'  ) . ", '{$r['adt']}', '{$r['nme']}', '{$r['txt']}' );" );
		
		// Переадресует обратно
		header( "Location: /?saved_order={$db -> insert_id}" );
		
		exit;
		
	}
	
// Текстовая заявка с сайта
	function call_o(){
		global $ua, $g, $db;
		
		// Получает данные об этой заявке
		$r = text_orders( $_REQUEST['s'], $_REQUEST['oid'] ); if( !$r ) die( 'Err_07' );

		// Получает скрипт для этого шлюза
		$a = json_decode( file_get_contents( "{$g['u']['crm']}.json" ),true );
		
		// Список всех скриптов
		$ss = NULL; foreach( $a as $k => $v ) if( ( !empty( $v[1] ) && ( $v[0] != 1000 ) ) ) $ss .= "<a href='/call_o/?s={$_GET['s']}&tid={$_GET['tid']}&oid={$_GET['oid']}&script={$k}'>{$v[1]}</a> / "; $ss .= "<a href='/call_i/{$r['uuid']}?script=other'>Другое</a>";

		list( $q, $a ) = get_quiz( $a, NULL ); print_r( $r ); echo $q; exit;

		// Домен откуда заявка
		$ws = "<a href='{$r['url']}' target='_blank'>" . parse_url( $r['url'] )['host'] . "</a>";

		// Текст заявки, если есть
		$txt = mb_strlen( $r['txt'] ) > 2 ? "<div class='my-2 text-info border border-info p-3'>{$r['txt']}</div>" : '';
		
		$g['body'] = preg_replace( [ '/-WS-/', '/-SSS-/', '/-CTY-/', '/-CNME-/', '/-NME-/', '/-TXT-/', '/-QUIZ-/' ], [ $ws, $ss, $r['city_im'], $r['nme'], "[{$g['u']['name']}]", $txt, $q ], file_get_contents( 'inc/tm_call_o.html' ) );
		$g['title'] = "Исходящий";
		
		include_once( 'bootstrap.php' );
		
	}

	// Входящий звонок
	function call_i(){
		global $ua, $g, $db;
		
		// Получает звонок
		if( !$r = get_call( $ua[1] ) ) exit( 'err_04' );
		
		// Получает скрипт для этого шлюза
		$a = json_decode( file_get_contents( "{$g['u']['crm']}.json" ),true );
		
		// Список всех скриптов
		$ss = NULL; foreach( $a as $k => $v ) if( ( !empty( $v[1] ) && ( $v[0] != 1000 ) ) ) $ss .= "<a href='/call_i/{$r['uuid']}?script={$k}'>{$v[1]}</a> / "; $ss .= "<a href='/call_i/{$r['uuid']}?script=other'>Другое</a>";
		
		list( $q, $a ) = get_quiz( $a, $r['g'] );
		
		// Получает все звонки по этому номеру
		if( $na = call_byNum( $r['o'] )  ) $s = NULL; foreach( $na as $k => $v ) $s .= "<p class='mt-3'>" . date( 'd.m.Y H:i', $v['uts'] ) . " ( {$v['dura']} сек )<br />" . ( !empty( $v['url'] ) ? "<audio controls=''><source src='{$v['url']}' type='audio/mpeg'></audio>" : NULL ) . "</p>";
		
		// Если по этому номеру тел. уже были заявки
		$so = ''; if( $ora = get_orders( $r['o'] ) ){ $so = "<p>😳 <b class='text-danger'>Внимание: по этому номеру есть заявки:</b><br />"; foreach( $ora as $k => $v ) $so .= "<a target='_blank' href='/order/{$v['id']}'>№{$v['id']} от " . date( 'd.m.Y H:i', $v['uts'] ) . "</a><br />"; $so .= "</p>"; }
		
		// Подключает форму, карточка звонка ( inc/tm_call_i.html )
		$g['body'] = preg_replace( [ '/-ORDERS-/', '/-SSS-/', '/-NME-/', '/-QUIZ-/', '/-THEME-/', '/-INTO-/', '/-FROM-/', '/-HIST-/' ], [ $so, $ss, "[{$g['u']['name']}]", $q, "[{$a[1]}]", $r['g'], $r['o'], $s ], file_get_contents( 'inc/tm_call_i.html' ) );
		$g['title'] = "Карточка звонка";
		
		include_once( 'bootstrap.php' );
		
	}

// Формирует html форму опросника принимает $a массив всех опросников и $gw - gatewaym куда звонят при входящем
	function get_quiz( $a, $gw ){

		// Если есть $_GET['tid'] - значит это исходящая заявка
		if( isset( $_GET['tid'] ) ){

			foreach( $a as $k => $ar ) if( $ar[0] == $_GET['tid'] ){

				$gw = $k;
				$scr = $k;

				break;

			}

		} else {

			// Применение допскрипта same
			$scr = ( isset( $_GET['script'] ) && !$gw ) ? $_GET['script'] : $gw;

		}

		// Если такой шлюз есть в списке same
		if( isset( $a[$scr] ) ){
			
			// Если у него маркер same // Тему оставит
			if( $a[$gw][2][0] == 'same' ){
				
				$tid = $a[$gw][0];
				$a = $a[$a[$gw][2][1]];
				$a[0] = $tid;
				
			} else {
				
				$a = $a[$scr];
				
			}
			
		} else {
			
			// Скрипта с таким шлюзом нет
			die( 'err_05' );
			
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

		return [ $q, $a ];
		// print_r( [ $q, $a ] ); die;
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
		global $g, $db;
		
		// достанет Смены пользователя
		$res = $db -> query( "SELECT * FROM `shift` WHERE `uid` = {$g['u']['id']} ORDER by `id` ASC" );
		
		if( $res -> num_rows ){
		
			$sh_s .= "<h3 class='mb-3'>Ваши смены</h3>";
			while( $r = $res -> fetch_array( MYSQLI_ASSOC ) ){

				$sh_s .= "<p>" . date( "d.m.Y <b>H:i</b>", $r['start'] ) . " - " . ( !empty( $r['end'] ) ? date( "<b>H:i</b>", $r['end'] ) . " | " . round( ( $r['end'] - $r['start'] ) / 3600 ) . " ч." : "..." ) . "</p>";

			}

		}

		$g['title'] = 'Профиль';
		$g['body'] = "<div class='col'><p>Ваш профиль. <a href='/logout'>Выход</a></p>{$sh_s}</div>";
		
		unset( $g['menu'] );
		include_once( 'bootstrap.php' );
		
	}

// Начало работы
	function start_work(){
		global $g, $db;
		
		// 2. шаг = выбрал время работы
		if( isset( $_REQUEST['go'] ) ){
			
			// Переупакую куки
			setcookie( 'user', null, -1 ); unset( $_COOKIE['user'] );
			
			// Добавлю время стопа смены + 5 минут
			$g['u']['shift_stop'] = $_SERVER['REQUEST_TIME'] +$_REQUEST['go'] * 60 * 60 + 5 * 60;
			
			// Запишет в кук
			setcookie( 'user', json_encode( $g['u'], JSON_UNESCAPED_UNICODE ), $_SERVER['REQUEST_TIME'] + 30 * 24 * 60 * 60, "/" );

			// Пометка в базу о начале смены
			$db -> query( "INSERT INTO `shift` ( `uid`, `start` ) VALUES ( {$g['u']['id']}, '{$_SERVER['REQUEST_TIME']}' )" );
			
			header( "Location: /?msg=" . urlencode( "Вы начали смену!" ) );
			
		}
		
		// 1. шаг Начало работы - экран, выбирает время смены
		$g['title'] = "Начало работы!";
		$g['body'] = "inc/start_work.html";
		
		unset( $g['menu'] );
		
		include_once( 'bootstrap.php' );
		
	}

// Конец смены
	function stop_work(){
		global $g, $db;
		
		
		// Переупакует куки
		setcookie( 'user', null, -1 ); unset( $_COOKIE['user'] );
		
		// Запишет новый кук без смены
		unset( $g['u']['shift_stop'] );
		setcookie( 'user', json_encode( $g['u'], JSON_UNESCAPED_UNICODE ), $_SERVER['REQUEST_TIME'] + 30 * 24 * 60 * 60, "/" );

		// Достанет из базы инфу о последнем начале смены
		$id = $db -> query( "SELECT `id` FROM `shift` WHERE `uid` = {$g['u']['id']} ORDER by `id` DESC LIMIT 1" ) -> fetch_array( MYSQLI_ASSOC )['id'];

		// Пометка в базу о конце смены
		$db -> query( "UPDATE `shift` SET `end` = '{$_SERVER['REQUEST_TIME']}' WHERE `id` = {$id}" );
		
		header( "Location: /?msg=" . urlencode( "Поздравляем! Смена закончилась. Можно передохнуть и попробовать еще раз" ) );
		
		die;
		
	}

// Главный экран
	function main_screen(){
		global $g;
		
		$start_shift = "<a href='/start_work'>Начать смену</a> для отображения";

		// Достанет заявки этого оператора
		$yo_s = NULL; if( $a = get_orders( 'by_user' ) ) foreach( $a as $r ) $yo_s .= "<p class='small'><b class='badge bg-secondary mx-1'>!</b> <a href='/order/{$r['id']}' class='text-info'>Заявка №{$r['id']}</a> <span class='text-secondary'>" . date( 'd.m H:i', $r['uts'] ) . "</span></p>\n";
		
		// Достанет все текстовые заявки!! Внимание, предусмотри дизабл тех, кто уже есть
		$fo_s = $start_shift;

		if( $g['u']['shift_stop'] ) if( $a = text_orders() ){

			$fo_s = NULL;

			// Проверит и уберет заявки с сайта, по которым уже были созвоны
			$a = check_2orders( $a );
			
			// Выводит не более 10 штук, чтобы не засирать экран
			$i = 0; foreach( $a as $r ){ $i++; if( $i > 10 ) break;

				$fo_s .= "<p class='small'><a href='/call_o?s={$r[0]}&tid={$r[1]}&oid={$r[2]}' class='btn border btn-sm btn-light'>{$r[4]} <small>(" . ( ceil( ( $_SERVER['REQUEST_TIME'] - $r[3] ) / 60 ) ) . " м )</small> </a></p>\n";

			}

		}

		// Входящие звонки показывает только если идёт смена
		$ca_s = empty( $g['u']['shift_stop'] ) ? $start_shift : "<div class='listener'>Загрузка ...</div>";
		
		// Вывод
		$g['title'] = 'CRM ' . $g['u']['crm'];
		$g['body'] = preg_replace( [ '/-CALLS-/', '/-YORS-/', '/-FORMS-/' ], [ $ca_s, $yo_s, $fo_s ], file_get_contents( 'inc/template.html' ) );
		
		include_once( 'bootstrap.php' );
		
	}

// Проверит по номера, если в системе уже есть такие заявки, то удалит номера из архива
	function check_2orders( $a ){
		global $db;
		
		// достанет номера по всем заявкам из системы за последние 5 дней
		$res = $db -> query( "SELECT `call` FROM `orders` WHERE `uts` > " . ( $_SERVER['REQUEST_TIME'] - 86400 * 50 ) . " ORDER by `id` DESC" );
		
		if( !$res -> num_rows ) return $a;
		
		// Проверка номеров из базы на вхождение в искомый массив
		while( $r = $res -> fetch_array( MYSQLI_ASSOC ) ){

			// Берем только последние 10 цифр телефона, т.к. могут быть варианты 922, 8922, 7922, +7922
			$n = substr( $r['call'], -10 );

			// Сравнение
			foreach( $a as $k => $ar ){

				// Если находит такой номер, то удаляет элемент их массива
				if( substr( $ar[4], -10 ) == $n ){

					unset( $a[$k] ); break;

				}

			}

		}
		
		return $a;

	}

// Разбор ЧПУ + редирект для прелоадов !!!
	function human_url(){
		global $ua;
		
		// Разбираю ЧПУ в массив
		if( !empty( $_REQUEST['p'] ) ) $ua = explode( "/", rtrim( $_REQUEST['p'], '/' ) ); if( empty( $ua[0] ) ) $ua[0] = 'main_screen';
		
		// Если есть функция без необходимости загрузки страницы, подключаю
		if( function_exists( $ua[0] ) ){

			if( !$ua[0]() ) die( 'Err:' . __FUNCTION__ . ":" . $ua[0] );

		}

	}
	

?>