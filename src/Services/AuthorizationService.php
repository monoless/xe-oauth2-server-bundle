<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 4:58
 */

namespace Monoless\Xe\OAuth2\Server\Services;


use Defuse\Crypto\Crypto;
use Lcobucci\JWT\Token;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use Monoless\Xe\OAuth2\Server\Entities\UserEntity;
use Monoless\Xe\OAuth2\Server\Repositories\AccessTokenRepository;
use Monoless\Xe\OAuth2\Server\Repositories\AuthCodeRepository;
use Monoless\Xe\OAuth2\Server\Repositories\ClientRepository;
use Monoless\Xe\OAuth2\Server\Repositories\GrantAppRepository;
use Monoless\Xe\OAuth2\Server\Repositories\RefreshTokenRepository;
use Monoless\Xe\OAuth2\Server\Repositories\ScopeRepository;
use Monoless\Xe\OAuth2\Server\Utils\CommonUtil;
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
        return new \DateInterval('PT2H');
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
     * @param $tokenJson
     * @param $encryptionKey
     * @throws \Exception
     */
    private static function grantApp($tokenJson, $encryptionKey)
    {
        $tokenJson = json_decode($tokenJson);
        if ($refreshToken = $tokenJson->refresh_token) {
            try {
                $refreshToken = Crypto::decryptWithPassword($refreshToken, $encryptionKey);
            } catch (\Exception $e) {
                $refreshToken = null;
            }
        }

        if ($accessToken = $tokenJson->access_token) {
            try {
                $accessToken = CommonUtil::parseJwt($accessToken);
            } catch (\Exception $e) {
                $accessToken = null;
            }
        }

        if ($refreshToken && $accessToken instanceof Token) {
            $refreshToken = json_decode($refreshToken);

            $now = new \DateTime('now', new \DateTimeZone(date_default_timezone_get()));
            $expired = $refreshToken->expire_time ?
                (new \DateTime())->setTimestamp($refreshToken->expire_time) :
                $now->add(self::refreshTokenExpiresIn());

            $grantAppRepository = new GrantAppRepository();
            $grantAppEntity = $grantAppRepository->getNewGrantApp();
            $grantAppEntity->setUniqueAppSrl($accessToken->getClaim('aud'));
            $grantAppEntity->setMemberSrl($refreshToken->user_id ? $refreshToken->user_id : 0);
            $grantAppEntity->setRevoked(false);
            $grantAppEntity->setCreatedAt($now->format('YmdHis'));
            $grantAppEntity->setUpdatedAt($now->format('YmdHis'));
            $grantAppEntity->setExpiredAt($expired->format('YmdHis'));
            $grantAppRepository->persisNewGrantApp($grantAppEntity);
        }
    }

    /**
     * @param string $privateKeyPath
     * @param string $encryptionKey
     * @return AuthorizationServer
     * @throws \Exception
     */
    public static function getServer($privateKeyPath, $encryptionKey)
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
    public static function approve(AuthorizationServer $server,
                                   ServerRequestInterface $request,
                                   ResponseInterface $response,
                                   UserEntity $userEntity)
    {
        $authRequest = $server->validateAuthorizationRequest($request);
        $authRequest->setUser($userEntity);
        $authRequest->setAuthorizationApproved(true);

        $response = $server->completeAuthorizationRequest($authRequest, $response);

        return $response;
    }

    /**
     * @param AuthorizationServer $server
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @param string $encryptionKey
     * @return ResponseInterface
     * @throws \League\OAuth2\Server\Exception\OAuthServerException|\Exception
     */
    public static function respondToAccessTokenRequest(AuthorizationServer $server,
                                                       ServerRequestInterface $request,
                                                       ResponseInterface $response,
                                                       $encryptionKey)
    {
        $response = $server->respondToAccessTokenRequest($request, $response);
        if ($response instanceof ResponseInterface) {
            self::grantApp((string)$response->getBody(), $encryptionKey);
        }

        return $response;
    }
}