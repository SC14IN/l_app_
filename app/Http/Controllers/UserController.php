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

class UserController extends Controller
{

    public function listUsers(Request $request){
        $user = auth()->user();
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
        return response()->json([$users]);

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
        $admin = auth()->user();
        if ($admin->role == 'admin'){

            $this->validate($request, [
                'name' => 'required|string',
                'email' => 'required|email|unique:users',
                // 'password' => 'required|confirmed',
            ]);
            
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
//can i delete the prevoius account from db?

    public function filter(Request $request){//edit
        $admin = auth()->user();
        $column = $request->input('column');
        $string = $request->input('string');
        //take the input for what column i want to filter by
        //string to filter
        if ($admin->role == 'admin'){
            if ($column == 'name' || $column == 'email'){
                $users = User::select('name','email','role')
                ->where($column, 'LIKE', '%'.$string.'%')
                ->where('verified', true)
                ->where('deleted', false)
                ->get();
                return $users;
            }
            if($column == 'role'){
                $users = User::select('name','email')
                ->where('role', $string)
                ->where('verified', true)
                ->where('deleted', false)
                ->get();
                return $users;
            }
            if($column == 'deleted'){
                $users = User::select('name','email')
                ->where('verified', true)
                ->where('deleted', true)
                ->get();
                return $users;
            }
            if($column == 'verified'){
                $users = User::select('name','email','role','deleted')
                ->where('verified', true)
                ->get();
                return $users;
            }
            if($column == 'date'){
                $users = User::select('name','email','role')
                ->whereDate('created_at', '=', $string)
                ->where('verified', true)
                ->where('deleted', false)
                ->get();
                return $users;
            }
            if($column == 'createdBy'){
                $users = User::select('name','email','role')
                ->where('createdBy', $string)
                ->where('verified', true)
                ->where('deleted', false)
                ->get();
                return $users;
            }
            if($column == 'deletedBy'){
                $users = User::select('name','email','role')
                ->where('deletedBy', $string)
                ->where('verified', true)
                ->where('deleted', true)
                ->get();
                return $users;
            }
            if($column == 'id'){
                return $admin;
                $users = User::select('name','email','role')
                ->where('id', $string)
                ->get();
                return $users;
            }
            
        }
        return response()->json(['message'=>'Not allowed']);
    }

}