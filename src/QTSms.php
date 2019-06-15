<?php

namespace QTSms;

use think\Db;

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

    /**
     * QTSms constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * @param $phone [手机号]
     * @param $params ['code' => '验证码','template' => '模板内容','scene' => '场景:login,']
     * @return array [code,0：失败，1：成功]
     */
    public function send($phone, $params)
    {
        $patt = '/^1[3456789][0-9]{9}$/';
        if (preg_match($patt, $phone)) {
            $sms_status = Db::table('qtsms')->where([
                'phone' => $phone,
                'scene' => $params['scene'],
                'status' => 1
            ])->whereTime('create_time', '>', date('Y-m-d H:i:s', (time() - $this->config['resend'])))->find();
            if ($sms_status) {
                $time = (($this->config['resend']) - (strtotime(date('Y-m-d H:i:s', time())) - strtotime($sms_status['create_time'])));
                return [
                    'code' => 0,
                    'message' => "{$time}秒后重新发送",
                ];
            }
            try {
                $username = $this->config['username']; //用户名
                $password = $this->config['password']; //密码
                $template = str_replace('{$code}', $params['code'], $params['template']);//内容
                $ContentS = rawurlencode(mb_convert_encoding($template, "gb2312", "utf-8"));//短信内容做GB2312转码处理
                $url = "https://sdk2.028lk.com/sdk2/LinkWS.asmx/BatchSend2?CorpID=" . $username . "&Pwd=" . $password . "&Mobile=" . $phone . "&Content=" . $ContentS . "&Cell=&SendTime=";
                $result = file_get_contents($url);
                $re = simplexml_load_string($result);
                if ($re[0] > 0) {
                    Db::table('qtsms')->where([
                        'phone' => $phone,
                        'scene' => $params['scene'],
                        'status' => 1
                    ])->update(['status' => 3]);
                    $res = Db::table('qtsms')->insert([
                        'phone' => $phone,
                        'code' => $params['code'],
                        'create_time' => date('Y-m-d H:i:s', time()),
                        'end_time' => date('Y-m-d H:i:s', strtotime("+{$this->config['code_failure_time']}minute")),
                        'scene' => $params['scene'],
                        'ip' => $_SERVER["REMOTE_ADDR"]
                    ]);
                    if($res !== false){
                        return [
                            'code' => 1,
                            'message' => '发送成功',
                        ];
                    }else{
                        return [
                            'code' => 0,
                            'message' => '验证码异常',
                        ];
                    }
                } elseif ($re == 0) {
                    return [
                        'code' => 0,
                        'message' => '网络访问超时，请稍后再试！',
                    ];
                } elseif ($re == -9) {
                    return [
                        'code' => 0,
                        'message' => '发送号码为空',
                    ];
                } elseif ($re == -101) {
                    return [
                        'code' => 0,
                        'message' => '调用接口速度太快',
                    ];
                } else {
                    return [
                        'code' => 0,
                        'message' => '发送失败',
                    ];
                }
            } catch (Exception $exception) {
                return [
                    'code' => 0,
                    'message' => '网络错误,无法连接服务器',
                ];
            }
        } else {
            return [
                'code' => 0,
                'message' => '手机号码格式不正确',
            ];
        }
    }

    /**
     * @param $phone
     * @param $code
     * @param $scene
     * @return array|false|\PDOStatement|string|\think\Model  [code,0：失败，1：成功]
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function check($phone,$code,$scene)
    {
        $sms_status = Db::table('qtsms')->where([
            'phone' => $phone,
            'scene' => $scene,
            'status' => 1
        ])->order('id desc')->find();
        if($sms_status){//验证成功
            if($sms_status['end_time'] < date('Y-m-d H:i:s',time())){
                return [
                    'code' => 0,
                    'message' => '验证码已失效',
                ];
            }else{
                $res = Db::table('qtsms')->where('id',$sms_status['id'])->update(['status' => 2]);
                if($res){
                    return [
                        'code' => 1,
                        'message' => '验证成功',
                    ];
                }else{
                    return [
                        'code' => 0,
                        'message' => '验证异常',
                    ];
                }

            }
        }else{
            return [
                'code' => 0,
                'message' => '您输入的验证码有误',
            ];
        }
        return $sms_status;
    }
}