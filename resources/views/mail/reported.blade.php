{{-- Keep Markdown mail content flush-left; four-space indentation renders as escaped code. --}}
<x-mail::message>
    {{-- format-ignore-start --}}
# {{ $safeReport['source'] }}

{{ __('capell-exception-reports::mail.intro', ['app' => $safeReport['summary']['app']]) }}

@if ($safeReport['unsafe']['detected'])
<x-mail::panel>
<strong>{{ __('capell-exception-reports::mail.unsafe_warning') }}</strong>
<br />
{{ __('capell-exception-reports::mail.affected_fields', ['fields' => implode(', ', $safeReport['unsafe']['paths'])]) }}
</x-mail::panel>
@endif

@if (($safeReport['digest']['count'] ?? 'n/a') !== 'n/a')
<x-mail::panel>
<strong>{{ __('capell-exception-reports::mail.digest') }}</strong>
<br />
{{ __('capell-exception-reports::mail.digest_summary', [
    'count' => $safeReport['digest']['count'],
    'window' => $safeReport['digest']['window_seconds'] ?? 'n/a',
]) }}
<br />
<strong>{{ __('capell-exception-reports::mail.digest_threshold') }}:</strong>
{{ $safeReport['digest']['threshold'] ?? 'n/a' }}
<br />
<strong>{{ __('capell-exception-reports::mail.digest_grouped_at') }}:</strong>
{{ $safeReport['digest']['grouped_at'] ?? 'n/a' }}
</x-mail::panel>
@endif

<x-mail::panel>
<strong>{{ __('capell-exception-reports::mail.environment') }}:</strong>
{{ $safeReport['summary']['environment'] }}
<br />
<strong>{{ __('capell-exception-reports::mail.exception') }}:</strong>
{{ $safeReport['summary']['exception'] }}
<br />
<strong>{{ __('capell-exception-reports::mail.message') }}:</strong>
{{ $safeReport['summary']['message'] }}
<br />
<strong>{{ __('capell-exception-reports::mail.location') }}:</strong>
{{ $safeReport['summary']['file'] }}:{{ $safeReport['summary']['line'] }}
<br />
<strong>{{ __('capell-exception-reports::mail.reported_at') }}:</strong>
{{ $safeReport['summary']['reported_at'] }}
</x-mail::panel>

@php
    $present = static fn (array $rows): array => array_filter(
        $rows,
        static fn (mixed $value): bool => is_scalar($value) && trim((string) $value) !== '' && trim((string) $value) !== 'n/a',
    );
    $headingStyle = 'margin: 32px 0 12px; font-size: 18px; line-height: 1.3;';
    $tableStyle = 'width: 100%; border-collapse: collapse; margin: 0 0 8px;';
    $labelStyle = 'width: 30%; padding: 8px 10px 8px 0; text-align: left; vertical-align: top;';
    $valueStyle = 'padding: 8px 0; vertical-align: top; word-break: break-word; overflow-wrap: anywhere;';
    $blockStyle = 'white-space: pre-wrap; word-break: break-word; overflow-wrap: anywhere; font-size: 12px; line-height: 1.45; margin: 0 0 8px;';
    $sections = [
        __('capell-exception-reports::mail.request') => $present([
            __('capell-exception-reports::mail.method') => $safeReport['request']['method'] ?? null,
            __('capell-exception-reports::mail.url') => $safeReport['request']['url'] ?? null,
            __('capell-exception-reports::mail.path') => $safeReport['request']['path'] ?? null,
            __('capell-exception-reports::mail.route') => $safeReport['request']['route_name'] ?? null,
            __('capell-exception-reports::mail.action') => $safeReport['request']['route_action'] ?? null,
            __('capell-exception-reports::mail.ip_address') => $safeReport['request']['ip_address'] ?? null,
            __('capell-exception-reports::mail.accept') => $safeReport['request']['accept'] ?? null,
            __('capell-exception-reports::mail.request_id') => $safeReport['request']['request_id'] ?? null,
        ]),
        __('capell-exception-reports::mail.user') => $present([
            __('capell-exception-reports::mail.id') => $safeReport['user']['id'] ?? null,
            __('capell-exception-reports::mail.name') => $safeReport['user']['name'] ?? null,
            __('capell-exception-reports::mail.email') => $safeReport['user']['email'] ?? null,
        ]),
    ];
    $routeParameters = $safeReport['request']['route_parameters'] ?? [];
    $consoleRows = $present([
        __('capell-exception-reports::mail.command') => $safeReport['console']['command'] ?? null,
        __('capell-exception-reports::mail.arguments') => $safeReport['console']['arguments'] ?? null,
        __('capell-exception-reports::mail.command_line') => $safeReport['console']['command_line'] ?? null,
    ]);
    $trace = trim((string) ($safeReport['trace'] ?? ''));
@endphp

@foreach ($sections as $title => $rows)
@if ($rows !== [])
<h2 style="{{ $headingStyle }}">{{ $title }}</h2>

<table style="{{ $tableStyle }}">
<tbody>
@foreach ($rows as $label => $value)
<tr>
<th style="{{ $labelStyle }}">{{ $label }}</th>
<td style="{{ $valueStyle }}">{{ $value }}</td>
</tr>
@endforeach
</tbody>
</table>
@endif
@endforeach

@if ($routeParameters !== [])
<h2 style="{{ $headingStyle }}">{{ __('capell-exception-reports::mail.route_parameters') }}</h2>

<pre style="{{ $blockStyle }}">{{ json_encode($routeParameters, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
@endif

@if ($consoleRows !== [])
<h2 style="{{ $headingStyle }}">{{ __('capell-exception-reports::mail.console') }}</h2>

<table style="{{ $tableStyle }}">
<tbody>
@foreach ($consoleRows as $label => $value)
<tr>
<th style="{{ $labelStyle }}">{{ $label }}</th>
<td style="{{ $valueStyle }}">{{ $value }}</td>
</tr>
@endforeach
</tbody>
</table>
@endif

@if ($trace !== '')
<h2 style="{{ $headingStyle }}">{{ __('capell-exception-reports::mail.stack_trace') }}</h2>

<pre style="{{ $blockStyle }}">{{ $trace }}</pre>
@endif

Thanks,
<br />
{{ config('app.name') }}
{{-- format-ignore-end --}}
</x-mail::message>
