<?php

// ini_set('display_errors', 'On'); // сообщения с ошибками будут показываться
// error_reporting(E_ALL); // E_ALL - отображаем ВСЕ ошибки

require_once 'lib.php';

$contextName = 'IFRAME';
require_once 'user-context-loader.inc.php';

$app = AppInstance::loadApp($accountId);

$isSettingsRequired = $app->status != AppInstance::ACTIVATED;
// debug($app->status);
$tgAccounts = $app->telegramAccounts;

// $arr = [
//    '553896108' => [
//        'user_telegram_id' => '553896108',
//        'name' => 'Илья',
//        'account_name' => ''
//    ]
// ];
// $app->telegramAccounts['553896108'] = [
//        'number' => sprintf("%04d", count($app->telegramAccounts) + 1),
//        'user_telegram_id' => '553896108',
//        'name' => 'Илья',
//        'daw' => ''
// ];
// array_push($app->telegramAccounts,$arr);
// unset($app->telegramAccounts['553896109']);
// $app->persist();
//require_once 'createNotification.php';
//function number($n, $titles) {
//  $cases = array(2, 0, 1, 1, 1, 2);
// $days = 2;
// $notificationDate = date('Y-m-d',strtotime(date('Y-m-d') . '- ' . $days .  ' month'));
// debug($notificationDate);

// $resInfo = [
//     'chat_id' => '553896107',
//     'text' => "<a href='https://online.moysklad.ru/app/#embed-apps?id=a27b18e6-2cb4-4527-907e-44d5e8a29aa8'>Hello</a>",
//     'parse_mode' => 'html'
// ];

//     $body = json_encode($resInfo);
//     jsonApi()->telegramRequest1($app->tgToken,'sendMessage', $body);

require 'iframe.html';