<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-16
 * Time: 오후 3:56
 */

namespace Monoless\Xe\OAuth2\Server\Utils;


class ScopeUtil
{
    /**
     * @param string $permission
     * @return array
     */
    public static function permissionToScope($permission = 'r')
    {
        if ('r' == $permission) {
            return ['read'];
        } elseif ('w' == $permission) {
            return ['read', 'write'];
        } elseif ('m' == $permission) {
            return ['read', 'write', 'message'];
        } else {
            return [];
        }
    }
}