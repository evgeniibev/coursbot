<?php 
header('Content-Type: text/html; charset=utf-8');
$start = microtime(true);

if (!isset($_REQUEST)) { 
	return;
} 

$data = json_decode(file_get_contents("php://input"));
$key = file_get_contents("key.txt"); // ключ ВК
$appid = file_get_contents("appid.txt"); // ключ twitch
$bdname = file_get_contents("bdname.txt"); // имя БД
$bdpass = file_get_contents("bdpass.txt"); // пароль БД
$bdlogin = file_get_contents("bdlogin.txt"); // логин БД



// CURL запрос
	function curltw(&$curl_url, &$appid){
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $curl_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
		$headers = array();
		$headers[] = 'Accept: application/vnd.twitchtv.v5+json';
		$headers[] = "Client-Id: $appid";
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		global $result;
		$result = curl_exec($ch);
		curl_close($ch);
	}

	
	date_default_timezone_set('Asia/Yekaterinburg');
	//global $key, $data, $start, $appid;
	switch($data->type){


	case 'confirmation':
			$group_id = $data->group_id;
			if ($group_id != '189871008'){
				echo("error:(");
			}
  			else echo "d68cffd9";
  		break;

	case "message_new":

			$now_time = strtotime("now");
			$mes_time = $data->object->date;
			$var4 = $now_time - $mes_time;
			if ($var4 > 7){
				echo("ok");
				break;
			}
			
			//id user'a и сообщения
			$user_id = $data->object->user_id;
			$mes_id = $data->object->id;

			//понижение регистра
			$body = $data->object->body;
			$body = mb_strtolower($body);
			$qbody = explode(' ', $body);

			//получить имя пользователя
			$user_info = json_decode(file_get_contents("https://api.vk.com/method/users.get?user_ids={$user_id}&access_token={$key}&v=5.78"));
			$user_name = $user_info->response[0]->first_name;



			// отправка сообщения в ВК
			function sendvk(&$mes_for_send, &$user_id, &$key, &$keyboard){
				$req_mes = array(
					'user_id' => $user_id,
					'random_id' => mt_rand(),
					'message' => "$mes_for_send",
					'keyboard' => $keyboard,
					'access_token' => $key,
					'read_state' => 1,
					'v' => '5.78'
					);
				$get = http_build_query($req_mes);
				file_get_contents("https://api.vk.com/method/messages.send?".$get);
			}


			//список команд помощи
			$hello_mas = ["привет", "help", "хелп", "начать", "start", "команды"];
			// список команд
			if (in_array($body, $hello_mas)) {

				if ($body == "привет" or $body == "начать"){
					$hello = "Привет, $user_name! ";
				}

				$mes_for_send = "$hello" . "Список команд бота:\n\n1. sub - Подписаться на стримера (sub welovegames)\n\n2. unsub - Отписаться от стримера (unsub Jolygames)\n\n3. подписки - На кого вы подписаны (подписки)\n\n4. онлайн - Кто из ваших стримеров сейчас стримит (онлайн)\n\n5. top - кого сейчас смотрят (топ 5)\n5.1. Можно указать игру или категорию (топ 4 minecraft)\n\n6. info - когда стример создал канал (info twitch)";
				sendvk($mes_for_send, $user_id, $key, $keyboard);
			}
			


			// отписаться на стримера
			else if ($qbody[0] == 'ансаб' or $qbody[0] == 'unsub'){
				$connect = mysqli_connect('localhost', "$bdlogin", "$bdpass", "$bdname");
				$queryz = "SELECT * FROM `twitch` WHERE `idvk` LIKE '$user_id' AND `streamer` LIKE '$qbody[1]'";
				$result = mysqli_query($connect, $queryz);


				$del_check = $result->num_rows;

				//если не был подписан на стримера
				if ($del_check == 0){

					$mes_for_send = "Вы и не подписывались на $qbody[1] :|";
					sendvk($mes_for_send, $user_id, $key, $keyboard);

					//echo 'ok';
					break;
				}


				// если действительно был подписан - отписать
				else{
					$queryz = "DELETE FROM `twitch` WHERE `idvk` LIKE '$user_id' AND `streamer` LIKE '$qbody[1]'";
					$result = mysqli_query($connect, $queryz);


					//если у кого-то этот стример ещё есть
					$queryz = "SELECT * FROM `twitch` WHERE `streamer` LIKE '$qbody[1]'";
					$result = mysqli_query($connect, $queryz);

					$del_check_all = $result->num_rows;

					//если ни у кого нет - удалить его из БД 'status'
					if ($del_check_all == 0){
						$queryz = "DELETE FROM `status` WHERE `streamer` LIKE '$qbody[1]'";
						$result = mysqli_query($connect, $queryz);
					}

					$mes_for_send = "Вы отписались от $qbody[1]";
					sendvk($mes_for_send, $user_id, $key, $keyboard);

					//echo 'ok';
					break;
				}
			}



			// подписаться на стримера
			else if ($qbody[0] == 'саб' or $qbody[0] == 'sub'){
				$connect = mysqli_connect('localhost', "$bdlogin", "$bdpass", "$bdname");
				$queryz = "SELECT * FROM `twitch` WHERE `idvk` LIKE '$user_id' AND `streamer` LIKE '$qbody[1]'";
				$result = mysqli_query($connect, $queryz);

				$na_meste = $result->num_rows;
				//подписан ли человек на стримера. Если нет, тогда его подписать
				if ($na_meste == 0 or !$na_meste) {


					// CURL информация о стримере по никнейму
					$curl_url = "https://api.twitch.tv/kraken/users?login=$qbody[1]";
					curltw($curl_url, $appid);

					// разбор
					$meh = json_decode($result);
					$real_streamer = $meh->_total;

					//есть ли такой стример. Если нет..
					if ($real_streamer == 0){
						$mes_for_send = "Стример $qbody[1] не найден";
						sendvk($mes_for_send, $user_id, $key, $keyboard);
		  			}
					
					

					// если найден ->
					else {
						$id_streamer = $meh->users[0]->_id;
						$online_name = $meh->users[0]->name;
						$display_name = $meh->users[0]->display_name;

						// получаем инфо. о стримере(канале) через его id
						$curl_url = "https://api.twitch.tv/kraken/streams/?channel=$id_streamer";
						curltw($curl_url, $appid);

						$meh = json_decode($result);

						// стримит ли сейчас
						$broadcast = $meh->streams[0]->broadcast_platform;

						//ссылка на его канал
						$url_streamer = $meh->streams[0]->channel->url;

						// проверка на его онлайн
						if ($broadcast != 'rerun' and isset($broadcast)) {
							$online_status = '1';
							$on_strimit = ". Кстати, он сейчас стримит! \n $url_streamer";
						}
						else $online_status = '0';


						$queryz = "SELECT * FROM `status` WHERE `streamer` LIKE '$qbody[1]'";
						$result = mysqli_query($connect, $queryz);

						$streamer_status = $result->num_rows;

						//Есть ли стример в статусе(в БД). Если нет, тогда добавить
						if ($streamer_status == 0 or !$streamer_status){
							$queryz = "INSERT INTO status (streamer, streamerid, online, id) VALUES ('$online_name', '$id_streamer', '$online_status', NULL)";
							$result = mysqli_query($connect, $queryz);
						}


						//потом подписываем человека на стримера в таблице твича
						$queryz = "INSERT INTO twitch (namevk, idvk, streamer, streamerid, id) VALUES ('$user_name', '$user_id', '$online_name', '$id_streamer', NULL)";
						$result = mysqli_query($connect, $queryz);


						
						$mes_for_send = "Вы подписались на $display_name" . "$on_strimit";
						sendvk($mes_for_send, $user_id, $key, $keyboard);
					}
				}

				// если человек уже подписан на него
				else{
					$mes_for_send = "Вы уже подписаны на $qbody[1]";
					sendvk($mes_for_send, $user_id, $key, $keyboard);
				}
			}



			// на кого подписан
			else if ($qbody[0] == 'подписки' or $qbody[0] == 'subscription' or $qbody[0] == 'список')
			{	

				$connect = mysqli_connect('localhost', "$bdlogin", "$bdpass", "$bdname");
				$queryz = "SELECT * FROM `twitch` WHERE `idvk` LIKE '$user_id'";
				$result = mysqli_query($connect, $queryz);

				$subs_check = $result->num_rows;

				// если кол-во строк БД == 0
				if ($subs_check == 0){
					
					$mes_for_send = "У вас нет подписок";
					sendvk($mes_for_send, $user_id, $key, $keyboard);
				}

				// иначе закинуть в массив всех стриммеров на которых подписан
				else{
					while($row = mysqli_fetch_assoc($result)) { 
						$subs_list[] = $row['streamer'];
					}

					$subs_list_str = implode("\n", $subs_list);

					$mes_for_send = "Ваши подписки:\n" . "$subs_list_str";
					sendvk($mes_for_send, $user_id, $key, $keyboard);
				}
			}



			// кто сейчас стримит
			else if($qbody[0] == "онлайн" or $qbody[0] == "online"){
				$connect = mysqli_connect('localhost', "$bdlogin", "$bdpass", "$bdname");
				$queryz = "SELECT * FROM `twitch` WHERE `idvk` LIKE '$user_id'";
				$result = mysqli_query($connect, $queryz);


				// забираем из БД всех стриммеров, на которых подписан
				while($row = mysqli_fetch_assoc($result)) { 
					$online_vk[] = $row['streamerid'];
				}

				$na_skolkih_podpisan = $result->num_rows;
				$online_vk_str = implode(',', $online_vk);


				// Делаем запрос на twitch. Проверяем online их всех.
				$curl_url = "https://api.twitch.tv/kraken/streams/?channel=$online_vk_str";
				curltw($curl_url, $appid);

				$meh = json_decode($result);

				$kto_online = []; // для отправки
				

				// Суть: проверяем каждого стримера. Если статус стримера "live", тогда берём его никнейм и категорию. После чего добавляем в массив строк.
				for($i = 0; $i < $na_skolkih_podpisan; $i++){
					if ($meh->streams[$i]->stream_type == 'live'){
						$who_online = $meh->streams[$i]->channel->display_name;
						$game_online = $meh->streams[$i]->game;
						$tmp_online = "$who_online" . "\n" . "$game_online" . "\n";

						array_push($kto_online, $tmp_online);
						
					}
				}

				$kto_online_str = implode("\n", $kto_online);

				$mes_for_send = "Стримеры онлайн:\n\n" . "$kto_online_str";
				sendvk($mes_for_send, $user_id, $key, $keyboard);

			}



			// кнопка "подписка"
			else if ($body == "подписка"){

				$mes_for_send = "Для подписки на канал напиши боту: sub NICKNAME \n Вместо NICKNAME напиши ник стримера";
				sendvk($mes_for_send, $user_id, $key, $keyboard);

			}


			// кнопка "отписка"
			else if ($body == "отписка"){

				$mes_for_send = "Для отписки от канала напиши боту: unsub NICKNAME \n Вместо NICKNAME напиши ник стримера";
				sendvk($mes_for_send, $user_id, $key, $keyboard);

			}



			// топ в категории или среди всех
			else if ($qbody[0] == 'топ' or $qbody[0] == 'top'){

				if (!$qbody[1]){
					$top_kolvo = 3;
				}
				else{
					$top_kolvo = $qbody[1];

					if ($top_kolvo > 20 or $top_kolvo < 1){
						$top_kolvo = 20;
					}
				}


				if (!$qbody[2]){
					$game = '';
				}
				else{
					
					unset($qbody[0], $qbody[1]);

					$nazvanie = implode(' ', $qbody);
					$nazvanie = urlencode($nazvanie);

					$game = "&game=$nazvanie";
				}


				//CURL
				$curl_url = "https://api.twitch.tv/kraken/streams/?language=ru&stream_type=live$game";
				curltw($curl_url, $appid);

				$meh = json_decode($result);

				$arr_top_game = [];


				for ($i = 0; $i < $top_kolvo; $i++){

					$kanal = $meh->streams[$i]->channel->display_name;

					if (!$kanal){
						break;
					}

					$kanal = $kanal."\n";
					array_push($arr_top_game, $kanal);


					$igra = $meh->streams[$i]->channel->game;
					$igra = "Играет в: ".$igra."\n";
					array_push($arr_top_game, $igra);


					$zritelei = $meh->streams[$i]->viewers;
					$zritelei = "Зрителей: ".$zritelei."\n";
					array_push($arr_top_game, $zritelei);

					$url_streamer = $meh->streams[$i]->channel->url;
					$url_streamer = $url_streamer."\n"."\n";
					array_push($arr_top_game, $url_streamer);


					
				}


				$str_top_game = implode("", $arr_top_game);
				if(!$str_top_game){
					$str_top_game = "ничего не найдено";
				}

				$mes_for_send = "$str_top_game";
				sendvk($mes_for_send, $user_id, $key, $keyboard);
			}



			// информация о канале
			else if ($qbody[0] == 'инфо' or $qbody[0] == 'info'){

				//CURL
				$curl_url = "https://api.twitch.tv/kraken/users?login=$qbody[1]";
				curltw($curl_url, $appid);

				$meh = json_decode($result);

				$id_strimera = $meh->users[0]->_id;
				$NickName = $meh->users[0]->display_name;


				// когда был создан канал
				$create = $meh->users[0]->created_at;
				$WasCreate = mb_substr($create, 0, 10);
				$WasCreate = strtotime($WasCreate);
				$WasCreate = date('d.m.Y', $WasCreate);

				$WasMinute = mb_substr($create, 11, 5);
				$WasMinute = strtotime("+3 hours", strtotime($WasMinute));
				$WasMinute = date('H:i', $WasMinute);

				$WasSend = "Канал был создан: ".$WasCreate . " в " . "$WasMinute" . " по МСК";

				$curl_url = "https://api.twitch.tv/kraken/streams/?channel=$id_strimera";
				curltw($curl_url, $appid);

				$meh = json_decode($result);

				$partner = $meh->streams[0]->channel->partner;
				if (isset($partner)) {
					if ($partner == 1){
						$partnerka = "Партнёрка: есть" . "\n" . "Стрим: Online";
					}
					else{
						$partnerka = "Партнёрка: нет" . "\n" . "Стрим: Online";
					}
				}
				else {
					$partnerka = "Партнёрка: (нужен стрим)" . "\n" . "Стрим: Offline";
				}

				
				$info_streamer = "Ник: ".$NickName."\n"."ID: ". $id_strimera . "\n" . $partnerka . "\n" . "$WasSend";

				// если нет такого стримера
				if(!$id_strimera){
					$info_streamer = "Стример $qbody[1] не найден!";
				}

				$mes_for_send = "$info_streamer";
				sendvk($mes_for_send, $user_id, $key, $keyboard);
			}



			// WTF?
			else{

				$mes_for_send = "Неизвестная команда. Напиши -> привет";
				sendvk($mes_for_send, $user_id, $key, $keyboard);
			}
			
	echo("ok");
	break;





	case "message_reply":

			$now_time = strtotime("now");
			$mes_time = $data->object->date;
			$var4 = $now_time - $mes_time;
			if ($var4 > 7){
				echo("ok");
				break;
			}

	default:
		//echo "$time";
    	echo("ok");

    	echo "<br/>";
		$connect = mysqli_connect('localhost', "$bdlogin", "$bdpass", "$bdname");
		
		$queryz = "SELECT * FROM `status`";
		$result = mysqli_query($connect, $queryz);

		//streamer
    	$name_in_status = []; 
		 
		while($myrow = mysqli_fetch_assoc($result)) { 
			$name_in_status[] = $myrow['streamer'];

			$online_in_status[] = $myrow['online'];

			$id_in_status[] = $myrow['streamerid'];
		}


		print_r($name_in_status);
		echo "<br/>";
		print_r($online_in_status);
		echo "<br/>";

		//поменять значения с ключами
		$mas = array_flip($name_in_status);
		
		print_r($mas);
		echo "<br/>";
		
		//для каждого значения присвоить онлайн
		for($i = 0; $i < count($mas); $i++){
			$mas[$name_in_status[$i]] = $online_in_status[$i];
		}

		print_r($mas);
		echo "<br/>";
		//echo "$appid";
		echo "<br/>";

		//погнали по твичу
		$id_in_status = implode(",", $id_in_status);

		//CURL
		$curl_url = "https://api.twitch.tv/kraken/streams/?channel=$id_in_status";
		curltw($curl_url, $appid);

		$meh = json_decode($result);
		echo "<pre>";
		//echo "sykaa";
		print_r($meh);
		//echo "</pre>";

		//сколько онлайн
		$now_online_arr = $meh->streams;
		$now_online = count($now_online_arr);


		//если все оффлайн
		if ($now_online == 0){

			$queryz = "SELECT * FROM `status` WHERE `online` LIKE '1'";
			$result = mysqli_query($connect, $queryz);

			$change_status = $result->num_rows;

			if ($change_status == 0){
				break;
			}

			else{	
				
				$queryz = "UPDATE `status` SET `online` = '0' WHERE `online` = '1'";
				$result = mysqli_query($connect, $queryz);
				break;
			}
		}



		$streamers_online = [];
		for($i = 0; $i < $now_online; $i++){
			if ($meh->streams[$i]->stream_type == 'live'){
				$who_online = $meh->streams[$i]->channel->name;

				array_push($streamers_online, $who_online);
			}
		}



		//ключи с элементами поменять
		echo "<br/>";
		$streamers_revers = array_flip($streamers_online);
		print_r($streamers_online);

		//для каждого значения присвоить онлайн
		for($i = 0; $i < count($streamers_online); $i++){
			$arr_online[$streamers_online[$i]] = 1;
		}

		echo "<br/>";
		print_r($mas);
		$mas2 = $mas;
		echo "<br/>";

		//$asd = ['C_a_k_e' => 1];
		//$arr_online = $arr_online + $asd;


		print_r($arr_online);
		//echo "<- эти онлайн";


		$shozi = array_keys($arr_online);
		echo "<br/>";
		print_r($shozi);
		//echo "<- эти онлайн";


		for ($i = 0; $i < count($shozi); $i++){
			unset($mas["$shozi[$i]"]);
		}

		echo "<br/>";
		print_r($mas);
		//echo "<-----mas";


		$bbb = array_keys($mas);
		echo "<br/>";
		print_r($bbb);
		//echo "<-bbbbb";

		for ($i = 0; $i < count($mas); $i++){
			$mas["$bbb[$i]"] = 0;
		}

		echo "<br/>";//
		print_r($mas);

		//эти оффлайн
		$arr_offline = $mas;

		$arr_result = array_merge($arr_online, $arr_offline);

		echo "<br/>";
		print_r($arr_result);
		//echo "res";
		echo "<br/>";

		echo "<br/>";
		print_r($mas2);
		//echo "<-mas2";
		echo "<br/>";

		//echo "vasan9";
		//ВОТ ЭТО ТЕХ КТО ОФНУЛ ИЛИ ЗАВЁЛ
		$rezult = array_diff_assoc($arr_result, $mas2);
		$rezult_key = array_keys($rezult);

		echo "<br/>";
		print_r($rezult);

		echo "<br/>";
		print_r($rezult_key);

		//echo "vasan8";
		$rez_vk = [];

		for ($i = 0; $i < count($rezult); $i++){
			if ($rezult["$rezult_key[$i]"] == 1){
				array_push($rez_vk, $rezult_key[$i]);
			}
		}
		echo "<br/>";
		print_r($rez_vk);

		
		//echo "vasan6";
		for ($i = 0; $i < count($rezult); $i++){
			$set_online = $rezult["$rezult_key[$i]"];

			$queryz = "UPDATE `status` SET `online` = '$set_online' WHERE `streamer` LIKE '$rezult_key[$i]'";
			$result = mysqli_query($connect, $queryz);
		}
		

		//echo "vasan1";
		if (isset($rez_vk[0])) {
			//echo "vasan12";
			for ($i = 0; $i < count($rezult); $i++){

				unset($komy);
				unset($komy_str);
				unset($myrow);

				$queryz = "SELECT * FROM `twitch` WHERE `streamer` LIKE '$rez_vk[$i]'";
				$result = mysqli_query($connect, $queryz);

				$komy = array();
				
				while($myrow = mysqli_fetch_assoc($result)) { 
					$komy[] = $myrow['idvk'];
				}

				//echo "vasan3";
				$komy_str = implode(',', $komy);
				$req_mes = array(
		  				'user_ids' => $komy_str,
		  				'random_id' => mt_rand(),
		  				'message' => "$rezult_key[$i] ведёт трансляцию\n" . "https://www.twitch.tv/$rezult_key[$i]",
		  				'keyboard' => $keyboard,
		  				'access_token' => $key,
		  				'read_state' => 1,
		  				'v' => '5.78'
						);
				$get = http_build_query($req_mes);
				file_get_contents("https://api.vk.com/method/messages.send?".$get);

				// очистить массивы. Иначе будет отправлять стриммеров на которых человек не подписан
				unset($komy);
				unset($komy_str);
				unset($myrow);
				//echo "vasan4";
		}

		//echo "vasan5";

		






		//UPDATE `status` SET `online` = '1' WHERE `streamer` LIKE 'modestall';
		//SELECT * FROM `twitch` WHERE `streamer` LIKE 'mob5tertv'

		




	}// конец switch
}


/* проблемы:
макс 100 получателей

*/
?>
