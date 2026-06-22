<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private OrderService $orderService) {}

    public function index(Request $request): JsonResponse
    {
        $orders = Order::with(['customer', 'conversation'])
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->when($request->search, fn ($q) => $q
                ->where('order_number', 'ilike', "%{$request->search}%")
                ->orWhereHas('customer', fn ($cq) => $cq->where('name', 'ilike', "%{$request->search}%"))
            )
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($orders);
    }

    public function show(Order $order): JsonResponse
    {
        return response()->json($order->load(['customer', 'conversation.messages']));
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $validated = $request->validate([
            'status'          => 'sometimes|in:pending,confirmed,processing,out_for_delivery,delivered,cancelled',
            'items'           => 'sometimes|array',
            'subtotal'        => 'sometimes|numeric',
            'delivery_charge' => 'sometimes|numeric',
            'total'           => 'sometimes|numeric',
            'delivery_address'=> 'sometimes|string',
            'delivery_area'   => 'sometimes|string',
            'notes'           => 'sometimes|string|nullable',
        ]);

        if (isset($validated['status'])) {
            $order = $this->orderService->updateStatus($order, $validated['status']);
        } else {
            $order->update($validated);
        }

        return response()->json($order->fresh(['customer']));
    }

    public function stats(): JsonResponse
    {
        $today = now()->startOfDay();

        return response()->json([
            'today_orders'   => Order::where('created_at', '>=', $today)->count(),
            'today_revenue'  => Order::where('created_at', '>=', $today)->where('status', 'delivered')->sum('total'),
            'pending'        => Order::where('status', 'pending')->count(),
            'week_revenue'   => Order::where('created_at', '>=', now()->startOfWeek())->where('status', 'delivered')->sum('total'),
        ]);
    }
}