<?php

namespace App\Http\Controllers\Api;

use App\Enums\AccountEntryType;
use App\Enums\ExpenseStatus;
use App\Enums\InstallmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\LowStockVariationResource;
use App\Models\AccountsReceivable;
use App\Models\Expense;
use App\Models\PayableInstallment;
use App\Models\ProductVariation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * "Visão Financeira" — painel único combinando crediário, contas a pagar,
 * despesas e estoque crítico. Leitura pura agregada, sem regra de negócio,
 * então fica num Controller dedicado sem Action (mesmo raciocínio do
 * `ReportController`) e liberado a qualquer papel autenticado.
 *
 * Adaptação deliberada em relação à referência visual (AppLoja): lá,
 * "receitas previstas" existe porque o crediário tem parcela com
 * vencimento; aqui o crediário é conta corrente sem data (ver
 * `docs/03-database-modeling.md`, seção 9), então não há como projetar
 * "quanto entra em julho" — o card correspondente vira o saldo devedor
 * total, sem recorte de mês. Só o lado de contas a pagar/despesas
 * (que têm `due_date` de verdade) participa do "Planejamento do mês".
 */
class FinanceController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $month = $request->filled('month') ? Carbon::parse($request->string('month')->value().'-01') : Carbon::today()->startOfMonth();
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $previousMonthStart = $monthStart->copy()->subMonthNoOverflow()->startOfMonth();
        $previousMonthEnd = $previousMonthStart->copy()->endOfMonth();
        $today = Carbon::today();

        $receivableBalanceTotal = AccountsReceivable::with('entries')->get()->reduce(function ($carry, $account) {
            return bcadd($carry, $account->balance(), 2);
        }, '0.00');

        $dueInRange = function (Carbon $from, Carbon $to) {
            $payable = PayableInstallment::where('status', InstallmentStatus::Pending)
                ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->sum('amount');
            $expense = Expense::where('status', ExpenseStatus::Pending)
                ->whereBetween('due_date', [$from->toDateString(), $to->toDateString()])->sum('amount');

            return bcadd((string) $payable, (string) $expense, 2);
        };

        $paidInRange = function (Carbon $from, Carbon $to) {
            $payable = PayableInstallment::whereNotNull('paid_at')
                ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])->sum('paid_amount');
            $expense = Expense::whereNotNull('paid_at')
                ->whereBetween('paid_at', [$from->startOfDay(), $to->endOfDay()])->sum('amount');

            return bcadd((string) $payable, (string) $expense, 2);
        };

        $payablesDueThisMonth = $dueInRange($monthStart, $monthEnd);
        $payablesPaidThisMonth = $paidInRange($monthStart, $monthEnd);
        $payablesDuePreviousMonth = $dueInRange($previousMonthStart, $previousMonthEnd);

        $entriesInMonthCount = PayableInstallment::whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->count()
            + Expense::whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])->count();

        $overduePayable = PayableInstallment::where('status', InstallmentStatus::Pending)->where('due_date', '<', $today->toDateString());
        $overdueExpense = Expense::where('status', ExpenseStatus::Pending)->where('due_date', '<', $today->toDateString());
        $overdueTotal = bcadd((string) $overduePayable->sum('amount'), (string) $overdueExpense->sum('amount'), 2);
        $overdueCount = $overduePayable->count() + $overdueExpense->count();

        $topPayables = PayableInstallment::with('accountsPayable.supplier')
            ->where('status', InstallmentStatus::Pending)
            ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->get()
            ->map(fn ($installment) => [
                'description' => $installment->accountsPayable->description,
                'person_name' => $installment->accountsPayable->supplier?->trade_name ?? $installment->accountsPayable->supplier?->corporate_name,
                'due_date' => $installment->due_date->format('Y-m-d'),
                'amount' => $installment->amount,
            ])
            ->concat(
                Expense::where('status', ExpenseStatus::Pending)
                    ->whereBetween('due_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
                    ->get()
                    ->map(fn ($expense) => [
                        'description' => $expense->description,
                        'person_name' => $expense->category,
                        'due_date' => $expense->due_date->format('Y-m-d'),
                        'amount' => $expense->amount,
                    ])
            )
            ->sortByDesc('amount')
            ->take(6)
            ->values();

        $topReceivables = AccountsReceivable::with('customer', 'entries')->get()
            ->map(fn ($account) => [
                'customer_id' => $account->customer_id,
                'customer_name' => $account->customer?->name,
                'balance' => $account->balance(),
            ])
            ->filter(fn ($row) => bccomp($row['balance'], '0', 2) > 0)
            ->sortByDesc(fn ($row) => (float) $row['balance'])
            ->take(6)
            ->values();

        $lowStockVariations = ProductVariation::lowStock()->with('product')->take(6)->get();

        return response()->json(['data' => [
            'month' => $monthStart->format('Y-m'),
            'previous_month' => $previousMonthStart->format('Y-m'),
            'receivable_balance_total' => $receivableBalanceTotal,
            'payables_due_this_month' => $payablesDueThisMonth,
            'payables_paid_this_month' => $payablesPaidThisMonth,
            'payables_due_previous_month' => $payablesDuePreviousMonth,
            'overdue_count' => $overdueCount,
            'overdue_total' => $overdueTotal,
            'entries_in_month_count' => $entriesInMonthCount,
            'top_payables_this_month' => $topPayables,
            'top_receivable_balances' => $topReceivables,
            'low_stock_count' => ProductVariation::lowStock()->count(),
            'low_stock_items' => LowStockVariationResource::collection($lowStockVariations),
        ]]);
    }
}
