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
use Monoless\Xe\OAuth2\Server\Utils\RequestUtil;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{

    /**
     * @param AccessTokenEntityInterface $accessTokenEntity
     */
    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity)
    {
        $entry = new \stdClass();
        $entry->unique_token_srl = $accessTokenEntity->getIdentifier();
        $entry->member_srl = $accessTokenEntity->getUserIdentifier();
        $entry->unique_app_srl = $accessTokenEntity->getClient()->getIdentifier();
        $entry->name = '';
        $entry->scopes = json_encode($accessTokenEntity->getScopes());
        $entry->revoked = 'n';
        $entry->created_at = date('YmdHis');
        $entry->updated_at = date('YmdHis');
        $entry->expired_at = $accessTokenEntity->getExpiryDateTime()
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('YmdHis');

        executeQuery('devcenter.insertAccessToken', $entry);

        if (getModule('loginlog', 'class')) {
            $entry = new \stdClass;
            $entry->log_srl = getNextSequence();
            $entry->member_srl = $accessTokenEntity->getUserIdentifier();
            $entry->is_succeed = 'Y';
            $entry->regdate = date('YmdHis');
            $entry->platform = 'OAuth2';
            $entry->browser = $accessTokenEntity->getClient()->getName();
            $entry->user_id = 0;
            $entry->email_address = 0;
            $entry->ipaddress = RequestUtil::getIp();

            executeQuery('loginlog.insertLoginlog', $entry);
        }
    }

    /**
     * @param string $uniqueAppSrl
     * @param integer $memberSrl
     */
    public function revokeAccessTokenByUniqueAppSrlAndMemberSrl($uniqueAppSrl, $memberSrl)
    {
        $args = new \stdClass();
        $args->unique_app_srl = $uniqueAppSrl;
        $args->member_srl = $memberSrl;
        $args->revoked = 'y';
        $args->updated_at = date('YmdHis');

        executeQuery('devcenter.updateRevokedFromAccessTokenByUniqueAppSrlAndMemberSrl', $args);
    }

    /**
     * @param string $uniqueTokenSrl
     */
    public function revokeAccessToken($uniqueTokenSrl)
    {
        $args = new \stdClass();
        $args->unique_token_srl = $uniqueTokenSrl;
        $args->revoked = 'y';
        $args->updated_at = date('YmdHis');

        executeQuery('devcenter.updateRevokedFromAccessToken', $args);
    }

    /**
     * @param string $uniqueTokenSrl
     * @return bool
     */
    public function isAccessTokenRevoked($uniqueTokenSrl)
    {
        $args = new \stdClass();
        $args->unique_token_srl = $uniqueTokenSrl;
        $output = executeQuery('devcenter.findAccessTokenByUniqueTokenSrl', $args);

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