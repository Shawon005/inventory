<?php

namespace Tests\Unit;

use App\Customer;
use App\Http\Controllers\Distribution\PayrollController;
use App\Http\Middleware\EnsureSalesRepresentativeApiUser;
use App\Http\Resources\Api\CustomerResource;
use Carbon\Carbon;
use Illuminate\Http\Request;
use ReflectionMethod;
use Tests\TestCase;

class DsrPayrollTest extends TestCase
{
    public function test_dsr_payroll_uses_base_salary_without_attendance_deductions(): void
    {
        $customer = new Customer([
            'business_id' => 1,
            'employee_type' => Customer::TYPE_DSR,
            'base_salary' => 25000,
        ]);
        $controller = app(PayrollController::class);
        $method = new ReflectionMethod($controller, 'buildPayrollData');
        $method->setAccessible(true);

        $payroll = $method->invoke(
            $controller,
            $customer,
            Carbon::parse('2026-08-01'),
            Carbon::parse('2026-08-31')
        );

        $this->assertFalse($payroll['uses_attendance']);
        $this->assertSame(0, $payroll['absent_days']);
        $this->assertSame(0, $payroll['absent_deduction']);
        $this->assertSame(0, $payroll['late_deduction']);
        $this->assertSame(25000.0, $payroll['net_payable']);
    }

    public function test_changed_contact_and_distribution_views_compile_to_valid_php(): void
    {
        $files = [
            'resources/views/contact/create.blade.php',
            'resources/views/contact/edit.blade.php',
            'resources/views/contact/index.blade.php',
            'resources/views/distribution/customers/index.blade.php',
            'resources/views/distribution/customers/create.blade.php',
            'resources/views/distribution/customers/edit.blade.php',
            'resources/views/distribution/customers/show.blade.php',
            'resources/views/distribution/payroll/index.blade.php',
            'resources/views/distribution/payroll/show.blade.php',
            'resources/views/distribution/payroll/print.blade.php',
        ];

        foreach ($files as $file) {
            $compiled = app('blade.compiler')->compileString(file_get_contents(base_path($file)));
            eval('if (false) { ?>'.$compiled.'<?php }');
        }

        $this->assertTrue(true);
    }

    public function test_dsr_api_resource_disables_attendance(): void
    {
        $customer = new Customer([
            'employee_type' => Customer::TYPE_DSR,
            'name' => 'Delivery Employee',
        ]);

        $resource = (new CustomerResource($customer))->toArray(request());

        $this->assertSame(Customer::TYPE_DSR, $resource['employee_type']);
        $this->assertFalse($resource['uses_attendance']);
    }

    public function test_dsr_is_rejected_from_the_mobile_api(): void
    {
        $request = Request::create('/api/customer/dashboard');
        $request->setUserResolver(function () {
            return new Customer(['employee_type' => Customer::TYPE_DSR]);
        });

        $response = (new EnsureSalesRepresentativeApiUser())->handle($request, function () {
            $this->fail('The protected API action should not run for a DSR.');
        });

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(
            'This employee does not have a mobile user account.',
            $response->getData(true)['message']
        );
    }

    public function test_sr_is_allowed_through_the_mobile_api_guard(): void
    {
        $request = Request::create('/api/customer/dashboard');
        $request->setUserResolver(function () {
            return new Customer(['employee_type' => Customer::TYPE_SR]);
        });

        $response = (new EnsureSalesRepresentativeApiUser())->handle($request, function () {
            return response()->json(['success' => true]);
        });

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_dsr_assignment_uses_the_one_to_one_sr_foreign_key(): void
    {
        $dsr = new Customer(['employee_type' => Customer::TYPE_DSR]);
        $sr = new Customer(['employee_type' => Customer::TYPE_SR]);

        $dsr->setRelation('assignedSalesRepresentative', $sr);

        $this->assertSame($sr, $dsr->assignedSalesRepresentative);
        $this->assertSame(
            'assigned_sr_customer_id',
            $dsr->assignedSalesRepresentative()->getForeignKeyName()
        );
        $this->assertSame(
            'assigned_sr_customer_id',
            $sr->deliverySalesRepresentative()->getForeignKeyName()
        );
    }
}
