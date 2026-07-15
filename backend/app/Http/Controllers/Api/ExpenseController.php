<?php

namespace App\Http\Controllers\Api;

use App\Actions\Expense\RegisterExpenseAction;
use App\Actions\Expense\SettleExpenseAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('manage', Expense::class);

        $query = Expense::query()->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        if ($request->filled('search')) {
            $query->where('description', 'ilike', '%'.$request->string('search')->value().'%');
        }

        return ExpenseResource::collection($query->get());
    }

    public function store(StoreExpenseRequest $request, RegisterExpenseAction $action): ExpenseResource
    {
        $this->authorize('manage', Expense::class);

        $expense = $action->execute($request->validated(), $request->user());

        return ExpenseResource::make($expense);
    }

    public function settle(Request $request, Expense $expense, SettleExpenseAction $action): ExpenseResource
    {
        $this->authorize('manage', Expense::class);

        $expense = $action->execute($expense, $request->user());

        return ExpenseResource::make($expense);
    }
}
