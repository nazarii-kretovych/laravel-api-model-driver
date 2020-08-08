<?php

namespace NazariiKretovych\LaravelApiModelDriver;

use GuzzleHttp\Client;
use Illuminate\Database\Connection as ConnectionBase;
use Illuminate\Database\Grammar as GrammerBase;
use function json_decode;

class Connection extends ConnectionBase
{
    /**
     * @return GrammerBase
     */
    protected function getDefaultQueryGrammar()
    {
        $grammar = app(Grammar::class);
        $query = $this->getConfig('query');
        if ($query) {
            $grammar->setDefaultQueryString($query);
        }

        return $this->withTablePrefix($grammar);
    }

    /**
     * @param string $query
     * @param mixed[] $bindings
     * @param bool $useReadPdo
     * @return mixed[]
     */
    public function select($query, $bindings = [], $useReadPdo = true)
    {
        return $this->run($query, $bindings, function ($query) {
            $url = $this->getDatabaseName() . $query;

            /** @var Client $client */
            $client = app(Client::class);
            $json = $this->getResponse($client, $url);

            return $json['data'];
        });
    }

    /**
     * @param Client $client
     * @param string $url
     * @return mixed[]
     */
    private function getResponse(Client $client, string $url): array
    {
        $response = $client->request('GET', $url, [
            'headers' => config('api-model-driver.headers'),
        ]);

        $body = $response->getBody()->getContents();
        $json = json_decode($body, true);

        return $json['data'];
    }
}