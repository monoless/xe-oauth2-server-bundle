<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 1:37
 */

namespace Monoless\Xe\OAuth2\Server\Entities;


use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\AccessTokenTrait;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\TokenEntityTrait;

class AccessTokenEntity implements AccessTokenEntityInterface
{
    use AccessTokenTrait, TokenEntityTrait, EntityTrait;
}