<?php

use QTSms\QTSms;

require __DIR__.'/vendor/autoload.php';
$sms=new QTSms([
    // HTTP 请求的超时时间（秒）
    'timeout' => 5.0,
    // 默认发送配置
    'default' => [
        // 默认可用的发送网关
        'gateways' => [
            'lingkai',
        ],
    ],
    // 可用的网关配置
    'gateways' => [
        'lingkai' => [//桥通公司合作短信商
            'username' => 'QTTX010114',//账号
            'password' => 'lv112358'//密码
        ],
        //...更多网关
    ],
    'code_failure_time' => 5,//验证码失效时间，单位：分钟
    'resend'  => 60,//重新发送的时间，单位：秒
]);

$sms->send('18615106623',[
    'content'  => '尊敬的用户，您的订单状态有变化，请尽快登录系统进行转让处理，否则30分钟后将自动交易。',
]);