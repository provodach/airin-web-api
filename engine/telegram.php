<?php

function curl_request ($method, $type, $data = Array())
{
	$curl = curl_init();

	if($curl)
	{
		curl_setopt($curl, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
		curl_setopt($curl, CURLOPT_URL, 'https://api.telegram.org/bot'.TELEGRAM_BOT_TOKEN.'/'.$method.($type == 'get' ? '?'.http_build_query($data) : ''));
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_TIMEOUT, 5);
		curl_setopt($curl, CURLOPT_USERAGENT, 'Integra Module for Airin Web Backend');
		
		if ($type == 'post')
		{
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
		}
		
		$out = curl_exec($curl);
		curl_close($curl);
		return (empty ($out)) ? false : $out;
	} else
		return false;
}

function sendMessage ($text, $chat, $additionalParams = null, $forceHTTP = false)
{

	$message = Array (
		'text' => $text,	
		'parse_mode' => 'markdown',
		'chat_id' => $chat);

	if (!empty($additionalParams))
			$message = array_merge($message, $additionalParams);

	if (TELEGRAM_USE_DIRECT_RESPONSE && !$forceHTTP) // see enconfig.php
	{

		$message = array_merge($message, Array('method' => 'sendMessage'));

		header('Content-Type: application/json');
		die(json_encode($message));
	}
	else
		$answer = curl_request('sendMessage', 'post', $message);
}

function getFile ($file_id, $filename)
{

	$filedata_raw = curl_request('getFile', 'get', Array('file_id' => $file_id));
	$filedata = json_decode($filedata_raw, true);
	if (!$filedata)
		return false;

	if (!$filedata['ok'])
		return false;

	$filepath = $filedata['result']['file_path'];

	$fcontent = file_get_contents('https://api.telegram.org/file/bot'.TELEGRAM_BOT_TOKEN.'/'.$filepath);

	$filename = $filename.'.'.pathinfo($filepath, PATHINFO_EXTENSION);
	
	file_put_contents($filename, $fcontent);

	return $filename;
}

function telegramAuth($chat, $login, $external = false)
{
	tmpa_removeCode ($login); // all codes are temporal, all codes should be reset

	$code = md5(microtime().TEMP_CODE_SALT.$login.$chat);
	tmpa_setCode ($login, $code);

	if ($external)
		sendMessage(sprintf("Твой ключ авторизации: `%s`\n".
							"Используй его в приложении, которое его запросило.\n\n".
							"*Будь осторожен!*",
							$code), $chat);
	else
	{
		$keyboardArray = Array(Array(Array(
					'text' => 'Завершить авторизацию',
					'url'  => 'https://api.https.cat/airin/authgram?code='.$code
				)));

		sendMessage("Всё готово. Нажми на кнопку ниже чтобы завершить авторизацию.\n".
					"Ссылка одноразовая, время её действия ограничено.", $chat,
			Array('reply_markup' => Array(
					'inline_keyboard' => $keyboardArray
				)
			)
		);
	}
}

function telegramDeauth($chat, $login)
{
	$token = getToken($login);

	if (!empty($token['internal_token']))
		setTokenActive($token['internal_token'], false);

	tmpa_removeCode($login);

	sendMessage('Авторизация отменена, временные ссылки аннулированы. Прости, если сделала что-то не так...', $chat);
}

function telegramTrackVote($isClass, $chat, $login)
{
	if (!isUserAuthorized($login))
	{
		sendMessage("Ты ещё не авторизовался. Чтобы голосовать за треки, выполни команду /passme и пройди по ссылке. Это придётся сделать только один раз. После этого ты сможешь голосовать за треки прямо из этого чата.", $chat);
	}
	else
	{
		$voteResult = trackVote($login, $isClass);
		$track = trim(file_get_contents(CURRENT_TAG_FILE));

		switch ($voteResult)
		{
			case -1 :
				sendMessage('Мне не удалось сохранить твой голос. Прости.', $chat);
			break;

			case 0 :
				$what = ($isClass == 1) ? 'класс' : 'дискласс';
				sendMessage(sprintf('Поставили %s треку %s.', $what, $track), $chat);
				break;

			case 3 :
				sendMessage('Ну ты чего? Ты уже голосовал за этот трек.', $chat);
				break;

			case 4 :
				$what = ($isClass == 1) ? 'класс' : 'дискласс';
				sendMessage (sprintf('Эх, непостоянство... Переголосовали, теперь у трека «%s» стоит %s.', $track, $what), $chat);
				break;
			
			default:
				sendMessage('Мне не удалось сохранить твой голос. Прости.', $chat);
				break;
		}
	}
}


