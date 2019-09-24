<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/23
 * Time: 14:41
 */
namespace App\Entity;

trait Password {
    public function encodeSecret (string $secret)
    {
        return md5($secret . 'doSomethingElse');
    }
}