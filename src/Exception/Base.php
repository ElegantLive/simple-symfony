<?php
/**
 * Created by PhpStorm.
 * User: qucaixian
 * Date: 2019/9/17
 * Time: 10:05
 */

namespace App\Exception;


use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Class Base
 * @package App\Exception
 */
class Base extends HttpException
{
    /**
     * 返回的http状态码
     * @var int
     */
    protected $status = Response::HTTP_INTERNAL_SERVER_ERROR;

    /**
     * 错误信息
     * @var string
     */
    protected $message = 'invalid parameters';

    /**
     * 自定义错误码
     * @var int
     */
    protected $errorCode = 999;

    /**
     * 附加数据
     * @var array
     */
    protected $data = [];

    /**
     * 允许携带的附加数据key
     * @var array
     */
    public $accessKey = [
        'data', 'errorCode', 'message'
    ];


    /**
     * Base constructor.
     * @param array           $errorData
     * @param int             $statusCode
     * @param \Throwable|null $previous
     */
    public function __construct (array $errorData = [], int $statusCode = 0, \Throwable $previous = null)
    {
        foreach ($this->accessKey as $key => $value) {
            if (empty($errorData[$value]) == false) $this->$value = $errorData[$value];
        }

        if ($statusCode) $this->setStatus($statusCode);

        parent::__construct($this->getStatus(), $this->getMessage(), $previous, [], $this->getStatus());
    }

    /**
     * @return int
     */
    public function getStatus (): int
    {
        return $this->status;
    }

    /**
     * @param int $status
     */
    public function setStatus (int $status): void
    {
        $this->status = $status;
    }

    /**
     * @return int
     */
    public function getErrorCode (): int
    {
        return $this->errorCode;
    }

    /**
     * @param int $errorCode
     */
    public function setErrorCode (int $errorCode): void
    {
        $this->errorCode = $errorCode;
    }

    /**
     * @return array
     */
    public function getData (): array
    {
        return $this->data;
    }

    /**
     * @param array $data
     */
    public function setData (array $data): void
    {
        $this->data = $data;
    }
}