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
        $accessToken = $refreshTokenEntity->getAccessToken();

        $entry = new \stdClass();
        $entry->unique_token_srl = $refreshTokenEntity->getIdentifier();
        $entry->access_token_srl = $accessToken->getIdentifier();
        $entry->revoked = 'n';
        $entry->created_at = date('YmdHis');
        $entry->updated_at = date('YmdHis');
        $entry->expired_at = $refreshTokenEntity->getExpiryDateTime()
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('YmdHis');

        executeQuery('devcenter.insertRefreshToken', $entry);
    }

    /**
     * @param string $uniqueAppSrl
     * @param integer $memberSrl
     */
    public function revokeRefreshTokenByUniqueAppSrlAndMemberSrl($uniqueAppSrl, $memberSrl)
    {
        $entry = new \stdClass();
        $entry->unique_app_srl = $uniqueAppSrl;
        $entry->member_srl = $memberSrl;
        $entry->expired_at = date('YmdHis');

        $output = executeQueryArray('devcenter.findActiveRefreshTokenByUniqueAppSrlAndMemberSrl', $entry);

        if (!$output->toBool() || !count($output->data)) {
            return;
        }

        $entries = $output->data;
        foreach ($entries as $entry) {
            $this->revokeRefreshToken($entry->unique_token_srl);
        }
    }

    /**
     * @param string $uniqueTokenSrl
     */
    public function revokeRefreshToken($uniqueTokenSrl)
    {
        $args = new \stdClass();
        $args->unique_token_srl = $uniqueTokenSrl;
        $args->revoked = 'y';
        $args->updated_at = date('YmdHis');

        executeQuery('devcenter.updateRevokedFromRefreshToken', $args);
    }

    /**
     * @param string $uniqueTokenSrl
     * @return bool
     */
    public function isRefreshTokenRevoked($uniqueTokenSrl)
    {
        $args = new \stdClass();
        $args->unique_token_srl = $uniqueTokenSrl;
        $output = executeQuery('devcenter.findRefreshTokenByUniqueTokenSrl', $args);

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