<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 14:47
 */

namespace App\Controller;


use FOS\RestBundle\Controller\AbstractFOSRestController;

class User extends AbstractFOSRestController
{
    public function getUsersAction ()
    {
        return [
            'user' => '?'
        ];
    }

    public function putUsersAction ()
    {
        return ['put'];
    }

    public function postUsersAction ()
    {
        return ['post'];
    }

    public function deleteUsersAction ()
    {
        return ['delete'];
    }

    public function patchUsersAction ()
    {
        return ['patch'];
    }
}