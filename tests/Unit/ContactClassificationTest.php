<?php

namespace Tests\Unit;

use App\Contact;
use Tests\TestCase;

class ContactClassificationTest extends TestCase
{
    public function test_dealer_flag_is_cast_to_boolean(): void
    {
        $dealer = (new Contact())->forceFill(['is_dealer' => 1]);
        $customer = (new Contact())->forceFill(['is_dealer' => 0]);

        $this->assertTrue($dealer->is_dealer);
        $this->assertFalse($customer->is_dealer);
    }

    public function test_dealer_and_retail_customer_scopes_are_separate(): void
    {
        $dealerQuery = Contact::query()->dealers();
        $customerQuery = Contact::query()->retailCustomers();

        $this->assertStringContainsString('contacts`.`is_dealer', $dealerQuery->toSql());
        $this->assertSame([true], $dealerQuery->getBindings());
        $this->assertSame([false], $customerQuery->getBindings());
    }
}
