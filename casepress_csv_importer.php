<?php

/**
*Plugin Name: GdeSlon Coupons CSV Import
*Plugin URI: -
*Description: A plugin that helps to import the data's from a CSV file to posts.
*Version: 0.20140316
*Author: CasePress
*Author URI: http://casepress.org
 */


	
/*******************************************************************************
* Передаем данные из массива в пост WordPress
*
*/
function CSVtoPost($real_path) {
	
	//Пытаемся установить локаль, чтобы функция парсинга CSV нормально распознала файл
	echo '<br>локаль: ' . setlocale(LC_ALL, 'ru_RU.UTF-8', 'rus_RUS.UTF-8', 'Russian_Russia.UTF-8');

	//посчитаем количество строк
	if (($handle = fopen($real_path, "r")) !== FALSE) {
		$row = 0;

		while (($data = fgetcsv($handle, 2000, ';')) !== FALSE) {
			$row++;
		}
		$count_row_csv = $row;
		fclose($handle);
	}
	echo ", count_row_csv = " . $count_row_csv;

	//Загружаем файл
	if (($handle = fopen($real_path, "r")) !== FALSE) {
		$row = 0;
		//echo '<br>сount($data) = ' . сount($data1);
		while (($data = fgetcsv($handle, 0, ';', '"')) !== FALSE) {
			$row++;
			echo '$row = ' . $row;
			//если это первая строка CSV файла, которая содержит заголовок, то пропустить
			if($row == 1) continue;
		   // if($row > 11) continue;
			
			//если запись с таким ИД есть, то пропустить, не забыть убрать коммент с условия
			$chekidcsv = new WP_Query('post_type=coupon&post_status=any&meta_key=id_csv&meta_value='.$data[0]);
			if($chekidcsv->found_posts > 0) continue;
			echo ', $chekidcsv->found_posts: ' . $chekidcsv->found_posts;
			
			$num = count($data);
			echo ", $num полей в строке $row, ";
			$cp_gdeslon_post_types_select = esc_attr( get_option( 'cp_gdeslon_post_types_select' ) );
			
			//create var for new post
			$newpost = array(  
				 'post_title' => 'Заголовок записи',  
				 'post_content' => 'Здесь должен быть контент (текст) записи.',  
				 'post_type' => $cp_gdeslon_post_types_select,  
				 'post_status' => 'draft',  
				 'post_author' => 1
				);  
			$post_id = wp_insert_post($newpost);
			echo ', new post id = '.$post_id;
			for ($c=0; $c < $num; $c++) {
				
				//вывод информации в лог
				echo ", Поле №".$c." = ".$data[$c];
				
				// запись ID из CSV
				if($c == 0) add_post_meta($post_id, 'id_csv', $data[$c]);

				//Заголовок
				if($c == 1) $newpost['post_title'] = $data[$c];
				
				//Описание
				if($c == 2) $newpost['post_content'] = $data[$c];
				//Тип бонуса
				if($c == 3) add_post_meta($post_id, 'instruction', $data[$c]);
				
				//дата начала акции
				if($c == 4) add_post_meta($post_id, 'start_at', $data[$c]);
				
				//дата окончания акции
				if($c == 5) add_post_meta($post_id, 'finish_at', $data[$c]);
				
				//Code
				if($c == 6) add_post_meta($post_id, 'promocode', $data[$c]);
				
				//kind
				if($c == 7) add_post_meta($post_id, 'kind', $data[$c]);

				//merchant
				if($c == 8) add_post_meta($post_id, 'merchant', $data[$c]);
				
				//logo
				if($c == 9) add_post_meta($post_id, 'logo', $data[$c]);

				//Ссылка перехода
				if($c == 10) add_post_meta($post_id, 'csv_gotolink', $data[$c]);

				//url-with-code
				if($c == 11) add_post_meta($post_id, 'url-with-code', $data[$c]);

				//categories
				if($c == 12) add_post_meta($post_id, 'categories', $data[$c]);

			}
			$newpost['ID'] = $post_id;
			wp_update_post($newpost);
			echo "<hr>";
		}
		fclose($handle);
	}

	return;
}


/**********************************************
 * Механизм переадресации по $url если указан ключ ?url=go для поста
 */
	
add_action( 'template_redirect', 'cp_gdeslon_redirect_to_url', 10 );
function cp_gdeslon_redirect_to_url() {
    global $post;
    
	if (is_singular('coupon') && isset( $_GET['url'] ) && $_GET['url'] == 'go' ) {
        $location = get_post_meta($post->ID, 'csv_gotolink', true);
        wp_redirect( $location, '302' );  
        exit;
	}
}





add_action( 'edited_category', 'cp_gdeslon_csv_key_save');
function cp_gdeslon_csv_key_save( $term_id ) {
    if ( isset( $_POST['csv_key'] ) ) {
         
        //Загружаем текущую связку ключей
        $keys = get_option( 'cp_csv_keys_and_term_ids' );
 
        //Если опции такой нет, определяем пустой массив
        if($keys == FALSE) $keys = array();
        
        //Присваиваем в ячейку массива связь термина и ключа CSV
        $keys[$term_id] = $_POST['csv_key'];
         
        //save the option array
        update_option( 'cp_csv_keys_and_term_ids', $keys );
    }
}

	
/*****************************************************************
* Механика страницы консоли для управления загрузкой
*/

add_action('admin_menu', 'cp_gdeslon_csv_menu_setup'); //menu setup
//Страница настроек
function cp_gdeslon_csv_menu_setup(){
	add_management_page(
	 'ГдеСлон - Импорт купонов',
	 'ГдеСлон - Импорт купонов',
	 'manage_options',
	 'msp_cpcsv',
	 'gdeslon_msp_cpcsv_admin_page_screen'
	 );
}

//Запуск AJAX-хука из консоли
function gdeslon_msp_cpcsv_admin_page_screen() {
?>
<div id="cp_csv_imp">
	<h1>Загрузка CSV</h1>
	<p>Данные загружаются в метаполя. Классификация загружается в Метки. Тип поста и прочие параметры можно задать на <a href="<?php echo admin_url() ?>options-general.php?page=cp_gdeslon_settings_page">странице настроек</a></p>
	<a href="#" name="loadcsv" onClick="loadcsv()">Загрузить</a>
	<script>
		function loadcsv() {
			//alert("Hi!");
			var data = {
				action: 'load_cp_csv_coupons'
			};

			// since 2.8 ajaxurl is always defined in the admin header and points to admin-ajax.php
			jQuery.post(ajaxurl, data, function(response) {
				jQuery('#cp_csv_imp').append('<p>Ответ сервера: ' + response + '</p>');
			});
		}
	</script>
</div>
<?php
}


//Запуск функции через AJAX-хук
add_action('wp_ajax_load_cp_csv_coupons', 'gdeslon_load_cp_csv_coupons');
function gdeslon_load_cp_csv_coupons(){
	$setting_name = 'cp_gdeslon_url_csv';
	$urlcsv = esc_attr( get_option( $setting_name ) );

    echo '<br>Пошла загрузка файла...';
    $tmp = realpath(download_url( $urlcsv ));
    echo "<br>Файл загружен: " . $tmp;
	
	//Передаем путь к файлу в функцию загрузки
	CSVtoPost($tmp);
	
    //echo "файл удален: " . unlink($tmp);
    
	//Die - нужен чтобы нормально сообщения в лог загрузки вышли
	die();
}
  
include_once('includes/view.php');
include_once('includes/controllers.php');
include_once('includes/settings-api.php');
include_once('includes/wp-cron-api.php');
?>
