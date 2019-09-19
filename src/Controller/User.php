<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 14:47
 */

namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class User extends AbstractController
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