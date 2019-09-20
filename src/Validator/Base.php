<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/20
 * Time: 09:52
 */

namespace App\Validator;


use App\Exception\Parameter;
use Symfony\Component\Validator\Constraints\Collection;
use Symfony\Component\Validator\Constraints\ValidValidator;
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
    protected $validator;

    /**
     * @return ValidatorInterface
     */
    public function getValidator ()
    {
        return $this->validator;
    }

    public function setValidator (): void
    {
        $this->validator = Validation::createValidator();
    }

    /**
     * @return Collection
     */
    public function getCollection ()
    {
        return $this->collection;
    }

    public function setCollection ()
    {
    }

    /**
     * Base constructor.
     */
    public function __construct () {
        if (empty(self::getValidator() instanceof ValidValidator)) {
            self::setValidator();
        }
        static::setCollection();

        return self::getValidator();
    }

    /**
     * @param array $input
     * @throws \Exception
     */
    public function check (array $input)
    {
        $res = self::getValidator()->validate($input, self::getCollection());

        if ($res) {
            $message = '';

            foreach ($res as $item) {
                $prefix = empty($message) ? '': ';';

                $message .= $prefix . $item->getMessage();
            }

            if ($message) throw new Parameter(['message' => $message]);
        }
    }

}