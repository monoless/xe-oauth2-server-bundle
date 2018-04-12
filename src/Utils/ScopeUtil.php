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
     * @return array
     */
    public static function getPermissions()
    {
        return [
            'r' => ['read'],
            'w' => ['read', 'write'],
            'm' => ['read', 'write', 'message'],
            's' => ['read', 'write', 'message', 'stream']
        ];
    }

    /**
     * @param array $scope
     * @return string
     */
    public static function scopeToPermission(array $scope)
    {
        $permissions = self::getPermissions();
        foreach ($permissions as $key => $value) {
            if ($value === $scope) {
                return $key;
            }
        }

        return '';
    }

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
        } elseif ('s' == $permission) {
            return ['read', 'write', 'message', 'stream'];
        } else {
            return [];
        }
    }

    /**
     * @param \stdClass $lang
     * @param array $scopes
     * @return string
     */
    public static function convertReadable(\stdClass $lang, array $scopes = [])
    {
        $buf = [];
        foreach ($scopes as $scope) {
            if ('read' == $scope) {
                $buf[] = $lang->devcenter_scope_read;
            } elseif ('write' == $scope) {
                $buf[] = $lang->devcenter_scope_write;
            } elseif ('message' == $scope) {
                $buf[] = $lang->devcenter_scope_message;
            } elseif ('stream' == $scope) {
                $buf[] = $lang->devcenter_scope_stream;
            }
        }

        return implode(', ', $buf);
    }
}