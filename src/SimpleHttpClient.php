<?php

namespace liesauer;

class SimpleHttpClient
{
    /**
     * @param string $url
     * @param array<string, string>|null $header
     * @param string|string[]|array<string, string>|null $cookie
     * @param string|array<string, string>|null $data
     * @param array<int, mixed>|null $options
     * 
     * @return array{http_code: int, header: string|bool, data: string|bool}
     */
    public static function quickGet($url, $header = [], $cookie = '', $data = '', $options = [])
    {
        if (!empty($data) && is_array($data)) {
            $data = http_build_query($data);
        }

        return self::quickRequest($url . (empty($data) ? '' : '?' . $data), 'GET', $header, $cookie, '', $options);
    }

    /**
     * @param string $url
     * @param array<string, string>|null $header
     * @param string|string[]|array<string, string>|null $cookie
     * @param string|array<string, string>|null $data
     * @param array<int, mixed>|null $options
     * 
     * @return array{http_code: int, header: string|bool, data: string|bool}
     */
    public static function quickPost($url, $header = [], $cookie = '', $data = '', $options = [])
    {
        return self::quickRequest($url, 'POST', $header, $cookie, $data, $options);
    }

    /**
     * @param string $url
     * @param string $method
     * @param array<string, string>|null $header
     * @param string|string[]|array<string, string>|null $cookie
     * @param string|array<string, string>|null $data
     * @param array<int, mixed>|null $options
     * 
     * @return array{http_code: int, header: string|bool, data: string|bool}
     */
    public static function quickRequest($url, $method, $header = [], $cookie = '', $data = '', $options = [])
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        switch (strtoupper($method)) {
            case 'GET':
                //curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
                break;
            default:
                // if ($method === 'POST') {
                //     curl_setopt($ch, CURLOPT_POST, true);
                // }
                if (!empty($data)) {
                    if (is_array($data)) {
                        $data = http_build_query($data);
                    }
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                }
                break;
        }
        if (count($header)) {
            foreach ($header as $key => &$value) {
                if (is_string($key)) {
                    $value = "{$key}: {$value}";
                }
            }
            unset($value);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        if (!empty($cookie)) {
            curl_setopt($ch, CURLOPT_COOKIE, self::getCookieString($cookie));
        }

        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if (strtolower(substr($url, 0, 5)) === 'https') {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }
        if (count($options)) {
            curl_setopt_array($ch, $options);
        }

        $result = self::parseResponse($ch, curl_exec($ch));
        curl_close($ch);
        return $result;
    }

    /**
     * @param string[]|list<array{url: string, method?: string, header?: array<string, string>|null, cookie?: string|string[]|array<string, string>|null, data?: string|array<string, string>|null, options?: array<int, mixed>|null}> $urls
     * @param array<string, string>|null $header
     * @param string|string[]|array<string, string>|null $cookie
     * @param string|array<string, string>|null $data
     * @param array<int, mixed>|null $options
     * 
     * @return array{http_code: int, header: string|bool, data: string|bool}
     */
    public static function multiGet($urls, $header = [], $cookie = '', $data = '', $options = [])
    {
        if (!empty($data) && is_array($data)) {
            $data = http_build_query($data);
        }

        foreach ($urls as &$url) {
            if (is_string($url)) {
                if (!empty($data)) {
                    $url .= '?' . $data;
                }
            } else {
                $url['url'].= '?' . $data;
            }
        }

        return self::multiRequest($urls, 'GET', $header, $cookie, '', $options);
    }

    /**
     * @param string[]|list<array{url: string, method?: string, header?: array<string, string>|null, cookie?: string|string[]|array<string, string>|null, data?: string|array<string, string>|null, options?: array<int, mixed>|null}> $urls
     * @param array<string, string>|null $header
     * @param string|string[]|array<string, string>|null $cookie
     * @param string|array<string, string>|null $data
     * @param array<int, mixed>|null $options
     * 
     * @return array{http_code: int, header: string|bool, data: string|bool}
     */
    public static function multiPost($urls, $header = [], $cookie = '', $data = '', $options = [])
    {
        return self::multiRequest($urls, 'POST', $header, $cookie, $data, $options);
    }

