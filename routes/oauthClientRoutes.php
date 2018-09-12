<?php
//oauth授权
Route::group([
//    'namespace' => 'OauthClient',
], function () {
    Route::get('/m2ocloud','M2OController@m2oCloudAuth')->middleware('m2oCloud')->name('m2oCloudAuth');

});