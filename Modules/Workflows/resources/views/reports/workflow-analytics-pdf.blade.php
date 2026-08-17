<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Workflow Analytics Report</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1F2937;
            font-size: 10px;
            line-height: 1.3;
            margin: 0;
            padding: 10px;
        }
        .kpi-grid {
            width: 100%;
            margin-bottom: 15px;
        }
        .kpi-card {
            background-color: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            padding: 8px;
            text-align: center;
        }
        .kpi-value {
            font-size: 16px;
            font-weight: bold;
            color: #1E3A8A;
            margin-bottom: 2px;
        }
        .kpi-label {
            font-size: 8px;
            text-transform: uppercase;
            color: #6B7280;
            font-weight: 600;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 9px;
        }
        table.data-table th {
            background-color: #F3F4F6;
            color: #374151;
            font-weight: bold;
            text-align: left;
            padding: 6px 5px;
            border-bottom: 2px solid #D1D5DB;
        }
        table.data-table td {
            padding: 5px;
            border-bottom: 1px solid #E5E7EB;
        }
        table.data-table tr:nth-child(even) {
            background-color: #F9FAFB;
        }
        .badge {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: 600;
        }
        .badge-success { background-color: #D1FAE5; color: #065F46; }
        .badge-warning { background-color: #FEF3C7; color: #92400E; }
        .badge-danger { background-color: #FEE2E2; color: #991B1B; }
        .section-title {
            font-size: 12px;
            font-weight: bold;
            color: #111827;
            margin-top: 12px;
            margin-bottom: 6px;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 3px;
        }
    </style>
</head>
<body>
    @include('workflows::reports.partials.header', [
        'title' => 'Manager Workflow Analytics Report',
        'report' => $report
    ])

    <!-- KPI Summary Grid -->
    <table class="kpi-grid" style="width: 100%;">
        <tr>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value">{{ $report['summary']['total_workflows'] ?? 0 }}</div>
                    <div class="kpi-label">Team Workflows</div>
                </div>
            </td>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value">{{ $report['summary']['total_executions'] ?? 0 }}</div>
                    <div class="kpi-label">Total Runs</div>
                </div>
            </td>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #059669;">{{ $report['summary']['successful_executions'] ?? 0 }}</div>
                    <div class="kpi-label">Success Runs</div>
                </div>
            </td>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #DC2626;">{{ $report['summary']['failed_executions'] ?? 0 }}</div>
                    <div class="kpi-label">Failed Runs</div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #2563EB;">{{ $report['summary']['success_rate'] ?? 0 }}%</div>
                    <div class="kpi-label">Success Rate</div>
                </div>
            </td>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #DC2626;">{{ $report['summary']['failure_rate'] ?? 0 }}%</div>
                    <div class="kpi-label">Failure Rate</div>
                </div>
            </td>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #D97706;">{{ $report['summary']['running_executions'] ?? 0 }}</div>
                    <div class="kpi-label">Active / Running</div>
                </div>
            </td>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value">{{ $report['summary']['avg_duration_formatted'] ?? '0s' }}</div>
                    <div class="kpi-label">Avg Execution Time</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Workflows Overview Table -->
    <div class="section-title">Workflow Execution Breakdown</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 25%;">Workflow Name</th>
                <th style="width: 10%; text-align: center;">Status</th>
                <th style="width: 8%; text-align: center;">Version</th>
                <th style="width: 10%; text-align: center;">Total Runs</th>
                <th style="width: 10%; text-align: center;">Success</th>
                <th style="width: 10%; text-align: center;">Failed</th>
                <th style="width: 12%; text-align: center;">Success Rate</th>
                <th style="width: 10%; text-align: center;">Avg Duration</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['workflows'] ?? [] as $index => $wf)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td><strong>{{ $wf['name'] }}</strong></td>
                    <td style="text-align: center;">
                        <span class="badge {{ $wf['status'] === 'active' ? 'badge-success' : 'badge-warning' }}">
                            {{ ucfirst($wf['status']) }}
                        </span>
                    </td>
                    <td style="text-align: center;">v{{ $wf['version'] }}</td>
                    <td style="text-align: center; font-weight: bold;">{{ $wf['total_runs'] }}</td>
                    <td style="text-align: center; color: #059669;">{{ $wf['success_runs'] }}</td>
                    <td style="text-align: center; color: #DC2626;">{{ $wf['failed_runs'] }}</td>
                    <td style="text-align: center;">
                        <span class="badge {{ $wf['success_rate'] >= 90 ? 'badge-success' : ($wf['success_rate'] >= 70 ? 'badge-warning' : 'badge-danger') }}">
                            {{ $wf['success_rate'] }}%
                        </span>
                    </td>
                    <td style="text-align: center;">{{ $wf['avg_duration_formatted'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; color: #9CA3AF; padding: 10px;">No workflows found for this team.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Bottleneck Nodes Section -->
    @if(!empty($report['bottlenecks']))
        <div class="section-title" style="margin-top: 15px;">Bottleneck Nodes & Slowest Operations</div>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Workflow</th>
                    <th style="width: 20%;">Node Key</th>
                    <th style="width: 20%;">Node Type</th>
                    <th style="width: 10%; text-align: center;">Runs</th>
                    <th style="width: 13%; text-align: center;">Avg Duration</th>
                    <th style="width: 12%; text-align: center;">Failures</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['bottlenecks'] as $bn)
                    <tr>
                        <td><strong>{{ $bn['workflow_name'] }}</strong></td>
                        <td><code>{{ $bn['node_key'] }}</code></td>
                        <td>{{ $bn['node_type'] }}</td>
                        <td style="text-align: center;">{{ $bn['execution_count'] }}</td>
                        <td style="text-align: center; color: #D97706; font-weight: bold;">{{ $bn['avg_duration_seconds'] }}s</td>
                        <td style="text-align: center; color: {{ $bn['failure_count'] > 0 ? '#DC2626' : '#6B7280' }};">
                            {{ $bn['failure_count'] }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @include('workflows::reports.partials.footer')
</body>
</html>
