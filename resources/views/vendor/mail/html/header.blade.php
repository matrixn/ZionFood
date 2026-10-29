@props(['url'])
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
<img src="{{ rtrim(config('app.url'), '/') }}/images/logo/food.zion3d.ro_icons-128x128.png" width="64" height="64" alt="" style="display: block; width: 64px; height: 64px; margin: 0 auto 8px; border: 0;">
{!! $slot !!}
</a>
</td>
</tr>
