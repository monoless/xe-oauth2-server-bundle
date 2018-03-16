<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 4:58
 */

namespace Monoless\Xe\OAuth2\Server\Services;


use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use Monoless\Xe\OAuth2\Server\Entities\UserEntity;
use Monoless\Xe\OAuth2\Server\Repositories\AccessTokenRepository;
use Monoless\Xe\OAuth2\Server\Repositories\AuthCodeRepository;
use Monoless\Xe\OAuth2\Server\Repositories\ClientRepository;
use Monoless\Xe\OAuth2\Server\Repositories\RefreshTokenRepository;
use Monoless\Xe\OAuth2\Server\Repositories\ScopeRepository;
use Psr\Http\Message\ServerRequestInterface;

class AuthorizationService
{
    /**
     * @param string $privateKeyPath
     * @param string $encryptionKey
     * @return AuthorizationServer
     * @throws \Exception
     */
    public static function getAuthorizationServer($privateKeyPath, $encryptionKey)
    {
        $clientRepository = new ClientRepository();
        $scopeRepository = new ScopeRepository();
        $accessTokenRepository = new AccessTokenRepository();
        $authCodeRepository = new AuthCodeRepository();
        $refreshTokenRepository = new RefreshTokenRepository();

        $server = new AuthorizationServer(
            $clientRepository,
            $accessTokenRepository,
            $scopeRepository,
            $privateKeyPath,
            $encryptionKey
        );

        $server->enableGrantType(
            new AuthCodeGrant(
                $authCodeRepository,
                $refreshTokenRepository,
                new \DateInterval('PT10M')
            ),
            new \DateInterval('PT1H')
        );

        return $server;
    }

    /**
     * @param AuthorizationServer $server
     * @param ServerRequestInterface $request
     * @param Response $response
     * @param UserEntity $userEntity
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \League\OAuth2\Server\Exception\OAuthServerException
     */
    public static function authorizationApprove(AuthorizationServer $server,
                              ServerRequestInterface $request,
                              Response $response,
                              UserEntity $userEntity)
    {
        $authRequest = $server->validateAuthorizationRequest($request);
        $authRequest->setUser($userEntity);
        $authRequest->setAuthorizationApproved(true);

        return $server->completeAuthorizationRequest($authRequest, $response);
    }
}