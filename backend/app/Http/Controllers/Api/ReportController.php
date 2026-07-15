<?php

namespace App\Http\Controllers\Api;

use App\Enums\SaleStatus;
use App\Exports\ReportArrayExport;
use App\Http\Controllers\Controller;
use App\Http\Resources\LowStockVariationResource;
use App\Models\ProductVariation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StoreSetting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ReportController extends Controller
{
    /** @var array<string, string> Catálogo de relatórios disponíveis na tela "Relatórios" (categorias Vendas/Estoque). */
    private const CATALOG = [
        'vendas_totais' => 'buildSalesTotal',
        'vendas_produto' => 'buildSalesByProductReport',
        'vendas_categoria' => 'buildSalesByCategoryReport',
        'vendas_vendedor' => 'buildSalesBySellerReport',
        'vendas_forma_pagamento' => 'buildSalesByPaymentMethodReport',
        'lucro_bruto' => 'buildGrossProfitByProduct',
        'nivel_estoque' => 'buildLowStockReport',
        'valor_estoque' => 'buildStockValue',
    ];

    public function salesByDay(Request $request): JsonResponse
    {
        [$dateFrom, $dateTo] = $this->resolvePeriod($request, 30);

        $query = Sale::completed()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo);

        if ($request->filled('seller_id')) {
            $query->where('seller_id', $request->integer('seller_id'));
        }

        $rows = $query->selectRaw('DATE(created_at) as date, COUNT(*) as sales_count, COALESCE(SUM(total), 0) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function salesByProduct(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->salesByProductRows($request)]);
    }

    public function salesBySeller(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->salesBySellerRows($request)]);
    }

    public function salesByCategory(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->salesByCategoryRows($request)]);
    }

    public function lowStock(): JsonResponse
    {
        $variations = ProductVariation::lowStock()->with('product')->get();

        return response()->json(['data' => LowStockVariationResource::collection($variations)]);
    }

    public function dashboardSummary(): JsonResponse
    {
        $today = Carbon::today()->toDateString();

        $todaySummary = Sale::completed()
            ->whereDate('created_at', $today)
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total), 0) as total')
            ->first();

        $sellersToday = Sale::completed()
            ->join('users', 'users.id', '=', 'sales.seller_id')
            ->whereDate('sales.created_at', $today)
            ->selectRaw('sales.seller_id as seller_id, users.name as seller_name, COALESCE(SUM(sales.total), 0) as total')
            ->groupBy('sales.seller_id', 'users.name')
            ->orderByDesc('total')
            ->get();

        $lowStockCount = ProductVariation::lowStock()->count();

        $monthsStart = Carbon::today()->startOfMonth()->subMonths(5)->toDateString();

        $monthlyRows = Sale::completed()
            ->whereDate('created_at', '>=', $monthsStart)
            ->selectRaw("to_char(created_at, 'YYYY-MM') as month, COUNT(*) as sales_count, COALESCE(SUM(total), 0) as total")
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return response()->json(['data' => [
            'today_total' => (string) $todaySummary->total,
            'today_sales_count' => (int) $todaySummary->sales_count,
            'sales_by_seller_today' => $sellersToday,
            'low_stock_count' => $lowStockCount,
            'monthly_sales_count' => $monthlyRows->map(fn ($row) => [
                'month' => $row->month,
                'count' => (int) $row->sales_count,
            ]),
            'monthly_sales_total' => $monthlyRows->map(fn ($row) => [
                'month' => $row->month,
                'total' => (string) $row->total,
            ]),
        ]]);
    }

    public function show(string $key, Request $request): JsonResponse
    {
        return response()->json(['data' => $this->reportData($key, $request)]);
    }

    public function exportPdf(string $key, Request $request): SymfonyResponse
    {
        $report = $this->reportData($key, $request);

        $mpdf = new Mpdf(['tempDir' => sys_get_temp_dir()]);
        $mpdf->WriteHTML(view('reports.export', ['report' => $report, 'letterhead' => $this->letterhead()])->render());

        return response($mpdf->Output($key.'.pdf', \Mpdf\Output\Destination::STRING_RETURN), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$key.'.pdf"',
        ]);
    }

    public function exportExcel(string $key, Request $request): SymfonyResponse
    {
        $report = $this->reportData($key, $request);

        return (new ReportArrayExport($report, $this->letterhead()))->download($key.'.xlsx');
    }

    /**
     * Dados de "papel timbrado" (nome, CNPJ, endereço, contato, logo) reaproveitados
     * pelos exports PDF e Excel — mesma fonte (`store_settings`) que preenche o
     * comprovante térmico da Sprint 3.
     */
    private function letterhead(): array
    {
        $store = StoreSetting::current();

        $cityState = trim($store->city.($store->state ? ' - '.$store->state : ''));
        $addressLine = implode(', ', array_filter([
            $store->address,
            $store->address_number ? 'Nº'.$store->address_number : null,
            $store->neighborhood,
            $store->zip_code,
            $cityState ?: null,
        ]));

        $contactLine = implode('     ', array_filter([
            $store->phone ?: $store->mobile_phone,
            $store->email ? 'Email: '.$store->email : null,
        ]));

        $logoPath = null;
        if ($store->logo_path && Storage::disk('public')->exists($store->logo_path)) {
            $logoPath = Storage::disk('public')->path($store->logo_path);
        }

        return [
            'display_name' => $store->trade_name ?: $store->name,
            'corporate_name' => ($store->trade_name && $store->trade_name !== $store->name) ? $store->name : null,
            'cnpj' => $store->cnpj,
            'address_line' => $addressLine ?: null,
            'contact_line' => $contactLine ?: null,
            'logo_path' => $logoPath,
        ];
    }

    private function reportData(string $key, Request $request): array
    {
        if (! array_key_exists($key, self::CATALOG)) {
            abort(404, 'Relatório não encontrado.');
        }

        return $this->{self::CATALOG[$key]}($request);
    }

    private function buildSalesTotal(Request $request): array
    {
        [$dateFrom, $dateTo] = $this->resolvePeriod($request, 30);

        $summary = Sale::completed()
            ->whereDate('created_at', '>=', $dateFrom)
            ->whereDate('created_at', '<=', $dateTo)
            ->selectRaw('COUNT(*) as sales_count, COALESCE(SUM(total), 0) as total, COALESCE(AVG(total), 0) as average_ticket')
            ->first();

        return [
            'title' => 'Vendas Totais',
            'headers' => [
                ['key' => 'metric', 'label' => 'Métrica'],
                ['key' => 'value', 'label' => 'Valor'],
            ],
            'rows' => [
                ['metric' => 'Período', 'value' => "{$dateFrom} a {$dateTo}"],
                ['metric' => 'Quantidade de vendas', 'value' => (string) $summary->sales_count],
                ['metric' => 'Faturamento total', 'value' => $this->money($summary->total)],
                ['metric' => 'Ticket médio', 'value' => $this->money($summary->average_ticket)],
            ],
        ];
    }

    private function buildSalesByProductReport(Request $request): array
    {
        $rows = $this->salesByProductRows($request);

        return [
            'title' => 'Vendas por Produto',
            'headers' => [
                ['key' => 'product_name', 'label' => 'Produto'],
                ['key' => 'quantity_sold', 'label' => 'Quantidade'],
                ['key' => 'total', 'label' => 'Total'],
            ],
            'rows' => $rows->map(fn ($row) => [
                'product_name' => $row->product_name,
                'quantity_sold' => (string) $row->quantity_sold,
                'total' => $this->money($row->total),
            ])->all(),
        ];
    }

    private function buildSalesByCategoryReport(Request $request): array
    {
        $rows = $this->salesByCategoryRows($request);

        return [
            'title' => 'Vendas por Categoria',
            'headers' => [
                ['key' => 'category_name', 'label' => 'Categoria'],
                ['key' => 'quantity_sold', 'label' => 'Quantidade'],
                ['key' => 'total', 'label' => 'Total'],
            ],
            'rows' => $rows->map(fn ($row) => [
                'category_name' => $row->category_name,
                'quantity_sold' => (string) $row->quantity_sold,
                'total' => $this->money($row->total),
            ])->all(),
        ];
    }

    private function buildSalesBySellerReport(Request $request): array
    {
        $rows = $this->salesBySellerRows($request);

        return [
            'title' => 'Vendas por Vendedor',
            'headers' => [
                ['key' => 'seller_name', 'label' => 'Vendedor'],
                ['key' => 'sales_count', 'label' => 'Qtd. de vendas'],
                ['key' => 'total', 'label' => 'Total'],
            ],
            'rows' => $rows->map(fn ($row) => [
                'seller_name' => $row->seller_name,
                'sales_count' => (string) $row->sales_count,
                'total' => $this->money($row->total),
            ])->all(),
        ];
    }

    private function buildSalesByPaymentMethodReport(Request $request): array
    {
        $query = SalePayment::query()
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->join('payment_methods', 'payment_methods.id', '=', 'sale_payments.payment_method_id')
            ->where('sales.status', SaleStatus::Completed->value);

        $this->applyDateFilter($query, $request, 'sales.created_at');

        $rows = $query->selectRaw('payment_methods.id as payment_method_id, payment_methods.name as payment_method_name,
                COUNT(DISTINCT sale_payments.sale_id) as sales_count,
                COALESCE(SUM(sale_payments.amount), 0) as total')
            ->groupBy('payment_methods.id', 'payment_methods.name')
            ->orderByDesc('total')
            ->get();

        return [
            'title' => 'Vendas por Forma de Pagamento',
            'headers' => [
                ['key' => 'payment_method_name', 'label' => 'Forma de Pagamento'],
                ['key' => 'sales_count', 'label' => 'Qtd. de vendas'],
                ['key' => 'total', 'label' => 'Total'],
            ],
            'rows' => $rows->map(fn ($row) => [
                'payment_method_name' => $row->payment_method_name,
                'sales_count' => (string) $row->sales_count,
                'total' => $this->money($row->total),
            ])->all(),
        ];
    }

    private function buildGrossProfitByProduct(Request $request): array
    {
        $query = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('product_variations', 'product_variations.id', '=', 'sale_items.product_variation_id')
            ->join('products', 'products.id', '=', 'product_variations.product_id')
            ->where('sales.status', SaleStatus::Completed->value);

        $this->applyDateFilter($query, $request, 'sales.created_at');

        $rows = $query->selectRaw('products.id as product_id, products.name as product_name,
                SUM(sale_items.quantity) as quantity_sold,
                COALESCE(SUM(sale_items.total), 0) as revenue,
                COALESCE(SUM(sale_items.quantity * product_variations.cost_price), 0) as cost')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('revenue')
            ->get();

        return [
            'title' => 'Lucro Bruto por Produto',
            'headers' => [
                ['key' => 'product_name', 'label' => 'Produto'],
                ['key' => 'quantity_sold', 'label' => 'Quantidade'],
                ['key' => 'revenue', 'label' => 'Receita'],
                ['key' => 'cost', 'label' => 'Custo'],
                ['key' => 'profit', 'label' => 'Lucro Bruto'],
            ],
            'rows' => $rows->map(function ($row) {
                $profit = (float) $row->revenue - (float) $row->cost;

                return [
                    'product_name' => $row->product_name,
                    'quantity_sold' => (string) $row->quantity_sold,
                    'revenue' => $this->money($row->revenue),
                    'cost' => $this->money($row->cost),
                    'profit' => $this->money($profit),
                ];
            })->all(),
        ];
    }

    private function buildLowStockReport(): array
    {
        $variations = ProductVariation::lowStock()->with('product')->get();

        return [
            'title' => 'Nível de Estoque',
            'headers' => [
                ['key' => 'product_name', 'label' => 'Produto'],
                ['key' => 'product_code', 'label' => 'Código'],
                ['key' => 'current_quantity', 'label' => 'Quantidade Atual'],
                ['key' => 'min_quantity', 'label' => 'Quantidade Mínima'],
            ],
            'rows' => $variations->map(fn ($variation) => [
                'product_name' => $variation->product->name,
                'product_code' => $variation->product_code,
                'current_quantity' => (string) $variation->current_quantity,
                'min_quantity' => (string) $variation->min_quantity,
            ])->all(),
        ];
    }

    private function buildStockValue(): array
    {
        $variations = ProductVariation::query()->with('product')->orderBy('product_id')->get();

        $totalCost = 0.0;
        $totalSale = 0.0;

        $rows = $variations->map(function ($variation) use (&$totalCost, &$totalSale) {
            $costValue = (float) $variation->current_quantity * (float) $variation->cost_price;
            $saleValue = (float) $variation->current_quantity * (float) $variation->sale_price;
            $totalCost += $costValue;
            $totalSale += $saleValue;

            return [
                'product_name' => $variation->product->name,
                'product_code' => $variation->product_code,
                'current_quantity' => (string) $variation->current_quantity,
                'cost_value' => $this->money($costValue),
                'sale_value' => $this->money($saleValue),
            ];
        })->all();

        return [
            'title' => 'Valor do Estoque',
            'summary' => [
                ['label' => 'Valor total (custo)', 'value' => $this->money($totalCost)],
                ['label' => 'Valor total (venda)', 'value' => $this->money($totalSale)],
            ],
            'headers' => [
                ['key' => 'product_name', 'label' => 'Produto'],
                ['key' => 'product_code', 'label' => 'Código'],
                ['key' => 'current_quantity', 'label' => 'Quantidade'],
                ['key' => 'cost_value', 'label' => 'Valor (Custo)'],
                ['key' => 'sale_value', 'label' => 'Valor (Venda)'],
            ],
            'rows' => $rows,
        ];
    }

    private function salesByProductRows(Request $request)
    {
        $query = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('product_variations', 'product_variations.id', '=', 'sale_items.product_variation_id')
            ->join('products', 'products.id', '=', 'product_variations.product_id')
            ->where('sales.status', SaleStatus::Completed->value);

        $this->applyDateFilter($query, $request, 'sales.created_at');

        if ($request->filled('category_id')) {
            $query->where('products.category_id', $request->integer('category_id'));
        }

        return $query->selectRaw('products.id as product_id, products.name as product_name, SUM(sale_items.quantity) as quantity_sold, COALESCE(SUM(sale_items.total), 0) as total')
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('quantity_sold')
            ->get();
    }

    private function salesBySellerRows(Request $request)
    {
        $query = Sale::completed()->join('users', 'users.id', '=', 'sales.seller_id');

        $this->applyDateFilter($query, $request, 'sales.created_at');

        return $query->selectRaw('sales.seller_id as seller_id, users.name as seller_name, COUNT(sales.id) as sales_count, COALESCE(SUM(sales.total), 0) as total')
            ->groupBy('sales.seller_id', 'users.name')
            ->orderByDesc('total')
            ->get();
    }

    private function salesByCategoryRows(Request $request)
    {
        $query = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('product_variations', 'product_variations.id', '=', 'sale_items.product_variation_id')
            ->join('products', 'products.id', '=', 'product_variations.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->where('sales.status', SaleStatus::Completed->value);

        $this->applyDateFilter($query, $request, 'sales.created_at');

        return $query->selectRaw("products.category_id as category_id, COALESCE(categories.name, 'Sem categoria') as category_name, SUM(sale_items.quantity) as quantity_sold, COALESCE(SUM(sale_items.total), 0) as total")
            ->groupBy('products.category_id', 'categories.name')
            ->orderByDesc('total')
            ->get();
    }

    private function applyDateFilter(Builder $query, Request $request, string $column): void
    {
        if ($request->filled('date_from')) {
            $query->whereDate($column, '>=', $request->string('date_from')->value());
        }

        if ($request->filled('date_to')) {
            $query->whereDate($column, '<=', $request->string('date_to')->value());
        }
    }

    private function resolvePeriod(Request $request, int $defaultDays): array
    {
        $dateFrom = $request->filled('date_from')
            ? $request->string('date_from')->value()
            : Carbon::today()->subDays($defaultDays - 1)->toDateString();

        $dateTo = $request->filled('date_to')
            ? $request->string('date_to')->value()
            : Carbon::today()->toDateString();

        return [$dateFrom, $dateTo];
    }

    private function money(mixed $value): string
    {
        return 'R$ '.number_format((float) $value, 2, ',', '.');
    }
}
