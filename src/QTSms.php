<?php
namespace QTSms;
use think\Db;

/**
 * Class QTSms
 * @package QTSms
 */
class QTSms
{
    /**
     * 您好，您的验证码是".$randnum."。如非本人操作请勿泄露给他人。
     * $qt_sms = new QtSms($config)
     * $qt_sms->send('110',[
     *      'code' => 1,
     *      'flag' => 'register'
     * ])
     */

    /**
     * 配置
     * $config = ['username' => '用户名','password' => '密码','sms_code_failure_time' => '失效时间，单位：分钟','sms_resend' => '重新发送时间，单位：秒']
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
     * @param $params ['phone' => '手机号','code' => '验证码','template' => '模板内容','scene' => '场景:login,']
     * @return array
     */
    public function send($phone, $params)
    {
        $patt='/^1[3456789][0-9]{9}$/';
        if(preg_match($patt,$phone)){

            $sms_status = Db::table('qtsms')->where([
                'phone'  => $phone,
                'scene'  => $params['scene'],
                'status' => $params['status']
            ])->whereTime('create_time','<',date('Y-m-d H:i:s',(time() - $params['sms_resend'])))->find();
            if($sms_status){
                $time = $params['sms_resend'] - date('Y-m-d H:i:s',time()) - $sms_status['create_time'];
                return  [
                    'code' => 0,
                    'message' => "{$time}秒后重新发送",
                ];
            }
            try{
                $username = $this->config['username']; //用户名
                $password = $this->config['password']; //密码
                $template = str_replace('{$code}',$params['code'],$params['template']);//内容
                $ContentS = rawurlencode(mb_convert_encoding($template, "gb2312", "utf-8"));//短信内容做GB2312转码处理
                $url = "https://sdk2.028lk.com/sdk2/LinkWS.asmx/BatchSend2?CorpID=".$username."&Pwd=".$password."&Mobile=".$phone."&Content=".$ContentS."&Cell=&SendTime=";
                $result=file_get_contents($url);
                $re=simplexml_load_string($result);
                if($re[0]>0){
                    Db::table('qtsms')->insert([
                        'phone'=>$phone,
                        'code'=>$params['code'],
                        'create_time'=>date('Y-m-d H:i:s',time()),
                        'end_time'=>date('Y-m-d H:i:s',strtotime('+5minute')),
                        'scene' => $params['scene'],
                        'ip'=>$_SERVER["REMOTE_ADDR"]
                    ]);
                    return $status = array(
                        'code'=>1,
                        'message'=>'发送成功',
                    );
                }elseif ($re==-2){
                    return $status = array(
                        'code'=>2,
                        'message'=>'网络访问超时，请稍后再试！',
                    );
                }elseif($re==-9) {
                    return $status = array(
                        'code'=>3,
                        'message'=>'发送号码为空',
                    );
                }elseif($re==-101) {
                    return $status = array(
                        'code'=>4,
                        'message'=>'调用接口速度太快',
                    );
                }else{
                    return $status = array(
                        'code'=>0,
                        'message'=>'发送失败',
                    );
                }
            }catch (Exception $exception){
                return $status = array(
                    'code'=>5,
                    'message'=>'网络错误,无法连接服务器',
                );
            }
        }else{
            return $status = array(
                'code'=>6,
                'message'=>'手机号码格式不正确',
            );
        }
    }
}