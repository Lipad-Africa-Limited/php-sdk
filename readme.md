# Lipad SDK Usage Guide

## Introduction

The Lipad SDK for PHP facilitates the integration of Lipad's payment and checkout features into your PHP applications. This comprehensive guide will assist you in the setup and utilization of the Lipad SDK.

## Prerequisites

Before getting started, ensure you have the following:

- PHP installed on your server or development environment.
- Lipad API credentials, including the IV Key, Consumer Secret, Consumer Key.

## Installation

1. **Download the Lipad SDK:**
   Download the Lipad SDK and include it in your project.

   ```bash
   # Example using Composer
   composer require lipad/lipad-sdk
   
2. **Include the Composer autoloader in your PHP file:**

    ```bash
   require_once 'vendor/autoload.php';
   
3. **Instantiate the Lipad class with your credentials:**

    ```bash
   use Lipad\LipadSdk\Lipad;

    // Replace these values with your actual credentials
    $IVKey = 'your_iv_key';
    $consumerSecret = 'your_consumer_secret';
    $environment = 'sandbox';

## Checkout Usage

1. **To initialize the Lipad class, provide the $IVKey, $consumerKey, $consumerSecret, and $environment parameters. The $environment should be one of the following: 'production' or 'sandbox'.**

    ```bash
   $lipad = new Lipad($IVKey, $consumerKey, $consumerSecret, $environment);

2. **Validate Payload**

    ```bash
    try {
    $lipad->validateCheckoutPayload($payload);
    } catch (Exception $error) {
    echo 'Error: ' . $error->getMessage() . "\n";
    }
   
3. **Encrypt Payload**

    ```bash
   $encryptedPayload = $lipad->encrypt($payload);
   
4. **Get Checkout Status**

    ```bash
   try {
    $lipad->getCheckoutStatus($payload["merchant_transaction_id"]);
   } catch (Exception $error) {
    echo 'Error: ' . $error->getMessage() . "\n";
   }

5. **Build Checkout URL**

    ```bash
   try {
        $checkoutUrl =
        'https://checkout2.dev.lipad.io/?access_key=' .
        urlencode($accessKey) .
        '&payload=' .
        urlencode($encryptedPayload);
         echo 'Checkout URL: ' . $checkoutUrl . "\n";
       } catch (Exception $error) {
         echo 'Error: ' . $error->getMessage() . "\n";
       }

## Direct API Usage

1. **To initialize the Lipad class, provide the $IVKey, $consumerSecret, and $environment parameters. The $environment should be one of the following: 'production' or 'sandbox'.**

    ```bash
   $lipad = new Lipad($IVKey, $consumerSecret, $environment);
   
2. **Direct Charge**

    ```bash
   try {
    $lipad->DirectCharge($payload);
   } catch (Exception $error) {
     echo 'Error: ' . $error->getMessage() . "\n";
   }

3. **Get Charge Request Status**

    ```bash
   try {
    $lipad->getChargeRequestStatus($chargeRequestId);
    } catch (Exception $error) {
    echo 'Error: ' . $error->getMessage() . PHP_EOL;
    }

# License

## This SDK is open-source and available under the MIT License. 
