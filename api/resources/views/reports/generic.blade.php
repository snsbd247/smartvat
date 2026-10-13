<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #0f172a; }
        .head { border-bottom: 2px solid #0f1e3d; padding-bottom: 6px; margin-bottom: 10px; }
        .company { font-size: 15px; font-weight: bold; color: #0f1e3d; }
        .title { font-size: 13px; font-weight: bold; margin-top: 4px; }
        .sub, .meta { color: #64748b; font-size: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #cbd5e1; padding: 4px 6px; }
        th { background: #e8edf6; text-align: left; }
        td.right, th.right { text-align: right; }
    </style>
</head>
<body>
    <div class="head">
        <div class="company">{{ $r->meta['company'] ?? config('app.name') }}</div>
        <div class="title">{{ $r->title }}</div>
        @if ($r->subtitle)<div class="sub">{{ $r->subtitle }}</div>@endif
        <div class="meta">Generated: {{ $r->meta['generated_at'] ?? now()->toDayDateTimeString() }}</div>
    </div>

    <table>
        <thead>
            <tr>
                @foreach ($r->columns as $c)
                    <th class="{{ ($c['align'] ?? '') === 'right' ? 'right' : '' }}">{{ $c['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($r->rows as $row)
                <tr>
                    @foreach ($r->columns as $c)
                        <td class="{{ ($c['align'] ?? '') === 'right' ? 'right' : '' }}">{{ $row[$c['key']] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr><td colspan="{{ count($r->columns) }}">No data.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
