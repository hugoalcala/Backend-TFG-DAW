<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use App\Services\StripeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class PaymentController extends Controller
{
    protected $stripeService;

    public function __construct(StripeService $stripeService)
    {
        $this->stripeService = $stripeService;
    }

    /**
     * Obtener la clave pública de Stripe
     */
    public function getStripePublicKey()
    {
        try {
            return response()->json([
                'public_key' => config('services.stripe.public'),
            ]);
        } catch (Throwable $exception) {
            Log::error('Error getting Stripe public key', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Unable to get Stripe key',
            ], 500);
        }
    }

    /**
     * Verificar que las claves de Stripe están configuradas correctamente
     */
    public function testStripeKeys()
    {
        try {
            $publicKey = config('services.stripe.public');
            $secretKey = config('services.stripe.secret');
            
            Log::info('Stripe Keys Test', [
                'public_key_length' => strlen($publicKey),
                'public_key_prefix' => substr($publicKey, 0, 20),
                'secret_key_length' => strlen($secretKey),
                'secret_key_prefix' => substr($secretKey, 0, 20),
            ]);

            // Test connection to Stripe
            $account = $this->stripeService->getAccount();
            
            return response()->json([
                'success' => true,
                'public_key_length' => strlen($publicKey),
                'secret_key_length' => strlen($secretKey),
                'account' => $account,
            ]);
        } catch (Throwable $exception) {
            Log::error('Stripe Keys Test Failed', [
                'message' => $exception->getMessage(),
                'public_key_length' => strlen(config('services.stripe.public')),
                'secret_key_length' => strlen(config('services.stripe.secret')),
            ]);

            return response()->json([
                'success' => false,
                'error' => $exception->getMessage(),
                'public_key_length' => strlen(config('services.stripe.public')),
                'secret_key_length' => strlen(config('services.stripe.secret')),
            ], 400);
        }
    }

    /**
     * Crear un payment intent para una contratación
     */
    public function createPaymentIntent(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'hours' => 'required|integer|min:1|max:100',
        ]);

        try {
            $student = $request->user();
            $teacher = User::findOrFail($validated['teacher_id']);
            $hours = $validated['hours'];

            // Verificar que el profesor esté aprobado
            if ($teacher->role !== 'teacher' || $teacher->teacher_status !== 'approved') {
                return response()->json([
                    'error' => 'Este profesor no está disponible',
                ], 400);
            }

            // Verificar que el estudiante no sea el mismo profesor
            if ($student->id === $teacher->id) {
                return response()->json([
                    'error' => 'No puedes contratarte a ti mismo',
                ], 400);
            }

            // Calcular el total
            $pricePerHour = $teacher->price_per_hour ?? 30;
            $totalAmount = (int)($pricePerHour * $hours * 100); // Stripe usa centavos

            // Crear payment intent con StripeService
            $paymentIntent = $this->stripeService->createPaymentIntent(
                $totalAmount,
                'usd',
                [
                    'student_id' => (string)$student->id,
                    'teacher_id' => (string)$teacher->id,
                    'hours' => (string)$hours,
                    'price_per_hour' => (string)$pricePerHour,
                ]
            );

            // Crear registro de booking inicial
            $booking = Booking::create([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'hours' => $hours,
                'price_per_hour' => $pricePerHour,
                'total_amount' => $pricePerHour * $hours,
                'stripe_payment_intent_id' => $paymentIntent['id'],
                'payment_status' => 'pending',
                'status' => 'pending',
            ]);

            return response()->json([
                'client_secret' => $paymentIntent['client_secret'],
                'payment_intent_id' => $paymentIntent['id'],
                'booking_id' => $booking->id,
                'total_amount' => $pricePerHour * $hours,
                'currency' => 'USD',
            ]);
        } catch (Throwable $exception) {
            Log::error('Error creating payment intent', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error creando intención de pago',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Crear sesión de checkout de Stripe
     */
    public function createCheckoutSession(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'required|exists:users,id',
            'hours' => 'required|integer|min:1|max:100',
        ]);

        try {
            $student = $request->user();
            $teacher = User::findOrFail($validated['teacher_id']);
            $hours = $validated['hours'];

            // Verificar validaciones
            if ($teacher->role !== 'teacher' || $teacher->teacher_status !== 'approved') {
                return response()->json([
                    'error' => 'Este profesor no está disponible',
                ], 400);
            }

            if ($student->id === $teacher->id) {
                return response()->json([
                    'error' => 'No puedes contratarte a ti mismo',
                ], 400);
            }

            // Calcular precio
            $pricePerHour = $teacher->price_per_hour ?? 30;
            $totalAmount = $pricePerHour * $hours;

            // Crear booking
            $booking = Booking::create([
                'student_id' => $student->id,
                'teacher_id' => $teacher->id,
                'hours' => $hours,
                'price_per_hour' => $pricePerHour,
                'total_amount' => $totalAmount,
                'payment_status' => 'pending',
                'status' => 'pending',
            ]);

            // Preparar parámetros para checkout session
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            
            $sessionParams = [
                'payment_method_types' => ['card'],
                'line_items' => [
                    [
                        'price_data' => [
                            'currency' => 'usd',
                            'product_data' => [
                                'name' => "Contratar a {$teacher->name}",
                                'description' => "{$hours} hora(s) - Materia: {$teacher->subject}",
                            ],
                            'unit_amount' => (int)($pricePerHour * 100),
                        ],
                        'quantity' => $hours,
                    ],
                ],
                'mode' => 'payment',
                'success_url' => "{$frontendUrl}/bookings?booking_id={$booking->id}&confirmed=true",
                'cancel_url' => "{$frontendUrl}/bookings?booking_id={$booking->id}&cancelled=true",
                'metadata' => [
                    'booking_id' => (string)$booking->id,
                    'student_id' => (string)$student->id,
                    'teacher_id' => (string)$teacher->id,
                ],
            ];

            // Crear sesión de checkout
            $session = $this->stripeService->createCheckoutSession($sessionParams);

            // Guardar el session_id en el booking para referencia posterior
            $booking->update([
                'stripe_checkout_session_id' => $session['id'],
            ]);

            return response()->json([
                'session_id' => $session['id'],
                'checkout_url' => $session['url'],
                'booking_id' => $booking->id,
            ]);
        } catch (Throwable $exception) {
            Log::error('Error creating checkout session', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error creando sesión de pago',
                'message' => $exception->getMessage(),
            ], 500);
        }
    }

    /**
     * Confirmar sesión de checkout y aprobar automáticamente
     */
    public function confirmCheckoutSession(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|integer',
        ]);

        try {
            $bookingId = $validated['booking_id'];

            // Obtener el booking
            $booking = Booking::findOrFail($bookingId);

            // Verificar que el usuario es el propietario del booking
            if ($booking->student_id !== $request->user()->id) {
                return response()->json([
                    'error' => 'No autorizados',
                ], 403);
            }

            // Obtener la sesión de checkout de Stripe usando el stripe_checkout_session_id guardado
            if (!$booking->stripe_checkout_session_id) {
                return response()->json([
                    'error' => 'Sesión de checkout no encontrada',
                ], 400);
            }

            $session = $this->stripeService->getCheckoutSession($booking->stripe_checkout_session_id);

            // Verificar que el pago fue completado
            if ($session['payment_status'] !== 'paid') {
                return response()->json([
                    'error' => 'El pago no fue completado',
                    'status' => $session['payment_status'],
                ], 400);
            }

            // Aprobar automáticamente el booking
            $booking->update([
                'payment_status' => 'completed',
                'status' => 'confirmed',
            ]);

            // Incrementar contador de estudiantes del profesor solo si es el primer pago de este estudiante
            $otherConfirmedBookings = Booking::where('student_id', $booking->student_id)
                ->where('teacher_id', $booking->teacher_id)
                ->where('id', '!=', $booking->id)
                ->where('status', 'confirmed')
                ->count();

            if ($otherConfirmedBookings === 0) {
                // Es el primer pago de este estudiante con este profesor
                $teacher = User::findOrFail($booking->teacher_id);
                $teacher->increment('number_of_students');
                Log::info('Incrementado número de estudiantes', [
                    'teacher_id' => $teacher->id,
                    'teacher_name' => $teacher->name,
                    'student_id' => $booking->student_id,
                    'new_count' => $teacher->number_of_students + 1,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Contratación aprobada automáticamente',
                'booking' => $booking,
            ]);
        } catch (Throwable $exception) {
            Log::error('Error confirming checkout session', [
                'message' => $exception->getMessage(),
                'trace' => $exception->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Error confirmando sesión',
            ], 500);
        }
    }

    /**
    {
        $validated = $request->validate([
            'payment_intent_id' => 'required|string',
        ]);

        try {
            $paymentIntentId = $validated['payment_intent_id'];

            // Obtener el payment intent de Stripe
            $paymentIntent = $this->stripeService->getPaymentIntent($paymentIntentId);

            if ($paymentIntent['status'] !== 'succeeded') {
                return response()->json([
                    'error' => 'El pago no fue procesado correctamente',
                ], 400);
            }

            // Actualizar el booking
            $booking = Booking::where('stripe_payment_intent_id', $paymentIntentId)->first();

            if (!$booking) {
                return response()->json([
                    'error' => 'Contratación no encontrada',
                ], 404);
            }

            $chargeId = null;
            if (isset($paymentIntent['charges']['data'][0]['id'])) {
                $chargeId = $paymentIntent['charges']['data'][0]['id'];
            }

            $booking->update([
                'payment_status' => 'completed',
                'status' => 'confirmed',
                'stripe_charge_id' => $chargeId,
            ]);

            return response()->json([
                'message' => 'Pago confirmado exitosamente',
                'booking' => $booking,
            ]);
        } catch (Throwable $exception) {
            Log::error('Error confirming payment', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error confirmando pago',
            ], 500);
        }
    }

    /**
     * Obtener todas las contrataciones del usuario actual
     */
    public function getBookings(Request $request)
    {
        try {
            $user = $request->user();

            // Obtener bookings como estudiante
            $bookings = Booking::query()
                ->where('student_id', $user->id)
                ->orWhere('teacher_id', $user->id)
                ->with(['student:id,name,avatar_path', 'teacher:id,name,subject,avatar_path,price_per_hour'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'data' => $bookings,
            ]);
        } catch (Throwable $exception) {
            Log::error('Error getting bookings', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error obteniendo contrataciones',
            ], 500);
        }
    }

    /**
     * Obtener detalles de una contratación específica
     */
    public function getBookingDetails($bookingId, Request $request)
    {
        try {
            $user = $request->user();
            $booking = Booking::with(['student', 'teacher'])
                ->findOrFail($bookingId);

            // Verificar que el usuario sea parte de la contratación
            if ($booking->student_id !== $user->id && $booking->teacher_id !== $user->id) {
                return response()->json([
                    'error' => 'No tienes permiso para ver esta contratación',
                ], 403);
            }

            return response()->json([
                'data' => $booking,
            ]);
        } catch (Throwable $exception) {
            Log::error('Error getting booking details', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error obteniendo detalles de la contratación',
            ], 500);
        }
    }

    /**
     * Cancelar una contratación
     */
    public function cancelBooking($bookingId, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $user = $request->user();
            $booking = Booking::findOrFail($bookingId);

            // Verificar permisos
            if ($booking->student_id !== $user->id && $booking->teacher_id !== $user->id) {
                return response()->json([
                    'error' => 'No tienes permiso para cancelar esta contratación',
                ], 403);
            }

            // Verificar que pueda ser cancelada
            if (!$booking->canBeCancelled()) {
                return response()->json([
                    'error' => 'Esta contratación no puede ser cancelada',
                ], 400);
            }

            // Procesar reembolso si fue pagada
            if ($booking->isPaid() && $booking->stripe_charge_id) {
                try {
                    $this->stripeService->createRefund($booking->stripe_charge_id);
                    $booking->payment_status = 'refunded';
                } catch (Throwable $e) {
                    Log::error('Error processing refund', [
                        'message' => $e->getMessage(),
                    ]);
                    // Continuar con la cancelación aunque falle el reembolso
                }
            }

            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['reason'] ?? null,
                'cancelled_at' => now(),
            ]);

            // Decrementar contador de estudiantes si este era el único pago confirmado de este estudiante
            if ($booking->status === 'confirmed') {
                $otherConfirmedBookings = Booking::where('student_id', $booking->student_id)
                    ->where('teacher_id', $booking->teacher_id)
                    ->where('id', '!=', $booking->id)
                    ->where('status', 'confirmed')
                    ->count();

                if ($otherConfirmedBookings === 0) {
                    // Era el único pago de este estudiante con este profesor
                    $teacher = User::findOrFail($booking->teacher_id);
                    if ($teacher->number_of_students > 0) {
                        $teacher->decrement('number_of_students');
                    }
                }
            }

            return response()->json([
                'message' => 'Contratación cancelada exitosamente',
                'booking' => $booking,
            ]);
        } catch (Throwable $exception) {
            Log::error('Error canceling booking', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error cancelando contratación',
            ], 500);
        }
    }

    /**
     * Aceptar una solicitud de contratación pendiente (profesor)
     */
    public function acceptBooking($bookingId, Request $request)
    {
        try {
            $user = $request->user();
            $booking = Booking::with(['student', 'teacher'])->findOrFail($bookingId);

            // Verificar que el usuario es el profesor
            if ($booking->teacher_id !== $user->id) {
                return response()->json([
                    'error' => 'No tienes permiso para aceptar esta solicitud',
                ], 403);
            }

            // Verificar que la solicitud está pendiente
            if ($booking->status !== 'pending') {
                return response()->json([
                    'error' => 'Esta solicitud no está pendiente',
                ], 400);
            }

            // Cambiar estado a confirmado
            $booking->update([
                'status' => 'confirmed',
            ]);

            // Incrementar contador de estudiantes solo si es el primer pago de este estudiante
            $otherConfirmedBookings = Booking::where('student_id', $booking->student_id)
                ->where('teacher_id', $booking->teacher_id)
                ->where('id', '!=', $booking->id)
                ->where('status', 'confirmed')
                ->count();

            if ($otherConfirmedBookings === 0) {
                $user->increment('number_of_students');
                Log::info('Estudiante añadido al profesor', [
                    'teacher_id' => $user->id,
                    'teacher_name' => $user->name,
                    'student_id' => $booking->student_id,
                    'new_count' => $user->number_of_students + 1,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Solicitud aceptada exitosamente',
                'booking' => $booking,
            ]);
        } catch (Throwable $exception) {
            Log::error('Error accepting booking', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error aceptando solicitud',
            ], 500);
        }
    }

    /**
     * Rechazar una solicitud de contratación pendiente (profesor)
     */
    public function rejectBooking($bookingId, Request $request)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $user = $request->user();
            $booking = Booking::with(['student', 'teacher'])->findOrFail($bookingId);

            // Verificar que el usuario es el profesor
            if ($booking->teacher_id !== $user->id) {
                return response()->json([
                    'error' => 'No tienes permiso para rechazar esta solicitud',
                ], 403);
            }

            // Verificar que la solicitud está pendiente
            if ($booking->status !== 'pending') {
                return response()->json([
                    'error' => 'Esta solicitud no está pendiente',
                ], 400);
            }

            // Cambiar estado a rechazado
            $booking->update([
                'status' => 'cancelled',
                'cancellation_reason' => $validated['reason'] ?? 'Rechazado por el profesor',
                'cancelled_at' => now(),
            ]);

            // Si fue pagada, procesar reembolso
            if ($booking->payment_status === 'completed' && $booking->stripe_charge_id) {
                try {
                    $this->stripeService->createRefund($booking->stripe_charge_id);
                    $booking->update(['payment_status' => 'refunded']);
                } catch (Throwable $e) {
                    Log::error('Error processing refund on rejection', [
                        'message' => $e->getMessage(),
                    ]);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Solicitud rechazada',
                'booking' => $booking,
            ]);
        } catch (Throwable $exception) {
            Log::error('Error rejecting booking', [
                'message' => $exception->getMessage(),
            ]);

            return response()->json([
                'error' => 'Error rechazando solicitud',
            ], 500);
        }
    }
}
