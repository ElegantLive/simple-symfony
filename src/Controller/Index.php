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
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Request;

/**
 * Class Index
 * @Route("index")
 * @package App\Controller
 */
class Index extends AbstractController
{
    /**
     * @Route("/index",methods={"PUT"})
     * @param Request $request
     * @throws \Exception
     */
    public function index (Request $request)
    {
        $data = $request->getData();
        (new Example())->check($data);

        throw new Success(['data' => $data]);
    }

    /**
     * @Route("/second",methods={"DELETE"})
     * @param Request $request
     * @throws \Exception
     */
    public function second (Request $request)
    {
        $data = $request->request->query->all();
        (new Example())->check($data);

        throw new Success(['data' => $data]);
    }

    /**
     * @Route("/three",methods={"PATCH"})
     * @param Request $request
     * @throws \Exception
     */
    public function three (Request $request)
    {
        $data = $request->getData();
        (new Example())->check($data);

        throw new Success(['data' => $data]);
    }
}