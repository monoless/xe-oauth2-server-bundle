<?php
/**
 * Created by PhpStorm.
 * User: ecst
 * Date: 2018-04-02
 * Time: 오후 6:11
 */

namespace Monoless\Xe\OAuth2\Server\Utils;


use GuzzleHttp\Psr7\ServerRequest;
use Psr\Http\Message\RequestInterface;

class RequestUtil
{
    /**
     * @param RequestInterface|ServerRequest $request
     * @return array
     */
    public static function getParsedParams(RequestInterface $request)
    {
        $params = $request->getParsedBody();
        if ('PUT' == $request->getMethod()) {
            parse_str(file_get_contents('php://input'), $params);
        }

        return $params;
    }

    public static function getIp()
    {
        $proxy_headers = array(
            'CLIENT_IP',
            'FORWARDED',
            'FORWARDED_FOR',
            'FORWARDED_FOR_IP',
            'HTTP_CLIENT_IP',
            'HTTP_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED_FOR_IP',
            'HTTP_PC_REMOTE_ADDR',
            'HTTP_PROXY_CONNECTION',
            'HTTP_VIA',
            'HTTP_X_FORWARDED',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED_FOR_IP',
            'HTTP_X_IMFORWARDS',
            'HTTP_XROXY_CONNECTION',
            'VIA',
            'X_FORWARDED',
            'X_FORWARDED_FOR',
            'HTTP_CF_CONNECTING_IP'
        );
        $regEx = "/^([1-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])(\.([0-9]|[1-9][0-9]|1[0-9][0-9]|2[0-4][0-9]|25[0-5])){3}$/";
        foreach ($proxy_headers as $proxy_header) {
            if (isset($_SERVER[$proxy_header])) {
                /* HEADER ist gesetzt und dies ist eine gültige IP */
                return $_SERVER[$proxy_header];
            } else if (stristr(',', $_SERVER[$proxy_header]) !== false) {
                // Behandle mehrere IPs in einer Anfrage
                //(z.B.: X-Forwarded-For: client1, proxy1, proxy2)
                $proxy_header_temp = trim(
                    array_shift(explode(',', $_SERVER[$proxy_header]))
                ); /* Teile in einzelne IPs, gib die letzte zurück und entferne Leerzeichen */

                // if IPv4 address remove port if exists
                if (preg_match($regEx, $proxy_header_temp)
                    && ($pos_temp = stripos($proxy_header_temp, ':')) !== false
                ) {
                    $proxy_header_temp = substr($proxy_header_temp, 0, $pos_temp);
                }
                return $proxy_header_temp;
            }
        }

        return $_SERVER['REMOTE_ADDR'];
    }

    /**
     * @param RequestInterface $request
     * @param string $allowMethod
     * @return boolean
     */
    public static function checkRequestMethod(RequestInterface $request, $allowMethod = 'get')
    {
        return (strtolower($allowMethod) == strtolower($request->getMethod()));
    }
}