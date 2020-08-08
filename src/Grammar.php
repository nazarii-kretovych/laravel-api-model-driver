<?php

namespace NazariiKretovych\LaravelApiModelDriver;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar as GrammarBase;

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
        if ($query->limit) {
            $params['per_page'] = $query->limit;
        }
        foreach ($query->wheres as $where) {
            switch ($where['type']) {
                case 'In':
                    $params[$where['column']] = $where['values'];
                    break;

                default:
                    $params[$where['column']] = $where['value'];
                    break;
            }
            $this->addWhereClause($where, $params);
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
