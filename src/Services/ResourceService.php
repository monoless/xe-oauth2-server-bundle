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
use Monoless\Xe\OAuth2\Server\Utils\RequestUtil;
use Monoless\Xe\OAuth2\Server\Utils\ResponseUtil;
use Phossa2\Middleware\Interfaces\MiddlewareInterface;
use Phossa2\Middleware\Queue;
use Phossa2\Middleware\TerminateQueue;
use Psr\Http\Message\ResponseInterface;
use Zend\Diactoros\Response\JsonResponse;

class ResourceService
{
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
     * @param \stdClass $config
     * @return RateLimiter
     */
    private static function getRateLimiter(\stdClass $config)
    {
        return new RateLimiter([
            'capacity' => $config->rate_limit_capacity,
            'leak' => 1,
            'prefix' => RequestUtil::getHost() . '-xe-',
            'suffix' => '-limiter',
            'header' => 'Rate-Limiting-Meta',
            'scheme' => 'tcp://',
            'host' => $config->redis_host,
            'port' => $config->redis_port,
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
     * @param \stdClass $config
     * @param callable $callback
     */
    public static function processResource($publicKeyPath, \stdClass $config, callable $callback)
    {
        $request = ServerRequest::fromGlobals();
        $response = new Response();

        $queues = [new ResourceServerMiddleware(self::getResourceServer($publicKeyPath))];

        if ($config->use_rate_limiter) {
            $queues[] = [new TerminateQueue(), new InvalidHttpStatusCondition()];
            $queues[] = self::getRateLimiter($config);
        }

        $queues[] = [new TerminateQueue(), new InvalidHttpStatusCondition()];
        $queues[] = $callback;

        $queue = new Queue($queues);
        ResponseUtil::finalizeResponse($queue($request, $response));
    }
}