<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-03-14
 * Time: 오후 1:52
 */

namespace Monoless\Xe\OAuth2\Server\Utils;



class CommonUtil
{
    /**
     * @param string $email
     * @param string $mask
     * @return string
     */
    public static function obfuscateEmail($email, $mask = '*')
    {
        $em   = explode("@", $email);
        $name = implode(array_slice($em, 0, count($em) - 1), '@');
        $len  = floor(strlen($name) / 2);

        return implode([
            substr($name,0, $len),
            str_repeat($mask, $len),
            '@',
            end($em)
        ]);
    }
}