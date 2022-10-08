<?php

use \Firebase\JWT\JWT;

require_once 'jwt.lib.php';

if (!isset($dirRoot)) {
    $dirRoot = '/home/admin/php-apps/app/';
}

//
//  Config
//

class AppConfig {

    var $appId = 'APP-ID';
    var $appUid = 'APP-UID';
    var $secretKey = 'SECRET-KEY';

    var $appBaseUrl = 'APP-BASE-URL';

    var $moyskladVendorApiEndpointUrl = 'https://online.moysklad.ru/api/vendor/1.0';
    var $moyskladJsonApiEndpointUrl = 'https://online.moysklad.ru/api/remap/1.2';

    public function __construct(array $cfg)
    {
        foreach ($cfg as $k => $v) {
            $this->$k = $v;
        }
    }
}

$cfg = new AppConfig(require('config.php'));

function cfg(): AppConfig {
    return $GLOBALS['cfg'];
}

//
//  Vendor API 1.0
//

class VendorApi {

    function context(string $contextKey) {
        return $this->request('POST', '/context/' . $contextKey);
    }

    function updateAppStatus(string $appId, string $accountId, string $status) {
        return $this->request('PUT',
            "/apps/$appId/$accountId/status",
            "{\"status\": \"$status\"}");
    }

    private function request(string $method, $path, $body = null) {
        return makeHttpRequest(
            $method,
            cfg()->moyskladVendorApiEndpointUrl . $path,
            buildJWT(),
            $body);
    }

}

function makeHttpRequest(string $method, string $url, string $bearerToken, $body = null) {
    loginfo("APP => MOYSKLAD", "Send: $method $url\n$body");

    $opts = $body
        ? array('http' =>
            array(
                'method'  => $method,
                'header'  => array('Authorization: Bearer ' . $bearerToken, "Content-type: application/json"),
                'content' => $body,
                'ignore_errors' => true
            )
        )
        : array('http' =>
            array(
                'method'  => $method,
                'header'  => 'Authorization: Bearer ' . $bearerToken
            )
        );
    $context = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);
    return json_decode($result);
}

$vendorApi = new VendorApi();

function vendorApi(): VendorApi {
    return $GLOBALS['vendorApi'];
}

function buildJWT() {
    $token = array(
        "sub" => cfg()->appUid,
        "iat" => time(),
        "exp" => time() + 300,
        "jti" => bin2hex(random_bytes(32))
    );
    return JWT::encode($token, cfg()->secretKey);
}


//
//  JSON API 1.2
//

class JsonApi {

    private $accessToken;

    function __construct(string $accessToken) {
        $this->accessToken = $accessToken;
    }
    
    function telegramRequest($url){
        return makeHttpRequest(
            'GET',
            $url,
            $this->accessToken);
    }
    function telegramRequest1($token,$method,$body){
        return makeHttpRequest(
            'POST',
            "https://api.telegram.org/bot$token/$method",
            $this->accessToken,
            $body);
    }

    function stores() {
        return makeHttpRequest(
            'GET',
            cfg()->moyskladJsonApiEndpointUrl . '/entity/store',
            $this->accessToken);
    }

    function getObject($entity, $objectId, $expand = null) {
        
        $result = ($expand != null)
                ? makeHttpRequest(
                    'GET',
                    cfg()->moyskladJsonApiEndpointUrl . "/entity/$entity/$objectId?expand=$expand",
                    $this->accessToken)
                :
                  makeHttpRequest(
                    'GET',
                    cfg()->moyskladJsonApiEndpointUrl . "/entity/$entity/$objectId",
                    $this->accessToken);
        
        return $result;
                
    }
    
    function createNotification ($entity,$documentType,$app,$days=null){
        
        if($days == null){
            $startPeriod = date('Y-m-d',strtotime(date('Y-m-d') . '- ' . 3 .  ' month'));
            $endPeriod =   date('Y-m-d',strtotime(date('Y-m-d') . '+ ' . 0 .  ' days')); 
            $filter = urlencode("deliveryPlannedMoment>$startPeriod 00:00:00.000;deliveryPlannedMoment<$endPeriod 23:59:00.000");
            $documentsObject = JsonApi()->getRows($entity,$filter);

        }else{

            $notificationDate = date('Y-m-d',strtotime(date('Y-m-d') . '+   ' . $days .  ' days'));
            $filter = urlencode("deliveryPlannedMoment>$notificationDate 00:00:00.000;deliveryPlannedMoment<$notificationDate 23:59:00.000");
            $documentsObject = JsonApi()->getRows($entity,$filter);
        }
        
        // loginfo('documentsObject',print_r($documentsObject,true));
        
        foreach($documentsObject as $object){
            if($object->meta->type == 'customerorder'){
                // loginfo('object',print_r($object,true));
                if(!isset($object->demands)){
                    if(isset($object->invoicesOut)){
                        foreach($object->invoicesOut as $invoicesout){
                            if($invoicesoutObject = JsonApi()->getObject('invoiceout',getId($invoicesout->meta->href),'demands')){
                                if(!isset($invoicesoutObject->demands)){
                                    jsonApi()->telegramQuery($days,$documentsObject,$documentType,$app);
                                }
                            }
                        }
                    }
                }
            }
            if($object->meta->type == 'purchaseorder'){
                // loginfo('object',print_r($object,true));
                if(!isset($object->supplies)){
                    if(isset($object->invoicesIn)){
                        foreach($object->invoicesIn as $invoicesin){
                            if($invoicesinObject = JsonApi()->getObject('invoicein',getId($invoicesin->meta->href),'supplies')){
                                if(!isset($invoicesinObject->supplies)){
                                    jsonApi()->telegramQuery($days,$documentsObject,$documentType,$app);
                                }
                            }    
                        }
                    }
                }                
            }
        }
    }

