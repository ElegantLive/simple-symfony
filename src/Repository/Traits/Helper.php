<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2020/3/28
 * Time: 15:03
 */

namespace App\Repository\Traits;


use App\Exception\Gone;
use App\Exception\Miss;

trait Helper
{
    /**
     * @var string
     */
    protected $missMessage;

    /**
     * @var string
     */
    protected $goneMessage;

    /**
     * @param $id
     * @return mixed
     */
    public function get ($id)
    {
       $obj = $this->find($id);
       if (empty($obj)) {
           $data = empty($this->missMessage) ? []: ['message' => $this->missMessage];
           throw new Miss($data);
       }
       if (property_exists($obj, 'deletedAt')) {
           if ($obj->isDeleted()) {
               $data = empty($this->goneMessage) ? []: ['message' => $this->goneMessage];
               $data['data'] = $obj->filterDeleted();
               throw new Gone($data);
           }
       }

       return $obj;
    }
}