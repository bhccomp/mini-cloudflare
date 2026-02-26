<div class="space-y-3 text-sm">
    <div class="grid gap-2">
        <div><strong>Time:</strong> {{ \Illuminate\Support\Carbon::parse($record['timestamp'] ?? now())->toDateTimeString() }}</div>
        <div><strong>Country:</strong> {{ $record['country'] ?? '??' }}</div>
        <div><strong>IP:</strong> {{ $record['ip'] ?? '-' }}</div>
        <div><strong>Method:</strong> {{ $record['method'] ?? 'GET' }}</div>
        <div><strong>Path:</strong> {{ $record['path'] ?? '/' }}</div>
        <div><strong>Action:</strong> {{ $record['action'] ?? 'ALLOW' }}</div>
        <div><strong>Rule:</strong> {{ $record['rule'] ?? 'n/a' }}</div>
        <div><strong>Status:</strong> {{ $record['status_code'] ?? 0 }}</div>
        <div><strong>User Agent:</strong> {{ $record['user_agent'] ?? 'n/a' }}</div>
    </div>
</div>
