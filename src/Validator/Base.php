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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Class Base
 * @package App\Validator
 */
abstract class Base
{
    /**
     * @var Collection
     */
    protected $collection;

    /**
     * @var array
     */
    protected $fields = [];

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
        $this->collection = new Assert\Collection([
            'fields'               => $this->fields,
            'missingFieldsMessage' => '缺少定义字段 {{ field }}',
            'extraFieldsMessage'   => '请移除额外的字段 {{ field }}'
        ]);
    }

    abstract protected function setFields ();

    /**
     * Base constructor.
     */
    public function __construct ()
    {
        if (empty(self::getValidator() instanceof ValidatorInterface)) self::setValidator();
        static::setFields();
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
            $prefix = empty($message) ? '' : '; ';

            $message .= $prefix . $item->getMessage();
        }

        if ($message) throw new Parameter(['message' => $message]);
    }

}