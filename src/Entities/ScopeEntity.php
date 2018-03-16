<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 1:29
 */

namespace Monoless\Xe\OAuth2\Server\Entities;


use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;

class ScopeEntity implements ScopeEntityInterface
{
    use EntityTrait;

    public function jsonSerialize()
    {
        return $this->getIdentifier();
    }
}