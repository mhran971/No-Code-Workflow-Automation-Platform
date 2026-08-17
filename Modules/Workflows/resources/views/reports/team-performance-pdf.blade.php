<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Team Performance Report</title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #1F2937;
            font-size: 11px;
            line-height: 1.4;
            margin: 0;
            padding: 10px;
        }
        .kpi-grid {
            width: 100%;
            margin-bottom: 20px;
        }
        .kpi-card {
            background-color: #F9FAFB;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            padding: 10px;
            text-align: center;
        }
        .kpi-value {
            font-size: 18px;
            font-weight: bold;
            color: #1E3A8A;
            margin-bottom: 2px;
        }
        .kpi-label {
            font-size: 9px;
            text-transform: uppercase;
            color: #6B7280;
            font-weight: 600;
        }
        table.data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
            font-size: 10px;
        }
        table.data-table th {
            background-color: #F3F4F6;
            color: #374151;
            font-weight: bold;
            text-align: left;
            padding: 8px 6px;
            border-bottom: 2px solid #D1D5DB;
        }
        table.data-table td {
            padding: 7px 6px;
            border-bottom: 1px solid #E5E7EB;
        }
        table.data-table tr:nth-child(even) {
            background-color: #F9FAFB;
        }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 600;
        }
        .badge-success { background-color: #D1FAE5; color: #065F46; }
        .badge-warning { background-color: #FEF3C7; color: #92400E; }
        .badge-danger { background-color: #FEE2E2; color: #991B1B; }
        .badge-info { background-color: #DBEAFE; color: #1E40AF; }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            color: #111827;
            margin-top: 15px;
            margin-bottom: 8px;
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 4px;
        }
    </style>
</head>
<body>
    @include('workflows::reports.partials.header', [
        'title' => 'Manager Team Performance Report',
        'report' => $report
    ])

    <!-- KPI Summary Grid -->
    <table class="kpi-grid" style="width: 100%;">
        <tr>
            <td style="width: 25%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-value">{{ $report['summary']['total_tasks_assigned'] ?? 0 }}</div>
                    <div class="kpi-label">Tasks Assigned</div>
                </div>
            </td>
            <td style="width: 25%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #059669;">{{ $report['summary']['total_tasks_completed'] ?? 0 }}</div>
                    <div class="kpi-label">Tasks Completed</div>
                </div>
            </td>
            <td style="width: 25%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #2563EB;">{{ $report['summary']['completion_rate'] ?? 0 }}%</div>
                    <div class="kpi-label">Completion Rate</div>
                </div>
            </td>
            <td style="width: 25%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #DC2626;">{{ $report['summary']['total_tasks_overdue'] ?? 0 }}</div>
                    <div class="kpi-label">Tasks Overdue</div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width: 25%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-value">{{ $report['summary']['total_members'] ?? 0 }}</div>
                    <div class="kpi-label">Team Members</div>
                </div>
            </td>
            <td style="width: 25%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #D97706;">{{ $report['summary']['total_tasks_open'] ?? 0 }}</div>
                    <div class="kpi-label">In Progress / Open</div>
                </div>
            </td>
            <td style="width: 25%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-value">{{ $report['summary']['avg_turnaround_hours'] ?? 0 }}h</div>
                    <div class="kpi-label">Avg Turnaround Time</div>
                </div>
            </td>
            <td style="width: 25%; padding: 4px;">
                <div class="kpi-card">
                    <div class="kpi-value" style="color: #DC2626;">{{ $report['summary']['overdue_rate'] ?? 0 }}%</div>
                    <div class="kpi-label">Overdue Rate</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Member Performance Breakdown Table -->
    <div class="section-title">Individual Member Performance</div>
    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 25%;">Member</th>
                <th style="width: 10%; text-align: center;">Assigned</th>
                <th style="width: 10%; text-align: center;">Completed</th>
                <th style="width: 10%; text-align: center;">Open</th>
                <th style="width: 10%; text-align: center;">Overdue</th>
                <th style="width: 15%; text-align: center;">Completion Rate</th>
                <th style="width: 15%; text-align: center;">Avg Turnaround</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['members'] ?? [] as $index => $member)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        <strong>{{ $member['name'] }}</strong>
                        <div style="font-size: 8px; color: #6B7280;">{{ $member['email'] }}</div>
                    </td>
                    <td style="text-align: center;">{{ $member['assigned'] }}</td>
                    <td style="text-align: center; color: #059669; font-weight: bold;">{{ $member['completed'] }}</td>
                    <td style="text-align: center; color: #D97706;">{{ $member['open'] }}</td>
                    <td style="text-align: center; color: #DC2626;">{{ $member['overdue'] }}</td>
                    <td style="text-align: center;">
                        <span class="badge {{ $member['completion_rate'] >= 80 ? 'badge-success' : ($member['completion_rate'] >= 50 ? 'badge-warning' : 'badge-danger') }}">
                            {{ $member['completion_rate'] }}%
                        </span>
                    </td>
                    <td style="text-align: center;">{{ $member['avg_turnaround_hours'] }} hrs</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; color: #9CA3AF; padding: 15px;">No member performance data available for this period.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @include('workflows::reports.partials.footer')
</body>
</html>
