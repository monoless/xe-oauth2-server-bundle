<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-16
 * Time: 오후 3:56
 */

namespace Monoless\Xe\OAuth2\Server\Utils;


use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Stream;

class ResponseUtil
{
    /**
     * @param ResponseInterface $response
     * @return \BaseObject
     */
    public static function finalizeResponse(ResponseInterface $response)
    {
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                header(sprintf('%s: %s', $name, $value), false);
            }
        }

        header(sprintf(
            'HTTP/%s %s %s',
            $response->getProtocolVersion(),
            $response->getStatusCode(),
            $response->getReasonPhrase()
        ));

        echo $response->getBody();

        return new \BaseObject();
    }

    /**
     * @param ResponseInterface $response
     * @param \Exception $exception
     * @return \BaseObject
     */
    public static function finalizeExceptionResponse(ResponseInterface $response, \Exception $exception)
    {
        $body = new Stream('php://temp', 'r+');
        $body->write($exception->getMessage());

        $response->withStatus(500)->withBody($body);

        return self::finalizeResponse($response);
    }
}