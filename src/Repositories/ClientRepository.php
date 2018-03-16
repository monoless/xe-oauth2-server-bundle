<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-08
 * Time: 오전 11:56
 */

namespace Monoless\Xe\OAuth2\Server\Repositories;

use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Monoless\Xe\OAuth2\Server\Entities\ClientEntity;

class ClientRepository implements ClientRepositoryInterface
{

    /**
     * @param string $clientIdentifier
     * @param null $grantType
     * @param null $clientSecret
     * @param bool $mustValidateSecret
     * @return \League\OAuth2\Server\Entities\ClientEntityInterface|ClientEntity|void
     */
    public function getClientEntity($clientIdentifier, $grantType = null, $clientSecret = null, $mustValidateSecret = true)
    {
        $args = new \stdClass();
        $args->clientId = $clientIdentifier;
        $output = executeQuery('oauth_server.findAppByClientId', $args);

        // Check if client is registered
        if (!$output->toBool() || !count($output->data)) {
            return;
        }

        $entry = $output->data;

        if ($mustValidateSecret === true && $entry->client_secret != $clientSecret) {
            return;
        }

        $client = new ClientEntity();
        $client->setIdentifier($clientIdentifier);
        $client->setName($entry->name);
        $client->setRedirectUri($entry->callback_url);

        return $client;
    }
}