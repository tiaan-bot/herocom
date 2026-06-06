<?php

declare(strict_types=1);

namespace Database\Factories\Ordering;

use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Enums\OrderStatus;
use App\Domain\Ordering\Enums\ZohoPushStatus;
use App\Domain\Ordering\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory()->approved(),
            'placed_by' => fn (array $attributes): int => User::factory()->create(['company_id' => $attributes['company_id']])->id,
            'order_number' => fn (): string => 'HD-'.fake()->unique()->numerify('######'),
            'status' => OrderStatus::Placed,
            'subtotal_ex_vat' => 1000,
            'currency' => 'ZAR',
            'discount_percent_applied' => 0,
            'delivery_address_line1' => fake()->streetAddress(),
            'delivery_city' => 'Johannesburg',
            'delivery_province' => 'Gauteng',
            'delivery_postal_code' => '2000',
            'delivery_country_code' => 'ZA',
            'zoho_push_status' => ZohoPushStatus::Pending,
        ];
    }

    public function pushed(): static
    {
        return $this->state(fn (): array => [
            'zoho_push_status' => ZohoPushStatus::Pushed,
            'zoho_salesorder_id' => (string) fake()->unique()->numerify('##########'),
            'zoho_pushed_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (): array => [
            'zoho_push_status' => ZohoPushStatus::Failed,
            'zoho_push_error' => 'Zoho API request failed (HTTP 500).',
        ]);
    }
}
