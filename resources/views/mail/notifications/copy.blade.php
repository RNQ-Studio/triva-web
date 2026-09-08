<x-mail::message>
# {{ count($items) > 1 ? count($items).' notifikasi baru' : 'Notifikasi baru' }}

Salinan notifikasi yang dikirim aplikasi {{ $appName }} ke penggunanya.

@if ($isBroadcast)
<x-mail::panel>
**{{ $items[0]['title'] }}**

{{ $items[0]['body'] }}
</x-mail::panel>

<x-mail::table>
| Detail | Nilai |
|:--|:--|
| Tipe | {{ $items[0]['type_label'] }} |
| Waktu | {{ $items[0]['created_at'] }} |
| Jumlah penerima | {{ count($items) }} |
</x-mail::table>

**Penerima**

<x-mail::table>
| Nama | Email | Audiens |
|:--|:--|:--|
@foreach ($items as $item)
| {{ $item['recipient_name'] }} | {{ $item['recipient_email'] }} | {{ $item['audience'] }} |
@endforeach
</x-mail::table>
@else
@foreach ($items as $item)
<x-mail::panel>
**{{ $item['title'] }}**

{{ $item['body'] }}
</x-mail::panel>

<x-mail::table>
| Detail | Nilai |
|:--|:--|
| Tipe | {{ $item['type_label'] }} |
| Penerima | {{ $item['recipient_name'] }} ({{ $item['recipient_email'] }}) |
| Audiens | {{ $item['audience'] }} |
| Waktu | {{ $item['created_at'] }} |
@if ($item['route'])
| Rute aplikasi | {{ $item['route'] }} |
@endif
</x-mail::table>
@endforeach
@endif

Email ini dikirim otomatis oleh {{ $appName }}. Balasan tidak dipantau.
</x-mail::message>
