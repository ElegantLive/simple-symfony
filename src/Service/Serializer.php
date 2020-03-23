<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/29
 * Time: 18:56
 */

namespace App\Service;

use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
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
    private $serializer;

    /**
     * Serializer constructor.
     */
    public function __construct ()
    {
        $encoders    = [new JsonDecode(), new ArrayDenormalizer(), new JsonEncode()];
        $normalizers = [new ObjectNormalizer()];

        $this->serializer = new BaseSerializer($normalizers, $encoders);
    }

    /**
     * @param $name
     * @param $arguments
     * @return mixed BaseSerializer
     */
    public function __call ($name, $arguments)
    {
        return call_user_func_array([$this->serializer, $name], $arguments);
    }
}