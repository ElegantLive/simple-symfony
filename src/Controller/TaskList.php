<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 14:47
 */

namespace App\Controller;


use App\Exception\Parameter;
use App\Exception\Success;
use App\Repository\TaskListRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class TaskList
 * @Route("/list")
 * @package App\Controller
 */
class TaskList extends AbstractController
{
    /**
     * @var TaskListRepository
     */
    private $taskListRepository;

    /**
     * TaskList constructor.
     * @param TaskListRepository $taskListRepository
     */
    public function __construct (TaskListRepository $taskListRepository) {
        $this->taskListRepository = $taskListRepository;
    }

    /**
     * @param Request $request
     * @Route("", methods={"POST"})
     */
    public function info (Request $request)
    {
//        throw new \Exception('something was wrong!');
        throw new Parameter(['data' => [$request->request->all()]]);
    }

    /**
     * @param Request $request
     * @Route("", methods={"PUT"})
     */
    public function putListAction ()
    {
        $request = Request::createFromGlobals()->query;

//        throw new \Exception('something was wrong!');
        throw new Parameter(['data' => [$request->all()]]);
    }

    public function postListAction ()
    {
        $request = Request::createFromGlobals();

//        throw new \Exception('something was wrong!');
        throw new Parameter(['data' => [json_decode($request->getContent(), true)]]);
    }

    /**
     * @return array
     */
    public function deleteListAction ()
    {
        return ['delete'];
    }

    public function patchListAction ()
    {
        return ['patch'];
    }
}