<?php

use App\Enums\CustomerType;
use App\Enums\ImportStatus;
use App\Imports\CustomersImport;
use App\Models\Customer;
use App\Models\Import;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

test('customer can be created with customer type', function () {
    $customer = Customer::factory()->create([
        'name' => 'مهندس أحمد علي',
        'phone' => '01012345678',
        'type' => CustomerType::Engineer->value,
    ]);

    expect($customer->type)->toBe(CustomerType::Engineer);
    expect($customer->type->label())->toBe('مهندس / مكتب هندسي');
});

test('customers import parses customer types and normalizes excel phone numbers', function () {
    $user = User::factory()->create();

    $import = Import::create([
        'uploaded_by' => $user->id,
        'file_name' => 'test.xlsx',
        'file_path' => 'imports/test.xlsx',
        'status' => ImportStatus::Pending->value,
    ]);

    $importHandler = new CustomersImport($import);

    $rows = new Collection([
        new Collection(['اسم العميل', 'رقم الهاتف', 'تصنيف العميل']),
        new Collection(['مهندس طارق', '1098765432', 'مهندس']),
        new Collection(['شركة العالمية', '1298765432', 'شركة تشطيبات']),
        new Collection(['عميل فردي', '01198765432', 'عميل']),
    ]);

    $importHandler->collection($rows);

    $engCustomer = Customer::where('name', 'مهندس طارق')->first();
    expect($engCustomer)->not->toBeNull();
    expect($engCustomer->phone)->toBe('01098765432');
    expect($engCustomer->type)->toBe(CustomerType::Engineer);

    $compCustomer = Customer::where('name', 'شركة العالمية')->first();
    expect($compCustomer)->not->toBeNull();
    expect($compCustomer->type)->toBe(CustomerType::FinishingCompany);

    $clientCustomer = Customer::where('name', 'عميل فردي')->first();
    expect($clientCustomer)->not->toBeNull();
    expect($clientCustomer->type)->toBe(CustomerType::Client);
});
