<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

use Carbon\Carbon;

use  App\Models\User;

use App\Mail\TestEmail;
use App\Mail\VerifyEmail;
use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

use  App\Models\Job;

// use  App\Models\Status;
//how to show status
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
        if($request->input('assignee')){
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
            $job->status = 'assigned';
            $job->assigneeName = $user->email;
        }
        $job->creator = $creator->id;
        $job->assignerName = $creator->email;
        $job->save();

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
        if ($request->input('assignee')){

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
            $job->status = 'assigned';
        }
        if ($request->input('status')){///should i make another assign job function
            $job->status = $request->input('status');
        }
        //findout how to give dateTime type
        //update status if there is assignee
        $job->creator = $creator->id;
        $job->save();
        // get mail id of assignee if exists and send mail 
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
        if ($request->input('status') == 'inprogress'){
            $job->status = 'inprogress';
        }
        if ($request->input('status') == 'completed'){
            if($job->duedate < date('Y-m-d H:i:s')){
                $job->status = 'completedAfterDeadline';
            }
            else{
                $job->status = 'completedOnTime';
            }
            
        }
        $job->save();
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
        $job->status = 'deleted';///add softdelete
        $job->save();
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
            //creator
            $jobCreator = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName')
                ->where('creator', $user->id)
                ->orderBy('duedate','asc')
                ->where('status','!=', 'deleted')
                ->get();
            $jobAssignee = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName')
                ->where('assignee', $user->id)
                ->orderBy('duedate','asc')
                ->where('status','!=', 'deleted')
                ->get();
            return response()->json([$jobCreator,$jobAssignee]);
        }
    }
    public function filterJobs(Request $request){//edit
        $admin = auth()->user();
        $column = $request->input('column');
        // global $string;
        $string = $request->input('string');
        if ($admin->role == 'admin'){
            if ($column=='title'||$column=='description'){                
                $jobs = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName',)
                    ->where(function ($query){
                        $query->where('status', NULL)
                                ->orWhere('status','!=','deleted');
                    })
                    ->orderBy('duedate','asc')
                    ->where(function ($query)  use($string) {
                        // global $string;
                        $query->where('title', 'LIKE', '%'.$string.'%')
                                ->orWhere('description', 'LIKE', '%'.$string.'%');
                })
                ->get();
                return $jobs;
            }
            if ($column == 'assigner'){
                $jobs = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName',)
                ->where('creator', $string )
                ->where(function ($query) {
                    $query->where('status', NULL)
                            ->orWhere('status','!=','deleted');
                })
                ->orderBy('duedate','asc')
                ->get();
                return $jobs;
            }
            if ($column == 'assignee'){
                $jobs = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName',)
                ->where('assignee', $string)
                ->where(function ($query) {
                    $query->where('status', NULL)
                            ->orWhere('status','!=','deleted');
                })
                ->orderBy('duedate','asc')
                ->get();
                return $jobs;
            }
            if($column=='status'){//when 
                if($string == 'all'){
                    return $jobs;
                }
                else if($string == 'inprogress'){
                    $jobs = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName',)
                    ->where('status', 'inprogress')
                    ->orderBy('duedate','asc')
                    ->get();
                    return $jobs;
                }
                else if($string == 'completed'){
                    $jobs = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName',)
                    ->where('status', 'completed')
                    ->orderBy('duedate','asc')
                    ->get();
                    return $jobs;
                }
                else if($string == 'overdue'){
                    $jobs = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName',)
                    ->where('duedate','<',date('Y-m-d H:i:s'))
                    ->where('status','!=', 'deleted')
                    ->orderBy('duedate','asc')
                    ->get();
                    return $jobs;
                }
            }
        }
        else{
            return response()->json(['message'=>'Not allowed']);
        }
    }
    public function getValues(Request $request){
        $string = auth()->user()->id;
        // echo $string;
        $overdue =  Job::select('id')
                ->where('assignee', $string)
                ->where('duedate','<',date('Y-m-d H:i:s'))
                ->where('status','!=','completedOnTime')
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
        $string = auth()->user()->id;
        $date = Carbon::now();
        $months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
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
}