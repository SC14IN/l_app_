<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

use  App\Models\User;

use App\Mail\TestEmail;
use App\Mail\VerifyEmail;
use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use  App\Models\ForgotPassword;
use validator;
class UserController extends Controller
{

    public function listUsers(Request $request){
        $user = auth()->user();
        if(!$user){
            return response()->json(['message'=>'Unauthorised'],401);
        }
        // return response()->json(['user' => $user]);
        if ($user->role == 'admin'){
            $users = User::select('id','name','email','role')
                // ->where('role', 'normal')
                ->where('verified', true)
                ->where('deleted', false)
                ->get();
        }
        if ($user->role == 'normal'){
            $users = User::select('id','name','email')
                ->where('role', 'normal')
                ->where('verified', true)
                ->where('deleted', false)
                ->get();
        }
        return response()->json($users);

    }
    public function delSelf(Request $request){
        $user = auth()->user();
        $user->deleted = true;
        $user->deletedBy = 'self';
        $user->save();
        return response()->json(['message'=>'Successfully deleted yourself']);
    }
    public function delUser(Request $request){////delete with id
        $admin = auth()->user();
        if ($admin->role == 'admin'){
            $this->validate($request, [
                'id' => 'required',
            ]);
            $user = User::where('id',$request->input('id'))->first();////already deleted
            if($user->deleted){
                return response()->json(['message' => 'Account already deleted by '.($user->deletedBy)], 401);
            }
            $user->deleted = true;
            $user->deletedBy = 'admin';
            $user->save();
            return response()->json(['message'=>'Successfully deleted user']);

            // return response()->json(['message'=>$request->input('email')]);
        }
        return response()->json(['message'=>'Not authorised']);
    }
    public function createUser(Request $request){
        // return response()->json(['string'=>'success'],200);
        $admin = auth()->user();
        if ($admin->role == 'admin'){
            $validator = Validator::make($request->all(), [
                'name' => 'required|string',
                'email' => 'required|email|unique:users'
            ]);
            if ($validator->fails()) {
                return response()->json(['message'=>'Email already exists'], 400);
              
              }
            $user = new User;
            $user->name = $request->input('name');
            $user->email = $request->input('email');
            $plainPassword = $this->generatetoken();
            $user->password = app('hash')->make($plainPassword);
            $user->verified = true;
            $user->deleted = false;
            $user->role = 'normal';
            $user->createdBy = 'admin';//add user id
            $user->save();
            
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
            
            //mail token and reset password
            return response()->json(['email' => $user->email,'token'=>$forgotpass->token, 'message' => 'Registered user! Reset password required'], 201);
        }
        return response()->json(['message'=>'Not authorised']);
    }
/////what to do if we want to re create a deleted account
/////can i delete the prevoius account from db
    public function filter(Request $request){//edit
        $admin = auth()->user();
        if(!$admin){
            return response()->json(['message'=>'Unauthorised'],401);
        }
        global $string ;
        $string= $request->input('string');
        // echo($string);
        if ($admin->role == 'admin'){
            $users = User::select('id','name','email','role')
                ->where('verified', true)
                ->where('deleted', false)
                ->where(function ($query) {
                    global $string;
                    $query->where('name', 'LIKE', '%'.$string.'%')
                            ->orWhere('email', 'LIKE', '%'.$string.'%');
                })
                ->get();
                // $users = User::select('id','name','email','role')
                // ->where('name', 'LIKE', '%'.$string.'%')
                // ->where('email', 'LIKE', '%'.$string.'%')
                // ->where('verified', true)
                // ->where('deleted', false)
                
            return $users;
        }
        else{
            return response()->json(['message'=>'Not allowed']);
        }
    }

    public function update(request $request){
        $admin = auth()->user();
        if ($admin->role == 'admin'){
            $this->validate($request, [
                'id' => 'required'
            ]);
            $user = User::where('id',$request->input('id'))->first();

            if ($request->input('name')){
                $user->name = $request->input('name');
            }
            if ($request->input('email')){
                $user->name = $request->input('email');
            }
            if ($request->input('verified')!== NULL){
                $user->verified = $request->input('verified');
            }if ($request->input('deleted')!== NULL){
                $user->deleted = $request->input('deleted');
            }
            if ($request->input('role')){
                $user->role = $request->input('role');
            }
            $user->save();
            return response()->json(['user' => $user, 'message' => 'Updated user!'], 201);
        }
        return response()->json(['message'=>'Not authorised']);
    }
    public function getUser(){
        return auth()->user();
    }
}