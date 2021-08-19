<?php

namespace App\Http\Controllers;

use Laravel\Lumen\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Auth;
use Pusher\Pusher;
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
    // protected function pusher(){
    //     $options = array(
    //         'cluster' => 'ap2',
    //         'useTLS' => true
    //       );
    //       $pusher = new Pusher\Pusher(
    //         '8807ac588a7067358747',
    //         '4158339defde7aab86d1',
    //         '1249057',
    //         $options
    //       );
        
    //       $data['message'] = 'hello world';
    //       $pusher->trigger('my-channel', 'my-event', $data);
    // }
}
