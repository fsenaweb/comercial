<?php

namespace App\Http\Controllers;

use App\Enums\PrintFormat;
use App\Enums\SaleStatus;
use App\Http\Requests\Receipt\PrintReceiptRequest;
use App\Models\Sale;
use App\Models\StoreSetting;
use Illuminate\View\View;
use Picqer\Barcode\BarcodeGeneratorSVG;

class ReceiptController extends Controller
{
    public function show(PrintReceiptRequest $request, Sale $sale): View
    {
        $generator = new BarcodeGeneratorSVG();

        return view('receipts.show', [
            'sale' => $sale->load(['items.productVariation.product', 'customer', 'seller', 'payments.paymentMethod', 'cashRegister']),
            'storeSetting' => StoreSetting::current(),
            'barcodeSvg' => $generator->getBarcode($sale->number, $generator::TYPE_CODE_128, 2, 50),
            'format' => PrintFormat::from($request->validated('format') ?? PrintFormat::Roll80->value),
            'isQuote' => $sale->status === SaleStatus::Pending,
        ]);
    }
}
