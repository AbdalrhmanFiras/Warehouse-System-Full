<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use App\Enums\OrderStatus;
use Illuminate\Http\Request;
use App\Models\DriverFeedback;
use App\Events\CustomerOtpLogin;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use Illuminate\Support\Facades\Auth;
use App\Http\Resources\OrderResource;
use Illuminate\Support\Facades\Cache;
use Tymon\JWTAuth\Exceptions\JWTException;
use App\Http\Requests\CustomerLoginRequest;
use App\Http\Requests\CustomerOrderRequest;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Requests\SubmitFeedbackRequest;
use Illuminate\Queue\Middleware\RateLimited;
use App\Http\Resources\CustomerOrderResource;
use App\Http\Requests\CustomerVerifyOtpRequest;
use App\Http\Requests\CustomerOrderTrackrRequest;
use App\Models\ComplaintReply;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CustomerController extends BaseController
{

    // test and role 
    public function login(CustomerLoginRequest $request)
    {
        $data = $request->validated();
        $user = Auth::user();
        if ($user && $user->customer && $user->customer->phone === $data['phone']) {
            return $this->successResponse('You are already logged in.', [
                'token' => JWTAuth::fromUser($user->customer),
            ]);
        }

        $customer = Customer::where('phone', $data['phone'])->first();


        if (!$customer) {
            $order = Order::where('customer_phone', $data['phone'])
                ->select('customer_name', 'customer_phone', 'customer_address')
                ->first();

            if (!$order) {
                return $this->errorResponse('No account or order history found for this phone number.', null, 404);
            }
            $customer = Customer::create([
                'phone' => $order->customer_phone,
                'name' => $order->customer_name,
                'customer_address' => $order->customer_address,
            ]);
        }

        if ($customer->is_verified) {
            $token = JWTAuth::fromUser($customer);
            return $this->successResponse('You are already verified.', ['token' => $token]);
        }
        $customer->assignRole('customer');
        if (RateLimiter::tooManyAttempts('otp:' . $data['phone'], 4)) {
            return $this->errorResponse('Too many OTP requests. Try again later.', null, 429);
        }
        RateLimiter::hit('otp:' . $data['phone'], 60);
        event(new CustomerOtpLogin($customer));
        return $this->successResponse('you will find the otp in laravel.log');
    }


    public function loginWithOutOrder(CustomerLoginRequest $request)
    {
        $data = $request->validated();

        $user = Auth::user();
        if ($user && $user->customer && $user->customer->phone === $data['phone']) {
            return $this->successResponse('You are already logged in.', [
                'token' => JWTAuth::fromUser($user->customer),
            ]);
        }
        $customer = Customer::where('phone', $data['phone'])->first();
        if (!$customer) {
            $order = Order::where('customer_phone', $data['phone'])
                ->select('customer_name', 'customer_phone', 'customer_address')
                ->first();

            if ($order) {
                $customer = Customer::create([
                    'phone' => $order->customer_phone,
                    'name' => $order->customer_name,
                    'customer_address' => $order->customer_address,
                ]);
            } else {
                $customer = Customer::create([
                    'phone' => $data['phone'],
                    'name' => $data['name'] ?? 'New Customer',
                    'customer_address' => $data['customer_address'] ?? null,
                ]);
                $customer->assignRole('customer');
            }
        }
        if ($customer->is_verified) {
            $token = JWTAuth::fromUser($customer);
            return $this->successResponse('You are already verified.', ['token' => $token]);
        }

        if (RateLimiter::tooManyAttempts('otp:' . $data['phone'], 4)) {
            return $this->errorResponse('Too many OTP requests. Try again later.', null, 429);
        }

        RateLimiter::hit('otp:' . $data['phone'], 60);
        event(new CustomerOtpLogin($customer));

        return $this->successResponse('OTP sent to your phone.');
    }


    public function verifyOtp(CustomerVerifyOtpRequest $request)
    {
        $data = $request->validated();
        $cacheKey = 'otp_' . $data['phone'];

        if (!Cache::has($cacheKey)) {
            return $this->errorResponse('OTP expired or not found.', null, 400);
        }
        $cachedOtp = Cache::get($cacheKey);

        if ($data['otp'] != $cachedOtp) {
            return $this->errorResponse('Invalid OTP.', null, 400);
        }

        Cache::forget($cacheKey);
        $customer = Customer::where('phone', $data['phone'])->first();
        if (!$customer) {
            return $this->errorResponse('Customer not found.', null, 404);
        }

        if (!$customer->is_verified) {
            $customer->is_verified = true;
            $customer->save();
        }
        $token = JWTAuth::fromUser($customer);

        return $this->successResponse(
            'OTP verified successfully.',
            ['token' => $token],
        );
    }


    public function trackOrder(CustomerOrderTrackrRequest $request)
    {
        $phone = Auth::guard('customer')->user()->phone;
        $data = $request->validated();
        $order = Order::where('tracking_number', $data['tracking_number'])
            ->first();

        if (!$order) {
            return $this->errorResponse('There is no orders', null, 404);
        }
        return new CustomerOrderResource($order);
    }


    public function cancelOrder(Request $request, $orderId)
    {
        $data = $request->validate(['reason' => 'required|string']);
        $customer = Auth::guard('customer')->user();
        $order = Order::id($orderId)->phone($customer->phone)
            ->where('status', [OrderStatus::AtWarehouse->value])
            ->first();
        if (!$order) {
            return $this->errorResponse('Order not found or cannot be cancelled.', null, 404);
        }
        $order->update(['status' => OrderStatus::Cancelled->value]);
        return $this->successResponse('Order has been cancelled successfully.');
    }

    public function getOrders()
    {
        $phone = Auth::guard('customer')->user()->phone;
        $customer = Auth::guard('customer')->user();
        if (!$customer) {
            return $this->errorResponse('Customer not found.', null, 404);
        }
        $orders = Order::phone($phone)
            ->latest()->paginate(10);

        if ($orders->isEmpty()) {
            return $this->errorResponse('No active orders found.', null, 404);
        }
        return  CustomerOrderResource::collection($orders);
    }


    public function getCompeleteOrders()
    {
        $phone = Auth::guard('customer')->user()->phone;
        $orders = Order::orderStatus(5)
            ->phone($phone)
            ->latest()->paginate(10);
        if ($orders->isEmpty()) {
            return $this->errorResponse('No delivered orders found.', null, 404);
        }
        return  CustomerOrderResource::collection($orders);
    }


    public function submitFeedback(SubmitFeedbackRequest $request)
    {
        $phone = Auth::guard('customer')->user()->phone;
        $data = $request->validated();
        try {
            $order = Order::where('tracking_number', $data['tracking_number'])
                ->where('customer_phone', $phone)
                ->orderStatus(5)
                ->firstOrFail();

            $order->feedback()->create([
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);

            DriverFeedback::create([
                'driver_id' => $order->driver_id,
                'order_id' => $order->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);

            $avgRating = DriverFeedback::where('driver_id', $order->driver_id)->avg('rating');
            $driver = $order->driver;
            $driver->rating = round($avgRating, 1);
            $driver->save();
            return $this->successResponse('Thanks for your feedback!');
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Order is not found.', null, 404);
        }
    }

    public function getResponse($complaintId)
    {
        if (!ComplaintReply::where('complaint_id', $complaintId)->exists()) {
            return $this->errorResponse('there is no response yet', null, 404);
        }

        $replies = ComplaintReply::where('complaint_id', $complaintId)->get();

        return response()->json($replies, 200);
    }
}
