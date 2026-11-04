<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Provider</x-slot>
        @if ($provider->isConfigured())
            <p>Active provider: <strong>{{ $provider->name() }}</strong>, model <strong>{{ $model }}</strong>.</p>
        @else
            <p>No AI provider is configured, so the assistant is replying with its setup notice. See docs/AI_KNOWLEDGE_BASE.md.</p>
        @endif
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Outcomes in the last 30 days</x-slot>
        @forelse ($statuses as $status => $total)
            <p>{{ str_replace('_', ' ', $status) }}: <strong>{{ number_format($total) }}</strong></p>
        @empty
            <p>No AI requests yet.</p>
        @endforelse
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Daily usage</x-slot>
        @if ($days->isEmpty())
            <p>No usage to show yet.</p>
        @else
            <table style="width:100%;text-align:left">
                <thead><tr><th>Day</th><th>Responses</th><th>Input tokens</th><th>Output tokens</th><th>Average latency (ms)</th></tr></thead>
                <tbody>
                    @foreach ($days as $d)
                        <tr>
                            <td>{{ $d->day }}</td>
                            <td>{{ number_format($d->answers) }}</td>
                            <td>{{ number_format($d->input_tokens) }}</td>
                            <td>{{ number_format($d->output_tokens) }}</td>
                            <td>{{ number_format($d->latency) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-panels::page>
