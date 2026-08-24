<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\MemberResource;
use App\Models\Member;
use Illuminate\Http\Request;

class TestController extends Controller
{
    public function index(Request $request)
    {
        $application = $request->attributes->get('application');

        $member = Member::query()
            ->first();

        return response()->json([
            'success' => true,

            'data' => [
                'application' => [
                    'id' => $application->id,
                    'name' => $application->name,
                    'code' => $application->code,
                ],

                'database' => app(
                    \App\Services\Api\V1\ApplicationDatabaseService::class
                )->databaseName(),

                'member' => $member
                    ? new MemberResource($member)
                    : null,
            ],
        ]);
    }
}
