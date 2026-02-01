<?php

namespace App\Repositories\Contracts;

use App\Customer;

interface CustomerRepositoryInterface
{
    public function find(int $id): ?Customer;

    public function findByEmail(string $email): ?Customer;

    public function create(array $data): Customer;

    public function update(Customer $customer, array $data): Customer;

    public function getActiveByBusiness(int $businessId): \Illuminate\Support\Collection;
}
