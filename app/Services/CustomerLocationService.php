<?php

namespace App\Services;

use App\Repositories\Contracts\CustomerLocationRepositoryInterface;

class CustomerLocationService
{
    public function __construct(
        protected CustomerLocationRepositoryInterface $customerLocationRepository
    ) {}

    /**
     * Record customer GPS location (with spam prevention - min 10 second interval).
     */
    public function updateLocation(int $customerId, float $latitude, float $longitude)
    {
        $latest = $this->customerLocationRepository->getLatestByCustomer($customerId);

        if ($latest && $latest->recorded_at->diffInSeconds(now()) < 10) {
            return $latest; // Prevent spam - ignore if last update was < 10 seconds ago
        }

        return $this->customerLocationRepository->create([
            'customer_id' => $customerId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'recorded_at' => now(),
        ]);
    }
}
