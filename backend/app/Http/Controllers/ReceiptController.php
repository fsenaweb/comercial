<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Models\StoreSetting;
use Illuminate\View\View;
use Picqer\Barcode\BarcodeGeneratorSVG;

class ReceiptController extends Controller
{
    public function show(Sale $sale): View
    {
        $generator = new BarcodeGeneratorSVG();

        return view('receipts.show', [
            'sale' => $sale->load(['items.productVariation.product', 'customer', 'seller', 'paymentMethod', 'cashRegister']),
            'storeSetting' => StoreSetting::current(),
            'barcodeSvg' => $generator->getBarcode($sale->number, $generator::TYPE_CODE_128, 2, 50),
        ]);
    }
}
