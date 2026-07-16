<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>{{ $report['title'] }}</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        font-family: sans-serif;
        font-size: 11px;
        color: #1a1a1a;
    }
    h1 {
        font-size: 16px;
        margin: 0 0 4px;
        text-align: center;
        text-transform: uppercase;
    }
    .subtitle {
        color: #666;
        margin: 0 0 12px;
        font-size: 10px;
    }
    .letterhead {
        width: 100%;
        border-collapse: collapse;
        border-bottom: 2px solid #000;
        margin-bottom: 14px;
        padding-bottom: 8px;
    }
    .letterhead td {
        border: none;
        padding: 0;
        vertical-align: top;
    }
    .letterhead .logo {
        width: 64px;
        padding-right: 12px;
    }
    .letterhead .logo img {
        width: 56px;
        height: 56px;
        border-radius: 8px;
    }
    .letterhead .name {
        font-size: 14px;
        font-weight: bold;
    }
    .letterhead .line {
        font-size: 10px;
        color: #333;
    }
    .summary {
        margin-bottom: 12px;
    }
    .summary span {
        display: inline-block;
        margin-right: 24px;
        font-size: 11px;
    }
    .summary strong {
        display: block;
        font-size: 13px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
    }
    th, td {
        padding: 5px 7px;
        text-align: left;
    }
    th {
        background: #f0f0f0;
        font-weight: bold;
        border-top: 1px solid #000;
        border-bottom: 1px solid #000;
    }
    @if (! empty($autoPrint))
        @media print {
            @page { size: A4; margin: 15mm; }
        }
    @endif
</style>
</head>
<body>
    <table class="letterhead">
        <tr>
            @if ($letterhead['logo_path'])
                <td class="logo"><img src="{{ $letterhead['logo_path'] }}" alt="Logo" width="56" height="56"></td>
            @endif
            <td>
                <div class="name">{{ $letterhead['display_name'] }}</div>
                @if ($letterhead['corporate_name'])
                    <div class="line">{{ $letterhead['corporate_name'] }}</div>
                @endif
                @if ($letterhead['cnpj'])
                    <div class="line">CNPJ: {{ $letterhead['cnpj'] }}</div>
                @endif
                @if ($letterhead['address_line'])
                    <div class="line">{{ $letterhead['address_line'] }}</div>
                @endif
                @if ($letterhead['contact_line'])
                    <div class="line">{{ $letterhead['contact_line'] }}</div>
                @endif
            </td>
        </tr>
    </table>

    <h1>{{ $report['title'] }}</h1>
    <p class="subtitle" style="text-align: center;">Gerado em {{ now()->format('d/m/Y H:i') }}</p>

    @if (! empty($report['summary']))
        <div class="summary">
            @foreach ($report['summary'] as $item)
                <span>{{ $item['label'] }}<strong>{{ $item['value'] }}</strong></span>
            @endforeach
        </div>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($report['headers'] as $header)
                    <th>{{ $header['label'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @forelse ($report['rows'] as $row)
                <tr>
                    @foreach ($report['headers'] as $header)
                        <td>{{ $row[$header['key']] ?? '' }}</td>
                    @endforeach
                </tr>
            @empty
                <tr>
                    <td colspan="{{ count($report['headers']) }}">Nenhum dado encontrado para o período selecionado.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    @if (! empty($autoPrint))
        <script>
            window.onload = () => window.print();
        </script>
    @endif
</body>
</html>
