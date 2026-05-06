<?php

class YoYoPayService
{
    private $merchantId;
    private $secretKey;
    private $countryCode;
    private $payType;
    private $gateway;
    private $callbackUrl;

    public function __construct()
    {
        $config = config('yoyopay');
        $this->merchantId = $config['merchant_id'] ?? '';
        $this->secretKey = $config['secret_key'] ?? '';
        $this->countryCode = $config['country_code'] ?? 'IN';
        $this->payType = $config['pay_type'] ?? 'IMPS';
        $this->gateway = rtrim($config['gateway'] ?? 'https://merchant.yoyopays.com', '/');
        $this->callbackUrl = $config['callback_url'] ?? '';
    }

    public function generateSign(array $params)
    {
        ksort($params);

        $signStr = '';
        foreach ($params as $key => $value) {
            if ($key === 'sign') continue;
            if ($value === null) continue;
            $signStr .= $key . '=' . $value . '&';
        }

        $signStr .= $this->secretKey;

        return md5($signStr);
    }

    private function makeRequest(string $endpoint, array $data)
    {
        $url = $this->gateway . $endpoint;

        $data['sign'] = $this->generateSign($data);

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
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            return ['code' => 500, 'msg' => 'Request failed: ' . $error, 'data' => null];
        }

        $result = json_decode($response, true);
        if (!$result) {
            return ['code' => 500, 'msg' => 'Invalid response from payment gateway', 'data' => null];
        }

        return $result;
    }

    public function checkBalance()
    {
        $params = [
            'merchantId' => $this->merchantId,
            'countryCode' => $this->countryCode,
        ];

        return $this->makeRequest('/api/order/amount/balance', $params);
    }

    public function createPayOrder(array $orderData)
    {
        $params = [
            'merchantId' => $this->merchantId,
            'transAmt' => $orderData['amount'],
            'merchantOrderId' => $orderData['order_id'],
            'payType' => $this->payType,
            'countryCode' => $this->countryCode,
            'ip' => $orderData['ip'] ?? '127.0.0.1',
            'orderRemark' => $orderData['remark'] ?? '',
        ];

        if (!empty($this->callbackUrl)) {
            $params['callbackUrl'] = $this->callbackUrl;
        }

        return $this->makeRequest('/api/order/api/payOrder/publicCreatePayOrder', $params);
    }

    public function queryPayOrder(string $merchantOrderId)
    {
        $params = [
            'merchantId' => $this->merchantId,
            'merchantOrderId' => $merchantOrderId,
        ];

        return $this->makeRequest('/api/order/api/payOrder/queryPayOrder', $params);
    }

    public function createWithdrawal(array $withdrawalData)
    {
        $params = [
            'account' => $withdrawalData['account'],
            'transAmt' => $withdrawalData['amount'],
            'bnkCode' => $withdrawalData['bank_code'] ?? $this->payType,
            'ip' => $withdrawalData['ip'] ?? '127.0.0.1',
            'merchantId' => $this->merchantId,
            'merchantOrderId' => $withdrawalData['order_id'],
            'name' => $withdrawalData['name'],
            'payType' => $this->payType,
            'countryCode' => $this->countryCode,
            'remark' => $withdrawalData['remark'] ?? '',
        ];

        if (!empty($withdrawalData['ifsc'])) {
            $params['ifsc'] = $withdrawalData['ifsc'];
        }

        if (!empty($this->callbackUrl)) {
            $params['callbackUrl'] = $this->callbackUrl;
        }

        return $this->makeRequest('/api/order/api/order/publicWithdrawal', $params);
    }

    public function queryWithdrawalOrder(string $merchantOrderId)
    {
        $params = [
            'merchantId' => $this->merchantId,
            'merchantOrderId' => $merchantOrderId,
        ];

        return $this->makeRequest('/api/order/api/order/queryWithdrawalOrder', $params);
    }

    public function getPaymentPageUrl(string $merchantOrderId)
    {
        $result = $this->queryPayOrder($merchantOrderId);

        if ($result['code'] === 200 && !empty($result['data'])) {
            return $result['data']['appLink'] ?? $result['data']['incomeCode'] ?? null;
        }

        return null;
    }
}
