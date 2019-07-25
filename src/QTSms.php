<?php

namespace QTSms;

use Overtrue\EasySms\EasySms;
use Overtrue\EasySms\Exceptions\InvalidArgumentException;
use Overtrue\EasySms\Exceptions\NoGatewayAvailableException;
use PDOStatement;
use QTSms\Gateways\LingkaiGateways;
use think\Db;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\exception\PDOException;
use think\Model;

/**
 * Class QTSms
 * @package QTSms
 */
class QTSms
{
//        使用方法
//        $sms_config = Config::get('sms');
//        $config = [
//            'username' => $sms_config['username'],
//            'password' => $sms_config['password'],
//            'code_failure_time' => $sms_config['code_failure_time'],
//            'resend' => $sms_config['resend'],
//        ];
//        $qt_sms = new QTSms($config);

//        //发送验证码
//        $res = $qt_sms->send('15306368950',[
//            'code' => rand(1000,9999),
//            'template' => '您好，您的验证码是{$code}。如非本人操作请勿泄露给他人。',
//            'scene' => 'login'
//        ]);
//
//        //验证
//        $res = $qt_sms->check('15306368950','2824','login');


    /**
     * 配置
     * $config = ['username' => '用户名','password' => '密码','code_failure_time' => '失效时间，单位：分钟','resend' => '重新发送时间，单位：秒']
     */
    protected $config;
    protected $easySms;

    /**
     * QTSms constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->easySms = new EasySms($this->config);

        // 注册
        $this->easySms->extend('lingkai', function ($gatewayConfig) {
            // $gatewayConfig 来自配置文件里的 `gateways.mygateway`
            return new LingkaiGateways($gatewayConfig);
        });
    }

    /**
     * 发送短信
     * @param $to
     * @param $message
     * @param array $gateways
     * @return array
     * @throws NoGatewayAvailableException
     * @throws InvalidArgumentException
     */
    public function send($to, $message, array $gateways = [])
    {
        return $this->easySms->send($to, $message, $gateways);
    }

    /**
     * @param string $phone [手机号]
     * @param array $params ['code' => '验证码','template' => '模板内容','scene' => '场景:login,']
     * @return array [code,0：失败，1：成功]
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function sendCode($phone, $params)
    {
        $patt = '/^1[3456789][0-9]{9}$/';
        if (!preg_match($patt, $phone)) {
            return [
                'code' => 0,
                'message' => '手机号码格式不正确',
            ];
        }
        $sms_status = Db::name('qtsms')->where('phone', $phone)
            ->where('scene', $params['scene'])
            ->where('status', 1)
            ->whereTime('create_time', '>', time() - $this->config['resend'])
            ->find();
        if ($sms_status) {
            $time = (($this->config['resend']) - (time() - strtotime($sms_status['create_time'])));
            return [
                'code' => 0,
                'message' => "{$time}秒后重新发送",
            ];
        }

        try {
            $this->easySms->send($phone, [
                'content' => isset($params['content']) ? $params['content'] : '',
                'template' => isset($params['template']) ? $params['template'] : '',
                'data' => isset($params['data']) ? $params['data'] : []
            ]);
            Db::name('qtsms')->where([
                'phone' => $phone,
                'scene' => $params['scene'],
                'status' => 1
            ])->update(['status' => 3]);
            $res = Db::name('qtsms')->insert([
                'phone' => $phone,
                'code' => $params['code'],
                'create_time' => date('Y-m-d H:i:s'),
                'end_time' => date('Y-m-d H:i:s', strtotime("+{$this->config['code_failure_time']}minute")),
                'scene' => $params['scene'],
                'ip' => $_SERVER["REMOTE_ADDR"]
            ]);
            if ($res !== false) {
                return [
                    'code' => 1,
                    'message' => '发送成功',
                ];
            } else {
                return [
                    'code' => 0,
                    'message' => '验证码异常',
                ];
            }
        } catch (NoGatewayAvailableException $e) {
            return [
                'code' => 0,
                'message' => $e->getLastException()->getMessage()
            ];
        } catch (\Exception $exception) {
            return [
                'code' => 0,
                'message' => '网络错误,无法连接服务器',
            ];
        }
    }

    /**
     * @param $phone
     * @param $code
     * @param $scene
     * @return array|false|PDOStatement|string|Model  [code,0：失败，1：成功]
     * @throws Exception
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     * @throws PDOException
     */
    public function check($phone, $code, $scene)
    {
        $sms_status = Db::name('qtsms')->where([
            'phone' => $phone,
            'scene' => $scene,
            'code' => $code,
            'status' => 1
        ])->order('id desc')->find();
        if ($sms_status) {//验证成功
            if ($sms_status['end_time'] < date('Y-m-d H:i:s', time())) {
                return [
                    'code' => 0,
                    'message' => '验证码已失效',
                ];
            } else {
                $res = Db::name('qtsms')->where('id', $sms_status['id'])->update(['status' => 2]);
                if ($res) {
                    return [
                        'code' => 1,
                        'message' => '验证成功',
                    ];
                } else {
                    return [
                        'code' => 0,
                        'message' => '验证异常',
                    ];
                }

            }
        } else {
            return [
                'code' => 0,
                'message' => '您输入的验证码有误',
            ];
        }
    }
}