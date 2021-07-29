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

use  App\Models\Status;
//how to show status
class JobController extends Controller
{
    public function createJob(Request $request){
        $creator = auth()->user();
        $this->validate($request, [
            'title' => 'required|string',
        ]);
        $job = new Job;
        $job->title = $request->input('title');
        $job->description = $request->input('description');
        $job->duedate = $request->input('duedate');//findout how to give dateTime type
        $job->assignee = $request->input('assignee');//update status if there is assignee
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
        }
        $job->creator = $creator->id;
        $job->save();

        $status = new Status;
        $status->job_id = $job->id;
        if ($job->assignee){
            $status->assigned = 1;
        }
        $status->save();
        // get mail id of assignee if exists and send mail 
        return response()->json(['job'=> $job,'status'=>$status]);
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
            $status = Status::where('job_id',$id)->first();
            $status->assigned = true;
            $status->save();
            
        }
        //findout how to give dateTime type
        //update status if there is assignee
        $job->creator = $creator->id;
        $job->save();
        // get mail id of assignee if exists and send mail 
        return response()->json(['message'=> $job]);
    }
    public function updateStatus(Request $request){
        $this->validate($request, [
            'id' => 'required',
        ]);
        $assignee = auth()->user();
        $status = Status::where('job_id',$request->input('id'))->first();
        $job = Job::where('id',$request->input('id'))->first();
        if (!$status){
            return response()->json(['message'=> 'No job with this id exists']);
        }
        if (!$job){
            return response()->json(['message'=> 'No job with this id exists']);
        }
        if (!$status->assigned){
            return response()->json(['message'=> 'Job not assigned']);
        }
        if ($assignee->id != $job->assignee){
            return response()->json(['message'=> 'Not authorised to change status']);
        }

        if(!($request->input('inprogress')===NULL )){
            if($request->input('inprogress')=='false'){
                $status->inprogress = 0;
            }
            else{
                $status->inprogress = 1;
            }
        }
        if(!($request->input('completed')===NULL )){//can check with current date and due date
            if($request->input('completed')=='false'){
                $status->completed = 0;
            }
            else{
                $status->completed = 1;
            }
        }
        if(!($request->input('deleted')===NULL )){
            if($request->input('deleted')=='false'){
                $status->deleted = 0;
            }
            else{
                $status->deleted = 1;
            }
        }

        return response()->json(['job'=> $job,'status'=>$status]);
    }
    public function viewTasks(Request $request){
        $user = auth()->user();
        if($user->role == 'admin'){
            //get all
            $users = Job::select('title','description','assignee','creator','duedate')
                // ->with(['status'])
                ->get();
            return $users;
        }
        else{
            //creator
            $jobCreator = Job::select('title','description','assignee','creator','duedate')
                ->where('creator', $user->id)
                ->get();
            $jobAssignee = Job::select('title','description','assignee','creator','duedate')
                ->where('assignee', $user->id)
                ->get();
            return response()->json([$jobCreator,$jobAssignee]);
        }
    }
}