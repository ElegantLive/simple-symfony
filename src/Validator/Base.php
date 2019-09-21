<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/20
 * Time: 09:52
 */

namespace App\Validator;

use Exception;
use App\Exception\Parameter;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class Base
 * @package App\Validator
 */
class Base
{
    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var ValidatorInterface
     */
    protected static $validator;

    /**
     * @return ValidatorInterface
     */
    protected function getValidator ()
    {
        return Base::$validator;
    }

    protected function setValidator (): void
    {
        Base::$validator = Validation::createValidator();
    }

    /**
     * @return Collection
     */
    protected function getCollection ()
    {
        return $this->collection;
    }

    protected function setCollection ()
    {
    }

    /**
     * Base constructor.
     */
    public function __construct () {
        if (empty(self::getValidator() instanceof ValidatorInterface)) {
            self::setValidator();
        }
        static::setCollection();
    }

    /**
     * @param array $input
     * @throws \Exception
     */
    public function check (array $input)
    {
        $collection = self::getCollection();
        if (empty($collection)) throw new Exception('please configure collection!');

        $res = self::getValidator()->validate($input, self::getCollection());

        $message = '';

        foreach ($res as $item) {
            $prefix = empty($message) ? '': ';';

            $message .= $prefix . $item->getMessage();
        }

        if ($message) throw new Parameter(['message' => $message]);
    }

}