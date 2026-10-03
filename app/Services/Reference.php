<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

final class Reference
{
    /** Human reference like DEED-000123, unique per table/column. */
    public static function next(string $prefix, string $table, string $column = 'reference'): string
    {
        $last = DB::table($table)->where($column, 'like', $prefix.'-%')->orderByDesc('id')->value($column);
        $n = $last ? ((int) substr($last, strlen($prefix) + 1)) + 1 : 1;

        do {
            $ref = sprintf('%s-%06d', $prefix, $n++);
        } while (DB::table($table)->where($column, $ref)->exists());

        return $ref;
    }
}
