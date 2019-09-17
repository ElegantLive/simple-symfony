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
use Symfony\Component\HttpFoundation\Request;

/**
 * Class Index
 * @Rest\Route("index")
 * @package App\Controller
 */
class Index extends AbstractFOSRestController
{
    /**
     * @Rest\Get("/index")
     * @return JsonResponse
     */
    public function index ()
    {
        $data = Request::createFromGlobals();

        return JsonResponse::create(['msg' => $data->getContent()]);
    }

    /**
     * @Rest\Post("/second")
     * @return array
     */
    public function second ()
    {
        $request = Request::createFromGlobals();
        $data = $request->getContent();

        return [
            'msg' => 'OK!',
            'data' => $data
        ];
    }

    /**
     * @Rest\Patch("/three")
     */
    public function three ()
    {
        return [
            'msg' => 'three'
        ];
    }
}