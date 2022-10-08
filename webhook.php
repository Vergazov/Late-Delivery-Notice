<?php

require_once 'lib.php';
$requesthook = file_get_contents('php://input');
$hook = json_decode($requesthook);
//loginfo('hook', print_r($hook,true));
$text = explode(' ',$hook->message->text);
$accountId = array_pop($text);
$app = AppInstance::loadApp($accountId);

$first_name = $hook->message->from->first_name;
$last_name = $hook->message->from->last_name;
$user_telegram_id = $hook->message->from->id;
$chat_id = $hook->message->chat->id;

//$url = "https://api.telegram.org/$app->tgToken/sendMessage";
//$url = "https://api.telegram.org/2023087240:AAFgWKzWFauFWVatKMpf32JFWCnBeKmlSmY/sendMessage";
$data = [
    'chat_id' => $chat_id,
    'text' => "$first_name, Ваш телеграм успешно добавлен в базу данных"
];
$body = json_encode($data);
$res = jsonApi()->telegramRequest1($app->tgToken,'sendMessage', $body);
loginfo('res', print_r($res,true));

$app->telegramAccounts[$user_telegram_id]['number'] = sprintf("%04d", count($app->telegramAccounts) + 1);  
$app->telegramAccounts[$user_telegram_id]['user_telegram_id'] = $user_telegram_id;
$app->telegramAccounts[$user_telegram_id]['name'] = $first_name . ' ' . $last_name;
$app->telegramAccounts[$user_telegram_id]['daw'] = '';
//$app->telegramAccounts = [];

$app->status = AppInstance::ACTIVATED;
vendorApi()->updateAppStatus(cfg()->appId, $accountId, $app->getStatusName());
$app->persist();
