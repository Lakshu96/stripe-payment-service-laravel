# Stripe Payment Service (Laravel)

A full-featured Stripe payment module built with Laravel. This service provides backend logic for handling Stripe payments, customer management, payment methods, payment intents, and transfers to connected accounts using Stripe Connect.

## Features

-   Stripe customer creation and retrieval
-   Payment method management (add, retrieve, attach, set default)
-   Payment intent creation, confirmation, and capture
-   Support for payments with and without customer accounts
-   Stripe Connect onboarding for users and transfers to connected accounts
-   Transaction recording in the database
-   Laravel Sanctum authentication ready

## Project Structure

-   **app/helpers/PaymentHelper.php**: Core payment logic and Stripe API integration
-   **database/migrations/2025_07_25_130027_create_transactions_table.php**: Transaction data model
-   **routes/api.php**: API route definitions (custom payment endpoints can be added here)

## Requirements

-   PHP >= 8.1
-   Laravel >= 10
-   Stripe PHP SDK (configured in composer.json)
-   MySQL or compatible database

## Installation

1. **Clone the repository:**
    ```bash
    git clone https://github.com/Lakshu96/stripe-payment-service-laravel
    cd stripe-payment-service-laravel
    ```
2. **Install dependencies:**
    ```bash
    composer install
    ```
3. **Copy and configure your environment:**
    ```bash
    cp .env.example .env
    # Edit .env to add your Stripe keys and database credentials
    ```
    Add the following to your `.env` file:
    ```env
    STRIPE_SECRET=your_stripe_secret_key
    # Add other Stripe keys as needed
    ```
4. **Generate application key:**
    ```bash
    php artisan key:generate
    ```
5. **Run migrations:**
    ```bash
    php artisan migrate
    ```

## Usage

-   Integrate the `PaymentHelper` methods in your controllers or services to:
    -   Create Stripe customers
    -   Manage payment methods
    -   Create and capture payment intents
    -   Onboard users for Stripe Connect
    -   Transfer funds to connected accounts
-   Store and retrieve transaction data using the `transactions` table.
-   Add your own API endpoints in `routes/api.php` to expose payment functionality as needed.

### Example: Creating a Payment Intent

```php
use App\Helpers\PaymentHelper;

$paymentIntent = PaymentHelper::createPaymentIntent($amount, $customerId, $paymentMethodId, $email);
```

### Example: Handling Payment via PaymentController

You can create a controller (e.g., `PaymentController`) to handle payment requests using the helper methods. Here is a simplified example:

```php
// app/Http/Controllers/PaymentController.php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Helpers\PaymentHelper;

class PaymentController extends Controller
{
    public function addPayment(Request $request)
    {
        // Validate and process payment using PaymentHelper
        $paymentIntent = PaymentHelper::createPaymentIntent(
            $request->amount,
            $request->customer_id,
            $request->payment_method_id,
            $request->email
        );
        // ... handle confirmation, capture, and response
    }
}
```

You can then define a route in `routes/api.php`:

```php
use App\Http\Controllers\PaymentController;

Route::post('/payment', [PaymentController::class, 'addPayment']);
```

**Sample API Request:**

```
POST /api/payment
Content-Type: application/json
Authorization: Bearer <token>

{
  "amount": 1000,
  "customer_id": "cus_123...",
  "payment_method_id": "pm_123...",
  "email": "customer@example.com"
}
```

## Database: Transactions Table

The `transactions`
