<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Domain\Ordering\DataTransferObjects\PlaceOrderData;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // route is gated by auth + approved reseller + can:place_orders
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'delivery_address_line1' => ['required', 'string', 'max:255'],
            'delivery_address_line2' => ['nullable', 'string', 'max:255'],
            'delivery_city' => ['required', 'string', 'max:255'],
            'delivery_province' => ['required', 'string', 'max:255'],
            'delivery_postal_code' => ['required', 'string', 'max:20'],
            'delivery_country_code' => ['nullable', 'string', 'size:2'],
            'customer_note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function toData(): PlaceOrderData
    {
        return new PlaceOrderData(
            deliveryAddressLine1: (string) $this->string('delivery_address_line1'),
            deliveryCity: (string) $this->string('delivery_city'),
            deliveryProvince: (string) $this->string('delivery_province'),
            deliveryPostalCode: (string) $this->string('delivery_postal_code'),
            deliveryAddressLine2: $this->input('delivery_address_line2'),
            deliveryCountryCode: (string) ($this->input('delivery_country_code') ?? 'ZA'),
            customerNote: $this->input('customer_note'),
        );
    }
}
