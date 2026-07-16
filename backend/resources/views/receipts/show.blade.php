<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<title>{{ $isQuote ? 'Orçamento' : 'Comprovante' }} {{ $sale->number }}</title>
@php
    $layout = match ($format) {
        \App\Enums\PrintFormat::Roll80 => ['width' => '80mm', 'padding' => '4mm', 'fontSize' => '12px', 'pageSize' => '80mm auto', 'pageMargin' => '0'],
        \App\Enums\PrintFormat::Roll58 => ['width' => '58mm', 'padding' => '3mm', 'fontSize' => '10px', 'pageSize' => '58mm auto', 'pageMargin' => '0'],
        \App\Enums\PrintFormat::A4 => ['width' => '190mm', 'padding' => '0', 'fontSize' => '14px', 'pageSize' => 'A4', 'pageMargin' => '15mm'],
    };
@endphp
<style>
    * { box-sizing: border-box; }
    body {
        margin: 0 auto;
        padding: {{ $layout['padding'] }};
        font-family: 'Courier New', monospace;
        font-size: {{ $layout['fontSize'] }};
        color: #000;
        width: {{ $layout['width'] }};
    }
    .center { text-align: center; }
    .bold { font-weight: bold; }
    .line { border-top: 1px dashed #000; margin: 6px 0; }
    table { width: 100%; border-collapse: collapse; }
    td { padding: 1px 0; vertical-align: top; }
    .right { text-align: right; }
    .totals td { padding: 2px 0; }
    .barcode { text-align: center; margin-top: 8px; }
    @media print {
        @page { size: {{ $layout['pageSize'] }}; margin: {{ $layout['pageMargin'] }}; }
        body { width: {{ $layout['width'] }}; padding: {{ $layout['padding'] }}; }
    }
</style>
</head>
<body>
    <div class="center bold">{{ $storeSetting->trade_name ?: $storeSetting->name }}</div>
    @if ($storeSetting->cnpj)
        <div class="center">CNPJ: {{ $storeSetting->cnpj }}</div>
    @endif
    @if ($storeSetting->address)
        <div class="center">{{ $storeSetting->address }}{{ $storeSetting->address_number ? ', '.$storeSetting->address_number : '' }} - {{ $storeSetting->city }}/{{ $storeSetting->state }}</div>
    @endif

    <div class="line"></div>
    @if ($isQuote)
        <div class="center bold">ORÇAMENTO</div>
        <div class="center">Sujeito à disponibilidade de estoque</div>
        <div class="center">Orçamento {{ $sale->number }}</div>
    @else
        <div class="center bold">DOCUMENTO NÃO FISCAL</div>
        <div class="center">Comprovante de venda {{ $sale->number }}</div>
    @endif
    <div class="line"></div>

    <table>
        <tr><td>Data</td><td class="right">{{ $sale->created_at->format('d/m/Y H:i') }}</td></tr>
        <tr><td>Vendedor</td><td class="right">{{ $sale->seller?->name ?? '-' }}</td></tr>
        <tr><td>Cliente</td><td class="right">{{ $sale->customer?->name ?? 'Não informado' }}</td></tr>
        @if ($isQuote && $sale->expires_at)
            <tr><td>Válido até</td><td class="right">{{ $sale->expires_at->format('d/m/Y') }}</td></tr>
        @endif
        @foreach ($sale->payments as $payment)
            <tr>
                <td>{{ $loop->first ? 'Forma de pagamento' : '' }}</td>
                <td class="right">{{ $payment->paymentMethod?->name }} — R$ {{ number_format($payment->amount, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="line"></div>

    <table>
        @foreach ($sale->items as $item)
            <tr>
                <td colspan="2">{{ $item->productVariation?->product?->name }}</td>
            </tr>
            <tr>
                <td>{{ $item->quantity }} x R$ {{ number_format($item->unit_price, 2, ',', '.') }}</td>
                <td class="right">R$ {{ number_format($item->total, 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="line"></div>

    <table class="totals">
        <tr><td>Subtotal</td><td class="right">R$ {{ number_format($sale->subtotal, 2, ',', '.') }}</td></tr>
        @if ($sale->discount > 0)
            <tr><td>Desconto</td><td class="right">- R$ {{ number_format($sale->discount, 2, ',', '.') }}</td></tr>
        @endif
        <tr class="bold"><td>Total</td><td class="right">R$ {{ number_format($sale->total, 2, ',', '.') }}</td></tr>
    </table>

    <div class="line"></div>

    <div class="barcode">
        {!! $barcodeSvg !!}
        <div>{{ $sale->number }}</div>
    </div>

    <div class="center" style="margin-top:8px;">Obrigado pela preferência!</div>

    <script>
        window.onload = () => window.print();
    </script>
</body>
</html>
