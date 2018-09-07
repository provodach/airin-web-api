<?php

function processYandexCallback()
{
	$chksum = '';

	$chksum = sha1(
			$_POST['notification_type'] . '&' .
			$_POST['operation_id'] . '&' .
			$_POST['amount'] . '&' .
			$_POST['currency'] . '&' .
			$_POST['datetime'] . '&' .
			$_POST['sender'] . '&' .
			$_POST['codepro'] . '&' .
			YANDEX_MONEY_CALLBACK_SECRET . '&' .
			$_POST['label']
	);

	if ($chksum !== $_POST['sha1_hash'])
	{
		error_log('WARNING! DATA INCONSISTENCE!');
		error_log('POST Data: '.print_r($_POST, true));
		error_log('Checksums: mine '.$chksum.'; Yandex '.$_POST['sha1_hash']);
		die ('oh fuck, intrusion detected');
	}

	$event = Array();
	$event['notification_type'] = $_POST['notification_type'];
	$event['operation_id'] = $_POST['operation_id'];
	$event['amount_income'] = $_POST['amount'];
	$event['amount_withdraw'] = $_POST['withdraw_amount'];
	$event['event_timestamp'] = $_POST['datetime'];
	$event['event_label'] = $_POST['label'];
	$event['sender_lastname'] = $_POST['lastname'];
	$event['sender_firstname'] = $_POST['firstname'];
	$event['sender_patronym'] = $_POST['fathersname'];
	$event['sender_address'] = trim(sprintf ('%s %s %s %s %s %s',
												$_POST['zip'],
												$_POST['city'],
												$_POST['street'],
												$_POST['building'],
												$_POST['flat'],
												$_POST['suite']));

	saveDonationEvent($event);

	die('OK');

}

function paymentRediect()
{
	header('HTTP/1.1 302 Thank you :3');
	header('Location: https://provoda.ch/thanks');
}

function processDonate()
{
	global $route;

	error_log(sprintf('Donate Event: %s, Contains: %s', $route[1], print_r($_POST, true)));

	switch ($route[1])
	{
		case 'callback' : processYandexCallback(); break;
		case 'ok' : paymentRediect(); break;
		default : die ('fcuk u criminal scum'); return;
	}
}