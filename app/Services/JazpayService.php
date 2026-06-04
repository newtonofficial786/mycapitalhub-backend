<?php

require_once __DIR__ . '/../../bootstrap.php';

class JazpayService
{
    private $merchantId;
    private $apiKey;
    private $apiBase;
    private $callbackUrl;

    public function __construct()
    {
        $this->merchantId = env('JAZPAY_MERCHANT_ID', '');
        $this->apiKey = env('JAZPAY_API_KEY', '');
        $this->apiBase = rtrim(env('JAZPAY_API_BASE', 'https://api.jazpays.com'), '/');
        $this->callbackUrl = env('JAZPAY_CALLBACK_URL', '');
    }

    /**
     * Generate MD5 signature for Jazpay API requests.
     *
     * Rules:
     * 1. Sort parameters by key alphabetically (ascending)
     * 2. Concatenate as key=value&key=value&...
     * 3. Append &key=YOUR_API_KEY
     * 4. MD5 hash the result
     */
    public function generateSignature(array $params): string
    {
        ksort($params);

        $signStr = '';
        foreach ($params as $key => $value) {
            $signStr .= $key . '=' . $value . '&';
        }

        $signStr .= 'key=' . $this->apiKey;

        return md5($signStr);
    }

    /**
     * Verify signature from Jazpay callback.
     * Removes signature field, re-generates hash, and compares.
     */
    public function verifyCallbackSignature(array $data): bool
    {
        if (!isset($data['signature'])) {
            return false;
        }

        $receivedSign = $data['signature'];
        unset($data['signature']);

        $params = [
            'merchant_id' => $this->merchantId,
            'amount' => $data['amount'] ?? '0',
            'merchant_order_no' => $data['merchantOrder'] ?? '',
            'callback_url' => $this->callbackUrl,
        ];

        $calculatedSign = $this->generateSignature($params);

        return $calculatedSign === $receivedSign;
    }

    /**
     * Make HTTP POST request to Jazpay API.
     */
    private function makeRequest(string $endpoint, array $data): array
    {
        $url = $this->apiBase . $endpoint;

        error_log('[Jazpay Request] URL: ' . $url);
        error_log('[Jazpay Request] Data: ' . json_encode($data));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        error_log('[Jazpay Response] HTTP: ' . $httpCode);
        error_log('[Jazpay Response] Body: ' . $response);
        if ($error) {
            error_log('[Jazpay Error] ' . $error);
        }

        if ($error) {
            return ['success' => false, 'error' => 'Request failed: ' . $error];
        }

        $result = json_decode($response, true);
        if (!$result) {
            return ['success' => false, 'error' => 'Invalid response from Jazpay'];
        }

        if ($httpCode !== 200 || (isset($result['success']) && $result['success'] === false)) {
            return [
                'success' => false,
                'error' => $result['message'] ?? $result['msg'] ?? 'Payment gateway error. Please try again.',
                'code' => $httpCode,
            ];
        }

        return ['success' => true, 'data' => $result];
    }

    /**
     * Create a payment order.
     *
     * Required params:
     * - amount: Transaction amount
     * - order_id: Unique merchant order ID
     * - callback_url: Server endpoint for status updates
     */
    public function createPaymentOrder(array $orderData): array
    {
        $merchantOrderNo = $orderData['order_id'];
        $amount = number_format($orderData['amount'], 2, '.', '');
        $callbackUrl = $orderData['callback_url'] ?? $this->callbackUrl;
        $returnUrl = $orderData['return_url'] ?? '';

        $signatureParams = [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'merchant_order_no' => $merchantOrderNo,
            'callback_url' => $callbackUrl,
        ];

        $signature = $this->generateSignature($signatureParams);

        $payload = $signatureParams;
        $payload['api_key'] = $this->apiKey;
        $payload['signature'] = $signature;

        if (!empty($returnUrl)) {
            $payload['return_url'] = $returnUrl;
        }

        return $this->makeRequest('/v1/create', $payload);
    }

    /**
     * Query an order status.
     */
    public function queryOrder(string $merchantOrderNo): array
    {
        $params = [
            'merchant_id' => $this->merchantId,
            'merchant_order_no' => $merchantOrderNo,
        ];

        $signature = $this->generateSignature($params);

        $payload = $params;
        $payload['api_key'] = $this->apiKey;
        $payload['signature'] = $signature;

        return $this->makeRequest('/v1/query', $payload);
    }

    /**
     * Parse and validate callback data.
     */
    public function handleCallback(array $callbackData): array
    {
        if (!$this->verifyCallbackSignature($callbackData)) {
            return ['success' => false, 'error' => 'Invalid signature'];
        }

        return [
            'success' => true,
            'orderNo' => $callbackData['orderNo'] ?? '',
            'merchantOrder' => $callbackData['merchantOrder'] ?? '',
            'status' => $callbackData['status'] ?? '',
            'amount' => $callbackData['amount'] ?? 0,
        ];
    }
}
