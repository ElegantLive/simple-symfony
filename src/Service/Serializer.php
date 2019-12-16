<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/29
 * Time: 18:56
 */

namespace App\Service;


use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Serializer as BaseSerializer;

/**
 * Class Serializer
 * @package App\Service
 */
class Serializer
{

    /**
     * @var BaseSerializer
     */
    private static $serializer;

    /**
     * Serializer constructor.
     */
    public function __construct ()
    {
        $encoders    = [new JsonDecode()];
        $normalizers = [new ObjectNormalizer()];

        self::$serializer = new BaseSerializer($normalizers, $encoders);
    }

    /**
     * @param $obj
     * @param $format
     * @return array
     */
    public function toArray ($obj, $format): array
    {
        return (array)self::$serializer->decode($obj, $format);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed
     */
    public static function __callStatic ($name, $arguments)
    {
        return call_user_func_array([self::$serializer, $name], $arguments);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed BaseSerializer
     */
    public function __call ($name, $arguments)
    {
        return call_user_func_array([self::$serializer, $name], $arguments);
    }
}