<?php

namespace NazariiKretovych\LaravelApiModelDriver;

use DateTime;
use DateTimeZone;
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
     * @return string|false
     */
    public function compileSelect(Builder $query): string
    {
        // Get params.
        $params = $this->config['default_params'] ?? [];
        foreach ($query->wheres as $where) {
            // Get key and strip table name.
            $key = $where['column'];
            $dotIx = strrpos($key, '.');
            if ($dotIx !== false) {
                $key = substr($key, $dotIx + 1);

                // If the key has dot and type = 'Basic', we need to change type to 'In'.
                // This fixes lazy loads.
                if ($where['type'] === 'Basic') {
                    $where['type'] = 'In';
                    $where['values'] = [$where['value']];
                    unset($where['value']);
                }
            }

            // Check where type.
            switch ($where['type']) {
                case 'Basic':
                    switch ($where['operator']) {
                        case '=':
                            $param = $key;
                            break;

                        case '>=':
                            $param = "min_$key";
                            break;

                        case '<=':
                            $param = "max_$key";
                            break;

                        default:
                            throw new RuntimeException('Unsupported query where operator ' . $where['operator']);
                    }
                    $params[$param] = $this->filterKeyValue($key, $where['value']);
                    break;

                case 'In':
                case 'InRaw':
                    $params[$key] = $this->filterKeyValue($key, $where['values']);
                    break;

                case 'between':
                    $params["min_$key"] = $this->filterKeyValue($key, $where['values'][0]);
                    $params["max_$key"] = $this->filterKeyValue($key, $where['values'][1]);
                    break;

                // Ignore the following where types.
                case 'NotNull':
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
            if ($query->limit >= $params['per_page']) {
                throw new RuntimeException('Query limit should be less than ' . $params['per_page']);
            }
            $params['per_page'] = $query->limit;
        }

        $url = "/$query->from";
        if (!empty($params)) {
            $url .= '?';
            $queryStr = Str::httpBuildQuery(
                $params,
                !empty($this->config['pluralize_array_query_params']),
                $this->config['pluralize_except'] ?? [],
            );
            if ($queryStr === false) {
                return false;
            }
            $url .= $queryStr;
        }

        return $url;
    }

    /**
     * @param string $key
     * @param string|array|integer|null $value
     * @return mixed
     */
    private function filterKeyValue($key, $value)
    {
        // Convert timezone.
        $connTimezone = $this->config['timezone'] ?? null;
        if ($connTimezone && in_array($key, $this->config['datetime_keys'])) {
            $connDtZone = new DateTimeZone($connTimezone);
            $appDtZone = new DateTimeZone(config('app.timezone'));
            if (is_string($value)) {
                if (strlen($value) === 19) {
                    $value = (new DateTime($value, $appDtZone))->setTimezone($connDtZone)->format('Y-m-d H:i:s');
                }
            } else if (is_array($value)) {
                $value = array_map(function ($value) use ($connDtZone, $appDtZone) {
                    if (is_string($value) && strlen($value) === 19) {
                        $value = (new DateTime($value, $appDtZone))->setTimezone($connDtZone)->format('Y-m-d H:i:s');
                    }
                    return $value;
                }, $value);
            }
        }

        return $value;
    }
}
