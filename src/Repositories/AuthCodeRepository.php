<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 1:49
 */

namespace Monoless\Xe\OAuth2\Server\Repositories;


use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use Monoless\Xe\OAuth2\Server\Entities\AuthCodeEntity;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{

    /**
     * @param AuthCodeEntityInterface $authCodeEntity
     */
    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity)
    {
        $entry = new \stdClass();
        $entry->unique_token_srl = $authCodeEntity->getIdentifier();
        $entry->member_srl = $authCodeEntity->getUserIdentifier();
        $entry->unique_app_srl = $authCodeEntity->getClient()->getIdentifier();
        $entry->scopes = json_encode($authCodeEntity->getScopes());
        $entry->revoked = 'n';
        $entry->created_at = date('YmdHis');
        $entry->updated_at = date('YmdHis');
        $entry->expired_at = $authCodeEntity->getExpiryDateTime()
            ->setTimezone(new \DateTimeZone(date_default_timezone_get()))
            ->format('YmdHis');

        executeQuery('devcenter.insertAuthCode', $entry);
    }

    /**
     * @param string $uniqueTokenSrl
     */
    public function revokeAuthCode($uniqueTokenSrl)
    {
        $args = new \stdClass();
        $args->unique_token_srl = $uniqueTokenSrl;
        $args->revoked = 'y';
        $args->updated_at = date('YmdHis');

        executeQuery('devcenter.updateRevokedFromAuthCode', $args);
    }

    /**
     * @param string $uniqueTokenSrl
     * @return bool
     */
    public function isAuthCodeRevoked($uniqueTokenSrl)
    {
        $args = new \stdClass();
        $args->unique_token_srl = $uniqueTokenSrl;
        $output = executeQuery('devcenter.findAuthCodeByUniqueTokenSrl', $args);

        // Check if client is registered
        if (!$output->toBool() || !count($output->data)) {
            return true;
        }

        $entry = $output->data;

        return in_array($entry->revoked, ['y', 'Y']);
    }

    /**
     * @return AuthCodeEntityInterface|AuthCodeEntity
     */
    public function getNewAuthCode()
    {
        return new AuthCodeEntity();
    }
}