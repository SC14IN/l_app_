<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;

class Controller extends BaseController
{
    protected function respondWithToken($token,$id)
    {
        return response()->json([
            'id' => $id,
            'token' => $token,
            'token_type' => 'bearer',
            'expires_in' => Auth::factory()->getTTL() * 60
        ], 200);//////////////////////////change time
    }
    protected function generateToken($n = 16){
        return bin2hex(random_bytes($n));////assign defualt 
    }
}
