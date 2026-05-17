<?php

require_once __DIR__ . '/../../bootstrap.php';

class GalePayService
{
    private $merchantId;
    private $merchantKey;
    private $apiBase;
    private $callbackUrl;
    private $channelCode;

    public function __construct()
    {
        $this->merchantId = env('GALEPAY_MERCHANT_ID', '');
        $this->merchantKey = env('GALEPAY_MERCHANT_KEY', '');
        $this->apiBase = rtrim(env('GALEPAY_API_BASE', 'https://gateway.openzpay.cc'), '/');
        $this->callbackUrl = env('GALEPAY_CALLBACK_URL', '');
        $this->channelCode = env('GALEPAY_CHANNEL_CODE', '300');
    }

    /**
     * Generate MD5 signature for GalePay API requests.
     *
     * Signature rules:
     * 1. Filter out empty/null values
     * 2. Sort parameters by ASCII (dictionary order)
     * 3. Join as key=value&key=value...
     * 4. Append &key=MERCHANT_KEY
     * 5. MD5 hash the result
     */
    public function generateSignature(array $params): string
    {
        $filtered = array_filter($params, function ($v) {
            return $v !== '' && $v !== null && $v !== false;
        });

        ksort($filtered);

        $signStr = '';
        foreach ($filtered as $key => $value) {
            $signStr .= $key . '=' . $value . '&';
        }

        $signStr .= 'key=' . $this->merchantKey;

        return md5($signStr);
    }

    /**
     * Verify signature from GalePay callback.
     * Dynamically sorts all received parameters (excluding 'sign') and validates.
     */
    public function verifyCallbackSignature(array $data): bool
    {
        if (!isset($data['sign'])) {
            return false;
        }

        $receivedSign = $data['sign'];
        unset($data['sign']);

        $calculatedSign = $this->generateSignature($data);

        return $calculatedSign === $receivedSign;
    }

    /**
     * Make HTTP POST request to GalePay API.
     */
    /**
     * Translate GalePay Chinese error messages to English.
     */
    private function translateError(string $msg): string
    {
        $translations = [
            '网关不存在' => 'Payment gateway not available. Please try again.',
            '签名错误' => 'Authentication failed. Please try again.',
            '订单号重复' => 'Order already exists. Please try with a different amount.',
            '金额错误' => 'Invalid amount. Please check and try again.',
            '商户号不存在' => 'Merchant account not found. Contact support.',
            '参数错误' => 'Invalid request parameters. Please try again.',
            '系统繁忙' => 'System busy. Please try again later.',
            '订单不存在' => 'Order not found.',
            '订单状态异常' => 'Order status abnormal. Contact support.',
            '支付失败' => 'Payment failed. Please try again.',
            '余额不足' => 'Insufficient balance.',
            '通道维护中' => 'Payment channel under maintenance. Please try later.',
        ];

        foreach ($translations as $chinese => $english) {
            if (strpos($msg, $chinese) !== false) {
                return $english;
            }
        }

        return $msg;
    }

    private function makeRequest(string $endpoint, array $data): array
    {
        $url = $this->apiBase . $endpoint;

        error_log('[GalePay Request] URL: ' . $url);
        error_log('[GalePay Request] Data: ' . json_encode($data));

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

        error_log('[GalePay Response] HTTP: ' . $httpCode);
        error_log('[GalePay Response] Body: ' . $response);
        if ($error) {
            error_log('[GalePay Error] ' . $error);
        }

        if ($error) {
            return ['success' => false, 'error' => 'Request failed: ' . $error];
        }

        $result = json_decode($response, true);
        if (!$result) {
            return ['success' => false, 'error' => 'Invalid response from GalePay'];
        }

        if ($httpCode !== 200 || isset($result['code']) && $result['code'] !== 200) {
            return [
                'success' => false,
                'error' => $this->translateError($result['msg'] ?? 'Payment gateway error. Please try again.'),
                'code' => $result['code'] ?? $httpCode,
            ];
        }

        return ['success' => true, 'data' => $result['data'] ?? $result];
    }

