<?php

namespace App\Services;

use GuzzleHttp\Client;
use Exception;

class PayPalService
{
    protected $client;
    protected $baseUrl;
    protected $clientId;
    protected $secret;

    public function __construct()
    {
        $this->client = new Client();
        $mode = config('services.paypal.mode', 'sandbox');
        $this->baseUrl = $mode === 'live' 
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
        
        $this->clientId = config('services.paypal.client_id');
        $this->secret = config('services.paypal.secret');
    }

    /**
     * Get access token
     */
    protected function getAccessToken()
    {
        try {
            $response = $this->client->post($this->baseUrl . '/v1/oauth2/token', [
                'auth' => [$this->clientId, $this->secret],
                'form_params' => [
                    'grant_type' => 'client_credentials',
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return $data['access_token'] ?? null;
        } catch (Exception $e) {
            \Log::error('PayPal Token Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create order
     */
    public function createOrder($amount, $currency = 'USD', $orderData = [])
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'error' => 'Failed to get access token'];
            }

            $response = $this->client->post($this->baseUrl . '/v2/checkout/orders', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'amount' => [
                                'currency_code' => $currency,
                                'value' => number_format($amount, 2, '.', ''),
                            ],
                            'description' => $orderData['description'] ?? 'Restaurant Order',
                        ],
                    ],
                    'application_context' => [
                        'return_url' => $orderData['return_url'] ?? config('app.url') . '/payment/success',
                        'cancel_url' => $orderData['cancel_url'] ?? config('app.url') . '/payment/cancel',
                    ],
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return [
                'success' => true,
                'order_id' => $data['id'],
                'data' => $data,
            ];
        } catch (Exception $e) {
            \Log::error('PayPal Create Order Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Capture order
     */
    public function captureOrder($orderId)
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'error' => 'Failed to get access token'];
            }

            $response = $this->client->post($this->baseUrl . "/v2/checkout/orders/{$orderId}/capture", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            \Log::error('PayPal Capture Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get order details
     */
    public function getOrderDetails($orderId)
    {
        try {
            $token = $this->getAccessToken();
            if (!$token) {
                return ['success' => false, 'error' => 'Failed to get access token'];
            }

            $response = $this->client->get($this->baseUrl . "/v2/checkout/orders/{$orderId}", [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                ],
            ]);

            $data = json_decode($response->getBody(), true);
            return [
                'success' => true,
                'data' => $data,
            ];
        } catch (Exception $e) {
            \Log::error('PayPal Get Order Error: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
