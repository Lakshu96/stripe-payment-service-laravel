<?php

namespace App\Http\Controllers;

use Dotenv\Validator;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;
use Stripe\Exception\CardException;

class PaymentController extends Controller
{
    //
    public function addPayment(Request $request)
    {
        try {
            $user = Auth::guard("user")->user();
            $validator = Validator::make($request->all(), [
                'package_id' => 'required|numeric|min:1|exists:packages,id',
                'payment_method' => "required"
            ]);
            if ($validator->fails()) {
                return ApiResponse::validationResponse($validator->errors()->all(), ProjectConstants::VALIDATION_ERROR);
            }
            DB::beginTransaction();

            $paymentMethod = PaymentHelper::getPaymentMethodById($request->payment_method);
            $package = Packages::findOrFail($request->package_id);
            $transaction = new Transactions();
            $transaction->package_id = $package->id;
            $tax = round(($package->shipping_fee * 0.05), 2);
            $paymentIntent = null;
            if ($user) {
                $userCard = UserCards::where("user_id", $user->id)->where("payment_method", $request->payment_method)->first();
                if ($userCard) {
                    $paymentIntent = PaymentHelper::createPaymentIntent(($package->shipping_fee + $tax) * 100, $user->stripe_customer_id, $request->payment_method, $package->senderDetails->email);
                } else {
                    if ($paymentMethod->customer) {
                        $paymentIntent = PaymentHelper::createPaymentIntent(($package->shipping_fee + $tax) * 100, $paymentMethod->customer, $request->payment_method, $package->senderDetails->email);
                    } else {
                        $paymentIntent = PaymentHelper::createPaymentIntentWithoutCustomer(($package->shipping_fee + $tax) * 100, $request->payment_method, $package->senderDetails->email);
                    }
                }
                $transaction->user_id = $user->id;
            } else {
                if ($paymentMethod->customer) {
                    $paymentIntent = PaymentHelper::createPaymentIntent(($package->shipping_fee + $tax) * 100, $paymentMethod->customer, $request->payment_method, $package->senderDetails->email);
                } else {
                    $paymentIntent = PaymentHelper::createPaymentIntentWithoutCustomer(($package->shipping_fee + $tax) * 100, $request->payment_method, $package->senderDetails->email);
                }
            }
            PaymentHelper::confirmPaymentFromIntent($paymentIntent->id);
            PaymentHelper::capturePaymentFromIntent($paymentIntent->id);
            $paymentIntent = PaymentHelper::getPaymentIntentDetails($paymentIntent->id);
            $chargeDetails = PaymentHelper::getChargesDetails($paymentIntent->latest_charge);

            $transaction->payment_intent = $paymentIntent->id;
            $transaction->balance_transaction = $chargeDetails->balance_transaction;
            $transaction->latest_charge = $paymentIntent->latest_charge;
            $transaction->receipt_url = $chargeDetails->receipt_url;
            $transaction->payment_method = $request->payment_method;
            $transaction->amount = $paymentIntent->amount;
            $transaction->tax = $tax * 100;
            $transaction->receipt_email = $paymentIntent->receipt_email;
            $transaction->save();

            $package->step = 4;
            $package->status = 1;
            $package->save();

            $response = [
                "package" => [
                    "id" => $package->id,
                    "type" => ProjectConstants::PACKAGE_NAME_ARRAY[$package->type] ?? "UNKNOWN",
                    "shipping_fee" => $package->shipping_fee,
                    "area" => $package->area,
                    "reference_number" => $package->reference_number
                ],
                "sender" => $package->senderDetails ? $package->senderDetails->only(['id', 'name', 'address', 'phone_number', 'near_by_box']) : null,
                "reciver" => $package->reciverDetails ? $package->reciverDetails->only(['id', 'name', 'address', 'phone_number']) : null,
                "payment" => [
                    "status" => 1,
                ]
            ];
            DB::commit();
            return ApiResponse::successResponse($response, "Payment done successfully.", ProjectConstants::SUCCESS);
        } catch (ApiErrorException $ex) {
            DB::rollBack();
            Log::error($ex);
            return ApiResponse::errorResponse(null, $ex->getMessage(), ProjectConstants::BAD_REQUEST);
        } catch (CardException $ex) {
            DB::rollBack();
            Log::error($ex);
            return ApiResponse::errorResponse(null, $ex->getMessage(), ProjectConstants::SERVER_ERROR);
        } catch (Exception $ex) {
            DB::rollBack();
            Log::error($ex);
            return ApiResponse::errorResponse(null, "Server Error", ProjectConstants::SERVER_ERROR);
        }
    }
}
