<?php

namespace Modules\Team\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HistoricalDataGuardService
{
    /**
     * Return blocking historical data references for a user.
     */
    public function findBlockingRecords(int $tenantId, int $userId): array
    {
        $specs = (array) config('team.deletion_guard.history_tables', []);
        $blocking = [];

        foreach ($specs as $spec) {
            $table = (string) ($spec['table'] ?? '');
            $userColumn = (string) ($spec['user_column'] ?? '');
            $label = (string) ($spec['label'] ?? $table);

            if ($table === '' || $userColumn === '') {
                continue;
            }

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $userColumn)) {
                continue;
            }

            $query = DB::table($table)->where($userColumn, $userId);

            $tenantColumn = $spec['tenant_column'] ?? null;
            if ($tenantColumn && Schema::hasColumn($table, $tenantColumn)) {
                $query->where($tenantColumn, $tenantId);
            }

            $statusColumn = $spec['status_column'] ?? null;
            $historyStatuses = (array) ($spec['history_statuses'] ?? []);
            if ($statusColumn && $historyStatuses !== [] && Schema::hasColumn($table, $statusColumn)) {
                $query->whereIn($statusColumn, $historyStatuses);
            }

            $count = (int) $query->count();

            if ($count > 0) {
                $blocking[] = [
                    'table' => $table,
                    'label' => $label,
                    'count' => $count,
                    'message' => "{$count} record(s) found in {$label}.",
                ];
            }
        }

        return $blocking;
    }
}
