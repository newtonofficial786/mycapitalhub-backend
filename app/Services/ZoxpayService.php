<?php

require_once __DIR__ . '/../../bootstrap.php';

class ZoxpayService
{
    private $merchantId;
    private $apiKey;
    private $apiBase;
    private $callbackUrl;

    public function __construct()
    {
        $this->merchantId = env('ZOXPAY_MERCHANT_ID', '');
        $this->apiKey = env('ZOXPAY_API_KEY', '');
        $this->apiBase = rtrim(env('ZOXPAY_API_BASE', 'https://api.zoxpays.com'), '/');
        $this->callbackUrl = env('ZOXPAY_CALLBACK_URL', '');
    }

    private function makeRequest(string $endpoint, array $data): array
    {
        $url = $this->apiBase . $endpoint;

        error_log('[Zoxpay Request] URL: ' . $url);
        error_log('[Zoxpay Request] Data: ' . json_encode($data));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        error_log('[Zoxpay Response] HTTP: ' . $httpCode);
        error_log('[Zoxpay Response] Body: ' . $response);
        if ($error) {
            error_log('[Zoxpay Error] ' . $error);
        }

        if ($error) {
            return ['success' => false, 'error' => 'Request failed: ' . $error];
        }

        $result = json_decode($response, true);
        if (!$result) {
            error_log('[Zoxpay] Raw response was not JSON: ' . substr($response, 0, 500));
            return ['success' => false, 'error' => 'Invalid response from Zoxpay: ' . substr($response, 0, 200)];
        }

        error_log('[Zoxpay] Parsed response: ' . json_encode($result));

        $statusOk = isset($result['success']) && ($result['success'] === true || $result['success'] === 1 || $result['success'] === 'true' || $result['success'] === '1');

        if ($httpCode !== 200 || !$statusOk) {
            $apiMsg = $result['message'] ?? $result['msg'] ?? json_encode($result);
            return [
                'success' => false,
                'error' => substr($apiMsg, 0, 200),
                'code' => $httpCode,
            ];
        }

        return ['success' => true, 'data' => $result];
    }

    public function createPaymentOrder(array $orderData): array
    {
        $orderNo = $orderData['order_no'];
        $amount = intval($orderData['amount']);
        $callbackUrl = $orderData['callback_url'] ?? $this->callbackUrl;
        $returnUrl = $orderData['return_url'] ?? '';

        $params = [
            'merchant_id' => $this->merchantId,
            'api_key' => $this->apiKey,
            'order_no' => $orderNo,
            'amount' => $amount,
            'callback_url' => $callbackUrl,
        ];

        if (!empty($returnUrl)) {
            $params['return_url'] = $returnUrl;
        }

        return $this->makeRequest('/payin.php', $params);
    }

    public function handleCallback(array $data): array
    {
        $required = ['merchant_id', 'order_no', 'amount'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return ['success' => false, 'error' => 'Missing field: ' . $field];
            }
        }

        if ($data['merchant_id'] !== $this->merchantId) {
            return ['success' => false, 'error' => 'Merchant ID mismatch'];
        }

        $isSuccess = isset($data['success']) && ($data['success'] === true || $data['success'] === 1 || $data['success'] === 'true' || $data['success'] === '1');

        return [
            'success' => true,
            'orderNo' => $data['order_no'],
            'amount' => floatval($data['amount']),
            'status' => $isSuccess ? 'success' : 'pending',
        ];
    }
}
