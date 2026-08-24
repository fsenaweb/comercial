@foreach ($rows as $row)
    <tr>
        @foreach ($headers as $header)
            <td>{{ $row[$header['key']] ?? '' }}</td>
        @endforeach
    </tr>
@endforeach
