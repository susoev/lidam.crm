<!doctype html>
<html lang='en'>
	<head>
		<!-- Required meta tags -->
		<meta charset='utf-8'>
		<meta name='viewport' content='width=device-width, initial-scale=1'>

		<!-- Bootstrap CSS -->
		<link href='/css/bootstrap.min.css' rel='stylesheet' crossorigin='anonymous'>
		<link href='/css/main.css' rel='stylesheet' />

		<style>.in_tag{text-decoration:none;cursor:pointer}</style>

		<title><?=$g['title']?></title>
	</head>
	<body>
		<nav class='bg-dark py-2'>
			<div class='container text-warning'>
<?
	
	// Если идет смена
	$s = "<span class='count_down' data-counter='{$g['u']['shift_stop']}'>загрузка...</span>";
	
	// Если не на смене
	if( !isset( $g['u']['shift_stop'] ) ) $s = "<a class='badge bg-danger' href='/start_work'>Начать cмену</a>";
	
	// Если не авторизован, меню не показывает
	if( !empty( $g['u']['crm'] ) ){

		// Если нужно обновить с гитхаба
		if( git_check() ) echo "<a href='/git_check' class='tdn' title='Сделать обновление с гитхаба'>🔥</a>";
		
?>
	
	<a href='/profile' class='text-white'><?=$g['u']['name']?></a> / 
	<a href='/' class='text-white'>Главная</a> / 
	<a class='text-white' href='/'>#Возражения</a> / 
	<a class='text-white' href='/help'>Помощь</a> / 
	<?=$s?>
	
<?
	}
?>
			</div>
		</nav>
		<div class='container py-3'>
			<div class='row'>
<?
	
	// Короткие ссылки
	if( preg_match( '/^inc\/.+/', $g['body'] ) ) $g['body'] = file_get_contents( $g['body'] );

	// Покажет алерты
	if( isset( $_REQUEST['msg'] ) ){

?>
<div class="alert alert-warning alert-dismissible fade show" role="alert">
	<?=$_REQUEST['msg']?>
	<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?

	}
	
	echo $g['body'];
	
?>

			</div>
		</div>
		
		<!-- Option 1: Bootstrap Bundle with Popper -->
		<script src='/js/bootstrap.bundle.min.js'></script>
		<script src="/js/jquery-3.6.3.min.js"></script>
		

<script>
	
	// Только цифры
	$('.numeric').on('input', function ( event ) { 
		
		this.value = this.value.replace( /\D/g, '' );
	
	});
	
// Переключит фон для активного инпута
	$( 'input[type=text]' ).focus(function() {
		
		// Уберет везде бгшку
		$( '.click' ).each( function() {
				
			$( this ).removeClass( 'bg-warning' )
			
		});
		
		$( this ).closest( '.click' ).addClass( 'bg-warning' );
	
	});
	
// Фокус в инпут по клику на TR
	$( '.click' ).on( 'click', function () {
		
		if( t = $( this ).data( 'target' ) ){
			
			$( '.click' ).each( function() {
				
				$( this ).removeClass( 'bg-warning' )
				
			});
			
			$( this ).addClass( 'bg-warning' );
			
			$( '#' + t ).focus()
			
		}
		
	});
	
// Добавляет теги в инпут по клику console
	$( document.body ).on( 'click', '.in_tag', function () {
		
		var v = $( this ).text();
		
		var inp =  $( this ).closest( '.click' ).find( 'input[type=text]' );
		
		var txt = false;
		
		if( $( this ).data( 'replace' ) ){
			
			txt = v;
			$( '.sresalt' ).addClass( 'd-none' );
		}
		
		inp.val( function(){
			
			if( txt ) return txt;
			return this.value + v + ', ';
			
		});
		
		inp.focus();
		
	});
	
// Поисковое поле с аяксом
	var aj = document.querySelector( '[data-ajax]' ); if( aj ) aj.addEventListener( 'input', function ( e ){
		
		console.log( this.value.length +' : '+ this.value );
		
		// Не отправляет сообщения менее 3 символов
		if( this.value.length < 3 ){
			
			$( '.sresalt' ).css( 'display', 'none' );
			return false;
			
		} else {
			
			$( '.sresalt' ).removeClass( 'd-none' )
			
		}
		
		// Запрос на удаленку
		var url = '/ajax_cty.php?cty=' + encodeURI( this.value );
		
		$.get( url, function( data ) {
			
			// Если есть ответ
			if( data.length ){
				console.log( data );
				var p = $( '.sresalt' );
				p.css( 'display', 'block' );
				p.html( data );
				
			} else {
				
				var p = $( '.sresalt' );
				p.css( 'display', 'none' );
				
			}
			
		});
		
	} );
	
// Копирование в буфер по клику!
	$( '.click2copy' ).click( function(){
		
		var x = $( '#' + $( this ).data( 'target' ) );
		x.select();
		navigator.clipboard.writeText( x.val() );
		
	});
	
// Обратный отсчет
	if( $( 'span' ).hasClass( 'count_down' ) ){
		
		// var jso = JSON.parse( getCookie( 'user' ) );
		// var obj = jQuery.parseJSON( getCookie( 'user' ) );
		// console.log( obj );

		var tid = setInterval( function(){
			
			// Всего сек
			var t1 = $( '.count_down' ).data( 'counter' ) - Math.floor( ( new Date() ).getTime() / 1000 );

			
			// jso = JSON.parse( getCookie( 'user' ) );
			// console.log( jso.shift_stop );
			
			// Закончит смену, если закончилось время
			if( t1 < 1 ){

				document.body.innerHTML = "<h1 class='text-center py-4 my-4'><span class='text-danger'>Внимание!</span><br />Смена закончилась!</h1>";
				clearInterval( tid );
				window.location.replace( "http://lidam.crm/stop_work?ref=auto" );
				return;

			}

			// Всего мин
			var t2 = Math.floor( t1 / 60 );
			
			// Всего часов
			var t3 = Math.floor( t1 / 3600 );
			
			// Минут
			var t4 = t2 - t3 * 60;
			
			var t5 = t1 - t2 * 60;
			
			$( '.count_down' ).html( t3 + ':' + t4 + ':' + t5 + ' сек' );
			
			console.log( t1 + ' : ' + t2 + ' : ' + t3 + ' : ' + t4 + ' : ' + t5 )
			
		}, 1000 );
		
	}
	
// Инит листенера // Прослушка звонков
	if( $( 'div' ).hasClass( 'listener' ) ){
		
		var tid = setInterval( listener, 500 );
		
		function listener() {
			
			fetch( '/listener.php?crm=<?=$g['u']['crm']?>' ).then( function( response ) {
				
				return response.text();
				
			}).then( function( data ) {
				
				var arr = JSON.parse( data ); if( !arr ) return false; $( '.listener' ).html( '' );
				
				arr.forEach( ( ar ) => {
					
					// Если такого элемента нет
					if ( !$( '#' + ar['uuid'] ).length ){
						
						d = ar['delay'] ? '<b class="btn btn-sm btn-light">(' + ar['delay'] + ')</b>' : '';
						
						if( !document.getElementById( ar['o'] ) ){
							
							var out = ar['d'] == 'o' ? ' 📲' : '';
							
							$( '.listener' ).append( '<p id="' + ar['uuid'] + '" class="p-1 mb-1"><a class="btn btn-sm btn-' + ar['color'] + '" href="/call_' + ar['d'] + '/' + ar['uuid']  + '">' + ar['o']  + '</a>' + out + d + '</p>' );
							
						}

					}
				
				});
				
			});
		}
		
	}
	
</script>
	</body>
</html><? exit; ?>