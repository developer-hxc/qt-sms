<?php


namespace QTSms\Gateways;


use Overtrue\EasySms\Contracts\MessageInterface;
use Overtrue\EasySms\Contracts\PhoneNumberInterface;
use Overtrue\EasySms\Exceptions\GatewayErrorException;
use Overtrue\EasySms\Gateways\Gateway;
use Overtrue\EasySms\Support\Config;
use Overtrue\EasySms\Traits\HasHttpRequest;

class LingkaiGateways extends Gateway
{
    use HasHttpRequest;

    const ENDPOINT_URL = 'https://sdk2.028lk.com/sdk2/LinkWS.asmx/BatchSend2';

    /**
     * Send a short message.
     * @param PhoneNumberInterface $to
     * @param MessageInterface $message
     * @param Config $config
     * @return array
     * @throws GatewayErrorException
     */
    public function send(PhoneNumberInterface $to, MessageInterface $message, Config $config)
    {
        $content = $message->getContent($this);
        $result = $this->get(self::ENDPOINT_URL, [
            'CorpID' => $this->config->get('username'),
            'Pwd' => $this->config->get('password'),
            'Mobile' => $to->getNumber(),
            'Content' => mb_convert_encoding($content, 'GBK'),
            'Cell' => '',
            'SendTime' => ''
        ]);

        if (isset($result[0]) && $result[0] > 0) {
            return $result;
        }
        if ($result[0] == 0) {
            throw new GatewayErrorException('网络访问超时，请稍后再试！', $result[0], $result);
        } elseif ($result[0] == -9) {
            throw new GatewayErrorException('发送号码为空', $result[0], $result);
        } elseif ($result[0] == -101) {
            throw new GatewayErrorException('调用接口速度太快', $result[0], $result);
        } else {
            throw new GatewayErrorException('发送失败', $result[0], $result);
        }
    }
}