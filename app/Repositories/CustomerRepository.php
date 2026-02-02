<?php

namespace App\Repositories;

use App\Customer; // or App\Customer
use App\Repositories\Contracts\CustomerRepositoryInterface;
use Illuminate\Support\Collection;

class CustomerRepository implements CustomerRepositoryInterface
{
    public function getAll(): Collection
    {
        return Customer::all();
    }

    public function find(int $id): ?Customer
    {
        return Customer::find($id);
    }
    public function findByEmail(string $email): ?Customer
    {
        return Customer::where('email', $email)->first();
    }
    public function create(array $data): Customer
    {
        return Customer::create($data);
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer;
    }

    public function getActiveByBusiness(int $businessId): Collection
    {
        return Customer::where('business_id', $businessId)->where('status', 'active')->get();
    }
}
