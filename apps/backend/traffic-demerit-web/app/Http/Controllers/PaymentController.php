<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Violation;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    // Flat fine rate per demerit point — tune or replace with a lookup table.
    protected int $finePerPoint = 500; // KES

    /**
     * Initiates a mock M-Pesa STK push, mirroring eCitizen's payment UX.
     * Replace the transaction_ref generation with Daraja API integration
     * when you're ready to go beyond the prototype.
     */
    public function initiate(Request $request, Violation $violation)
    {
        $amount = $violation->points_assigned * $this->finePerPoint;

        $payment = Payment::create([
            'violation_id' => $violation->id,
            'driver_id' => $violation->driver_id,
            'amount' => $amount,
            'method' => $request->input('method', 'mpesa'),
            'status' => 'pending',
            'transaction_ref' => 'MOCK-' . Str::upper(Str::random(10)),
        ]);

        return response()->json([
            'payment' => $payment,
            'message' => 'Payment initiated. Awaiting verification before the driver is cleared.',
            'requires_verification' => true,
        ], 201);
    }

    public function verify(Request $request, Payment $payment)
    {
        $validated = $request->validate([
            'status' => 'required|in:completed,failed',
            'reference_number' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|max:10',
        ]);

        $payment->fill([
            'status' => $validated['status'],
            'reference_number' => $validated['reference_number'] ?? $payment->reference_number,
            'amount_paid' => $validated['amount'] ?? $payment->amount,
            'currency' => $validated['currency'] ?? $payment->currency ?? 'KES',
        ]);

        if ($validated['status'] === 'completed') {
            $payment->paid_at = $payment->paid_at ?? now();

            if ($payment->violation) {
                $payment->violation->update(['status' => 'confirmed']);
            }

            $driver = $payment->driver;
            if ($driver && $driver->status !== 'active') {
                $driver->update(['status' => 'active']);
            }
        }

        $payment->save();

        return response()->json($payment->fresh());
    }
}
