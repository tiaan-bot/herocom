<?php

declare(strict_types=1);

namespace Database\Factories\Billing;

use App\Domain\Billing\Enums\InvoiceStatus;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Onboarding\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 10000);
        $tax = round($subtotal * 0.15, 2);

        return [
            'company_id' => Company::factory()->approved(),
            'order_id' => null,
            'zoho_invoice_id' => (string) $this->faker->unique()->numerify('############'),
            'zoho_customer_id' => (string) $this->faker->unique()->numerify('##########'),
            'invoice_number' => 'INV-'.$this->faker->unique()->numerify('######'),
            'status' => InvoiceStatus::Sent,
            'invoice_date' => now()->subDays(5)->toDateString(),
            'due_date' => now()->addDays(25)->toDateString(),
            'subtotal_ex_vat' => $subtotal,
            'tax_total' => $tax,
            'total' => $subtotal + $tax,
            'balance' => $subtotal + $tax,
            'currency' => 'ZAR',
            'payment_url' => 'https://invoice.zoho.com/pay/'.$this->faker->uuid(),
            'last_synced_at' => now(),
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (): array => ['status' => InvoiceStatus::Paid, 'balance' => 0]);
    }

    public function overdue(): static
    {
        return $this->state(fn (): array => [
            'status' => InvoiceStatus::Overdue,
            'due_date' => now()->subDays(3)->toDateString(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => InvoiceStatus::Draft]);
    }
}