    /**
     * Create a PayIn (recharge) order.
     *
     * Required params:
     * - amount: Payment amount in INR
     * - order_id: Merchant order ID (unique)
     * - callback_url: Webhook URL for payment notifications
     * - return_url: URL to redirect after payment
     * - phone: User phone number
     * - email: User email
     * - channel_code: Payment channel (300=test, 301=prod India, 302=native India)
     */
    public function createPayIn(array $orderData): array
    {
        $amount = number_format($orderData['amount'], 2, '.', '');
        $mchOrderId = $orderData['order_id'];
        $callbackUrl = $orderData['callback_url'] ?? $this->callbackUrl;
        $returnUrl = $orderData['return_url'] ?? $callbackUrl;
        $phone = $orderData['phone'] ?? '9102380668';
        $email = $orderData['email'] ?? 'user@example.com';
        $channelCode = $orderData['channel_code'] ?? $this->channelCode;

        $params = [
            'mchId' => $this->merchantId,
            'mchOrderId' => $mchOrderId,
            'amount' => $amount,
            'channelCode' => $channelCode,
            'returnUrl' => $returnUrl,
            'notifyUrl' => $callbackUrl,
            'email' => $email,
            'phone' => $phone,
        ];

        $sign = $this->generateSignature($params);
        $params['sign'] = $sign;

        return $this->makeRequest('/v2/api/payGate/payin/create', $params);
    }

    /**
     * Query a PayIn order status.
     */
    public function queryPayIn(string $mchOrderId): array
    {
        $params = [
            'mchId' => $this->merchantId,
            'mchOrderId' => $mchOrderId,
        ];

        $sign = $this->generateSignature($params);
        $params['sign'] = $sign;

        return $this->makeRequest('/v2/api/payGate/payin/query', $params);
    }

    /**
     * Create a Payout (withdrawal) order.
     */
    public function createPayout(array $payoutData): array
    {
        $params = [
            'mchId' => $this->merchantId,
            'mchOrderId' => $payoutData['order_id'],
            'amount' => number_format($payoutData['amount'], 2, '.', ''),
            'accountName' => $payoutData['account_name'] ?? '',
            'accountNo' => $payoutData['account_no'] ?? '',
            'ifsc' => $payoutData['ifsc'] ?? '',
            'phone' => $payoutData['phone'] ?? '',
            'notifyUrl' => $payoutData['callback_url'] ?? $this->callbackUrl,
        ];

        $sign = $this->generateSignature($params);
        $params['sign'] = $sign;

        return $this->makeRequest('/v2/api/payGate/payout/create', $params);
    }

    /**
     * Query a Payout order status.
     */
    public function queryPayout(string $mchOrderId): array
    {
        $params = [
            'mchId' => $this->merchantId,
            'mchOrderId' => $mchOrderId,
        ];

        $sign = $this->generateSignature($params);
        $params['sign'] = $sign;

        return $this->makeRequest('/v2/api/payGate/payout/query', $params);
    }

    /**
     * Query merchant balance.
     */
    public function getBalance(): array
    {
        $params = [
            'mchId' => $this->merchantId,
        ];

        $sign = $this->generateSignature($params);
        $params['sign'] = $sign;

        return $this->makeRequest('/v2/api/payGate/balance', $params);
    }

    /**
     * Query if a UPI ID belongs to GalePay.
     */
    public function queryUpi(string $upi): array
    {
        $params = [
            'mchId' => $this->merchantId,
            'upi' => $upi,
        ];

        $sign = $this->generateSignature($params);
        $params['sign'] = $sign;

        return $this->makeRequest('/v2/api/payGate/queryUpi', $params);
    }

    /**
     * Query UTR status.
     */
    public function queryUtr(string $utr): array
    {
        $params = [
            'mchId' => $this->merchantId,
            'utr' => $utr,
        ];

        $sign = $this->generateSignature($params);
        $params['sign'] = $sign;

        return $this->makeRequest('/v2/api/payGate/queryUtr', $params);
    }

    /**
     * Submit UTR for manual verification.
     */
    public function submitUtr(string $utr, string $mchOrderId): array
    {
        $params = [
            'mchId' => $this->merchantId,
            'mchOrderId' => $mchOrderId,
            'utr' => $utr,
        ];

        $sign = $this->generateSignature($params);
        $params['sign'] = $sign;

        return $this->makeRequest('/v2/api/payGate/submitUtr', $params);
    }

    /**
     * Parse and validate callback data.
     * Returns normalized payment data if signature is valid.
     */
    public function handleCallback(array $callbackData): array
    {
        if (!$this->verifyCallbackSignature($callbackData)) {
            return ['success' => false, 'error' => 'Invalid signature'];
        }

        return [
            'success' => true,
            'orderNo' => $callbackData['orderNo'] ?? '',
            'mchOrderId' => $callbackData['mchOrderId'] ?? '',
            'amount' => $callbackData['amount'] ?? 0,
            'payStatus' => $callbackData['payStatus'] ?? '0',
            'payTime' => $callbackData['payTime'] ?? '',
            'utr' => $callbackData['utr'] ?? '',
        ];
    }
}
