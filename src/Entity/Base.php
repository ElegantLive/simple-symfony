<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/19
 * Time: 11:10
 */

namespace App\Entity;


use App\Entity\Traits\Hidden;
use App\Entity\Traits\Trust;

class Base
{
    use Hidden;
    use Trust;

    const ORDER_DESC = 'desc';
    const ORDER_ASC = 'asc';

    public static $_orderState = [
        self::ORDER_DESC,
        self::ORDER_ASC
    ];

    protected $trust = [];
    protected $hidden = [];
    protected $deleteField = ['deletedAt', 'deleted'];
}