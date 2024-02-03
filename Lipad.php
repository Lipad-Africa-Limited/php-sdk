<?php

namespace Lipad\LipadSdk;
use Exception;

class Lipad
{
    private $IVKey;
    private $consumerSecret;
    private $environment;

    private $consumerKey;

    const CHECKOUT_BASE_URL = [
        'production' => 'https://api.lipad.io/api/v1',
        'sandbox' => 'https://uat.checkout-api.lipad.io/api/v1',
    ];
    const DIRECT_CHARGE_BASE_URL = [
        'production' => 'https://charge.lipad.io/v1',
        'sandbox' => 'https://dev.charge.lipad.io/v1',
    ];
    const DIRECT_API_AUTH_URL = [
        'production' => 'https://api.lipad.io/v1/auth',
        'sandbox' => 'https://dev.lipad.io/v1/auth',
    ];

    public function __construct($IVKey, $consumerSecret, $environment, $consumerKey)
    {
        $this->IVKey = $IVKey;
        $this->consumerSecret = $consumerSecret;
        $this->consumerKey = $consumerKey;

        if (empty($environment)) {
            echo "Error: Environment is required.\n";
            return;
        }

        $this->environment = $environment;

        if (!isset(self::CHECKOUT_BASE_URL[$this->environment]) ||
            !isset(self::DIRECT_CHARGE_BASE_URL[$this->environment]) ||
            !isset(self::DIRECT_API_AUTH_URL[$this->environment])) {
            echo "Invalid environment: {$this->environment}\n";
        }
    }

    /**
     * @throws Exception
     */
    public function validateCheckoutPayload($obj) {
        $requiredKeys = [
            "msisdn",
            "account_number",
            "country_code",
            "currency_code",
            "client_code",
            "due_date",
            "customer_email",
            "customer_first_name",
            "customer_last_name",
            "merchant_transaction_id",
            "preferred_payment_option_code",
            "callback_url",
            "request_amount",
            "request_description",
            "success_redirect_url",
            "fail_redirect_url",
            "invoice_number",
            "language_code",
            "service_code",
        ];
    foreach ($requiredKeys as $key) {
        if (!isset($obj[$key])) {
            throw new Exception("Missing required key: " . $key);
        }
    }
}
    public function encrypt($payload): string
    {
        $key = substr(hash('sha256', $this->IVKey), 0, 16);
        $secret = substr(hash('sha256', $this->consumerSecret), 0, 32);
        $cipher = openssl_encrypt(json_encode($payload), "AES-256-CBC", $secret, OPENSSL_RAW_DATA, $key);
        return base64_encode($cipher);
    }

    /**
     * @throws Exception
     */
    private function accessTokens($apiUrl, $postData)
    {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $headers = [
            'Content-Type: application/x-www-form-urlencoded',
            'Content-Length: ' . strlen($postData),
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($response === false) {
            throw new Exception("Failed to get access token: " . curl_error($ch));
        }

        curl_close($ch);

        $responseData = json_decode($response, true);
        if ($httpCode === 401) {
            echo "Invalid Credentials\n";
            throw new Exception("Invalid Credentials!");
        } elseif ($httpCode === 201) {
            if (isset($responseData['access_token'])) {
                return $responseData['access_token'];
            } else {
                throw new Exception("Access Token not found in response");
            }
        } else {
            throw new Exception("Failed to retrieve checkout status. Response Code: " . $httpCode);
        }
    }

    /**
     * @throws Exception
     */
    private function getDirectAPIAccessToken()
    {
        $authData = [
            'consumer_key' => $this->consumerKey,
            'consumer_secret' => $this->consumerSecret,
        ];

        $postData = http_build_query($authData);


        $apiUrl = self::DIRECT_API_AUTH_URL[$this->environment];
        return $this->accessTokens($apiUrl, $postData);
    }

    /**
     * @throws Exception
     */
    private function getAccessToken()
    {
        $authData = [
            'consumerKey' => $this->consumerKey,
            'consumerSecret' => $this->consumerSecret,
        ];

        $postData = http_build_query($authData);


        $apiUrl = self::CHECKOUT_BASE_URL[$this->environment] . "/api-auth/access-token";

        return $this->accessTokens($apiUrl, $postData);
    }


    /**
     * @throws Exception
     */
    private function getCheckoutStats($merchant_transaction_id, $access_token) {
        $apiUrl = self::CHECKOUT_BASE_URL[$this->environment] . "/checkout/request/status?merchant_transaction_id=$merchant_transaction_id";
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => 'Authorization: Bearer ' . $access_token
        ]
    ];
    $context = stream_context_create($options);

