<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-04-13
 * Time: 오후 3:03
 */

namespace Monoless\Xe\OAuth2\Server\Conditions;

use Phossa2\Middleware\Interfaces\ConditionInterface;
use Phossa2\Shared\Base\ObjectAbstract;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class InvalidHttpStatusCondition extends ObjectAbstract implements ConditionInterface
{
    /**
     * @var integer
     */
    private $statusCode = [];

    /**
     * HttpStatusCondition constructor.
     * @param integer $normalStatusCode
     */
    public function __construct($normalStatusCode = 200)
    {
        $this->statusCode = $normalStatusCode;
    }

    /**
     * @param RequestInterface $request
     * @param ResponseInterface $response
     * @return boolean
     */
    public function evaluate(RequestInterface $request, ResponseInterface $response)
    {
        return $response->getStatusCode() != $this->statusCode;
    }
}