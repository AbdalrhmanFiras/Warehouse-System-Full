<?php

namespace App\Http\Requests;

use Illuminate\Support\Facades\Auth;
use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = Auth::user();
        if ($user->hasRole('super_admin')) {
            return [
                'customer_name' => 'required|string',
                'customer_phone' => 'required|string|min:11',
                'customer_address' => 'required|string',
                'total_price' => 'required|numeric|min:0',
                'warehouse_id' => 'required|exists:warehouses,id',
                'merchant_id' => 'required|exists:merchants,id'
            ];
        }
        return [
            'customer_name' => 'required|string',
            'customer_phone' => 'required|string|min:11',
            'customer_address' => 'required|string',
            'total_price' => 'required|numeric|min:0',
            'warehouse_id' => 'required|exists:warehouses,id'

        ];
    }




    public function getMerchantId(): string
    {
        $user = Auth::user();

        if ($user->hasRole('merchant')) {
            return $user->merchant->id;
        }

        if ($user->hasRole('super_admin')) {
            $merchantId = $this->input('merchant_id');
            if (!$merchantId) {
                abort(response()->json([
                    'message' => 'Merchant ID is required for Super Admin.'
                ], 422));
            }
            return $merchantId;
        }

        abort(response()->json([
            'message' => 'Unauthorized access.'
        ], 403));
    }
}
