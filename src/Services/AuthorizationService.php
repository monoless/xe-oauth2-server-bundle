<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 4:58
 */

namespace Monoless\Xe\OAuth2\Server\Services;


use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use Monoless\Xe\OAuth2\Server\Entities\UserEntity;
use Monoless\Xe\OAuth2\Server\Repositories\AccessTokenRepository;
use Monoless\Xe\OAuth2\Server\Repositories\AuthCodeRepository;
use Monoless\Xe\OAuth2\Server\Repositories\ClientRepository;
use Monoless\Xe\OAuth2\Server\Repositories\RefreshTokenRepository;
use Monoless\Xe\OAuth2\Server\Repositories\ScopeRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthorizationService
{
    /**
     * @return \DateInterval
     * @throws \Exception
     */
    private static function tokensExpireIn()
    {
        // 1 hour
        return new \DateInterval('PT1H');
    }

    /**
     * @return \DateInterval
     * @throws \Exception
     */
    private static function authCodeExpiresIn()
    {
        // 10 min
        return new \DateInterval('PT10M');
    }

    /**
     * @return \DateInterval
     * @throws \Exception
     */
    private static function refreshTokenExpiresIn()
    {
        // 1 month
        return new \DateInterval('P1M');
    }

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

        // auth code grant
        $server->enableGrantType(
            new AuthCodeGrant(
                $authCodeRepository,
                $refreshTokenRepository,
                self::authCodeExpiresIn()
            ),
            self::tokensExpireIn()
        );

        $refreshTokenGrant = new RefreshTokenGrant($refreshTokenRepository);
        $refreshTokenGrant->setRefreshTokenTTL(self::refreshTokenExpiresIn());

        // refresh token
        $server->enableGrantType(
            $refreshTokenGrant,
            self::tokensExpireIn()
        );

        return $server;
    }

    /**
     * @param AuthorizationServer $server
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param UserEntity $userEntity
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \League\OAuth2\Server\Exception\OAuthServerException
     */
    public static function authorizationApprove(AuthorizationServer $server,
                              ServerRequestInterface $request,
                              ResponseInterface $response,
                              UserEntity $userEntity)
    {
        $authRequest = $server->validateAuthorizationRequest($request);
        $authRequest->setUser($userEntity);
        $authRequest->setAuthorizationApproved(true);

        return $server->completeAuthorizationRequest($authRequest, $response);
    }
}