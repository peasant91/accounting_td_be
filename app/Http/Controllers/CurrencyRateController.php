<?php

namespace App\Http\Controllers;

use App\Http\Requests\CurrencyRate\UpsertRateRequest;
use App\Http\Resources\CurrencyRateResource;
use App\Models\CurrencyRate;
use Illuminate\Http\JsonResponse;

class CurrencyRateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => CurrencyRateResource::collection(CurrencyRate::orderBy('currency')->get()),
            'base_currency' => config('billing.base_currency', 'IDR'),
        ]);
    }

    public function upsert(UpsertRateRequest $request, string $currency): JsonResponse
    {
        $code = strtoupper($currency);
        $base = config('billing.base_currency', 'IDR');

        if (!preg_match('/^[A-Z]{3}$/', $code)) {
            return response()->json(['message' => 'Invalid currency code'], 422);
        }
        if ($code === $base) {
            return response()->json(['message' => 'Cannot set a rate for the base currency'], 422);
        }

        $rate = CurrencyRate::updateOrCreate(
            ['currency' => $code],
            ['rate_to_base' => $request->validated()['rate_to_base']]
        );

        return response()->json(['data' => new CurrencyRateResource($rate->fresh())]);
    }
}
