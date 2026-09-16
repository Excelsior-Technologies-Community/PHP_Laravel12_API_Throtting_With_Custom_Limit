<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class AdminOrderController extends Controller
{
    public function index()
    {
        return response()->json([
            'status' => true,
            'message' => 'Admin orders retrieved successfully.',
            'orders' => [],
        ]);
    }
}