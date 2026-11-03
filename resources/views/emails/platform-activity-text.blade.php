ZionFood — {{ $title }}
{{ $occurredAt }} · Europe/Bucharest

@foreach ($fields as $key => $value)
@if (filled($value))
{{ __('platform_activity.fields.'.$key) }}: {!! $key === 'account_kind' ? __('platform_activity.'.$value) : $value !!}
@endif
@endforeach

{{ __('platform_activity.open_admin') }}: {!! $adminUrl !!}

{{ __('platform_activity.private_notice') }}
{{ __('platform_activity.reference') }}: {{ $activityReference }}
