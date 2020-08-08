<?php

namespace NazariiKretovych\LaravelApiModelDriver;

use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar as GrammarBase;

class Grammar extends GrammarBase
{
    /**
     * @var string[]
     */
    private $defaultQueryString = [];

    /**
     * @return string[]
     */
    public function getDefaultQueryString(): array
    {
        return $this->defaultQueryString;
    }

    /**
     * @param string[] $defaultQueryString
     * @return ApiGrammar
     */
    public function setDefaultQueryString(array $defaultQueryString): self
    {
        $this->defaultQueryString = $defaultQueryString;
        return $this;
    }

    /**
     * @param Builder $query
     * @return string
     */
    public function compileSelect(Builder $query): string
    {
        $queryString = $this->getDefaultQueryString();
        if ($query->limit) {
            $queryString['per_page'] = $query->limit;
        }

        // Add the where clauses to the query string.
        foreach ($query->wheres as $where) {
            $queryString = $this->addWhereClause($where, $queryString);
        }

        if (empty($queryString)) {
            return '/' . $query->from;
        }

        return '/' . $query->from . '?' . http_build_query($queryString);
    }

    /**
     * @param string[] $where
     * @param mixed[] $queryString
     * @return string[]
     */
    private function addWhereClause(array $where, array $queryString): array
    {
        switch ($where['type']) {
            default:
                $queryString[$where['column']] = $where['value'];
                break;

            case 'In':
                $queryString[$where['column']] = $where['values'];
                break;
        }

        return $queryString;
    }
}
