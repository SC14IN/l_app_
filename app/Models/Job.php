<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Job extends Model
{
    protected $table = 'jobs';
    protected $fillable = [
        'title', 'description', 'duedate','assignee','creator','status'
    ];
}
