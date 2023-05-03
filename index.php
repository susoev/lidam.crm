<?
	
	// Подключаются настройки
	include_once( 'set_up.php' );
	
	// Пароли
	include_once( 'secret.php' );
	
	// И функционал ()
	include_once( 'func_list.php' );
	
	// Проверка авторизации
	check_auth();
	
	// Отрабатывает ЧПУ
	human_url();
	
?>