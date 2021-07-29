<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Status extends Model{

    // use SoftDeletes;
    protected $table = 'status';
    protected $fillable = ['job_id', 'assigned','inprogress','completed','deleted'];
}