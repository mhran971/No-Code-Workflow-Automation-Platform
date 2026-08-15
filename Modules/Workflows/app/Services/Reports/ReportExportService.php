<?php

namespace Modules\Workflows\Services\Reports;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportService
{
    /**
     * Export Team Performance Report as a streamed CSV file.
     *
     * @param  array<string, mixed>  $reportData
     */
    public function exportTeamPerformanceCsv(array $reportData): StreamedResponse
    {
        $filename = 'team-performance-' . ($reportData['period']['from'] ?? 'report') . '-to-' . ($reportData['period']['to'] ?? 'now') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($reportData): void {
            $handle = fopen('php://output', 'w');

            // Write UTF-8 BOM for Excel UTF-8 decoding
            fputs($handle, "\xEF\xBB\xBF");

            // Report Header Metadata
            fputcsv($handle, ['Team Performance Report']);
            fputcsv($handle, ['Team', $reportData['team']['name'] ?? 'N/A']);
            fputcsv($handle, ['Manager', $reportData['team']['manager']['name'] ?? 'N/A']);
            fputcsv($handle, ['Period', ($reportData['period']['from'] ?? '') . ' to ' . ($reportData['period']['to'] ?? '')]);
            fputcsv($handle, []);

            // Summary Section
            fputcsv($handle, ['--- SUMMARY METRICS ---']);
            fputcsv($handle, ['Total Members', $reportData['summary']['total_members'] ?? 0]);
            fputcsv($handle, ['Total Tasks Assigned', $reportData['summary']['total_tasks_assigned'] ?? 0]);
            fputcsv($handle, ['Total Tasks Completed', $reportData['summary']['total_tasks_completed'] ?? 0]);
            fputcsv($handle, ['Total Tasks Open', $reportData['summary']['total_tasks_open'] ?? 0]);
            fputcsv($handle, ['Total Tasks Overdue', $reportData['summary']['total_tasks_overdue'] ?? 0]);
            fputcsv($handle, ['Completion Rate (%)', ($reportData['summary']['completion_rate'] ?? 0) . '%']);
            fputcsv($handle, ['Overdue Rate (%)', ($reportData['summary']['overdue_rate'] ?? 0) . '%']);
            fputcsv($handle, ['Avg Turnaround Time (Hours)', $reportData['summary']['avg_turnaround_hours'] ?? 0]);
            fputcsv($handle, []);

            // Members Table
            fputcsv($handle, ['--- MEMBER PERFORMANCE BREAKDOWN ---']);
            fputcsv($handle, [
                'User ID',
                'Name',
                'Email',
                'Assigned Tasks',
                'Completed Tasks',
                'Open Tasks',
                'Overdue Tasks',
                'Completion Rate (%)',
                'On-Time Rate (%)',
                'Avg Turnaround (Hours)',
            ]);

            foreach ($reportData['members'] ?? [] as $member) {
                fputcsv($handle, [
                    $member['user_id'],
                    $member['name'],
                    $member['email'],
                    $member['assigned'],
                    $member['completed'],
                    $member['open'],
                    $member['overdue'],
                    $member['completion_rate'] . '%',
                    $member['on_time_rate'] . '%',
                    $member['avg_turnaround_hours'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export Team Performance Report as a downloadable PDF document.
     *
     * @param  array<string, mixed>  $reportData
     */
    public function exportTeamPerformancePdf(array $reportData): Response
    {
        $filename = 'team-performance-' . ($reportData['period']['from'] ?? 'report') . '-to-' . ($reportData['period']['to'] ?? 'now') . '.pdf';

        $pdf = Pdf::loadView('workflows::reports.team-performance-pdf', [
            'report' => $reportData,
        ])->setPaper('a4', 'portrait');

        return $pdf->download($filename);
    }

    /**
     * Export Workflow Analytics Report as a streamed CSV file.
     *
     * @param  array<string, mixed>  $reportData
     */
    public function exportWorkflowAnalyticsCsv(array $reportData): StreamedResponse
    {
        $filename = 'workflow-analytics-' . ($reportData['period']['from'] ?? 'report') . '-to-' . ($reportData['period']['to'] ?? 'now') . '.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($reportData): void {
            $handle = fopen('php://output', 'w');

            fputs($handle, "\xEF\xBB\xBF");

            // Report Header Metadata
            fputcsv($handle, ['Workflow Analytics Report']);
            fputcsv($handle, ['Team', $reportData['team']['name'] ?? 'N/A']);
            fputcsv($handle, ['Period', ($reportData['period']['from'] ?? '') . ' to ' . ($reportData['period']['to'] ?? '')]);
            fputcsv($handle, []);

            // Summary Section
            fputcsv($handle, ['--- SUMMARY METRICS ---']);
            fputcsv($handle, ['Total Workflows', $reportData['summary']['total_workflows'] ?? 0]);
            fputcsv($handle, ['Total Executions', $reportData['summary']['total_executions'] ?? 0]);
            fputcsv($handle, ['Successful Executions', $reportData['summary']['successful_executions'] ?? 0]);
            fputcsv($handle, ['Failed Executions', $reportData['summary']['failed_executions'] ?? 0]);
            fputcsv($handle, ['Running/Active Executions', $reportData['summary']['running_executions'] ?? 0]);
            fputcsv($handle, ['Success Rate (%)', ($reportData['summary']['success_rate'] ?? 0) . '%']);
            fputcsv($handle, ['Failure Rate (%)', ($reportData['summary']['failure_rate'] ?? 0) . '%']);
            fputcsv($handle, ['Avg Duration (Seconds)', $reportData['summary']['avg_duration_seconds'] ?? 0]);
            fputcsv($handle, ['Avg Duration (Formatted)', $reportData['summary']['avg_duration_formatted'] ?? '0s']);
            fputcsv($handle, []);

            // Workflows Breakdown
            fputcsv($handle, ['--- WORKFLOW EXECUTION DETAILS ---']);
            fputcsv($handle, [
                'Workflow ID',
                'Name',
                'Status',
                'Version',
                'Total Runs',
                'Success Runs',
                'Failed Runs',
                'Success Rate (%)',
                'Avg Duration (Seconds)',
                'Avg Duration (Formatted)',
                'Last Run At',
            ]);

            foreach ($reportData['workflows'] ?? [] as $wf) {
                fputcsv($handle, [
                    $wf['id'],
                    $wf['name'],
                    $wf['status'],
                    $wf['version'],
                    $wf['total_runs'],
                    $wf['success_runs'],
                    $wf['failed_runs'],
                    $wf['success_rate'] . '%',
                    $wf['avg_duration_seconds'],
                    $wf['avg_duration_formatted'],
                    $wf['last_run_at'] ?? 'Never',
                ]);
            }

            fputcsv($handle, []);

            // Bottlenecks Breakdown
            fputcsv($handle, ['--- BOTTLENECK NODES (SLOWEST NODES) ---']);
            fputcsv($handle, [
                'Workflow ID',
                'Workflow Name',
                'Node Key',
                'Node Type',
                'Execution Count',
                'Avg Duration (Seconds)',
                'Max Duration (Seconds)',
                'Failure Count',
            ]);

            foreach ($reportData['bottlenecks'] ?? [] as $bn) {
                fputcsv($handle, [
                    $bn['workflow_id'] ?? 'N/A',
                    $bn['workflow_name'] ?? 'N/A',
                    $bn['node_key'],
                    $bn['node_type'],
                    $bn['execution_count'],
                    $bn['avg_duration_seconds'],
                    $bn['max_duration_seconds'],
                    $bn['failure_count'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export Workflow Analytics Report as a downloadable PDF document.
     *
     * @param  array<string, mixed>  $reportData
     */
    public function exportWorkflowAnalyticsPdf(array $reportData): Response
    {
        $filename = 'workflow-analytics-' . ($reportData['period']['from'] ?? 'report') . '-to-' . ($reportData['period']['to'] ?? 'now') . '.pdf';

        $pdf = Pdf::loadView('workflows::reports.workflow-analytics-pdf', [
            'report' => $reportData,
        ])->setPaper('a4', 'landscape');

        return $pdf->download($filename);
    }
}