function processCommand($commandline, $chat, $login)
{
	if ($commandline[0] != '/')
	{
		sendMessage('Прости, но я понимаю только команды, которые начинаются с символа `/`. Например, /start.', $chat);
		return; // not a command
	}

	$commandline = mb_substr($commandline, 1, NULL, 'UTF-8');

	if (strlen($commandline) == 0)
	{
		sendMessage('Зачем посылать пустые команды? Наркоман что ли?', $chat);
		return; // not a command
	}

	$commands = explode(' ', $commandline);

	switch ($commands[0])
	{
		case 'start':
			sendMessage("Привет, меня зовут Интегра, я — посланник Проводача в Telegram.\n".
						"Я понимаю специальные команды, с помощью которых можно взаимодействовать с радиостанцией.\n\n".
						"Ты можешь использовать следующие команды:\n".
						" > /passme чтобы зарегистрироваться в чате радио через Telegram\n".
						" > /forgetme чтобы отменить регистрацию\n".
						" > /track чтобы узнать, что сейчас играет на Проводаче\n".
						" > /class чтобы отметить текущий трек как понравившийся (только если ты авторизован)\n".
						" > /disclass чтобы отметить текущий трек как непонравившийся\n".
						" > /streams чтобы получить ссылки на все работающие потоки радио\n".
						" > /getm3u чтобы я прислала тебе M3U-файл со всеми потоками\n".
						" > /status чтобы убедиться, что с радио всё хорошо (статус сервера)\n\n".
						"Командами не следует злоупотреблять, это может привести к блокировке.\n".
						"Если я буду чувствовать себя нехорошо, пожалуйста, сообщи об этом @namikiri.", $chat);
			break;

		case 'track' :
			$track = file_get_contents(CURRENT_TAG_FILE);

			if (empty($track))
				sendMessage('Ой, что-то не получилось. Моей силы не хватает, чтобы выяснить причину проблемы.', $chat);
			else
				sendMessage('Сейчас на радио: '.$track, $chat);
			break;		

		case 'streams':
			sendMessage(
				"Проводач вещает в различных форматах и битрейтах:\n\n".

				"Основной поток (128 кбит/с AAC):\n".
				"http://station.waveradio.org/provodach\n\n".

				"Поток с низким битрейтом (64 кбит/с AAC):\n".
				"http://station.waveradio.org/provodach-low\n\n".

				"Альтернативный поток (128 кбит/с MP3):\n".
				"http://station.waveradio.org/provodach.mp3\n\n".

				"Альтернативный с низким битрейтом (96 кбит/с MP3):\n".
				"http://station.waveradio.org/provodach-low.mp3\n\n".

				"Все потоки поддерживают работу по HTTPS.", $chat);
			break;


		case 'getm3u' :
			sendMessage('Я пока не умею отправлять файлы, но у меня есть кое-что: https://provoda.ch/listen.m3u', $chat);
			break;

		case 'yaebal' :
			sendMessage('Я так и знала что ты попробуешь это ввести. Пытливые умы многого добиваются. Ты молодец. Сохраняй решимость!', $chat);
			break;

		case "\xF0\x9F\x90\x88":
			sendMessage('Няяши говорят няя и делают мурумур.', $chat);
			break;

		case 'passme' :
			telegramAuth ($chat, $login, ($commands[1] == 'ext'));
			break;

		case 'forgetme' :
			telegramDeauth ($chat, $login);
			break;

		case 'class'    :
		case 'disclass' :
			$isClass = ($commands[0] == 'class') ? 1 : -1;
			telegramTrackVote($isClass, $chat, $login);
			break;

		case 'status' :
			sendMessage('Хм... Раз я могу тебе отвечать, значит с сервером, пожалуй, всё хорошо.', $chat);
			break;

		case 'mylogin' :
			sendMessage(sprintf("Твой системный логин: `%s`\n".
								"ID нашей переписки: `%s`", 
								$login, $chat), $chat);
			break;

		// TODO: /subscribe /unsubscribe 
		case 'subscribe'   :
		case 'unsubscribe' :

			if (!isUserAdmin($login))
				sendMessage('Ты пытаешься зайти туда, куда тебе нельзя. Откуда ты вообще узнал эту команду?', $chat);
			else
			{
				$isSubscribed = ($commands[0] == 'subscribe') ? true : false;
				$reply = ($isSubscribed)
						? 'Теперь я буду присылать тебе голосовые сообщения пользователей.'
						: 'Голосовые сообщения пользователей больше не будут приходить.';

				setTgAdminSubscription($login, $isSubscribed, $chat);
				sendMessage($reply, $chat);
			}


			break;

		default:
			sendMessage('Я тебя не понимаю или такой команде меня ещё не обучили.', $chat);
		break;
	}
}

function processTelegram ()
{
	global $route;

	if ($route[1] != TELEGRAM_CALLBACK_KEY)
		die('Bad Telegram API Key! HUI SASI!');

	$event = json_decode(file_get_contents('php://input'), true);

	if (empty($event))
		die('Bad event data.');

	if (!empty($event['message']))
	{
		$message = $event['message'];
		$user = (int)$message['from']['id'];
		$chat = $message['chat']['id'];

		if(isUserBanned ('tg_'.$user))
			sendMessage('Прости, но стражники сочли тебя врагом нашего общества.', $chat);

		if (!empty($message['voice']))
		{
			sendMessage('Запись получила, сохраняю...', $chat, null, true);

			$filename = TELEGRAM_AUDIO_SAVE_PATH.sprintf('/us%s_ch%s_t%s',
														  $user, $chat, time());

			$res = getFile($message['voice']['file_id'], $filename);

			$admins = getTgAdminSubscribers();

			if (count($admins) > 0)
			{
				foreach ($admins as $adm)
				{
					curl_request('forwardMessage', 'post',
							Array('chat_id' => $adm['telegram_chat_id'],
								  'from_chat_id' => $chat,
								  'message_id' => $message['message_id']));
				}
			}

			if ($res)
				sendMessage('Всё сохранила, спайсибо.', $chat);
			else
				sendMessage('Сохранить не получилось, что-то пошло не так.', $chat);

			// TODO: Admins Audio Broadcast
		}
		else
			processCommand($message['text'], $chat, 'tg_'.$user);
	}
}