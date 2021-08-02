<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

use  App\Models\User;
use  App\Models\VerifyUser;
use  App\Models\ForgotPassword;

use App\Mail\TestEmail;
use App\Mail\VerifyEmail;
use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    
    public function register(Request $request){
        //validate incoming request 
        //if email already registered and not verified, register again and give new token for
        $this->validate($request, [
            'name' => 'required|string',
            'email' => 'required|email|unique:users',
            'password' => 'required
                        |min:6
                        |regex:/^.*(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9])(?=.*[~!@#$%^&*]).*$/
                        |confirmed',
        ]);

        $user = new User;
        $user->name = $request->input('name');
        $user->email = $request->input('email');
        $plainPassword = $request->input('password');
        $user->password = app('hash')->make($plainPassword);
        $user->role = 'normal';
        $user->verified = false;
        $user->deleted = false;
        $user->createdBy = 'self';// user id
        
        $user->save();
        
        $verify = VerifyUser::create([
            'user_id' => $user->id,
            'token' => $this->generateToken(16)
        ]);
        try{
            $data = ['name' => $user->name,'token'=>$verify->token];
            Mail::to($user->email)->send(new TestEmail($data));
        }
        catch(\Exception $e){
            return response()->json(['message'=>'Mail not sent']);
        }
        
        return response()->json(['user' => $user, 'message' => 'Registered Successfully. Verification token: '.($verify->token).' sent to mail'], 200);
    }
    public function emailVerify(Request $request){
        $token = $request->input('token');
        $verify = VerifyUser::where('token',$token)->first();
        if($verify){
            $user = User::find($verify->user_id);//if deleted
            $user->verified = true;
            $user->save();
            return response()->json(['message'=>'User verified successfully']);
        }
        return response()->json(['message'=>'Token invalid or expired']);

    }
    public function login(Request $request){
        $this->validate($request, [
            'email' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = $request->only(['email', 'password']);

        $user = User::where('email',$credentials['email'])->first();
        if(!$user){
            return response()->json(['message'=>'Email not registered']);
        }
        if($user->deleted){
            return response()->json(['message' => 'account deleted by '.($user->deletedBy)], 401);
        }
        if(!$user->verified){
            return response()->json(['message' => 'Please verify your email'], 401);
        }

        $token = Auth::attempt($credentials);
        if (! $token) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }
        return $this->respondWithToken($token);
        // return response()->json(['time'=>$user->updated_at,'php_time'=>getdate()]);
    }
    public function forgotPassword(Request $request){
        $this->validate($request, [
            'email' => 'required|email',
        ]);
        $credentials = $request->only(['email']);
        $user = User::where('email',$credentials['email'])->first();
        
        if(!$user){
            return response()->json(['message'=>'Email not registered']);
        }
        if($user->deleted){
            return response()->json(['message' => 'Account deleted by '.($user->deletedBy)], 401);
        }
        if(!$user->verified){
            return response()->json(['message'=>'Email not Verified'],401);
        }
        $forgotpass = ForgotPassword::firstornew(['user_id'=> $user->id]);
        $forgotpass->token = $this->generateToken(16);
        $forgotpass->save();
        
        try{
            $data = ['name' => $user->name,'token'=>$forgotpass->token];
            Mail::to($user->email)->send(new TestEmail($data));
        }
        catch(\Exception $e){
            return response()->json(['message'=>'Mail not sent']);
        }
        return response()->json([ 
        'token' => 'Password Verification token: '.($forgotpass->token).' sent to mail'], 201);
        // localhost:8050/api/resetPassword?token=<insert>
 
    }
    public function resetPassword(Request $request){
        // check if token is expired

        $this->validate($request, [
            'token' => 'required|string',
            'password' => 'required|confirmed|min:6',//strong pasword
        ]);
        $token = $request->input('token');
        $verify = ForgotPassword::where('token',$token)->first();
        //check if token is expired
            // $date = $user->created_at->toDateTimeString();
            // return  date('Y-m-d H:i:s') , date('Y-m-d H:i:s',strtotime('+3 hours'));
        $created = $verify->updated_at->toDateTimeString();
        $now = date('Y-m-d H:i:s',strtotime('-8 hours'));
        // echo $created;
        // echo $now;
        if ($now>$created){
            return response()->json(['message'=>'Token expired']);
        }

        if($verify){
            $user = User::find($verify->user_id);
            // return $user;
            if($user->deleted){
                return response()->json(['message' => 'Account deleted by '.($user->deletedBy)], 401);
            }
            if(!$user->verified){
                return response()->json(['message'=>'Email not Verified']);
            }
            $user->password = app('hash')->make($request->input('password'));
            $user->save();
            return response()->json(['message'=>'Password reset successfully']);
        }
        return response()->json(['message'=>'Token invalid or expired']);

    }
    public function test(Request $request){
        return response()->json(['message'=>'backend called']);
    }
}