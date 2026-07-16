<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>Impressão de etiquetas</title>
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0;
        padding: 0;
        font-family: 'Hanken Grotesk', Arial, sans-serif;
        color: #000;
    }
    .grid {
        display: grid;
        grid-template-columns: repeat({{ $layout['columns'] }}, {{ $layout['label_width'] }}mm);
        gap: 2mm;
    }
    .label {
        width: {{ $layout['label_width'] }}mm;
        height: {{ $layout['label_height'] }}mm;
        border: 1px solid #ccc;
        padding: 1.5mm;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        text-align: center;
        overflow: hidden;
    }
    .label .store-name { font-size: 8px; font-weight: 600; }
    .label .name { font-size: {{ $layout['font_sizes']['name'] }}px; font-weight: 600; line-height: 1.2; }
    .label .price { font-size: {{ $layout['font_sizes']['price'] }}px; font-weight: 700; }
    .label .code { font-size: 8px; color: #444; }
    .label .barcode { width: 100%; }
    .label .barcode svg { width: 100%; height: {{ $layout['font_sizes']['barcode'] }}px; }
    @media print {
        @page {
            size: {{ $layout['page_width'] }}mm {{ $layout['page_height'] }}mm;
            margin: {{ $layout['margin_top'] }}mm {{ $layout['margin_right'] }}mm {{ $layout['margin_bottom'] }}mm {{ $layout['margin_left'] }}mm;
        }
        .label { border-color: transparent; }
    }
</style>
</head>
<body>
    <div class="grid">
        @foreach ($labels as $label)
            <div class="label">
                @if ($layout['content_fields']['store_name'])
                    <div class="store-name">{{ $storeSetting->trade_name ?: $storeSetting->name }}</div>
                @endif
                @if ($layout['content_fields']['name'])
                    <div class="name">{{ $label['name'] }}</div>
                @endif
                @if ($layout['content_fields']['price'])
                    <div class="price">R$ {{ number_format((float) $label['price'], 2, ',', '.') }}</div>
                @endif
                @if ($layout['content_fields']['code'])
                    <div class="code">{{ $label['code'] }}</div>
                @endif
                @if ($layout['content_fields']['barcode'])
                    <div class="barcode">{!! $label['barcode_svg'] !!}</div>
                @endif
            </div>
        @endforeach
    </div>

    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
