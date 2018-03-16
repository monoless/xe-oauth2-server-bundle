<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 1:45
 */

namespace Monoless\Xe\OAuth2\Server\Repositories;


use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use Monoless\Xe\OAuth2\Server\Entities\AccessTokenEntity;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{

    /**
     * @param AccessTokenEntityInterface $accessTokenEntity
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $entry = new \stdClass();
        $entry->unique_srl = $accessTokenEntity->getIdentifier();
        $entry->member_srl = $accessTokenEntity->getUserIdentifier();
        $entry->app_unique_srl = $accessTokenEntity->getClient()->getIdentifier();
        $entry->name = '';
        $entry->scopes = json_encode($accessTokenEntity->getScopes());
        $entry->revoked = 'n';
        $entry->created_at = date('YmdHis');
        $entry->updated_at = date('YmdHis');
        $entry->expired_at = $accessTokenEntity->getExpiryDateTime()
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('YmdHis');

        executeQuery('oauth_server.insertAccessToken', $entry);
    }

    /**
     * @param string $tokenId
     */
    public function revokeAccessToken($tokenId)
    {
        $args = new \stdClass();
        $args->tokenId = $tokenId;
        $args->revoked = 'y';
        $args->updated_at = date('YmdHis');

        executeQuery('oauth_server.updateRevokedFromAccessToken', $args);
    }

    /**
     * @param string $tokenId
     * @return bool
     */
    public function isAccessTokenRevoked($tokenId)
    {
        $args = new \stdClass();
        $args->tokenId = $tokenId;
        $output = executeQuery('oauth_server.findAccessTokenByTokenId', $args);

        // Check if client is registered
        if (!$output->toBool() || !count($output->data)) {
            return true;
        }

        $entry = $output->data;

        return in_array($entry->revoked, ['y', 'Y']);
    }

    /**
     * @param ClientEntityInterface $clientEntity
     * @param array $scopes
     * @param null $userIdentifier
     * @return AccessTokenEntityInterface|AccessTokenEntity
     */
    public function getNewToken(ClientEntityInterface $clientEntity, array $scopes, $userIdentifier = null)
    {
        $accessToken = new AccessTokenEntity();
        $accessToken->setClient($clientEntity);
        foreach ($scopes as $scope) {
            $accessToken->addScope($scope);
        }
        $accessToken->setUserIdentifier($userIdentifier);

        return $accessToken;
    }
}