<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 11:57
 */

namespace App\Controller;


use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class Index
 * @Route("index")
 * @package App\Controller
 */
class Index extends AbstractController
{
    /**
     * @Route("/index",methods={"GET"})
     * @return JsonResponse
     */
    public function index ()
    {
        $data = Request::createFromGlobals();

        return JsonResponse::create(['msg' => $data->getContent()]);
    }

    /**
     * @Route("/second",methods={"POST"})
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
     * @Route("/three",methods={"PATCH"})
     */
    public function three ()
    {
        return [
            'msg' => 'three'
        ];
    }
}