<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Servicio para manejar pagos con Stripe mediante HTTP API
 * Evita la dependencia de la SDK de PHP
 */
class StripeService
{
    private $secretKey;
    private $publicKey;
    private $baseUrl = 'https://api.stripe.com/v1';

    public function __construct()
    {
        $this->secretKey = config('services.stripe.secret');
        $this->publicKey = config('services.stripe.public');
    }

    /**
     * Crear un payment intent
     */
    public function createPaymentIntent($amount, $currency = 'usd', $metadata = [])
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->withoutVerifying()
                ->asForm()
                ->post("{$this->baseUrl}/payment_intents", [
                    'amount' => $amount,
                    'currency' => $currency,
                    'metadata' => $metadata,
                ])
                ->throw();

            return $response->json();
        } catch (Throwable $e) {
            Log::error('Stripe: Error creating payment intent', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener un payment intent
     */
    public function getPaymentIntent($paymentIntentId)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->withoutVerifying()
                ->get("{$this->baseUrl}/payment_intents/{$paymentIntentId}")
                ->throw();

            return $response->json();
        } catch (Throwable $e) {
            Log::error('Stripe: Error getting payment intent', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Confirmar un payment intent
     */
    public function confirmPaymentIntent($paymentIntentId, $paymentMethodId)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->withoutVerifying()
                ->asForm()
                ->post("{$this->baseUrl}/payment_intents/{$paymentIntentId}/confirm", [
                    'payment_method' => $paymentMethodId,
                ])
                ->throw();

            return $response->json();
        } catch (Throwable $e) {
            Log::error('Stripe: Error confirming payment intent', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Crear una sesión de checkout
     */
    public function createCheckoutSession($params = [])
    {
        try {
            $formData = [];
            
            // Convertir línea de items a formato Stripe
            if (isset($params['line_items'])) {
                foreach ($params['line_items'] as $index => $item) {
                    $formData["line_items[{$index}][price_data][currency]"] = $item['price_data']['currency'] ?? 'usd';
                    $formData["line_items[{$index}][price_data][product_data][name]"] = $item['price_data']['product_data']['name'] ?? '';
                    $formData["line_items[{$index}][price_data][product_data][description]"] = $item['price_data']['product_data']['description'] ?? '';
                    $formData["line_items[{$index}][price_data][unit_amount]"] = $item['price_data']['unit_amount'] ?? 0;
                    $formData["line_items[{$index}][quantity]"] = $item['quantity'] ?? 1;
                }
            }

            // Agregar otros parámetros
            $formData['mode'] = $params['mode'] ?? 'payment';
            $formData['success_url'] = $params['success_url'] ?? '';
            $formData['cancel_url'] = $params['cancel_url'] ?? '';
            $formData['payment_method_types[0]'] = 'card';

            // Agregar metadata
            if (isset($params['metadata'])) {
                foreach ($params['metadata'] as $key => $value) {
                    $formData["metadata[{$key}]"] = $value;
                }
            }

            $response = Http::withBasicAuth($this->secretKey, '')
                ->withoutVerifying()
                ->asForm()
                ->post("{$this->baseUrl}/checkout/sessions", $formData);

            Log::info('Stripe Checkout Session Response', [
                'status' => $response->status(),
                'response' => $response->json(),
            ]);

            $response->throw();

            return $response->json();
        } catch (Throwable $e) {
            Log::error('Stripe: Error creating checkout session', [
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'response' => method_exists($e, 'response') ? $e->response()?->json() : null,
                'response_body' => method_exists($e, 'response') ? $e->response()?->body() : null,
            ]);
            throw $e;
        }
    }

    /**
     * Obtener detalles de una sesión de checkout
     */
    public function getCheckoutSession($sessionId)
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->withoutVerifying()
                ->get("{$this->baseUrl}/checkout/sessions/{$sessionId}")
                ->throw();

            return $response->json();
        } catch (Throwable $e) {
            Log::error('Stripe: Error getting checkout session', [
                'session_id' => $sessionId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Reembolsar un cargo
     */
    public function createRefund($chargeId, $amount = null)
    {
        try {
            $params = ['charge' => $chargeId];
            if ($amount) {
                $params['amount'] = $amount;
            }

            $response = Http::withBasicAuth($this->secretKey, '')
                ->withoutVerifying()
                ->asForm()
                ->post("{$this->baseUrl}/refunds", $params)
                ->throw();

            return $response->json();
        } catch (Throwable $e) {
            Log::error('Stripe: Error creating refund', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener el cliente de Stripe (para balance, etc)
     */
    public function getAccount()
    {
        try {
            $response = Http::withBasicAuth($this->secretKey, '')
                ->withoutVerifying()
                ->get("{$this->baseUrl}/account")
                ->throw();

            return $response->json();
        } catch (Throwable $e) {
            Log::error('Stripe: Error getting account', [
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
