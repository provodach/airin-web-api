<?php

function processLog()
{
	$amount = (preg_match('/^[0-9]{1,8}$/', $_GET['amount']) === 1) ? (int)$_GET['amount'] : DEFAULT_AMOUNT;
	$offset = (preg_match('/^[0-9]{1,8}$/', $_GET['offset']) === 1) ? (int)$_GET['offset'] : 0;

	if ($amount > MAX_MESSAGE_AMOUNT)
		die ('{"status":299,"payload":"Bad request"}');
	
	$lastId = getLastId();
	$offset = ($offset <= 0 || $offset > $lastId) ? $lastId - $amount + 1 : $offset;


	$hidden = getMessages($amount, $offset, true); // true means return hidden messages amount
	$amount += $hidden; // add some more messages because some are hidden
	
	$messages = getMessages($amount, $offset);
	
	if (strtolower($_GET['order']) == 'desc')
		$messages = array_reverse($messages);
	
	if (empty($messages))
		die ('{"status":206,"payload":"No messages to display"}');
	else
		die ('{"status":0, "payload":'.json_encode($messages).'}');
	
}
