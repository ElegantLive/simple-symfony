<?php


namespace App\Service;


use App\Exception\Parameter;

class ParameterCheck
{
    /**
     * @var Request
     */
    private $request;

    /**
     * ParameterCheck constructor.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @param string $char
     * @return void
     */
    public function checkParams(string $char)
    {
        if (empty($char)) throw new Parameter(['message' => '参数错误了']);

        $request = $this->request->getRequest ();

        $params  = $this->request->getData ();

        $params['time'] = $request->headers->get ('time');
        $params['once'] = $request->headers->get ('once');

        $check = $request->headers->get ('parameter');

        ksort ($params);

        $paramStr = http_build_query ($params);

        $paramStr = str_replace ('+', '%20', $paramStr);

        $paramMd5 = md5 ($paramStr . $char);

        if ($check != $paramMd5) {
            throw new Parameter(['data' => [$char, $check, $paramMd5]]);
        }
    }
}