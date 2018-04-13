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
use LeakyBucketRateLimiter\RateLimiter;
use Monoless\Xe\OAuth2\Server\Conditions\InvalidHttpStatusCondition;
use Monoless\Xe\OAuth2\Server\Repositories\AccessTokenRepository;
use Monoless\Xe\OAuth2\Server\Utils\ResponseUtil;
use Phossa2\Middleware\Queue;
use Phossa2\Middleware\TerminateQueue;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse;

class ResourceService
{
    /**
     * @param string $publicKeyPath
     * @return Queue
     */
    private static function getMiddlewareQueues($publicKeyPath)
    {
        $queues = [new ResourceServerMiddleware(self::getResourceServer($publicKeyPath))];

        // TODO
        if (1) {
            $queues[] = [new TerminateQueue(), new InvalidHttpStatusCondition()];
            $queues[] = self::getRateLimiter();
        }

        return new Queue($queues);
    }

    /**
     * @param string $publicKeyPath
     * @return ResourceServer
     */
    private static function getResourceServer($publicKeyPath)
    {
        return new ResourceServer(
            new AccessTokenRepository(),
            $publicKeyPath
        );
    }

    /**
     * @return RateLimiter
     */
    private static function getRateLimiter()
    {
        return new RateLimiter([
            'capacity' => 45,               // TODO limit hit
            'leak' => 1,                    // TODO per second,
            'prefix' => 'xe-devcenter-',
            'suffix' => "-limiter",
            'header' => "Rate-Limiting-Meta",
            'scheme' => 'tcp://',           // TODO
            'host' => '127.0.0.1',          // TODO
            'port' => 6379,                 // TODO
            'callback' => function($request) {
                if (is_array($request)) $request = $request[0];
                return [
                    'token' => $request->getAttribute('oauth_user_id')
                ];
            },
            'throttle' => function(ResponseInterface $response) {
                return new JsonResponse([
                    'error' => "User request limit reached"
                ], 429);
            }
        ]);
    }

    /**
     * @param $publicKeyPath
     * @param callable $callback
     */
    public static function processResource($publicKeyPath, callable $callback)
    {
        $queues = self::getMiddlewareQueues($publicKeyPath);

        $request = ServerRequest::fromGlobals();
        $response = new Response();
        $response = $queues($request, $response);;
        if (!(new InvalidHttpStatusCondition())->evaluate($request, $response)) {
            $response = $callback($request, $response);
        }

        ResponseUtil::finalizeResponse($response);
    }
}