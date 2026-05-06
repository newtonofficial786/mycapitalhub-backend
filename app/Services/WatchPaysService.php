<?php

require_once __DIR__ . '/../../bootstrap.php';

class WatchPaysService
{
    private $merchantId;
    private $apiKey;
    private $gateway = 'https://api.watchpays.com/v1';
    private $callbackUrl;

    public function __construct()
    {
        $this->merchantId = env('WATCHPAYS_MERCHANT_ID', '');
        $this->apiKey = env('WATCHPAYS_API_KEY', '');
        $this->callbackUrl = env('WATCHPAYS_CALLBACK_URL', '');
    }

    public function generateSignature(array $params)
    {
        $filtered = array_filter($params, function($v) {
            return $v !== '' && $v !== null;
        });

        ksort($filtered);

        $signStr = '';
        foreach ($filtered as $key => $value) {
            $signStr .= $key . '=' . $value . '&';
        }

        $signStr .= 'key=' . $this->apiKey;

        return md5($signStr);
    }

    private function makeRequest(string $endpoint, array $data)
    {
        $url = $this->gateway . $endpoint;

        error_log('[WatchPays Request] URL: ' . $url);
        error_log('[WatchPays Request] Data: ' . json_encode($data));

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log('[WatchPays Response] HTTP: ' . $httpCode . ', Error: ' . $error);
        error_log('[WatchPays Response] Body: ' . $response);

        if ($error) {
            return ['success' => false, 'error' => 'Request failed: ' . $error];
        }

        $result = json_decode($response, true);
        if (!$result) {
            return ['success' => false, 'error' => 'Invalid response: ' . substr($response, 0, 200)];
        }

        return $result;
    }

    public function createPaymentOrder(array $orderData)
    {
        $amount = number_format($orderData['amount'], 2, '.', '');
        $merchantOrderNo = $orderData['order_id'];
        $callbackUrl = $orderData['callback_url'] ?? $this->callbackUrl;
        $extra = $orderData['extra'] ?? '';

        $params = [
            'merchant_id' => $this->merchantId,
            'amount' => $amount,
            'merchant_order_no' => $merchantOrderNo,
            'callback_url' => $callbackUrl,
        ];

        if (!empty($extra)) {
            $params['extra'] = $extra;
        }

        $signature = $this->generateSignature($params);
        $params['signature'] = $signature;

        error_log('[WatchPays] Params: ' . json_encode($params));
        error_log('[WatchPays] Merchant ID: ' . $this->merchantId);
        error_log('[WatchPays] API Key set: ' . (!empty($this->apiKey) ? 'yes' : 'no'));
        error_log('[WatchPays] Signature: ' . $signature);

        return $this->makeRequest('/create', $params);
    }

    public function handleCallback(array $callbackData)
    {
        return [
            'orderNo' => $callbackData['orderNo'] ?? '',
            'merchantOrder' => $callbackData['merchantOrder'] ?? '',
            'status' => $callbackData['status'] ?? '',
            'amount' => $callbackData['amount'] ?? 0,
        ];
    }
}
