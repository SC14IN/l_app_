<?php

namespace App\Console;
use App\Mail\DailyTaskEmail;
use Illuminate\Console\Scheduling\Schedule;
use Laravel\Lumen\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\DB;
use  App\Models\User;
use  App\Models\Job;

use App\Mail\TestEmail;
use App\Mail\VerifyEmail;
use App\Mail\WelcomeEmail;
use App\Mail\TaskAssignmentEmail;
use App\Mail\StatusUpdateEmail;

use Illuminate\Support\Facades\Mail;

use Illuminate\Support\Facades\Auth;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        //
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    // protected function schedule(Schedule $schedule)
    // {                      


    //     $schedule->call(function () {
    //         $id = 11;
    //         $user = User::where('id',$id)->first();
    //         try{
    //             $data = ['name' => $user->name,'token'=>'your tasks for today'];
    //             Mail::to($user->email)->send(new DailyTaskEmail($data));
    //         }
    //         catch(\Exception $e){
    //         }
    //     })->everyMinute();
    // }
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
            $email =$user->email;
            try{
                $data = ['token'=>$jobs];
                Mail::to($email)->send(new DailyTaskEmail($data));
            }
            catch(\Exception $e){
            }
        }
    }
    protected function schedule(Schedule $schedule)
    {                      
        $schedule->call(function () {
            $this->dailyEmailData();
        })->monthly();
    }
}
