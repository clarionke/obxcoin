<?php

namespace App\Exceptions;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $exception
     * @return void
     */
    public function report(Throwable $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Throwable  $exception
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function render($request, Throwable $exception)
    {
        if ($exception instanceof UserApiException) {
            $data = [
                'message' => $exception->getMessage(),
            ];

            $status = (int) $exception->getCode();
            if ($status < 400 || $status > 599) {
                $status = 400;
            }
            return response()->json($data, $status);
        }

        $response = parent::render($request, $exception);

        if (app()->environment('production')) {
            $errorId = (string) Str::uuid();
            $status = $exception instanceof HttpExceptionInterface
                ? $exception->getStatusCode()
                : (int) $response->getStatusCode();

            if ($status >= 500) {
                Log::error('Unhandled exception', [
                    'error_id' => $errorId,
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_id' => optional($request->user())->id,
                    'exception' => get_class($exception),
                    'message' => $exception->getMessage(),
                    'trace' => $exception->getTraceAsString(),
                ]);

                if ($request->expectsJson() || $request->is('api/*') || $request->ajax()) {
                    return response()->json([
                        'message' => __('Something went wrong. Please try again later!'),
                        'error_id' => $errorId,
                    ], 500);
                }

                return response()->view('errors.500', ['errorId' => $errorId], 500);
            }
        }

        return $response;
    }
}
