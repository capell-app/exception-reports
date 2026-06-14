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

## {{ __('capell-exception-reports::mail.request') }}

<table style="width: 100%; border-collapse: collapse">
<tbody>
@foreach ([
    __('capell-exception-reports::mail.method') => $safeReport['request']['method'] ?? 'n/a',
    __('capell-exception-reports::mail.url') => $safeReport['request']['url'] ?? 'n/a',
    __('capell-exception-reports::mail.route') => $safeReport['request']['route_name'] ?? 'n/a',
    __('capell-exception-reports::mail.action') => $safeReport['request']['route_action'] ?? 'n/a',
    __('capell-exception-reports::mail.referer') => $safeReport['request']['referer'] ?? 'n/a',
    __('capell-exception-reports::mail.browser') => $safeReport['request']['browser'] ?? 'n/a',
    __('capell-exception-reports::mail.ip_address') => $safeReport['request']['ip_address'] ?? 'n/a',
    __('capell-exception-reports::mail.accept') => $safeReport['request']['accept'] ?? 'n/a',
    __('capell-exception-reports::mail.request_id') => $safeReport['request']['request_id'] ?? 'n/a',
] as $label => $value)
<tr>
<th
style="
width: 30%;
padding: 8px 10px 8px 0;
text-align: left;
vertical-align: top;
"
>
{{ $label }}
</th>
<td
style="
padding: 8px 0;
vertical-align: top;
word-break: break-word;
overflow-wrap: anywhere;
"
>
{{ $value }}
</td>
</tr>
@endforeach
</tbody>
</table>

## {{ __('capell-exception-reports::mail.user') }}

<table style="width: 100%; border-collapse: collapse">
<tbody>
@foreach ([
    __('capell-exception-reports::mail.id') => $safeReport['user']['id'] ?? 'guest',
    __('capell-exception-reports::mail.name') => $safeReport['user']['name'] ?? 'n/a',
    __('capell-exception-reports::mail.email') => $safeReport['user']['email'] ?? 'n/a',
] as $label => $value)
<tr>
<th
style="
width: 30%;
padding: 8px 10px 8px 0;
text-align: left;
vertical-align: top;
"
>
{{ $label }}
</th>
<td
style="
padding: 8px 0;
vertical-align: top;
word-break: break-word;
overflow-wrap: anywhere;
"
>
{{ $value }}
</td>
</tr>
@endforeach
</tbody>
</table>

@if ($safeReport['request']['route_parameters'] !== [])
## {{ __('capell-exception-reports::mail.route_parameters') }}

<pre
style="
white-space: pre-wrap;
word-break: break-word;
overflow-wrap: anywhere;
font-size: 12px;
line-height: 1.45;
"
>
{{ json_encode($safeReport['request']['route_parameters'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
@endif

@if (($safeReport['console']['command'] ?? 'n/a') !== 'n/a' || ($safeReport['console']['command_line'] ?? 'n/a') !== 'n/a')
## {{ __('capell-exception-reports::mail.console') }}

<table style="width: 100%; border-collapse: collapse">
<tbody>
@foreach ([
    __('capell-exception-reports::mail.command') => $safeReport['console']['command'] ?? 'n/a',
    __('capell-exception-reports::mail.arguments') => $safeReport['console']['arguments'] ?? 'n/a',
    __('capell-exception-reports::mail.command_line') => $safeReport['console']['command_line'] ?? 'n/a',
] as $label => $value)
<tr>
<th
style="
width: 30%;
padding: 8px 10px 8px 0;
text-align: left;
vertical-align: top;
"
>
{{ $label }}
</th>
<td
style="
padding: 8px 0;
vertical-align: top;
word-break: break-word;
overflow-wrap: anywhere;
"
>
{{ $value }}
</td>
</tr>
@endforeach
</tbody>
</table>
@endif

## {{ __('capell-exception-reports::mail.stack_trace') }}

<pre
style="
white-space: pre-wrap;
word-break: break-word;
overflow-wrap: anywhere;
font-size: 12px;
line-height: 1.45;
"
>
{{ $safeReport['trace'] }}</pre>

Thanks,
<br />
{{ config('app.name') }}
{{-- format-ignore-end --}}
</x-mail::message>
