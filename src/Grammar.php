<?php

namespace NazariiKretovych\LaravelApiModelDriver;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar as GrammarBase;
use RuntimeException;

class Grammar extends GrammarBase
{
    private $config = [];

    /**
     * @param array $config
     * @return Grammar
     */
    public function setConfig(array $config): self
    {
        $this->config = $config;

        return $this;
    }

    /**
     * @param Builder $query
     * @return string
     */
    public function compileSelect(Builder $query): string
    {
        // Get params.
        $params = $this->config['default_params'] ?? [];
        foreach ($query->wheres as $where) {
            $key = $where['column'];
            switch ($where['type']) {
                case 'Basic':
                    $params[$key] = $where['value'];
                    break;

                case 'In':
                    $params[$key] = $where['values'];
                    break;

                // Support Laravel relationships.
                case 'InRaw':
                    $dotIx = strrpos($key, '.');
                    if ($dotIx !== false) {
                        $key = substr($key, $dotIx + 1);
                    }
                    $params[$key] = $where['values'];
                    break;

                case 'between':
                    $params["min_$key"] = $where['values'][0];
                    $params["max_$key"] = $where['values'][1];
                    break;

                default:
                    throw new RuntimeException('Unsupported query where type ' . $where['type']);
            }
        }
        if (!empty($query->orders)) {
            if (count($query->orders) > 1) {
                throw new RuntimeException('API query does not support multiple orders');
            }
            foreach ($query->orders as $order) {
                $params['order_by'] = $order['column'];
                if ($order['direction'] === 'desc') {
                    $params['sort'] = 'desc';
                } else {
                    unset($params['sort']);
                }
            }
        }
        if ($query->limit) {
            $params['per_page'] = $query->limit;
        }

        $url = "/$query->from";
        if (!empty($params)) {
            $url .= '?';
            $url .= Str::httpBuildQuery(
                $params,
                !empty($this->config['pluralize_array_query_params']),
                $this->config['pluralize_except'] ?? [],
            );
        }

        return $url;
    }
}
