<div class="report-header">
    <table style="width: 100%; border-bottom: 2px solid #3B82F6; padding-bottom: 12px; margin-bottom: 20px;">
        <tr>
            <td style="vertical-align: top;">
                <h1 style="margin: 0; color: #1E3A8A; font-size: 20px; font-weight: bold;">{{ $title ?? 'Analytics Report' }}</h1>
                <p style="margin: 4px 0 0 0; color: #4B5563; font-size: 11px;">
                    <strong>Team:</strong> {{ $report['team']['name'] ?? 'N/A' }} 
                    @if(!empty($report['team']['manager']['name']))
                        | <strong>Manager:</strong> {{ $report['team']['manager']['name'] }}
                    @endif
                </p>
                <p style="margin: 2px 0 0 0; color: #6B7280; font-size: 10px;">
                    <strong>Period:</strong> {{ $report['period']['from'] ?? '' }} &mdash; {{ $report['period']['to'] ?? '' }}
                </p>
            </td>
            <td style="vertical-align: top; text-align: right;">
                <div style="font-size: 13px; font-weight: bold; color: #3B82F6;">No-Code Workflow Platform</div>
                <div style="font-size: 9px; color: #9CA3AF; margin-top: 4px;">Generated: {{ now()->toDateTimeString() }}</div>
            </td>
        </tr>
    </table>
</div>
