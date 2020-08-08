<?php

namespace NazariiKretovych\LaravelApiModelDriver;

use Illuminate\Database\Connection as ConnectionBase;
use Illuminate\Database\Grammar as GrammerBase;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class Connection extends ConnectionBase
{
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
            $max_per_page = $this->getConfig('default_params')['per_page'];

            // Get full URL.
            $fullUrl = $this->getDatabaseName() . $query;

            // If the full URL is too long, we need to split it.
            if (strlen($fullUrl) > 5000) {
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
                    foreach ($json['data'] as $row) {
                        $allRows[] = $row;
                    }
                    $page = $json['current_page'];
                    while (count($json['data']) === $max_per_page) {
                        $page++;
                        $nextUrl = preg_replace('#(\?|&)page=\d+#', "$1page=$page", $url, 1);
                        if ($nextUrl !== $url) {
                            if (strpos($nextUrl, '?') !== false) {
                                $nextUrl .= '&';
                            } else {
                                $nextUrl .= '?';
                            }
                            $nextUrl .= "page=$page";
                        }
                        $json = $this->getJsonByUrl($nextUrl);
                        foreach ($json['data'] as $row) {
                            $allRows[] = $row;
                        }
                    }
                } else {
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
        return Http::withHeaders($this->getConfig('headers') ?: [])
            ->get($url)
            ->throw()
            ->json();
    }
}