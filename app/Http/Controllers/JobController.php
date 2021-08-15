<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

use Carbon\Carbon;

use  App\Models\User;

use App\Mail\TestEmail;
use App\Mail\VerifyEmail;
use App\Mail\WelcomeEmail;
use App\Mail\TaskAssignmentEmail;
use App\Mail\StatusUpdateEmail;
use App\Mail\DailyTaskEmail;

use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use  App\Models\Job;

class JobController extends Controller
{
    public function createJob(Request $request){
        $creator = auth()->user();
        $this->validate($request, [
            'title' => 'required|string',
        ]);
        $job = new Job;
        $job->title = $request->input('title');//////title unique?
        $job->description = $request->input('description');
        $job->duedate = $request->input('duedate');//findout how to give dateTime type
        $job->assignee = $request->input('assignee');//update status if there is assignee
        // $job->status = NULL;
        $job->creator = $creator->id;
        $job->assignerName = $creator->email;
        if($request->input('assignee')){//task assigned mail
            $id = $request->input('assignee');
            $user = User::where('id',$id)->first();
            if(!$user){
                return response()->json(['message'=> 'No user with this id exists']);
            }
            if(!$user->verified){
                return response()->json(['message'=> 'Assignee not verified email']);
            }
            if($user->deleted){
                return response()->json(['message'=> 'Assignee deleted by '.($user->deletedBy)]);
            }
            $job->assigneeName = $user->email;
            $job->status = 'assigned';
            try{
                $data = ['name' => $user->name,'token'=>$job];
                Mail::to($user->email)->send(new TaskAssignmentEmail($data));
            }
            catch(\Exception $e){
                return response()->json(['message'=>'Mail not sent']);
            }
        }
        
        $job->save();
        try{
            $data = ['name' => $user->name,'token'=>$job];
            Mail::to($job->assignerName)->send(new TaskAssignmentEmail($data)); 
        }
        catch(\Exception $e){
            return response()->json(['message'=>'Mail not sent']);
        }
        // get mail id of assignee if exists and send mail 
        return response()->json(['job'=> $job]);
    }
    public function updateJob(Request $request){//by creator
        $creator = auth()->user();

        $this->validate($request, [
            'id' => 'required',
        ]);
        $job = Job::where('id',$request->input('id'))->first();
        if (!$job){
            return response()->json(['message'=> 'No job with this id exists']);
        }
        if($job->creator != $creator->id){//only creator can update
            return response()->json(['message'=>'Not authorised to update job']);
        }
        if ($request->input('title')){
            $job->title = $request->input('title');
        }
        if ($request->input('description')){
            $job->description= $request->input('description');
        }
        if ($request->input('duedate')){
            $job->duedate = $request->input('duedate');
        }
        $job->creator = $creator->id;

        if ($request->input('assignee')){//task assigned mail to assignee and assigner? 

            $id = $request->input('assignee');
            $user = User::where('id',$id)->first();
            if(!$user){
                return response()->json(['message'=> 'No user with this id exists']);
            }
            if(!$user->verified){
                return response()->json(['message'=> 'Assignee not verified email']);
            }
            if($user->deleted){
                return response()->json(['message'=> 'Assignee deleted by '.($user->deletedBy)]);
            }
        
            $job->assignee = $request->input('assignee');///////////update status
            $job->assigneeName = $user->email;
            try{
                $data = ['name' => $user->name,'token'=>$job];
                Mail::to($user->email)->send(new TaskAssignmentEmail($data));
            }
            catch(\Exception $e){
                return response()->json(['message'=>'Mail not sent']);
            }
            $job->status = 'assigned';
        }
        // if ($request->input('status')){///should i make another assign job function
        //     $job->status = $request->input('status');
        // }
        $job->save();
        // get mail id of assignee if exists and send mail 
        try{
            $data = ['name' => $user->name,'token'=>$job];
            Mail::to($job->assignerName)->send(new TaskAssignmentEmail($data)); 
        }
        catch(\Exception $e){
            return response()->json(['message'=>'Mail not sent']);
        }
        return response()->json(['message'=> $job]);
    }
    public function updateStatus(Request $request){
        $assignee = auth()->user();
        $this->validate($request, [
            'id' => 'required',
        ]);
        $job = Job::where('id',$request->input('id'))->first();
        if (!$job){
            return response()->json(['message'=> 'No job with this id exists']);
        }
        if($job->assignee != $assignee->id){//only assignee can update status
            return response()->json(['message'=>'Not authorised to delete']);
        }
        if ($request->input('status') == 'inprogress'){//status update mail
            $job->status = 'inprogress';
        }
        if ($request->input('status') == 'completed'){//status update mail
            if($job->duedate < date('Y-m-d H:i:s')){
                $job->status = 'completedAfterDeadline';
            }
            else{
                $job->status = 'completedOnTime';
            }
            
        }
        $job->save();
        try{
            $data = ['token'=>$job];
            Mail::to($assignee->email)->send(new StatusUpdateEmail($data));
            Mail::to($job->assignerName)->send(new StatusUpdateEmail($data));
        }
        catch(\Exception $e){
            return response()->json(['message'=>'Mail not sent']);
        }
        
        return response()->json(['message'=> 'Status updated','job'=> $job]);
    }
    public function deleteJob(Request $request){
        $creator = auth()->user();

        $this->validate($request, [
            'id' => 'required',
        ]);
        $job = Job::where('id',$request->input('id'))->first();
        if (!$job){
            return response()->json(['message'=> 'No job with this id exists']);
        }
        if($job->creator != $creator->id){//only assigner can delete
            return response()->json(['message'=>'Not authorised to delete']);
        }
        $job->status = 'deleted';///add softdelete  //status updated
        $job->save();
        try{
            $data = ['token'=>$job];
            if($job->assigneeName){
                Mail::to($job->assigneeName)->send(new StatusUpdateEmail($data));
            }
            Mail::to($job->assignerName)->send(new StatusUpdateEmail($data));
        }
        catch(\Exception $e){
            return response()->json(['message'=>'Mail not sent']);
        }
        return response()->json(['message'=>'Successfully deleted task']);
    }
    public function viewJobs(Request $request){
        $user = auth()->user();
        if($user->role == 'admin'){//hidden and visible in model 
            return Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName')
                ->where(function ($query) {
                    $query->where('status', NULL)
                            ->orWhere('status','!=','deleted');
                })
                ->orderBy('duedate','asc')
                ->get();
            
        }
        else{
            //change 
            $jobs = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName')
                
                ->where(function ($query) use($user) {
                    $query->where('creator', $user->id)
                            ->orWhere('assignee', $user->id);
                })
                ->orderBy('duedate','asc')
                ->where('status','!=', 'deleted')
                ->get();
           
                
            return response()->json($jobs);
        }
    }
    public function filterJobs(Request $request){//edit
        $user = auth()->user();
         
        $jobs = Job::where(function ($query) {
                        $query->where('status', NULL)
                            ->orWhere('status','!=','deleted');
                    })
                    ->where(function ($query) use($user) {
                        $query->where('creator', $user->id)
                                ->orWhere('assignee', $user->id);
                    })
                    ->orderBy('duedate','asc')
                    ->get();

        $string = $request->input('search');
        if ($string){                
            $jobs = Job::
                where(function ($query){
                    $query->where('status', NULL)
                            ->orWhere('status','!=','deleted');
                })
                ->orderBy('duedate','asc')
                ->where(function ($query)  use($string) {
                    $query->where('title', 'LIKE', '%'.$string.'%')
                            ->orWhere('description', 'LIKE', '%'.$string.'%');
            })
            ->get();
        }
        if($request->input('assignee')){
            if($request->input('assignee')!='All'){
                $jobs = $jobs->where('assignee', $request->input('assignee'));
            }
        }
        if($request->input('creator')){
            if($request->input('creator')!='All'){
                $jobs = $jobs->where('creator', $request->input('creator'));
            }
        }
        if($request->input('status')){
            if($request->input('status')=='Overdue'){
                $jobs = $jobs->where('duedate','<',date('Y-m-d H:i:s'))
                                ->where('status','!=','completedOnTime')
                                ->where('status','!=','completedAfterDeadline');
            }
            if($request->input('status')=='Inprogress'){
                $jobs = $jobs->where('status','inprogress');
            }
            if($request->input('status')=='CompletedOnTime'){
                $jobs = $jobs->where('status','completedOnTime');
            }
            if($request->input('status')=='CompletedAfterDeadline'){
                $jobs = $jobs->where('status','completedAfterDeadline');
            }
        }
        // if($request->input('interval')){

        // }
        $ans = [];
        foreach($jobs as $job){
            array_push($ans,$job);
        }
        
        return $ans;
    }
    public function getValues(Request $request){//only admin can see overview
        
        // echo $string;
        if (($request->input('id')=='undefined')||($request->input('id')==NULL)||$request->input('id')=='null'){
            $string = auth()->user()->id;
            
        }
        else{
            $string = $request->input('id');
        }
        // echo $string;
        $overdue =  Job::select('id')
                ->where('assignee', $string)
                ->where('duedate','<',date('Y-m-d H:i:s'))
                ->where('status','!=','completedOnTime')
                ->where('status','!=','deleted')
                ->where('status','!=','completedAfterDeadline')
                ->get()
                ->count();
        $inprogress =  Job::select('id')     
                ->where('assignee', $string)
                ->where('status', 'inprogress')
                ->get()
                ->count();
        $noactivity = Job::select('id')
                ->where('assignee', $string)
                ->where(function ($query) {
                    $query->where('status', NULL)
                            ->orWhere('status','assigned');
                })
                ->get()
                ->count();
        $completedOnTime =Job::select('id')                    
                ->where('assignee', $string)
                ->where('status', 'completedOnTime')
                ->get()
                ->count();
        $completedAfterDeadline =Job::select('id')                    
                ->where('assignee', $string)
                ->where('status', 'completedAfterDeadline')
                ->get()
                ->count();
        // echo $overdue,$inprogress,$noactivity,$completedAfterDeadline,$completedOnTime;
        return response()->json([
            'overdue' => $overdue,
            'inprogress' => $inprogress,
            'noactivity' => $noactivity,
            'completedOnTime' => $completedOnTime,
            'completedAfterDeadline' => $completedAfterDeadline,
        ], 200);
    }
    public function getMonthlyValues(Request $request){
        if (($request->input('id')=='undefined')||($request->input('id')==NULL)||$request->input('id')=='null'){
            $string = auth()->user()->id;
            
        }
        else{
            $string = $request->input('id');
        }
        $date = Carbon::now();
        $completedOnTime=[];
        $completedAfterDeadline=[];
        $overdue=[];
        $allDue=[];
        $current = $date->month;
        for ($i = 0; $i < $current; $i++){
            $temp  =Job::select('id')    
            ->whereMonth('created_at', '=', $i+1)
            ->where('assignee', $string)
            ->where('status', 'completedOnTime')
            ->get()
            ->count();
            array_push($completedOnTime,$temp);

            $temp  =Job::select('id')    
            ->whereMonth('created_at', '=', $i+1)
            ->where('assignee', $string)
            ->where('status','completedAfterDeadline')
            ->get()
            ->count();
            array_push($completedAfterDeadline,$temp);

            $temp  =Job::select('id')    
            ->whereMonth('created_at', '=', $i+1)
            ->where('assignee', $string)
            ->where('duedate','<',date('Y-m-d H:i:s'))
            ->where('status','!=','completedOnTime')
            ->where('status','!=','completedAfterDeadline')
            ->where('status','!=','deleted')
            ->get()
            ->count();
            array_push($overdue,$temp);

        }
        return response()->json([
            'overdue' => $overdue,
            'completedOnTime' => $completedOnTime,
            'completedAfterDeadline' => $completedAfterDeadline,
        ], 200);
    }
    public function jobs($id){//mail all the assignes about their assigned and onprogress tasks
        $user = User::where('id',$id)->first();
        $jobs = Job::select('id')
        ->where('assignee', $user->id)
        ->where(function ($query) {
            $query->where('status', 'assigned')
                ->orWhere('status','inprogress');
        })
        ->orderBy('duedate','asc')
        ->get();

        return $jobs;
    }
    public function dailyEmailData(){
        $users = User::select('id','name','email','role')
        // ->where('role', 'normal')
        ->where('verified', true)
        ->where('deleted', false)
        ->get();

        foreach($users as $user){
            $jobs = $this->jobs($user->id);
            $email = $user->email;
            echo ($email);
            try{
                $data = ['token'=>$jobs];
                Mail::to($email)->send(new DailyTaskEmail($data));
            }
            catch(\Exception $e){
            }
        }
    }
}