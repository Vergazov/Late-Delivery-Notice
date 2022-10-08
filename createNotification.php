<?php

// ini_set('display_errors', 'On'); // сообщения с ошибками будут показываться
// error_reporting(E_ALL); // E_ALL - отображаем ВСЕ ошибки

require 'lib.php';

$dir = new DirectoryIterator('/home/admin/php-apps/app/data');
foreach ($dir as $file){
    if($file->isFile()){
        $arr[] = $file->getFilename();
    }
}
foreach ($arr as $item){
    $data[] = file_get_contents('/home/admin/php-apps/app/data/' . $item);
}    
foreach ($data as $value){
    $newData[] = unserialize($value);
}  

foreach($newData as $data){
    $app = AppInstance::loadApp($data->accountId);
    // debug($app);
    foreach ($app->notifications as $key => $value){
        if($value['daw'] == 'on'){
            continue;
        }
        if($value['documentType'] === 'Заказ покупателя'){
            $entity = 'customerorder';
            $documentType = 'заказу покупателя № ';
            
        }else{
            $entity = 'purchaseorder';
            $documentType = 'заказу поставщику № ';
        }
        switch($value['notificationType']){            
            case 'Дата наступит сегодня':
                jsonApi()->createNotification($entity,$documentType,$app,0);
                break;
             case 'Дата наступит через 1 день':
                jsonApi()->createNotification($entity,$documentType,$app,1);
                break;
             case 'Дата наступит через 3 дня':
                jsonApi()->createNotification($entity,$documentType,$app,3); 
                break;
            case 'За последние 3 месяца':
                jsonApi()->createNotification($entity,$documentType,$app,null); 
                break;
        }
    }
}
