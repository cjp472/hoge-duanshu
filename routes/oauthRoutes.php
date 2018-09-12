<?php
//oauth授权
Route::group([
    'namespace' => 'Oauth2',
], function () {
    Route::get('/authorize','AuthorizeController@index')
        ->middleware(['web','auth','shop','check-authorization-params'])->name('oauth.authorize.get');
    Route::post('/authorize', 'AuthorizeController@authLogin')
        ->middleware(['web','auth','shop','check-authorization-params'])->name('oauth.authorize.post');
    Route::post('access_token', 'AuthorizeController@issueToken')->name('oauth.authorize.access_token');
    Route::post('refresh_token', 'AuthorizeController@refresh_token')->name('oauth.authorize.refresh_token');
    Route::post('verify_token', 'AuthorizeController@verify_token')->name('oauth.authorize.verify_token');
    Route::get('get/info', 'UserInfoController@getInfo')
        ->middleware(['oauth'])->name('oauth.authorize.get_info');
    //openid方式
    Route::get('user/info', 'UserInfoController@getBaseInfo')
        ->middleware(['oauth'])->name('oauth.authorize.getBaseInfo');
    Route::get('content', 'ContentController@getContent')
        ->middleware(['oauth'])->name('oauth.authorize.getContent');
});