<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreItemTemplateRequest;
use App\Http\Resources\ItemTemplateResource;
use App\Models\ItemTemplate;
use Illuminate\Http\JsonResponse;

class ItemTemplateController extends Controller
{
    public function index(): JsonResponse
    {
        $templates = ItemTemplate::orderBy('id')->get();

        return response()->json([
            'data' => ItemTemplateResource::collection($templates),
        ]);
    }

    public function store(StoreItemTemplateRequest $request): JsonResponse
    {
        $template = ItemTemplate::create($request->validated());

        return response()->json([
            'data' => new ItemTemplateResource($template),
            'message' => 'Template created successfully',
        ], 201);
    }

    public function update(StoreItemTemplateRequest $request, ItemTemplate $itemTemplate): JsonResponse
    {
        $itemTemplate->update($request->validated());

        return response()->json([
            'data' => new ItemTemplateResource($itemTemplate),
            'message' => 'Template updated successfully',
        ]);
    }

    public function destroy(ItemTemplate $itemTemplate): JsonResponse
    {
        $itemTemplate->delete();

        return response()->json([
            'message' => 'Template deleted successfully',
        ]);
    }
}
