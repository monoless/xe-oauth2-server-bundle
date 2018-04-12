<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-16
 * Time: 오후 3:56
 */

namespace Monoless\Xe\OAuth2\Server\Utils;


use GuzzleHttp\Psr7\ServerRequest;
use Monoless\Xe\OAuth2\Server\Entities\ClientEntity;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse;
use Zend\Diactoros\Stream;

class ResponseUtil
{
    /**
     * @param ServerRequest $request
     * @param ClientEntity $entity
     * @param string $state
     * @return string
     */
    public static function authorizeRedirectUrl(ServerRequest $request, ClientEntity $entity, $state = '')
    {
        $params = $request->getServerParams();

        return implode([
            array_key_exists('PHP_SELF', $params) ? $params['PHP_SELF'] : '',
            '?',
            http_build_query([
                'module' => 'devcenter',
                'act' => 'dispDevcenterAuthorize',
                'response_type' => 'code',
                'client_id' => $entity->getIdentifier(),
                'scope' => implode(' ', $entity->getScope()),
                'state' => $state
            ])
        ]);
    }

    public static function notSupportedMethod()
    {
        return new JsonResponse([
            'error' => 'method_not_allowed',
            'message' => 'method invalid',
        ], 405);
    }

    /**
     * @param ResponseInterface $response
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
        exit;
    }

    /**
     * @param ResponseInterface $response
     * @param \Exception $exception
     */
    public static function finalizeExceptionResponse(ResponseInterface $response, \Exception $exception)
    {
        $body = new Stream('php://temp', 'r+');
        $body->write($exception->getMessage());

        $response->withStatus(500)->withBody($body);
        self::finalizeResponse($response);
    }
}