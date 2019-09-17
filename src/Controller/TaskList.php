<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/12
 * Time: 14:47
 */

namespace App\Controller;


use App\Exception\Parameter;
use App\Repository\TaskListRepository;
use FOS\RestBundle\Controller\AbstractFOSRestController;

/**
 * Class TaskList
 * @package App\Controller
 */
class TaskList extends AbstractFOSRestController
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
     * @return \App\Entity\TaskList[]
     */
    public function getListAction ()
    {
        return $this->taskListRepository->findAll();
    }

    public function putListAction ()
    {
        return ['put'];
    }

    public function postListAction ()
    {
//        throw new \Exception('something was wrong!');
        throw new Parameter();
        return ['post'];
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