<?php

namespace Modules\Team\Services;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActiveTaskReassignmentService
{
    /**
     * Reassign active tasks from one user to another.
     */
    public function reassign(int $tenantId, int $fromUserId, int $toUserId): array
    {
        $specs = (array) config('team.task_reassignment.tables', []);
        $reassignedTotal = 0;
        $processed = [];
        $errors = [];

        foreach ($specs as $spec) {
            $table = (string) ($spec['table'] ?? '');
            $assigneeColumn = (string) ($spec['assignee_column'] ?? '');

            if ($table === '' || $assigneeColumn === '') {
                continue;
            }

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $assigneeColumn)) {
                continue;
            }

            try {
                $query = DB::table($table)->where($assigneeColumn, $fromUserId);

                $tenantColumn = $spec['tenant_column'] ?? null;
                if ($tenantColumn && Schema::hasColumn($table, $tenantColumn)) {
                    $query->where($tenantColumn, $tenantId);
                }

                $statusColumn = $spec['status_column'] ?? null;
                $activeStatuses = (array) ($spec['active_statuses'] ?? []);
                if ($statusColumn && $activeStatuses !== [] && Schema::hasColumn($table, $statusColumn)) {
                    $query->whereIn($statusColumn, $activeStatuses);
                }

                $affected = $query->update([$assigneeColumn => $toUserId]);

                if ($affected > 0) {
                    $processed[] = ['table' => $table, 'count' => $affected];
                }

                $reassignedTotal += $affected;
            } catch (QueryException $exception) {
                $errors[] = [
                    'table' => $table,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        return [
            'reassigned_total' => $reassignedTotal,
            'processed_tables' => $processed,
            'errors' => $errors,
        ];
    }
}