    /**
     * @param string[]|list<array{url: string, method?: string, header?: array<string, string>|null, cookie?: string|string[]|array<string, string>|null, data?: string|array<string, string>|null, options?: array<int, mixed>|null}> $urls
     * @param string $method
     * @param array<string, string>|null $header
     * @param string|string[]|array<string, string>|null $cookie
     * @param string|array<string, string>|null $data
     * @param array<int, mixed>|null $options
     * 
     * @return list<array{http_code: int, header: string|bool, data: string|bool}>
     */
    public static function multiRequest($urls, $method, $header = [], $cookie = '', $data = '', $options = [])
    {
        $urls = array_values($urls);

        $mHandle   = curl_multi_init();
        $handles   = [];
        $handleMap = [];

        $responses = [];

        foreach ($urls as $request) {
            $url = is_string($request) ? $request : $request['url'];

            $ch = curl_init($url);

            $url      = '';
            $_method  = $method;
            $_data    = $data;
            $_header  = $header;
            $_cookie  = self::parseCookie($cookie);
            $_options = $options;

            if (is_string($request)) {
                $url = $request;
            } else {
                $url = $request['url'];

                if (!empty($request['method'])) {
                    $_method = $request['method'];
                }

                if (!empty($request['header'])) {
                    if (empty($_header)) {
                        $_header = [];
                    }

                    $_header = self::dictionary_merge($_header, $request['header']);
                }

                if (!empty($request['cookie'])) {
                    $_cookie2 = self::parseCookie($request['cookie']);

                    $_cookie = self::dictionary_merge($_cookie, $_cookie2);
                }

                if (!empty($request['data'])) {
                    $_data = $request['data'];
                }

                if (!empty($request['options'])) {
                    if (empty($_options)) {
                        $_options = [];
                    }

                    $_options = self::dictionary_merge($_options, $request['options']);
                }
            }

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($_method));
            switch (strtoupper($_method)) {
                case 'GET':
                    //curl_setopt($ch, CURLOPT_HTTPGET, TRUE);
                    break;
                default:
                    // if ($_method === 'POST') {
                    //     curl_setopt($ch, CURLOPT_POST, true);
                    // }
                    if (!empty($_data)) {
                        if (is_array($_data)) {
                            $_data = http_build_query($_data);
                        }
                        curl_setopt($ch, CURLOPT_POSTFIELDS, $_data);
                    }
                    break;
            }
            if (count($_header)) {
                foreach ($_header as $key => &$value) {
                    if (is_string($key)) {
                        $value = "{$key}: {$value}";
                    }
                }
                unset($value);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $_header);
            }

            if (!empty($_cookie)) {
                curl_setopt($ch, CURLOPT_COOKIE, self::getCookieString($_cookie));
            }

            curl_setopt($ch, CURLOPT_HEADER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            if (strtolower(substr($url, 0, 5)) === 'https') {
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            }
            if (count($options)) {
                curl_setopt_array($ch, $options);
            }

            curl_multi_add_handle($mHandle, $ch);

            $handles[]   = $ch;
            $responses[] = null;
        }

        $handleMap = array_flip(array_map(function ($ch) {
            return (string)$ch;
        }, $handles));

        // $active = null;
        // do {
        //     curl_multi_exec($mHandle, $active);
        // } while ($active);

        // foreach ($handles as $ch) {
        //     $result = self::parseResponse($ch, curl_multi_getcontent($ch));
        //     $responses[] = $result;
        //     curl_multi_remove_handle($mHandle, $ch);
        //     curl_close($ch);
        // }

        do {
            while (($code = curl_multi_exec($mHandle, $active)) == CURLM_CALL_MULTI_PERFORM);

            if ($code != CURLM_OK) break;

            while ($done = curl_multi_info_read($mHandle)) {
                $result = self::parseResponse($done['handle'], curl_multi_getcontent($done['handle']));
                $responses[$handleMap[(string)$done['handle']]] = $result;
                curl_multi_remove_handle($mHandle, $done['handle']);
                curl_close($done['handle']);
            }

            if ($active > 0) {
                curl_multi_select($mHandle, 0);
            }
        } while ($active);

        curl_multi_close($mHandle);

