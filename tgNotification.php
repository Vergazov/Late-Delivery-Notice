<?php
//ini_set('display_errors', 'On'); // сообщения с ошибками будут показываться
//error_reporting(E_ALL); // E_ALL - отображаем ВСЕ ошибки

require 'lib.php';
$rule_number = $_POST['rule_number'];
$accountId = $_POST['accountId'];
$app = AppInstance::loadApp($accountId);
// debug($_POST);
// loginfo('test',print_r($_POST,true));

if($_POST['delete'] === 'delete'){    
    foreach ($app->notifications as $rule_key => $rule_value) {
        if (in_array($rule_number, $rule_value)) {
            unset($app->notifications[$rule_key]);
            break;
        }
    }
    $num = 0;
    foreach ($app->notifications as $rule_key => $rule_value) {
        $num++;
        $app->notifications[$rule_key]['number'] = sprintf("%04d", $num);
    }
    $successMessage = 'Уведомление успешно удалено';
}

if($_POST['addNotification'] == 'addNotification'){
    
    $i = count($app->notifications);
    $app->notifications[$i]['number'] = sprintf("%04d", count($app->notifications) + 1);
    $app->notifications[$i]['documentType'] = $_POST['documentType'];
    $app->notifications[$i]['notificationType'] = $_POST['notificationType'];
    $app->notifications[$i]['daw'] = '';
    $i++; 
}

    
if($_POST['dawNt'] == 'on'){
    foreach ($app->notifications as $k => $v){
        if($v['number'] == $_POST['rule_number']){
            $app->notifications[$k]['daw'] = 'on';
            $successMessage = 'Уведомления по выбранному правилу отключены!';
        }
    }
}
if(!in_array($_POST['dawNt'],$_POST)){
        
    foreach ($app->notifications as $k => $v){
        if($v['number'] == $_POST['rule_number']){
            $app->notifications[$k]['daw'] = '';
            $successMessage = 'Уведомления по выбранному правилу включены!';
        }
    }
}
$app->persist();
$tgAccounts = $app->telegramAccounts;
// debug($app);
require 'iframe.html';
