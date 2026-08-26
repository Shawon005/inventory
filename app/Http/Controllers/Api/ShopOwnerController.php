<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreShopOwnerRequest;
use App\Http\Resources\Api\ShopOwnerResource;
use App\ShopOwner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ShopOwnerController extends Controller
{
    /**
     * Return a searchable, paginated list of shop owners for the POS.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'distributor_id' => ['required', 'integer', 'exists:customers,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ]);

        $search = $validated['search'] ?? null;
        $perPage = $validated['per_page'] ?? 20;

        $shopOwners = ShopOwner::query()
            ->with('phones')
            ->where('distributor_id', $validated['distributor_id'])
            ->when($search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('address', 'like', "%{$search}%")
                        ->orWhereHas('phones', function ($query) use ($search) {
                            $query->where('phone', 'like', "%{$search}%");
                        });
                });
            })
            ->latest('id')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ShopOwnerResource::collection($shopOwners->getCollection()),
            'meta' => [
                'current_page' => $shopOwners->currentPage(),
                'last_page' => $shopOwners->lastPage(),
                'per_page' => $shopOwners->perPage(),
                'total' => $shopOwners->total(),
            ],
        ]);
    }

    /**
     * Store a new shop owner and all of their phone numbers.
     */
    public function store(StoreShopOwnerRequest $request): JsonResponse
    {
        $data = $request->validated();

        $shopOwner = DB::transaction(function () use ($data) {
            $shopOwner = ShopOwner::create([
                'distributor_id' => $data['distributor_id'],
                'name' => $data['name'],
                'email' => $data['email'],
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'address' => $data['address'],
            ]);

            $shopOwner->phones()->createMany(
                collect($data['phones'])->map(fn ($phone) => ['phone' => $phone])->all()
            );

            return $shopOwner->load('phones');
        });

        return response()->json([
            'success' => true,
            'message' => 'Shop owner information saved successfully.',
            'data' => new ShopOwnerResource($shopOwner),
        ], 201);
    }
}
