# PHP Laravel 12 API Throttling With Custom Limits

##  Overview

This guide explains how to build a secure API system with:

1. Sanctum token authentication
2. Login protection (anti brute-force)
3. User order rate limiting
4. Admin-only APIs with higher limits

---

##  Features

* Secure API authentication using Laravel Sanctum
* Brute-force login protection with rate limiting
* Per-user order request throttling
* Higher rate limits for admin users
* Custom JSON responses for throttled requests

---

##  Folder Structure (Important Files)

```
app/
 ├── Http/
 │    ├── Controllers/Api/
 │    │      ├── TestController.php
 │    │      ├── AuthController.php
 │    │      ├── OrderController.php
 │    │      └── AdminOrderController.php
 │    └── Middleware/
 │           └── IsAdmin.php
 ├── Models/
 │    └── User.php

bootstrap/
 └── app.php   ← Rate limit configuration

routes/
 └── api.php   ← All API routes
```

---

#  STEP 1 — Install Laravel

```bash
composer create-project laravel/laravel api-project

php artisan serve
```

---

#  STEP 2 — Install Sanctum (API Authentication)

Sanctum allows users to log in and receive API tokens.

```bash
composer require laravel/sanctum

php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"

php artisan migrate
```

---

#  STEP 3 — Update User Model

 `app/Models/User.php`

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
```

This enables:

* `$user->createToken()`
* `auth:sanctum` middleware

---

# 🚦 STEP 4 — Configure Rate Limiting

 `bootstrap/app.php`

```php
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Http\Exceptions\ThrottleRequestsException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )

    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'throttle' => ThrottleRequests::class,
            'is_admin' => \App\Http\Middleware\IsAdmin::class,
        ]);
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ThrottleRequestsException $e, $request) {
            return response()->json([
                'status' => false,
                'message' => 'Too many requests. Please try again later.',
                'retry_after' => $e->getHeaders()['Retry-After'] ?? null,
            ], 429);
        });
    })

    ->booted(function () {

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('orders', function (Request $request) {
            return Limit::perMinute(20)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        RateLimiter::for('admin-api', function (Request $request) {
            return Limit::perMinute(200)->by(
                $request->user()?->id ?: $request->ip()
            );
        });
    })
    ->create();
```

---

#  STEP 5 — Create TestController (for /api/test)

```bash
php artisan make:controller Api/TestController
```

 `app/Http/Controllers/Api/TestController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class TestController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'message' => 'API is working 🚀'
        ]);
    }
}
```

---

#  STEP 6 — Create Login API

```bash
php artisan make:controller Api/AuthController
```

 `app/Http/Controllers/Api/AuthController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Http\Controllers\Controller;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return response()->json([
            'status' => true,
            'token' => $token
        ]);
    }
}
```

---

#  STEP 7 — Customer Orders API

```bash
php artisan make:controller Api/OrderController
```

 `app/Http/Controllers/Api/OrderController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function store(Request $request)
    {
        return response()->json([
            'status' => true,
            'message' => 'Order placed successfully',
            'user_id' => $request->user()->id
        ]);
    }
}
```

---

#  STEP 8 — Admin Orders API

```bash
php artisan make:controller Api/AdminOrderController
```

 `app/Http/Controllers/Api/AdminOrderController.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class AdminOrderController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'orders' => []
        ]);
    }
}
```

---

#  STEP 9 — Admin Middleware

```bash
php artisan make:middleware IsAdmin
```

 `app/Http/Middleware/IsAdmin.php`

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class IsAdmin
{
    public function handle($request, Closure $next)
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json(['message' => 'Admin access only'], 403);
        }

        return $next($request);
    }
}
```

---

#  STEP 10 — Define API Routes

 `routes/api.php`

```php
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\TestController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\AdminOrderController;

Route::middleware('throttle:api')->get('/test', [TestController::class, 'index']);

Route::post('/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:orders'])
    ->post('/orders', [OrderController::class, 'store']);

Route::middleware(['auth:sanctum', 'is_admin', 'throttle:admin-api'])
    ->get('/admin/orders', [AdminOrderController::class, 'index']);
```

---

#  STEP 11 — Testing Flow

### 11.1 Public API Test

**Endpoint**

GET [http://127.0.0.1:8000/api/test](http://127.0.0.1:8000/api/test)

<img width="1795" height="728" alt="Screenshot 2026-01-27 155652" src="https://github.com/user-attachments/assets/f79b1169-c6c6-44e2-afc7-14a3fa89a0fb" />


 **Rate Limit:** 60 requests per minute per IP

| Request Count | Result                  |
| ------------- | ----------------------- |
| 1 – 60        | ✅ 200 OK                |
| 61+           | ❌ 429 Too Many Requests |

<img width="543" height="177" alt="Screenshot 2026-01-27 172127" src="https://github.com/user-attachments/assets/0a20b82b-aa14-45ac-9324-ce1d113075dc" />

---



### 11.2 Login API Test

**Endpoint**

POST [http://127.0.0.1:8000/api/login](http://127.0.0.1:8000/api/login)

<img width="1795" height="701" alt="Screenshot 2026-01-27 161237" src="https://github.com/user-attachments/assets/c840811d-cc37-470b-8e27-c6399ebe9f7a" />


**Rate Limit:** 5 attempts per minute per IP

| Attempt | Result                                    |
| ------- | ----------------------------------------- |
| 1 – 5   | ❌ Invalid credentials (if wrong password) |
| 6       | ❌ 429 Too Many Requests                   |

<img width="553" height="194" alt="Screenshot 2026-01-27 171949" src="https://github.com/user-attachments/assets/cb384a7e-a778-4bee-b3d1-9c24f7eccba4" />

---


### 11.3 Customer Orders API Test

**Endpoint**

POST [http://127.0.0.1:8000/api/orders](http://127.0.0.1:8000/api/orders)

<img width="1795" height="664" alt="Screenshot 2026-01-27 161431" src="https://github.com/user-attachments/assets/c3faf4e9-d36b-48a9-88eb-e0899eed6f9c" />


Headers:
Authorization: Bearer YOUR_TOKEN
Accept: application/json

**Rate Limit:** 20 orders per minute per logged-in user

| Request Count | Result                  |
| ------------- | ----------------------- |
| 1 – 20        | ✅ Order successful      |
| 21            | ❌ 429 Too Many Requests |

<img width="533" height="169" alt="Screenshot 2026-01-27 172515" src="https://github.com/user-attachments/assets/31bab890-254e-49c8-8b40-11782f5289e1" />

Limit resets after 60 seconds.

---


### 11.4 Admin Orders API Test

**Endpoint**

GET [http://127.0.0.1:8000/api/admin/orders](http://127.0.0.1:8000/api/admin/orders)

<img width="1801" height="623" alt="Screenshot 2026-01-27 161828" src="https://github.com/user-attachments/assets/9b9df0df-911b-4adc-9f3c-5b0d0bd5c3ab" />

Header: Authorization: Bearer ADMIN_TOKEN

**Rate Limit:** 200 requests per minute per admin user

| Request Count | Result                  |
| ------------- | ----------------------- |
| 1 – 200       | ✅ Success               |
| 201           | ❌ 429 Too Many Requests |

---

#  Final Result

You now have a secure Laravel 12 API system with:

* Token authentication
* Brute-force login protection
* User rate limiting
* Admin role protection
* Custom API throttling
