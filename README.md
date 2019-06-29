桥通天下短信
===========

在[overtrue/easy-sms](https://github.com/overtrue/easy-sms)的基础上扩展而来，支持更多小众短信商。另外，单独封装了短信验证码的发送和验证。

## 平台支持

* [阿里云](https://www.aliyun.com/)
* [云片](https://www.yunpian.com)
* [Submail](https://www.mysubmail.com)
* [螺丝帽](https://luosimao.com/)
* [容联云通讯](http://www.yuntongxun.com)
* [互亿无线](http://www.ihuyi.com)
* [聚合数据](https://www.juhe.cn)
* [SendCloud](http://www.sendcloud.net/)
* [百度云](https://cloud.baidu.com/)
* [华信短信平台](http://www.ipyy.com/)
* [253云通讯（创蓝）](https://www.253.com/)
* [融云](http://www.rongcloud.cn)
* [天毅无线](http://www.85hu.com/)
* [腾讯云 SMS](https://cloud.tencent.com/product/sms)
* [阿凡达数据](http://www.avatardata.cn/)
* [华为云](https://www.huaweicloud.com/product/msgsms.html)
* [凌凯通信](http://028lk.com/)

## 环境要求
* php >= 5.6.0
* ThinkPHP 5.0.*

## 安装方法

```shell
$ composer require hxc/qt-sms
```

## 如何使用？
* 【建议】将短信配置放入配置文件`APP_PATH\extra\sms.php`，调用时直接读取。
```php
[
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
            'username' => 'QTTX008293',//账号
            'password' => 'lv112358'//密码
        ],
        //...更多网关
    ],
    'code_failure_time' => 5,//验证码失效时间，单位：分钟
    'resend'  => 60,//重新发送的时间，单位：秒
]
```

* 如需使用短信验证码功能则创建如下数据表
```sql
CREATE TABLE `[表前缀]qtsms` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `phone` VARCHAR(15) NOT NULL COMMENT '手机号',
    `code` VARCHAR(10) NOT NULL COMMENT '验证码',
    `ip` VARCHAR(15) NOT NULL COMMENT '发送人ip',
    `scene` VARCHAR(50) NOT NULL COMMENT '发送场景',
    `status` TINYINT(3) NOT NULL DEFAULT '1' COMMENT '状态，1：待验证，2：已验证，3：已失效',
    `create_time` DATETIME NOT NULL COMMENT '发送时间',
    `end_time` DATETIME NOT NULL COMMENT '失效时间',
     PRIMARY KEY (`id`),
     INDEX `phone_scene_status_end_time` (`phone`, `scene`, `status`, `end_time`)
 ) COMMENT='短信发送记录' COLLATE='utf8_general_ci' ENGINE=InnoDB AUTO_INCREMENT=1;
```

## 使用
```php
$sms_config = Config::get('sms');//或其他方式传入配置，配置格式见上文
$qt_sms = new QTSms($sms_config);

//发送验证码
$code = rand(1000,9999);
$res = $qt_sms->sendCode(13188888888, [
    'code' => $code, //存入数据库，验证时使用
    'content' => '您好，您的验证码是' . $code . '。如非本人操作请勿泄露给他人。', 
    'template' => 'SMS_001', 
    'data' => [
        'code' => $code
    ]
    'scene' => 'login'
]);

//验证
$res = $qt_sms->check(13188888888, '2824', 'login');

//发送其他短信
$easySms->send(13188888888, [
    'content'  => '您的验证码为: 6379',
    'template' => 'SMS_001',
    'data' => [
        'code' => 6379
    ],
]);
```

**由于不同短信商发送方式不一样，抽象定义了三个属性，使用时只要满足所选网关要求即可。详见easy-sms文档**

## 返回值

* sendCode和check方法

返回一个数组，结构如下：
```php
[
    'code' => 0,               //0失败，1成功
    'message' => '验证码异常',  //状态的文本描述
]
```

* send 方法

由于使用多网关发送，所以返回值为一个数组，结构如下：
```php
[
    'yunpian' => [
        'gateway' => 'yunpian',
        'status' => 'success',
        'result' => [...] // 平台返回值
    ],
    'juhe' => [
        'gateway' => 'juhe',
        'status' => 'failure',
        'exception' => \Overtrue\EasySms\Exceptions\GatewayErrorException 对象
    ],
    //...
]
```

如果所选网关列表均发送失败时，将会抛出 `Overtrue\EasySms\Exceptions\NoGatewayAvailableException` 异常，你可以使用 `$e->results` 获取发送结果。

你也可以使用 `$e` 提供的更多便捷方法：

```php
$e->getResults();               // 返回所有 API 的结果，结构同上
$e->getExceptions();            // 返回所有调用异常列表
$e->getException($gateway);     // 返回指定网关名称的异常对象
$e->getLastException();         // 获取最后一个失败的异常对象 
```

## 各平台配置说明

easy-sms本身支持的短信商不再重复说明，增加的短信商如下：

### [凌凯通信](https://www.aliyun.com/)

短信内容使用 `content`

```php
'lingkai' => [
    'username' => '',
    'password' => '',
],
```

## 相关文档

* [easy-sms](https://github.com/overtrue/easy-sms)