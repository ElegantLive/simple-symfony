<?php


namespace App\Service;

use App\Exception\Signature as SignatureException;

/**
 * request signature
 *
 * @package app\api\service
 */
class Signature
{
    const PUBLIC_KEY  = 'PUBLIC_KEY';
    const PRIVATE_KEY = 'PRIVATE_KEY';
    /**
     * @var Request
     */
    private $request;
    /**
     * @var string
     */
    private $publicPem;
    /**
     * @var string
     */
    private $privatePem;
    /**
     * @var string
     */
    private $projectDirectory;
    /**
     * @var int
     */
    private $timeout;

    /**
     * Signature constructor.
     *
     * @param Request $request
     * @param string  $public
     * @param string  $private
     * @param string  $projectDirectory
     * @param int     $timeout
     */
    public function __construct(Request $request,
                                string $public,
                                string $private,
                                string $projectDirectory,
                                int $timeout)
    {
        $this->request          = $request;
        $this->publicPem        = $public;
        $this->privatePem       = $private;
        $this->projectDirectory = $projectDirectory;
        $this->timeout          = $timeout;
    }

    /**
     * get Pem
     *
     * @param string $pem
     * @return false|string
     */
    private function getPem($pem = self::PUBLIC_KEY)
    {
        $map = [
            self::PUBLIC_KEY  => $this->publicPem,
            self::PRIVATE_KEY => $this->privatePem
        ];

        if (array_key_exists ($pem, $map) == false) throw new SignatureException("引入证书失败");

        return file_get_contents ($this->projectDirectory . $map[$pem]);
    }

    /**
     * @return array
     */
    public function generateSign()
    {
        $request = $this->request->getRequest ();

        $url      = $request->headers->get ('url');
        $time     = time ();
        $once     = rand (100000, 999999);
        $platform = $request->headers->get ('platform');

        $params             = $this->request->getData ();
        $params['url']      = $url;
        $params['time']     = $time;
        $params['once']     = $once;
        $params['platform'] = $platform;

        ksort ($params);

        $paramStr = http_build_query ($params);

        $sign = self::encrypt ($paramStr, self::PUBLIC_KEY);

        return compact ($sign, $url, $time, $once, $platform);
    }

    /**
     * @return void
     * @throws SignatureException
     */
    public function checkSign()
    {
        $request = $this->request->getRequest ();

        $url      = $request->getPathInfo ();
        $sign     = $request->headers->get ('signature');
        $once     = $request->headers->get ('once');
        $time     = $request->headers->get ('time');
        $platform = $request->headers->get ('platform');

        if (empty($time) || empty($once) || empty($sign)) {
            // signature miss
            throw new SignatureException('signature miss');
        }

        if ($once < 100000 || $once > 999999) {
            throw new SignatureException('invalid once');
        }

        $now = time ();

        if ($now > bcadd ($time, $this->timeout)) {
            // signature expire
            throw new SignatureException('signature expire');
        }

        // 解密数据
        $decrypted = self::decrypt ($sign);
        if (empty($decrypted)) {
            // invalid signature
            throw new SignatureException(['data' => $decrypted, 'message' => 'invalid signature']);
        }

        parse_str ($decrypted, $payload);

        $testPayload = compact ('time', 'once', 'url', 'platform');

        $char = null;
        if (array_key_exists ('char', $payload)) {
            $char = $payload['char'];
            unset($payload['char']);
        }

        if ($testPayload != $payload) {
            // invalid signature
            throw new SignatureException(['message' => "证书异常"]);
        }

        return $char;
    }

    /**
     * @param string $str
     * @param string $key
     * @return string
     */
    private function encrypt($str, $key = self::PRIVATE_KEY)
    {
        $encrypted = '';
        switch ($key) {
            case self::PRIVATE_KEY:
                $private = self::getPem (self::PRIVATE_KEY);
                $pi      = openssl_pkey_get_private ($private);
                openssl_private_encrypt ($str, $encrypted, $pi);

                break;
            case self::PUBLIC_KEY:
                $public = self::getPem (self::PUBLIC_KEY);
                $pu     = openssl_pkey_get_public ($public);
                openssl_public_encrypt ($str, $encrypted, $pu);

                break;
            default:
                break;
        }

        return base64_encode ($encrypted);
    }

    /**
     * @param string $str
     * @param string $key
     * @return string
     */
    private function decrypt($str, $key = self::PRIVATE_KEY)
    {
        $decrypted = '';
        $str       = base64_decode ($str);

        switch ($key) {
            case self::PRIVATE_KEY:
                $private = self::getPem (self::PRIVATE_KEY);
                $pi      = openssl_pkey_get_private ($private);
                openssl_private_decrypt ($str, $decrypted, $pi);

                break;
            case self::PUBLIC_KEY:
                $public = self::getPem (self::PUBLIC_KEY);
                $pu     = openssl_pkey_get_public ($public);
                openssl_public_decrypt ($str, $decrypted, $pu);

                break;
            default:
                break;
        }

        return $decrypted;
    }
}