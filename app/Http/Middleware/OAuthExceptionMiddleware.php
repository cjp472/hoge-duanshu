<?php
/**
 * Created by PhpStorm.
 * User: hoge
 * Date: 2017/5/16
 * Time: 10:14
 */

namespace App\Http\Middleware;


use Illuminate\Http\JsonResponse;
use League\OAuth2\Server\Exception\OAuthException;
use LucaDegasperi\OAuth2Server\Middleware\OAuthExceptionHandlerMiddleware;

class OAuthExceptionMiddleware extends OAuthExceptionHandlerMiddleware
{
    public function handle($request, \Closure $next)
    {
        try {
            $response = $next($request);
            // Was an exception thrown? If so and available catch in our middleware
            if (isset($response->exception) && $response->exception) {
                throw $response->exception;
            }

            return $response;
        } catch (OAuthException $e) {
            $data = [
                'error' => $e->errorType,
                'message' => $e->getMessage(),
            ];
            return new JsonResponse($data, $e->httpStatusCode, $e->getHttpHeaders());
        }
    }

}