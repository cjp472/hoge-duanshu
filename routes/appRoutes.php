<?php
//内容管理
Route::group([
    'namespace' => 'Auth',
], function () {
    Route::post('login', 'LoginController@applogin')->name('appLogin');
});


//内容管理
Route::group([
    'namespace' => 'Content',
], function () {
    Route::get('sync/all', 'ContentController@appContentSync')->name('appContentSync');
    Route::get('sync/column', 'ContentController@createColumn')->name('appCreateColumn');
    Route::get('sync/article', 'ContentController@createArticle')->name('appCreateArticle');
    Route::get('sync/audio', 'ContentController@createAudio')->name('appCreateAudio');
    Route::get('sync/video', 'ContentController@createVideo')->name('appCreateVideo');
    Route::get('sync/live', 'ContentController@createLive')->name('appCreateLive');
    Route::get('sync/member', 'ContentController@createMember')->name('appCreateMember');
    Route::get('sync/comment', 'ContentController@createComment')->name('createComment');
    Route::get('sync/order', 'ContentController@orderToApp')->name('orderToApp');
    Route::get('sync/type','ContentController@createType')->name('createType');
    Route::get('sync/banner','ContentController@createBanner')->name('createBanner');
});

//评论管理
Route::group([
   'namespace' => 'Comment',
], function () {
    Route::post('comment/callback', 'CommentController@callback')->middleware('app.sign')->name('callback');
    Route::post('comment/delete', 'CommentController@deleteComment')->middleware('app.sign')->name('deleteComment');
});

//登录注册管理
Route::group([
    'namespace' => 'Auth',
], function () {
    Route::post('mobile/register', 'LoginController@mobileRegister')->middleware('app.sign')->name('mobileRegister');
    Route::post('mobile/login','LoginController@mobileLogin')->name('mobileLogin');
    Route::get('mobile/bind','BindController@mobileBind')->name('mobileBind');
    Route::post('mobile/password/update','LoginController@updatePassword')->name('mobileUpdatePassword');
});



//订单管理
Route::group([
    'namespace' => 'Order',
    'prefix'    => 'order',
], function () {
    Route::post('callback', 'OrderController@callback')->middleware('app.sign')->name('appOrderCallback');
    Route::get('lists', 'OrderController@orderList')->name('appOrderList');
});