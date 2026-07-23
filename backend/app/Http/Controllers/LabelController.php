<?php

namespace App\Http\Controllers;

use App\Http\Requests\Label\PrintLabelsRequest;
use App\Models\ProductVariation;
use App\Models\StoreSetting;
use Illuminate\View\View;
use Picqer\Barcode\BarcodeGeneratorSVG;

class LabelController extends Controller
{
    public function print(PrintLabelsRequest $request): View
    {
        $data = $request->validated();

        $variations = ProductVariation::with('product')
            ->whereIn('id', collect($data['products'])->pluck('variation_id'))
            ->get()
            ->keyBy('id');

        $generator = new BarcodeGeneratorSVG();

        $labels = collect($data['products'])
            ->flatMap(function (array $item) use ($variations, $generator) {
                $variation = $variations->get($item['variation_id']);

                if (! $variation) {
                    return [];
                }

                $variationLabel = collect([$variation->color, $variation->size])->filter()->implode(' / ');
                $barcodeValue = $variation->ean_gtin ?: $variation->code;

                $label = [
                    'name' => $variation->product?->name.($variationLabel ? " - {$variationLabel}" : ''),
                    'price' => $variation->sale_price,
                    'code' => $variation->code,
                    'barcode_svg' => $generator->getBarcode($barcodeValue, $generator::TYPE_CODE_128, 1.5, 28),
                ];

                return array_fill(0, $item['quantity'], $label);
            });

        return view('labels.print', [
            'labels' => $labels,
            'storeSetting' => StoreSetting::current(),
            'layout' => $data,
        ]);
    }
}
