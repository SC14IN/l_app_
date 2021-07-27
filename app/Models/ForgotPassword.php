<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ForgotPassword extends Model{

    // use SoftDeletes;
    protected $table = 'forgot_password';
    protected $fillable = ['user_id', 'token'];
}