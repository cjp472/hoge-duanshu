<?php

Route::group([
    'namespace' => 'DH3tong',
], function () {
    Route::post('/sms/submit', 'SmsController@submit')->middleware('check.sms.signature')->name('SmsSubmit');
//    Route::get('/mobile/code', 'SmsController@mobile')->name('mobileCode');
});
Route::group([
    'namespace' => 'Aliyun',
], function () {
    Route::post('/ali/sms/send', 'SmsController@send')->middleware(['log','check.sms.signature'])->name('AliyunSmsSend');
    Route::post('/service/mobilecode', 'SmsController@mobileCode')->middleware('log')->name('AliyunMobileCode');
    Route::get('/captcha/code', 'CaptchaController@create')->name('captcha');
});
