<?php

namespace App\Http\Middleware;

use App\Models\Application;
use App\Services\Api\V1\ApplicationDatabaseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveApplication
{
    public function __construct(
        private ApplicationDatabaseService $databaseService
    ) {}

    public function handle(
        Request $request,
        Closure $next
    ): Response {

        /*
         * Mobile application identifies itself
         * using X-App-Code.
         */
        $appCode = $request->header('X-App-Code');

        if (!$appCode) {
            return response()->json([
                'success' => false,
                'message' => 'X-App-Code header is required.',
            ], 400);
        }

        /*
         * Applications table lives in the central database.
         */
        $application = Application::query()
            ->active()
            ->where('code', $appCode)
            ->first();

        if (!$application) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid application.',
            ], 400);
        }

        /*
         * Establish the dynamic application database connection.
         */
        try {
            $this->databaseService->connect($application);
        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to connect to application database.',
                'debug' => $e->getMessage(),
            ], 503);
        }

        /*
         * Store resolved application on the request.
         */
        $request->attributes->set(
            'application',
            $application
        );

        /*
         * Make the application available through
         * the service container.
         */
        app()->instance(
            Application::class,
            $application
        );

        return $next($request);
    }
}
