<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use stdClass;

final class SingleQueryPaginator
{
    public static function preferredPerPage(int $default = 10): int
    {
        if (! app()->bound('request')) {
            return $default;
        }
        $value = request()->attributes->get('fleet.per_page');
        if (is_int($value) && in_array($value, [10, 20, 50, 100], true)) {
            return $value;
        }

        return $default;
    }

    /** @return LengthAwarePaginator<int, stdClass> */
    public static function paginate(Builder $query, ?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= self::preferredPerPage(10);
        $page = LengthAwarePaginator::resolveCurrentPage();
        // The scalar count is independent of each result row. Keep filters and
        // authorization in both reads, but send them in one database request.
        $count = $query->cloneWithout(['columns', 'orders', 'limit', 'offset'])
            ->cloneWithoutBindings(['select', 'order'])
            ->selectRaw('COUNT(*) AS aggregate');
        $rows = (clone $query)->selectSub($count, '__total')->forPage($page, $perPage)->get();
        $first = $rows->first();
        if ($first instanceof stdClass) {
            $total = Value::integer($first->__total ?? 0);
        } elseif ($page === 1) {
            $total = 0;
        } else {
            $aggregate = $count->first();
            $total = $aggregate instanceof stdClass ? Value::integer($aggregate->aggregate ?? 0) : 0;
        }
        foreach ($rows as $row) {
            unset($row->__total);
        }

        return (new LengthAwarePaginator($rows, $total, $perPage, $page, [
            'path' => LengthAwarePaginator::resolveCurrentPath(),
            'pageName' => 'page',
        ]))->withQueryString();
    }
}
