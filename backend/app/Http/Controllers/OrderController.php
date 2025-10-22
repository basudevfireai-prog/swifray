<?php

namespace App\Http\Controllers;

use App\Models\DriverEarning;
use App\Models\DriverProfile;
use App\Models\Order;
use Exception;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    // ===============================================
    //           CUSTOMER-FACING API METHODS
    // ===============================================

    /**
     * Customer Delivery Booking API.
     */
    public function bookDelivery(Request $request)
    {
        $request->validate([
            'delivery_type' => 'required|in:parcel,grocery,food,catering',
            'parcel_details' => 'required|string',
            'total_amount' => 'required|numeric|min:0.01',
            'pickup' => 'required|array',
            'dropoff' => 'required|array',
            'pickup.latitude' => 'required|numeric',
            'dropoff.latitude' => 'required|numeric',
            // ... validation for all required location fields
        ]);

        $customerID = $request->header('id');

        try {
            DB::beginTransaction();

            // 1. Create the Order
            $order = Order::create([
                'customer_id' => $customerID,
                'delivery_type' => $request->input('delivery_type'),
                'parcel_details' => $request->input('parcel_details'),
                'total_amount' => $request->input('total_amount'),
                'status' => 'pending', // Default status: waiting for payment
            ]);

            // 2. Create Order Locations (Pickup and Drop-off)
            $locations = [
                $request->input('pickup') + ['type' => 'pickup'],
                $request->input('dropoff') + ['type' => 'dropoff']
            ];

            foreach ($locations as $locationData) {
                $order->locations()->create($locationData);
            }

            // 3. Create initial Payment record (status: pending)
            $order->payment()->create([
                'amount' => $request->input('total_amount'),
                'status' => 'pending',
                // transaction_id will be updated after Stripe payment
                // 'transaction_id' => 'PENDING_' . $order->id . '_' . time(),
            ]);

            // 4. Create initial Tracking entry
            $order->tracking()->create([
                'status_code' => 'BOOKED',
                'status_message' => 'Order placed successfully. Awaiting payment.',
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order booked successfully. Please proceed to payment.',
                'order_id' => $order->id,
            ], 201);

        } catch (Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'failed',
                'message' => 'Failed to book delivery.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process Online Payment (Stripe).
     */
    public function completePayment(Request $request, $orderId)
    {
        $request->validate([
            'stripe_token' => 'required|string', // Token returned from Flutter/Stripe SDK
            // Note: In a real app, this should involve server-side Stripe logic
        ]);

        $customerID = $request->header('id');

        try {
            $order = Order::where('id', $orderId)
                          ->where('customer_id', $customerID)
                          ->firstOrFail();

            if ($order->payment->status === 'completed') {
                return response()->json(['status' => 'failed', 'message' => 'Payment already completed.'], 400);
            }

            // Simulate Stripe charge success and get a transaction ID
            $fakeTransactionId = 'txn_' . time() . rand(100, 999);

            // Update Payment record
            $order->payment->update([
                'transaction_id' => $fakeTransactionId,
                'status' => 'completed',
            ]);

            // Update Order status and tracking
            $order->update(['status' => 'accepted']); // Order is now ready for driver acceptance
            $order->tracking()->create([
                'status_code' => 'PAYMENT_COMPLETE',
                'status_message' => 'Payment successfully completed. Finding nearest driver.',
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Payment successful. Your delivery is being prepared.',
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'failed', 'message' => 'Order not found.'], 404);
        } catch (Exception $e) {
            // Log real payment gateway error here
            return response()->json(['status' => 'failed', 'message' => 'Payment processing failed.'], 500);
        }
    }

    /**
     * Get real-time order status tracking.
     */
    public function getOrderTracking($orderId)
    {
        $customerID = request()->header('id');

        try {
            $order = Order::where('id', $orderId)
                          ->where('customer_id', $customerID)
                          ->with('tracking')
                          ->firstOrFail();

            return response()->json([
                'status' => 'success',
                'order_status' => $order->status,
                'tracking_history' => $order->tracking()->orderBy('created_at', 'asc')->get(),
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'failed', 'message' => 'Order not found.'], 404);
        }
    }

    /**
     * Get Proof of Delivery (Photo/Signature).
     */
    public function getProofOfDelivery($orderId)
    {
        $customerID = request()->header('id');

        try {
            $order = Order::where('id', $orderId)
                          ->where('customer_id', $customerID)
                          ->firstOrFail();

            $proof = $order->proofOfDeliveries()->get();

            return response()->json([
                'status' => 'success',
                'proof' => $proof
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'failed', 'message' => 'Order not found.'], 404);
        }
    }

    // ===============================================
    //           DRIVER-FACING API METHODS
    // ===============================================

    /**
     * List available delivery jobs for drivers.
     */
    public function listAvailableJobs(Request $request)
    {
        $driverID = $request->header('id');

        // Ensure the driver is verified and available
        $driverProfile = DriverProfile::where('user_id', $driverID)->first();

        if (!$driverProfile || $driverProfile->document_status !== 'verified' || !$driverProfile->is_available) {
            return response()->json([
                'status' => 'failed',
                'message' => 'Driver is not verified or currently offline.'
            ], 403);
        }

        // Find orders that are paid and not yet assigned to a driver
        $jobs = Order::where('status', 'accepted') // 'accepted' status means payment is complete
                    ->whereNull('driver_id')
                    ->with('locations', 'customer')
                    // Add logic here to filter by proximity to the driver
                    ->limit(20)
                    ->get();

        return response()->json([
            'status' => 'success',
            'data' => $jobs
        ], 200);
    }

    /**
     * Driver accepts or rejects a delivery job.
     */
    public function handleJobAction(Request $request, $orderId)
    {
        $request->validate([
            'action' => 'required|in:accept,reject',
        ]);

        $driverID = $request->header('id');
        $action = $request->input('action');

        try {
            $order = Order::where('id', $orderId)
                          ->where('driver_id', 'null')
                          ->where('status', 'accepted')
                          ->firstOrFail();

            if ($action === 'accept') {
                $order->update([
                    'driver_id' => $driverID,
                    'status' => 'in_transit' // Driver is now en route to pickup
                ]);

                $order->tracking()->create([
                    'status_code' => 'DRIVER_ASSIGNED',
                    'status_message' => 'A driver has been assigned and is on the way to the pickup location.',
                ]);

                return response()->json(['status' => 'success', 'message' => 'Job accepted. Start navigation to pickup location.'], 200);

            } elseif ($action === 'reject') {
                // Optionally log rejection reason or leave status as 'accepted' for another driver
                return response()->json(['status' => 'success', 'message' => 'Job rejected.'], 200);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'failed', 'message' => 'Job not found or already assigned.'], 404);
        }
    }

    /**
     * Driver confirms successful pickup.
     */
    public function confirmPickup(Request $request, $orderId)
    {
        $request->validate([
            'photo' => 'nullable|string', // URL or base64 (if small)
            'signature' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $driverID = $request->header('id');

        try {
            $order = Order::where('id', $orderId)
                          ->where('driver_id', $driverID)
                          ->where('status', 'in_transit') // Should be in transit to pickup
                          ->firstOrFail();

            DB::beginTransaction();

            // 1. Store Proof of Pickup
            $order->proofOfDeliveries()->create([
                'type' => 'pickup',
                'photo_url' => $request->input('photo'),
                'signature_url' => $request->input('signature'),
                'notes' => $request->input('notes'),
            ]);

            // 2. Update Order Status
            $order->update(['status' => 'in_transit_to_dropoff']); // Use another status to differentiate stages

            // 3. Update Tracking
            $order->tracking()->create([
                'status_code' => 'PICKED_UP',
                'status_message' => 'The parcel has been picked up by the driver and is en route to the drop-off location.',
            ]);

            DB::commit();

            return response()->json(['status' => 'success', 'message' => 'Pickup confirmed. Start navigation to drop-off location.'], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'failed', 'message' => 'Order not found or not assigned to you.'], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'failed', 'message' => 'Failed to confirm pickup.'], 500);
        }
    }

    /**
     * Driver confirms successful final delivery.
     */
    public function confirmDelivery(Request $request, $orderId)
    {
        $request->validate([
            'photo' => 'required|string',
            'signature' => 'required|string',
            'notes' => 'nullable|string',
        ]);

        $driverID = $request->header('id');

        try {
            $order = Order::where('id', $orderId)
                          ->where('driver_id', $driverID)
                          ->where('status', 'in_transit_to_dropoff') // Should be in final stage
                          ->firstOrFail();

            DB::beginTransaction();

            // 1. Store Proof of Delivery
            $order->proofOfDeliveries()->create([
                'type' => 'delivery',
                'photo_url' => $request->input('photo'),
                'signature_url' => $request->input('signature'),
                'notes' => $request->input('notes'),
            ]);

            // 2. Update Order Status
            $order->update(['status' => 'delivered']);

            // 3. Update Tracking
            $order->tracking()->create([
                'status_code' => 'DELIVERED',
                'status_message' => 'The order has been successfully delivered and completed.',
            ]);

            // 4. Create Driver Earning Record
            // Assuming driver commission logic calculates the final amount
            $earningAmount = $order->total_amount * 0.8; // Example: 80% commission

            DriverEarning::create([
                'driver_id' => $driverID,
                'order_id' => $orderId,
                'amount' => $earningAmount,
                'status' => 'pending', // Awaiting payout
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Delivery successfully confirmed.',
                'earnings_summary' => ['amount' => $earningAmount]
            ], 200);

        } catch (ModelNotFoundException $e) {
            return response()->json(['status' => 'failed', 'message' => 'Order not found or already completed.'], 404);
        } catch (Exception $e) {
            DB::rollBack();
            return response()->json(['status' => 'failed', 'message' => 'Failed to confirm delivery.'], 500);
        }
    }
}