    function telegramQuery($days,$documentsObject,$documentType,$app){
        if($days === NULL){
            $message = 'просрочена!';
        }
        if($days === 0){
            $message = 'сегодня!';
        }

        $resInfo = [];
        foreach ($documentsObject as $k => $v){
            // loginfo('documentsObject',print_r($documentsObject,true));
            $href = $v->meta->uuidHref;
            // loginfo('$href',print_r($href,true));
            $agentObject = jsonApi()->getObject('counterparty', getId($v->agent->meta->href));
            $agentName = $agentObject->name; 
            foreach($app->telegramAccounts as $account){
                if($account['daw'] == 'on'){
                    continue;    
                }
                $resInfo = ($days != 0)
                    ? [
                        'chat_id' => $account['user_telegram_id'],
                        'text' => 'Отгрузка по ' . "<a href=\"$href\">" .$documentType  . $v->name  . "</a>" .  '(' . $agentName . ') ' . 'через ' . ' ' . $days . ' ' .  number($days, array('день', 'дня', 'дней')) . '!',
                        'parse_mode' => 'html'
                    ]
                    : [
                        'chat_id' => $account['user_telegram_id'],
                        'text' => 'Отгрузка по ' . "<a href=\"$href\">" . $documentType . $v->name . "</a>"  .  '(' . $agentName . ') ' . $message,
                        'parse_mode' => 'html'
                    ];           

                $body = json_encode($resInfo);
                jsonApi()->telegramRequest1($app->tgToken,'sendMessage', $body);
            }
        }    
    }

    function getRows($entity,$filter){
        $result = makeHttpRequest(
            'GET',
            cfg()->moyskladJsonApiEndpointUrl . "/entity/$entity?filter=$filter",
            $this->accessToken);
        return $result->rows;
    }
    
    function createWebhook($body) {
        return makeHttpRequest(
            'POST',
            cfg()->moyskladJsonApiEndpointUrl . "/entity/webhook",
            $this->accessToken,
            $body);
    }
    
    function deleteWebhook($hookId) {
        return makeHttpRequest(
            'DELETE',
            cfg()->moyskladJsonApiEndpointUrl . "/entity/webhook/$id",
            $this->accessToken);
    }

}

function jsonApi(): JsonApi {
    if (!$GLOBALS['jsonApi']) {
        $GLOBALS['jsonApi'] = new JsonApi(AppInstance::get()->accessToken);
    }
    return $GLOBALS['jsonApi'];
}

//
//  Logging
//

function loginfo($name, $msg) {
    global $dirRoot;
    $logDir = $dirRoot . 'logs';
    @mkdir($logDir);
    file_put_contents($logDir . '/log.txt', date(DATE_W3C) . ' [' . $name . '] '. $msg . "\n", FILE_APPEND);
}

//
//  AppInstance state
//

$currentAppInstance = null;

class AppInstance {

    const UNKNOWN = 0;
    const SETTINGS_REQUIRED = 1;
    const ACTIVATED = 100;
    
    var $tgToken = '2023087240:AAFgWKzWFauFWVatKMpf32JFWCnBeKmlSmY';    
    var $appId;
    var $accountId;
    var $infoMessage;
    var $telegramAccounts = [];
    var $notifications = [];
    var $accessToken;

    var $status = AppInstance::UNKNOWN;

    static function get(): AppInstance {
        $app = $GLOBALS['currentAppInstance'];
        if (!$app) {
            throw new InvalidArgumentException("There is no current app instance context");
        }
        return $app;
    }

    public function __construct($appId, $accountId)
    {
        $this->appId = $appId;
        $this->accountId = $accountId;
    }

    function getStatusName() {
        switch ($this->status) {
            case self::SETTINGS_REQUIRED:
                return 'SettingsRequired';
            case self::ACTIVATED:
                return 'Activated';
        }
        return null;
    }

    function persist() {
        @mkdir('/home/admin/php-apps/app/data');
        file_put_contents($this->filename(), serialize($this));
    }

    function delete() {
        @unlink($this->filename());
    }

    private function filename() {
        return self::buildFilename($this->appId, $this->accountId);
    }

    private static function buildFilename($appId, $accountId) {
        return $GLOBALS['dirRoot'] . "data/$appId.$accountId.app";
    }

    static function loadApp($accountId): AppInstance {
        return self::load(cfg()->appId, $accountId);
    }

    static function load($appId, $accountId): AppInstance {
        $data = @file_get_contents(self::buildFilename($appId, $accountId));
        if ($data === false) {
            $app = new AppInstance($appId, $accountId);
        } else {
            $app = unserialize($data);
        }
        $GLOBALS['currentAppInstance'] = $app;
        return $app;
    }

}

function debug($data){
    echo '<pre>';
        print_r($data);
    echo '</pre>';
}

function clean($value = "") {
    $value = trim($value);
    $value = stripslashes($value);
    $value = strip_tags($value);
    $value = htmlspecialchars($value);
    
    return $value;
}

function getId($href){
    $pp = explode('/', $href);
    // loginfo('pp', print_r($pp,true));
    $n = count($pp);
    
    $objectId = $pp[$n-1];
    return $objectId;
}

function number($n, $titles) {
  $cases = array(2, 0, 1, 1, 1, 2);
  return $titles[($n % 100 > 4 && $n % 100 < 20) ? 2 : $cases[min($n % 10, 5)]];
}