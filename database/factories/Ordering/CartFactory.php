<?php

declare(strict_types=1);

namespace Database\Factories\Ordering;

use App\Domain\Onboarding\Models\Company;
use App\Domain\Ordering\Enums\CartStatus;
use App\Domain\Ordering\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Cart>
 */
class CartFactory extends Factory
{
    protected $model = Cart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'company_id' => Company::factory()->approved(),
            // User belongs to the same company as the cart.
            'user_id' => fn (array $attributes): int => User::factory()->create(['company_id' => $attributes['company_id']])->id,
            'status' => CartStatus::Open,
        ];
    }
}
