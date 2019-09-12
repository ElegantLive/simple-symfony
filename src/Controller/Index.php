<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 11:57
 */

namespace App\Controller;


use FOS\RestBundle\Controller\AbstractFOSRestController;
use FOS\RestBundle\Controller\Annotations as Rest;
use Symfony\Component\HttpFoundation\JsonResponse;

class Index extends AbstractFOSRestController
{
    /**
     * @Rest\Get("index")
     * @return JsonResponse
     */
    public function index ()
    {
        return JsonResponse::create(['msg' => 'ok']);
    }
}