    $response = file_get_contents($apiUrl, false, $context);

    if($response === false) {
        throw new Exception("Merchant Transaction ID '$merchant_transaction_id'not Found");
    }
    return json_decode($response, true);
}

    /**
     * @throws Exception
     */
    public function getCheckoutStatus($merchant_transaction_id) {
        try {
            $access_token = $this->getAccessToken();

            return $this->getCheckoutStats($merchant_transaction_id, $access_token);
        }
        catch (Exception $error) {
            echo 'Error: ' . $error->getMessage() . "\n";
            throw $error;
        }

}


    /**
     * @throws Exception
     */
    public function DirectCharge($payload) {
        try {
            $baseUrl = self::DIRECT_CHARGE_BASE_URL[$this->environment] . '?payment_method=';
            $access_token = $this->getDirectAPIAccessToken();
            $paymentPayload = $this->buildPaymentPayload($payload);

            // Map payment method code to the corresponding endpoint
            $paymentMethodMap = [
                'MPESA_KEN' => 'mpesa',
                'AIRTEL_KEN' => 'airtel_money',
            ];

            // Get the correct endpoint based on the payment method code
            $endpoint = $paymentMethodMap[$paymentPayload['payment_method_code']] ?? '';

            if ($endpoint) {
                $url = $baseUrl . $endpoint;

                 $this->postRequest($url, $paymentPayload, $access_token);

            } else {
                throw new Exception('Invalid payment method code: ' . $paymentPayload['payment_method_code']);
            }
        } catch (Exception $error) {
            echo 'Error: ', $error->getMessage(), "\n";
            throw $error;
        }
    }
    private function buildPaymentPayload($payload)
    {
        $commonPayload = [
            'external_reference' => $payload['external_reference'],
            'origin_channel_code' => 'API',
            'originator_msisdn' => $payload['originator_msisdn'],
            'payer_msisdn' => $payload['payer_msisdn'],
            'service_code' => $payload['service_code'],
            'account_number' => $payload['account_number'],
            "client_code" => $payload['client_code'],
            "payer_email" => $payload['payer_email'],
            "country_code" => $payload['country_code'],
            'invoice_number' => $payload['invoice_number'],
            'currency_code' => $payload['currency_code'],
            'amount' => $payload['amount'],
            'add_transaction_charge' => $payload['add_transaction_charge'],
            'transaction_charge' => $payload['transaction_charge'],
            'extra_data' => $payload['extra_data'],
            'description' => 'Payment by ' . $payload['payer_msisdn'],
            'notify_client' => $payload['notify_client'],
            'notify_originator' => $payload['notify_originator'],
        ];

        $mpesaPayload = $commonPayload + [
                'payment_method_code' => 'MPESA_KEN',
                'paybill' => $payload['paybill'],
            ];

        $airtelPayload = $commonPayload + [
                'payment_method_code' => 'AIRTEL_KEN',
            ];

        // Determine which payment method to use based on the payload
        return $payload['payment_method_code'] === 'MPESA_KEN' ? $mpesaPayload : $airtelPayload;
    }

    /**
     * @throws Exception
     */
    private function postRequest($url, $data, $access_token)
    {
        $headers = [
            'x-access-token: ' . $access_token,
            'Content-Type: application/json',
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);


        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception('Failed to make POST request: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * @throws Exception
     */
    public function getChargeRequestStatus($charge_request_id) {
        $baseUrl = self::DIRECT_CHARGE_BASE_URL[$this->environment] . "/transaction/$charge_request_id/status";
        $access_token = $this->getDirectAPIAccessToken();

        $ch = curl_init($baseUrl);
        $headers = [
            'x-access-token: ' . $access_token,
            'Content-Type: application/json',
        ];

        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        if ($response === false) {
            throw new Exception('Failed to make GET request: ' . curl_error($ch));
        }

        curl_close($ch);

        return json_decode($response, true);
    }


}