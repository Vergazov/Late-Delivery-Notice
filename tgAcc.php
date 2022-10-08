<?php
//ini_set('display_errors', 'On'); // сообщения с ошибками будут показываться
//error_reporting(E_ALL); // E_ALL - отображаем ВСЕ ошибки

require 'lib.php';
$rule_number = $_POST['rule_number'];
$accountId = $_POST['accountId'];
$app = AppInstance::loadApp($accountId);
// loginfo('test',print_r($_POST,true));
if($_POST['delete'] == 'delete'){    
    foreach ($app->telegramAccounts as $rule_key => $rule_value) {
        if (in_array($rule_number, $rule_value)) {
            unset($app->telegramAccounts[$rule_key]);
            break;
        }
    }
    $num = 0;
    foreach ($app->telegramAccounts as $rule_key => $rule_value) {
        $num++;
        $app->telegramAccounts[$rule_key]['number'] = sprintf("%04d", $num);
    }
    if ($num == 0) {
        $notify = $app->status != AppInstance::ACTIVATED;
        $app->status = AppInstance::SETTINGS_REQUIRED;
        vendorApi()->updateAppStatus(cfg()->appId, $accountId, $app->getStatusName());
    }
    $successMessage = 'Аккаунт успешно удален';
}

if($_POST['dawTg'] == 'on'){
    foreach ($app->telegramAccounts as $k => $v){
        if($v['number'] == $_POST['rule_number']){
            $app->telegramAccounts[$k]['daw'] = 'on';
            $successMessage = 'Уведомления по выбранному аккаунту отключены!';
        }
    }
}

if(!in_array($_POST['dawTg'],$_POST)){
    foreach ($app->telegramAccounts as $k => $v){
        if($v['number'] == $_POST['rule_number']){
            $app->telegramAccounts[$k]['daw'] = '';
            $successMessage = 'Уведомления по выбранному аккаунту включены!';
        }
    }
}
    $app->persist();
    $tgAccounts = $app->telegramAccounts;
    require 'iframe.html';