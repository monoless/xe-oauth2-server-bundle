<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 4:58
 */

namespace Monoless\Xe\OAuth2\Server\Services;


use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\Middleware\ResourceServerMiddleware;
use League\OAuth2\Server\ResourceServer;
use Monoless\Xe\OAuth2\Server\Repositories\AccessTokenRepository;
use Monoless\Xe\OAuth2\Server\Utils\ResponseUtil;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class ResourceService
{
    /**
     * @param string $publicKeyPath
     * @return ResourceServer
     */
    private static function getServer($publicKeyPath)
    {
        return new ResourceServer(
            new AccessTokenRepository(),
            $publicKeyPath
        );
    }

    /**
     * @param ResourceServer $resourceServer
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param $next
     * @return ResponseInterface
     */
    private static function process(ResourceServer $resourceServer,
                                   ServerRequestInterface $request,
                                   ResponseInterface $response,
                                   $next)
    {
        $middleware = new ResourceServerMiddleware($resourceServer);
        return $middleware($request, $response, $next);
    }

    /**
     * @param string $publicKeyPath
     * @param $callback
     */
    public static function processResource($publicKeyPath, $callback)
    {
        ResponseUtil::finalizeResponse(self::process(
            self::getServer($publicKeyPath),
            ServerRequest::fromGlobals(),
            new Response(),
            $callback
        ));
    }
}