        return $responses;
    }

    /**
     * 解析cookie成键值对形式
     * 
     * 支持以下三种形式cookie：
     * 1. `a=test;b=测试`                  kv进行trim处理
     * 2. `['a' => 'test', 'b' => '测试']` kv进行rawurlencode处理
     * 3. `['a=test', 'b=测试']`           kv进行trim处理
     * @param string|string[]|array<string, string> $cookie
     * 
     * @return array<string, string>
     */
    public static function parseCookie($cookie) {
        $cookies = [];

        if (empty($cookie)) return $cookies;

        if (is_string($cookie)) {
            foreach (explode(';', $cookie) as $_cookie) {
                $kv = explode('=', $_cookie, 2);

                if (!isset($kv[1])) continue;

                $key           = trim($kv[0]);
                $value         = trim($kv[1]);
                $cookies[$key] = $value;
            }
        } else if (is_array($cookie)) {
            foreach ($cookie as $key => $value) {
                if (is_string($key)) {
                    $cookies[rawurlencode($key)] = rawurlencode($value);
                } else {
                    $kv = explode('=', $value, 2);

                    if (!isset($kv[1])) continue;

                    $key           = trim($kv[0]);
                    $value         = trim($kv[1]);
                    $cookies[$key] = $value;
                }
            }
        }

        return $cookies;
    }

    /**
     * @param array<string, string> $cookie
     * 
     * @return string
     */
    public static function getCookieString($cookie) {
        $cookies = [];

        foreach ($cookie as $key => $value) {
            $cookies[] = "{$key}={$value}";
        }

        return implode(';', $cookies);
    }

    /**
     * @param \CurlHandle|resource $handle
     * @param mixed $response
     * 
     * @return array{http_code: int, header: string|false, data: string|false}
     */
    private static function parseResponse($handle, $response) {
        $result = [
            'http_code' => 0,
            'header'    => false,
            'data'      => $response,
        ];

        if ($result['data'] === false) return $result;

        $result['http_code'] = curl_getinfo($handle, CURLINFO_HTTP_CODE);

        $total_size  = strlen($result['data']);
        $header_size = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        // $header_size = strpos($result['data'], "\r\n\r\n");
        // if ($header_size !== false) $header_size += 4;

        $result['header'] = substr($result['data'], 0, $header_size);
        $result['data']   = substr($result['data'], $header_size);

        $header1 = substr(ltrim($result['data']), 0, 5)   === 'HTTP/';
        $header2 = substr(ltrim($result['header']), 0, 5) === 'HTTP/';

        if ($header1 && $header2) {
            // double headers
            $data         = ltrim($result['data']);
            $firstLineEnd = strpos($data, "\r\n");

            if (!$firstLineEnd) {
                $result = [
                    'http_code' => 0,
                    'header'    => false,
                    'data'      => false,
                ];
                return $result;
            }

            $responseLine                = substr($data, 0, $firstLineEnd);
            list(, $result['http_code']) = explode(' ', $responseLine);
            $headerEnd                   = strpos($data, "\r\n\r\n", $firstLineEnd);
            $result['header']            = substr($data, 0, $headerEnd - $firstLineEnd - 2);
            $result['data']              = substr($data, $headerEnd + 4);
        } else if ($total_size === $header_size) {
            $result['data'] = '';
        } else {
            $result['header'] = rtrim($result['header']);

            $pos = strrpos($result['header'], 'HTTP/');

            if ($pos) {
                $result['header'] = substr($result['header'], $pos);

                $firstLineEnd = strpos($result['header'], "\r\n");

                if ($firstLineEnd) {
                    $responseLine                = substr($result['header'], 0, $firstLineEnd);
                    list(, $result['http_code']) = explode(' ', $responseLine);
                }
            }
        }

        $result['http_code'] = (int)$result['http_code'];

        return $result;
    }

    /**
     * @param array<int|string, mixed> $array1
     * @param array<int|string, mixed> $array2
     * 
     * @return array<int|string, mixed>
     */
    private static function dictionary_merge($array1, $array2) {
        foreach ($array2 as $key => $value) {
            $array1[$key] = $value;
        }

        return $array1;
    }
}
