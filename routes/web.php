<?php

/** @var \Laravel\Lumen\Routing\Router $router */

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});
$router->group(['prefix' => 'api'], function () use ($router) {
    // Matches "/api/register
    $router->post('register', 'AuthController@register');
    $router->post('login', 'AuthController@login');
    // $router->post('logout', 'AuthController@logout');
    $router->post('emailVerify', 'AuthController@emailVerify');
    // $router->post('sendEmail', 'MailController@sendEmail');
    $router->post('forgotPassword', 'AuthController@forgotPassword');
    $router->post('resetPassword', 'AuthController@resetPassword');
    $router->get('test', 'AuthController@test');
    // $router->get('listUsers', 'UserController@listUsers');
});
$router->group(['prefix' => 'api','middleware'=>'auth'], function () use ($router) {
    $router->get('listUsers', 'UserController@listUsers');
    $router->get('delSelf', 'UserController@delSelf');
    $router->delete('delUser', 'UserController@delUser');
    $router->post('createUser', 'UserController@createUser');
    $router->get('createUser', 'UserController@createUser');

    $router->post('filter', 'UserController@filter');
    $router->get('filter', 'UserController@filter');
    $router->post('createUser', 'UserController@createUser');
    $router->put('update', 'UserController@update');
    $router->post('createJob', 'JobController@createJob');
    $router->put('updateJob', 'JobController@updateJob');
    $router->put('updateStatus', 'JobController@updateStatus');
    $router->get('viewJobs', 'JobController@viewJobs');
    $router->delete('deleteJob','JobController@deleteJob');

    $router->post('filterJobs', 'JobController@filterJobs');
    $router->get('filterJobs', 'JobController@filterJobs');

});
