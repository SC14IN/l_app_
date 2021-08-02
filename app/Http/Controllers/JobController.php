<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;

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
            $job->assigneeName = $user->name;
        }
        $job->creator = $creator->id;
        $job->assignerName = $creator->name;
        $job->save();

        // get mail id of assignee if exists and send mail 
        return response()->json(['job'=> $job]);
    }
    public function updateJob(Request $request){
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
                return response()->json(['message'=> 'No ser with this id exists']);
            }
            if(!$user->verified){
                return response()->json(['message'=> 'Assignee not verified email']);
            }
            if($user->deleted){
                return response()->json(['message'=> 'Assignee deleted by '.($user->deletedBy)]);
            }
        
            $job->assignee = $request->input('assignee');///////////update status
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
    public function updateStatus(Request $request){//dont need this function  
    }
    public function deleteJob(Request $request){
        $assignee = auth()->user();

        $this->validate($request, [
            'id' => 'required',
        ]);
        $job = Job::where('id',$request->input('id'))->first();
        if (!$job){
            return response()->json(['message'=> 'No job with this id exists']);
        }
        if($job->assignee != $assignee->id){//only assignee can update status
            return response()->json(['message'=>'Not authorised to update status']);
        }
        $job->status = 'deleted';
        $job->save();
        return response()->json(['message'=>'Successfully deleted task']);
    }
    public function viewJobs(Request $request){
        $user = auth()->user();
        if($user->role == 'admin'){
            //get all
            $jobs = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName')
                ->where('status','!=','deleted')
                ->orderBy('duedate','asc')
                ->get();
            return $jobs;
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
        $string = $request->input('string');
        if ($admin->role == 'admin'){
            if ($column == 'assigner'){
                $jobs = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName',)
                ->where('creator', $string )
                ->where('status','!=', 'deleted')
                ->orderBy('duedate','asc')
                ->get();
                return $jobs;
            }
            if ($column == 'assignee'){
                $jobs = Job::select('id','title','description','assignee','creator','duedate','status','assignerName','assigneeName',)
                ->where('assignee', $string)
                ->where('status','!=', 'deleted')
                ->orderBy('duedate','asc')
                ->get();
                return $jobs;
            }
        }
        return response()->json(['message'=>'Not allowed']);
    }
}