<!doctype html>
<html lang="{{ app()->getLocale() }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $title }}</title></head>
<body style="margin:0;background:#f5f6fa;color:#24312c;font-family:Arial,sans-serif">
<table role="presentation" style="width:100%;padding:32px 16px"><tr><td align="center">
<table role="presentation" style="max-width:600px;width:100%;background:white;border:1px solid #e5e8ef;border-radius:20px;padding:28px">
<tr><td><img src="{{ rtrim(config('app.url'), '/') }}/images/logo/food.zion3d.ro_icons-128x128.png" width="48" height="48" alt="" style="display:block;width:48px;height:48px;margin:0 0 12px;border:0">
<p style="color:#635bff;font-weight:bold;margin:0 0 24px">ZionFood · {{ __('platform_activity.admin') }}</p>
<h1 style="font-size:24px;line-height:1.3;margin:0 0 12px">{{ $title }}</h1>
<p style="font-size:13px;color:#65746c">{{ $occurredAt }} · Europe/Bucharest</p>
<table style="width:100%;border-collapse:collapse;font-size:14px">
@foreach ($fields as $key => $value)
@if (filled($value))
<tr><th scope="row" style="text-align:left;vertical-align:top;width:34%;padding:12px 8px 12px 0;border-bottom:1px solid #edf0f4;color:#65746c;font-weight:normal">{{ __('platform_activity.fields.'.$key) }}</th>
<td style="padding:12px 0;border-bottom:1px solid #edf0f4;overflow-wrap:anywhere">{{ $key === 'account_kind' ? __('platform_activity.'.$value) : $value }}</td></tr>
@endif
@endforeach
</table>
<p style="margin:28px 0"><a href="{{ $adminUrl }}" style="display:inline-block;padding:14px 20px;border-radius:10px;color:white;background:#635bff;text-decoration:none;font-weight:bold">{{ __('platform_activity.open_admin') }}</a></p>
<p style="font-size:12px;line-height:1.6;color:#65746c">{{ __('platform_activity.private_notice') }}</p>
<p style="font-size:11px;color:#7b857f">{{ __('platform_activity.reference') }}: {{ $activityReference }}</p>
</td></tr></table></td></tr></table>
</body></html>
