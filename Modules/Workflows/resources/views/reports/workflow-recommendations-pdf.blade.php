<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Workflow Recommendations Report</title>
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
            margin-top: 8px;
            margin-bottom: 14px;
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
            vertical-align: top;
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
        .badge-optimal { background-color: #D1FAE5; color: #065F46; }
        .badge-good { background-color: #DBEAFE; color: #1E40AF; }
        .badge-warning { background-color: #FEF3C7; color: #92400E; }
        .badge-critical { background-color: #FEE2E2; color: #991B1B; }
        .section-title {
            font-size: 11px;
            font-weight: bold;
            color: #111827;
            margin-top: 10px;
            margin-bottom: 4px;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 3px;
        }
        .suggestion-box {
            font-style: italic;
            color: #4B5563;
        }
    </style>
</head>
<body>
    @include('workflows::reports.partials.header', [
        'title' => 'Manager Workflow Recommendations & Optimization Report',
        'report' => $report
    ])

    <!-- Health Score & Summary KPI Grid -->
    <table class="kpi-grid" style="width: 100%;">
        <tr>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: {{ ($report['health_score']['score'] ?? 100) >= 75 ? '#059669' : (($report['health_score']['score'] ?? 100) >= 50 ? '#D97706' : '#DC2626') }};">
                        {{ $report['health_score']['score'] ?? 100 }}/100
                    </div>
                    <div class="kpi-label">Health Score ({{ $report['health_score']['grade'] ?? 'Optimal' }})</div>
                </div>
            </td>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value">{{ $report['health_score']['total_recommendations'] ?? 0 }}</div>
                    <div class="kpi-label">Total Action Items</div>
                </div>
            </td>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #DC2626;">{{ $report['health_score']['critical_count'] ?? 0 }}</div>
                    <div class="kpi-label">Critical Issues</div>
                </div>
            </td>
            <td style="width: 25%; padding: 3px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #D97706;">{{ $report['health_score']['warning_count'] ?? 0 }}</div>
                    <div class="kpi-label">Warnings / Opportunities</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- 1. Bottlenecks Section -->
    <div class="section-title">1. Execution Bottlenecks (&gt; 50% Above Workflow Median)</div>
    @if(empty($report['recommendations']['bottlenecks']))
        <p style="color: #059669; font-size: 9px;">✓ No execution bottlenecks detected across workflow steps.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Workflow</th>
                    <th style="width: 15%;">Node</th>
                    <th style="width: 12%;">Avg Time (Median)</th>
                    <th style="width: 10%;">Excess %</th>
                    <th style="width: 8%;">Severity</th>
                    <th style="width: 35%;">Actionable Recommendation</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['recommendations']['bottlenecks'] as $bn)
                    <tr>
                        <td><strong>{{ $bn['workflow_name'] }}</strong></td>
                        <td>{{ $bn['node_label'] }} (<code>{{ $bn['node_key'] }}</code>)</td>
                        <td>{{ $bn['avg_duration_seconds'] }}s (vs {{ $bn['workflow_median_seconds'] }}s)</td>
                        <td>+{{ $bn['excess_percentage'] }}%</td>
                        <td>
                            <span class="badge {{ $bn['severity'] === 'critical' ? 'badge-critical' : 'badge-warning' }}">
                                {{ strtoupper($bn['severity']) }}
                            </span>
                        </td>
                        <td class="suggestion-box">{{ $bn['suggestion'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 2. High Failure Rate Nodes Section -->
    <div class="section-title">2. High-Failure Nodes (&ge; {{ $report['thresholds']['failure_rate_threshold_percent'] ?? 10 }}% Failure Rate)</div>
    @if(empty($report['recommendations']['high_failure_nodes']))
        <p style="color: #059669; font-size: 9px;">✓ All nodes are operating within acceptable reliability thresholds.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Workflow</th>
                    <th style="width: 15%;">Node</th>
                    <th style="width: 12%;">Failures / Total</th>
                    <th style="width: 10%;">Failure Rate</th>
                    <th style="width: 8%;">Severity</th>
                    <th style="width: 35%;">Actionable Recommendation</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['recommendations']['high_failure_nodes'] as $fn)
                    <tr>
                        <td><strong>{{ $fn['workflow_name'] }}</strong></td>
                        <td>{{ $fn['node_label'] }} (<code>{{ $fn['node_key'] }}</code>)</td>
                        <td>{{ $fn['failed_executions'] }} / {{ $fn['total_executions'] }}</td>
                        <td style="color: #DC2626; font-weight: bold;">{{ $fn['failure_rate'] }}%</td>
                        <td>
                            <span class="badge {{ $fn['severity'] === 'critical' ? 'badge-critical' : 'badge-warning' }}">
                                {{ strtoupper($fn['severity']) }}
                            </span>
                        </td>
                        <td class="suggestion-box">{{ $fn['suggestion'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 3. High Cancellation Workflows Section -->
    <div class="section-title">3. High Abandonment / Cancellation Workflows (&ge; {{ $report['thresholds']['cancellation_rate_threshold_percent'] ?? 15 }}% Cancellation Rate)</div>
    @if(empty($report['recommendations']['high_cancellation_workflows']))
        <p style="color: #059669; font-size: 9px;">✓ No workflows exceed the cancellation threshold.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 25%;">Workflow</th>
                    <th style="width: 15%;">Cancelled / Total</th>
                    <th style="width: 12%;">Cancel Rate</th>
                    <th style="width: 12%;">Avg Run Before Cancel</th>
                    <th style="width: 8%;">Severity</th>
                    <th style="width: 28%;">Actionable Recommendation</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['recommendations']['high_cancellation_workflows'] as $cw)
                    <tr>
                        <td><strong>{{ $cw['workflow_name'] }}</strong></td>
                        <td>{{ $cw['cancelled_instances'] }} / {{ $cw['total_instances'] }}</td>
                        <td style="color: #DC2626; font-weight: bold;">{{ $cw['cancellation_rate'] }}%</td>
                        <td>{{ $cw['avg_runtime_before_cancel_seconds'] }}s</td>
                        <td>
                            <span class="badge {{ $cw['severity'] === 'critical' ? 'badge-critical' : 'badge-warning' }}">
                                {{ strtoupper($cw['severity']) }}
                            </span>
                        </td>
                        <td class="suggestion-box">{{ $cw['suggestion'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- 4. SLA Breach Hot Spots Section -->
    <div class="section-title">4. SLA Breach Hot Spots in Human Tasks</div>
    @if(empty($report['recommendations']['sla_breach_hot_spots']))
        <p style="color: #059669; font-size: 9px;">✓ All human tasks and SLA deadlines are currently on schedule.</p>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 20%;">Workflow</th>
                    <th style="width: 15%;">Task Step</th>
                    <th style="width: 12%;">Breaches / Total</th>
                    <th style="width: 10%;">Breach Rate</th>
                    <th style="width: 10%;">Avg Overdue</th>
                    <th style="width: 8%;">Severity</th>
                    <th style="width: 25%;">Actionable Recommendation</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['recommendations']['sla_breach_hot_spots'] as $sb)
                    <tr>
                        <td><strong>{{ $sb['workflow_name'] }}</strong></td>
                        <td>{{ $sb['task_title'] }} (<code>{{ $sb['node_key'] }}</code>)</td>
                        <td>{{ $sb['breached_tasks'] }} / {{ $sb['total_tasks'] }}</td>
                        <td style="color: #DC2626; font-weight: bold;">{{ $sb['breach_rate'] }}%</td>
                        <td>{{ $sb['avg_overdue_hours'] }}h</td>
                        <td>
                            <span class="badge {{ $sb['severity'] === 'critical' ? 'badge-critical' : 'badge-warning' }}">
                                {{ strtoupper($sb['severity']) }}
                            </span>
                        </td>
                        <td class="suggestion-box">{{ $sb['suggestion'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
