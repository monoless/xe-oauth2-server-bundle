<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-09
 * Time: 오후 1:38
 */

namespace Monoless\Xe\OAuth2\Server\Entities;


use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use League\OAuth2\Server\Entities\Traits\RefreshTokenTrait;

class RefreshTokenEntity implements RefreshTokenEntityInterface
{
    use RefreshTokenTrait, EntityTrait;
}