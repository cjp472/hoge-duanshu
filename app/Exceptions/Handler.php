<?php

namespace App\Exceptions;

use App\Events\ErrorHandle;
use Exception;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Validation\ValidationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use League\OAuth2\Server\Exception\OAuthException;
use Psy\Util\Json;
use Symfony\Component\Debug\Exception\FlattenException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        AuthorizationException::class,
        HttpException::class,
        ModelNotFoundException::class,
        ValidationException::class,
        OAuthException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $e
     * @return void
     */
    public function report(Exception $e)
    {
        parent::report($e);
        if (!$this->shouldntReport($e)) {
            event(new ErrorHandle($e));
        }
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $e
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $e)
    {
        if ($e instanceof HttpResponseException) {
            $response = $e->getResponse();
        } elseif ($e instanceof ModelNotFoundException) {
            $response = $this->dataNotFound();
        } elseif ($e instanceof AuthorizationException) {
            $e = new HttpException(403, $e->getMessage());
            $response = $this->toIlluminateResponse($this->convertExceptionToResponse($e), $e);
        } elseif ($e instanceof ValidationException) {
            $response = $this->convertValidationExceptionToResponse($e,$request);
        } elseif ($e instanceof TokenMismatchException){
            $response = response('TokenMismatch.', 422);
        } else{
            if ($this->isHttpException($e)) {
                $response = $this->toIlluminateResponse($this->renderHttpException($e), $e);
            } else {
                $response = $this->toIlluminateResponse($this->convertExceptionToResponse($e), $e);
            }
        }
        $response = app('Barryvdh\Cors\CorsService')->addActualRequestHeaders($response, $request);
        return $response;
    }

    protected function convertExceptionToResponse(Exception $e)
    {
        if(config('app.debug')){
            return parent::convertExceptionToResponse($e);
        }
        return $this->forbidReport($e);
    }

    protected function forbidReport(Exception $e)
    {
        $e = FlattenException::create($e);
        if($e->getStatusCode() == 404){
            return response('Page Not Found',$e->getStatusCode(), $e->getHeaders());
        }
        return response('Internal Server Error', $e->getStatusCode(), $e->getHeaders());
    }

    protected function validated($validation)
    {
        if($validation && is_array($validation)){
            foreach ($validation as $key=>$item){
                return response(Json::encode([
                    'error' => 'error_'.$key,
                    'message' => $item[0],
                ]));
            }
        }
        return response('Validation.', 422);
    }

    protected function dataNotFound()
    {
        return response(Json::encode([
            'error' => 'data-not-fond',
            'message' => trans('validation.data-not-fond',[]),
        ]));
    }

    /**
     * Create a response object from the given validation exception.
     *
     * @param  \Illuminate\Validation\ValidationException  $e
     * @param  \Illuminate\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function convertValidationExceptionToResponse(ValidationException $e, $request)
    {
        $errors = $e->validator->errors()->getMessages();

        return $this->validated($errors);
    }
}
