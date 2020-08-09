<?php

namespace NazariiKretovych\LaravelApiModelDriver;

use Illuminate\Database\Connection as ConnectionBase;
use Illuminate\Database\Grammar as GrammerBase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class Connection extends ConnectionBase
{
    const AUTH_TYPE_PASSPORT_CLIENT_CREDENTIALS = 'passport_client_credentials';

    /**
     * @return GrammerBase
     */
    protected function getDefaultQueryGrammar()
    {
        $grammar = app(Grammar::class);
        $grammar->setConfig($this->getConfig());

        return $this->withTablePrefix($grammar);
    }

    /**
     * @param string $query E.g. /articles?status=published
     * @param mixed[] $bindings
     * @param bool $useReadPdo
     * @return mixed[]
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query) {
            // Get connection configuration.
            $maxPerPage = $this->getConfig('default_params')['per_page'];
            $maxUrlLength = $this->getConfig('max_url_length') ?: 4000;

            // Get full URL.
            $fullUrl = $this->getDatabaseName() . $query;

            // If the full URL is too long, we need to split it.
            if (strlen($fullUrl) > $maxUrlLength) {
                // Parse query string and get params.
                $questionIx = strpos($fullUrl, '?');
                if ($questionIx === false) {
                    throw new RuntimeException('Long URLs should have query string');
                }
                parse_str(substr($fullUrl, $questionIx + 1), $params);

                // Get key with max. number of values.
                $keyWithMaxCnt = null;
                $maxCnt = 0;
                foreach ($params as $key => $values) {
                    if (is_array($values)) {
                        $cnt = count($values);
                        if ($cnt > $maxCnt) {
                            $keyWithMaxCnt = $key;
                            $maxCnt = $cnt;
                        }
                    }
                }
                if ($keyWithMaxCnt === null) {
                    throw new RuntimeException('Long URLs should have at least one array in query string');
                }

                // Create partial URLs.
                $urls = [];
                foreach (array_chunk($params[$keyWithMaxCnt], 200) as $values) {
                    $params[$keyWithMaxCnt] = $values;
                    $urls[] = substr($fullUrl, 0, $questionIx + 1) . Str::httpBuildQuery($params);
                }
            } else {
                // The full URL is not long, so we don't touch it.
                $urls = [$fullUrl];
            }

            // Get rows for each partial URL.
            $allRows = [];
            foreach ($urls as $url) {
                // Get data.
                $json = $this->getJsonByUrl($url);
                if (isset($json['current_page'])) {
                    // There is pagination. We expect to receive data objects in the 'data' property.
                    foreach ($json['data'] as $row) {
                        $allRows[] = $row;
                    }

                    // If the URL does not have the 'page' parameter, get data from all the pages.
                    if (count($json['data']) >= $maxPerPage && !preg_match('#(\?|&)page=\d+#', $url)) {
                        $page = $json['current_page'];
                        $hasQueryString = (strpos($url, '?') !== false);
                        while (count($json['data']) >= $maxPerPage) {
                            $page++;
                            $nextUrl = $url . ($hasQueryString ? '&' : '?') . "page=$page";
                            $json = $this->getJsonByUrl($nextUrl);
                            foreach ($json['data'] as $row) {
                                $allRows[] = $row;
                            }
                        }
                    }
                } else {
                    // No pagination.
                    foreach ($json as $row) {
                        $allRows[] = $row;
                    }
                }
            }

            return $allRows;
        });
    }
    
    private function getJsonByUrl($url)
    {
        // Get curl handler.
        static $ch = null;
        if ($ch === null) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FAILONERROR, true);
        }

        // Set headers.
        $headers = $this->getConfig('headers') ?: [];
        $auth = $this->getConfig('auth');
        if ($auth && $auth['type'] === self::AUTH_TYPE_PASSPORT_CLIENT_CREDENTIALS) {
            // Try to retrieve the access token from cache.
            $key = 'laravel_api_model_driver|' . $this->getDatabaseName() . '|token';
            $tokenData = Cache::get($key);
            $ts = time();
            if (!$tokenData || $tokenData['expires'] + 3600 > $ts) {
                // Get a new access token.
                curl_setopt($ch, CURLOPT_URL, $auth['url']);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                    'grant_type' => 'client_credentials',
					'client_id' => $auth['client_id'],
					'client_secret' => $auth['client_secret'],
                ]));
                curl_setopt($ch, CURLOPT_HTTPHEADER, []);
                $result = curl_exec($ch);
                if (!$result) {
                    throw new RuntimeException('Failed to get access token from ' . $auth['url']);
                }
                $json = json_decode($result, true);
                $tokenData = [
                    'access_token' => $json['access_token'],
                    'expires' => $ts + $json['expires_in'],
                ];

                // Cache the token.
                Cache::put($key, $tokenData, $json['expires_in']);
            }
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // Set URL.
        curl_setopt($ch, CURLOPT_URL, $url);

        // Call API.
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($ch, CURLOPT_POSTFIELDS, null);
        $result = curl_exec($ch);
        if (!$result) {
            throw new RuntimeException("Failed to call $url");
        }

        return json_decode($result, true);
    }
}