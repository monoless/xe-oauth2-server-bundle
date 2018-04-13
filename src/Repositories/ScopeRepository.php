<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 1:21
 */

namespace Monoless\Xe\OAuth2\Server\Repositories;


use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use Monoless\Xe\OAuth2\Server\Entities\ScopeEntity;

class ScopeRepository implements ScopeRepositoryInterface
{
    /**
     * @param string $scopeIdentifier
     * @return \League\OAuth2\Server\Entities\ScopeEntityInterface|ScopeEntity|void
     */
    public function getScopeEntityByIdentifier($scopeIdentifier)
    {
        $scopes = [
            'read' => [
                'description' => 'read your time line',
            ],

            'write' => [
                'description' => 'write your time line',
            ],

            'message' => [
                'description' => 'send message to other guys'
            ],

            'stream' => [
                'description' => 'read others time line',
            ],
        ];

        if (array_key_exists($scopeIdentifier, $scopes) === false) {
            return;
        }

        $scope = new ScopeEntity();
        $scope->setIdentifier($scopeIdentifier);
        return $scope;
    }

    /**
     * @param array $scopes
     * @param string $grantType
     * @param ClientEntityInterface $clientEntity
     * @param null $userIdentifier
     * @return array|\League\OAuth2\Server\Entities\ScopeEntityInterface[]
     */
    public function finalizeScopes(array $scopes, $grantType, ClientEntityInterface $clientEntity,
                                   $userIdentifier = null)
    {
        return $scopes;
    }
}