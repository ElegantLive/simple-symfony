<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 11:57
 */

namespace App\Controller;


use App\Exception\Success;
use App\Validator\Example;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
     * @throws \Exception
     */
    public function index ()
    {
        $request = Request::createFromGlobals();

        $data = $request->query->all();
        (new Example())->check($data);

        throw new Success(['data' => $data]);
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