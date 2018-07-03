<?php

namespace liesauer;

class SimpleHttpClient
{
    public static function quickGet($url, $header = null, $cookie = '', $data = '', $options = null)
    {
        return self::quickRequest($url . (empty($data) ? '' : '?' . $data), 'GET', $header, $cookie, '', $options);
    }
    public static function quickPost($url, $header = null, $cookie = '', $data = '', $options = null)
    {
        return self::quickRequest($url, 'POST', $header, $cookie, $data, $options);
    }
    final private static function quickRequest($url, $method, $header = null, $cookie = '', $data = '', $options = null)
    {
        $ch = curl_init($url);
        switch (strtoupper($method)) {
            case 'GET':
                //curl_setopt($ch,CURLOPT_HTTPGET,TRUE);
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if (!empty($data)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }

                break;
            default:
                return false;
                break;
        }
        if (count($header) >= 1) {
            foreach ($header as $key => &$value) {
                if (is_string($key)) {
                    $value = "{$key}: {$value}";
                }
            }
            unset($value);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        if (!empty($cookie)) {
            if (is_array($cookie)) {
                $keys    = array_keys($cookie);
                $values  = array_values($cookie);
                $counter = count($cookie);
                $cookies = [];
                for ($i = 0; $i < $counter; $i++) {
                    $key   = '';
                    $value = '';
                    if (is_string($keys[$i])) {
                        $key   = rawurlencode(trim($keys[$i]));
                        $value = rawurlencode(trim($values[$i]));
                    } else {
                        $parse_cookie = explode('=', $values[$i]);
                        if (isset($parse_cookie[0])) {
                            $key = trim($parse_cookie[0]);
                        }
                        if (isset($parse_cookie[1])) {
                            $value = trim($parse_cookie[1]);
                        }
                    }
                    $cookies[] = "{$key}={$value}";
                }
                $cookie = implode(';', $cookies);
            }
            curl_setopt($ch, CURLOPT_COOKIE, $cookie);
        }

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if (strtolower(substr($url, 0, 5)) === 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        if (count($options) >= 1) {
            curl_setopt_array($ch, $options);
        }

        $re = array(
            'http_code' => 0,
            'header'    => '',
            'data'      => '',
        );
        $re['data']      = curl_exec($ch);
        $re['http_code'] = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size     = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $re['header']    = substr($re['data'], 0, $header_size);
        $re['data']      = substr($re['data'], $header_size);
        curl_close($ch);
        return $re;
    }
}
