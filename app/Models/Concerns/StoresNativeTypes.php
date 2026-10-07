<?php

namespace App\Models\Concerns;

/**
 * MongoDB compares by type ("9.5" !== 9.5, "1" !== true), and form/multipart
 * requests send everything as strings. This stores cast attributes as real
 * numbers/booleans and keeps every foreign key (*_id) as a string id, so
 * queries, sums and relationships behave the same as they did on MySQL.
 */
trait StoresNativeTypes
{
    public function setAttribute($key, $value)
    {
        if ($value !== null && $value !== '' && $this->hasCast($key)) {
            $type = $this->getCastType($key);

            if (in_array($type, ['int', 'integer'], true)) {
                $value = (int) $value;
            } elseif (in_array($type, ['real', 'float', 'double', 'decimal'], true)) {
                $value = (float) $value;
            } elseif (in_array($type, ['bool', 'boolean'], true)) {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        if ($value !== null && $key !== '_id' && str_ends_with($key, '_id') && is_scalar($value)) {
            $value = (string) $value;
        }

        return parent::setAttribute($key, $value);
    }
}
