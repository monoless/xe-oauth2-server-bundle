<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 1:51
 */

namespace Monoless\Xe\OAuth2\Server\Repositories;


use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use Monoless\Xe\OAuth2\Server\Entities\RefreshTokenEntity;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{

    /**
     * @param RefreshTokenEntityInterface $refreshTokenEntity
     */
    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity)
    {
        $entry = new \stdClass();
        $entry->unique_srl = $refreshTokenEntity->getIdentifier();
        $entry->access_token_srl = $refreshTokenEntity->getAccessToken()->getIdentifier();
        $entry->revoked = 'n';
        $entry->created_at = date('YmdHis');
        $entry->updated_at = date('YmdHis');
        $entry->expired_at = $refreshTokenEntity->getExpiryDateTime()
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('YmdHis');

        executeQuery('oauth_server.insertRefreshToken', $entry);
    }

    /**
     * @param string $tokenId
     */
    public function revokeRefreshToken($tokenId)
    {
        $args = new \stdClass();
        $args->tokenId = $tokenId;
        $args->revoked = 'y';
        $args->updated_at = date('YmdHis');

        executeQuery('oauth_server.updateRevokedFromRefreshToken', $args);
    }

    /**
     * @param string $tokenId
     * @return bool
     */
    public function isRefreshTokenRevoked($tokenId)
    {
        $args = new \stdClass();
        $args->tokenId = $tokenId;
        $output = executeQuery('oauth_server.findRefreshTokenByTokenId', $args);

        // Check if client is registered
        if (!$output->toBool() || !count($output->data)) {
            return true;
        }

        $entry = $output->data;

        return in_array($entry->revoked, ['y', 'Y']);
    }

    /**
     * @return RefreshTokenEntityInterface|RefreshTokenEntity
     */
    public function getNewRefreshToken()
    {
        return new RefreshTokenEntity();
    }
}