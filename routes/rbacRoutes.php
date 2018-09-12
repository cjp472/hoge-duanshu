<?php
//内容管理
Route::group([
    'namespace' => 'Role',
    'prefix'    => 'role',
], function () {
    Route::get('/user/lists', 'UserController@roleUser')->name('roleUserList');
    Route::get('/user/delete', 'UserController@deleteRoleUser')->name('deleteRoleUser');
    Route::post('/user/add', 'UserController@addRoleUser')->name('addRoleUser');
});