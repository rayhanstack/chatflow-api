<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::withCount('orders')
            ->when($request->search, fn ($q) => $q
                ->where('name', 'ilike', "%{$request->search}%")
                ->orWhere('phone', 'like', "%{$request->search}%")
            )
            ->when($request->platform, fn ($q) => $q->where('platform', $request->platform))
            ->orderByDesc('total_spent')
            ->paginate(25);

        return response()->json($customers);
    }

    public function show(Customer $customer): JsonResponse
    {
        return response()->json(
            $customer->load(['orders' => fn ($q) => $q->orderByDesc('created_at')->limit(10)])
        );
    }
}
