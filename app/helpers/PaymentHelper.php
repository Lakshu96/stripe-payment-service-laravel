<?php

namespace App\Helpers;

use App\Models\User;
use Carbon\Carbon;
use DB;
use Log;
use Stripe\Charge;
use Stripe\Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\PaymentIntent;
use Stripe\StripeClient;
use Stripe\TaxRate;

class PaymentHelper
{
    public static function createCustomer($user)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $customer = Customer::create([
            'email' => $user->email,
            'name' => $user->name,
        ]);

        return $customer;
    }

    public function getCustomer($customerId)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $customer = Customer::retrieve($customerId);
        return $customer;
    }

    public function getAllPaymentMethod($customerId)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $paymentMethods = PaymentMethod::all([
            'customer' => $customerId,
            'type' => 'card',
            'limit' => 100,
        ]);
        return $paymentMethods;
    }

    public static function getPaymentMethodById($paymentMethod)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $paymentMethod = PaymentMethod::retrieve($paymentMethod);
        return $paymentMethod;
    }

    public static function attachPaymentMetodToCustomer($paymentMethod, $customerId)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $paymentMethod = PaymentMethod::retrieve($paymentMethod);
        $paymentMethod->attach(['customer' => $customerId]);
        return $paymentMethod;
    }

    public static function makeDefaultPaymentMethod($paymentMethod, $customerId)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        Customer::update($customerId, [
            'invoice_settings' => [
                'default_payment_method' => $paymentMethod,
            ],
        ]);
    }

    public static function createPaymentIntent($amount, $customerId, $paymentMethodId, $email)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $paymentIntent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => "CAD",
            'customer' => $customerId,
            'receipt_email' => $email,
            'payment_method' => $paymentMethodId,
            'capture_method' => 'manual',
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => "never",
            ],
        ]);
        return $paymentIntent;
    }

    public static function createPaymentIntentWithoutCustomer($amount, $payment_method, $email)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $paymentIntent = PaymentIntent::create([
            'amount' => $amount,
            'currency' => "CAD",
            'receipt_email' => $email,
            'payment_method' => $payment_method,
            'capture_method' => 'manual',
            'automatic_payment_methods' => [
                'enabled' => true,
                'allow_redirects' => 'never',
            ],
        ]);
        return $paymentIntent;
    }

    public static function getPaymentIntentDetails($paymentIntent)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $paymentIntent = PaymentIntent::retrieve($paymentIntent);
        return $paymentIntent;
    }

    public static function getChargesDetails($chargeId)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $charge = Charge::retrieve($chargeId);
        return $charge;
    }

    public static function confirmPaymentFromIntent($paymentIntent)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $paymentIntent = PaymentIntent::retrieve($paymentIntent);
        $paymentIntent->confirm();
        return $paymentIntent;
    }

    public static function capturePaymentFromIntent($paymentIntent)
    {
        Stripe::setApiKey(env('STRIPE_SECRET'));
        $paymentIntent = PaymentIntent::retrieve($paymentIntent);
        $paymentIntent->capture();
        return $paymentIntent;
    }

    public function onboardUser($user)
    {
        $stripe = new StripeClient(env('STRIPE_SECRET'));
        $stripeAccountId = $user->stripe_account_id;

        if (!$stripeAccountId) {
            $accountId = $stripe->accounts->create(['type' => 'express']);
            $stripeAccountId = $accountId->id;
            \DB::table('dealers')->where('id', $user->id)->update(['stripe_account_id' => $stripeAccountId]);
        }

        if (!$stripeAccountId) {
            throw new \Exception("Stripe account ID is missing.");
        }

        $onboardingLink = $stripe->accountLinks->create([
            'account' => $stripeAccountId,
            'refresh_url' => url(""),
            'return_url' => url(""),
            'type' => 'account_onboarding',
        ]);

        return $onboardingLink;
    }
    public function getOnboardedAccount($stripeAccountId)
    {
        $stripe = new StripeClient(env('STRIPE_SECRET'));
        if (!$stripeAccountId) {
            throw new \Exception("Stripe account ID is missing.");
        }
        $account = $stripe->accounts->retrieve($stripeAccountId, []);
        if (!$account) {
            throw new \Exception("Stripe account not found.");
        }
        return $account;
    }
    public static function transferToConnectedAccount($amount, $currency, $connectedAccountId, $description = '', $metadata = [])
    {
        try {
            Stripe::setApiKey(env('STRIPE_SECRET'));
            $transfer = \Stripe\Transfer::create([
                'amount' => $amount, // in cents: $10 = 1000
                'currency' => $currency,
                'destination' => $connectedAccountId,
                'description' => $description,
                'transfer_group' => 'DEALER_TRANSFERS_0014',
                'metadata' => $metadata
            ]);
            DB::table("transfers")->insert([
                'dealer_id' => @$metadata["dealer_id"],
                'reference_number' => @$metadata["reference_number"],
                'amount' => $amount / 100,
                'stripe_transfer_id' => $transfer->id,
                'transfer_status' => $transfer->status, // 'pending' or 'paid'
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            return [
                'success' => true,
                'transfer' => $transfer
            ];
        } catch (\Stripe\Exception\ApiErrorException $e) {
            Log::error($e->getMessage());
            return [
                'success' => false,
                'transfer' => $transfer
            ];
        }
    }
}

?>