<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-08
 * Time: 오후 12:01
 */

namespace Monoless\Xe\OAuth2\Server\Entities;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\ClientTrait;

class ClientEntity implements ClientEntityInterface
{
    use EntityTrait, ClientTrait;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setRedirectUri($uri)
    {
        $this->redirectUri = $uri;
    }
}