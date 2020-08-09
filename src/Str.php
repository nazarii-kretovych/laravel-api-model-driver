<?php

namespace NazariiKretovych\LaravelApiModelDriver;

use RuntimeException;

class Str
{
    /**
     * @param array $params
     * @param bool $pluralizeArrQueryParams
     * @param string[] $pluralizeExcept
     * @return string
     */
    public static function httpBuildQuery(array $params, $pluralizeArrQueryParams = false, array $pluralizeExcept = [])
    {
		$query = '';
		$paramIx = 0;
		foreach ($params as $key => $values) {
			if (is_array($values)) {
				if ($pluralizeArrQueryParams && !in_array($key, $pluralizeExcept)) {
					$key .= self::endsWith($key, 's') ? 'es' : 's';
				}
				$key .= '[]';
			} else {
				$values = [$values];
			}

			foreach ($values as $value) {
				if ($paramIx++) {
					$query .= '&';
				}

				$query .= "$key=";
				if (!is_string($value) && !is_integer($value)) {
					throw new RuntimeException('Value should be a string or an integer');
				}
				$query .= urlencode($value);
			}
		}

		return $query;
	}

    /**
     * @param string $query
     * @return array
     */
    public static function parseQuery($query)
    {
        $params = [];
        foreach (explode('&', $query) as $queryPart) {
            $parts = explode('=', $queryPart);
            if (count($parts) === 2) {
                $key = self::endsWith($parts[0], '[]') ? substr($parts[0], 0, -2) : $parts[0];
                $params[$key] = urldecode($parts[1]);
            }
        }

        return $params;
    }

    /**
     * @param string $haystack
     * @param string $needle
     * @return boolean
     */
    public static function endsWith($haystack, $needle)
    {
        $length = strlen($needle);
        if ($length) {
            return (substr($haystack, -$length) === $needle);
        }

        return false;
    }
}