<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LegalController extends Controller
{
    /**
     * Get Privacy Policy.
     */
    public function privacyPolicy(Request $request): JsonResponse
    {
        return $this->getPage(
            'privacy_policy',
            'Privacy Policy'
        );
    }

    /**
     * Get Refund & Cancellation Policy.
     */
    public function refundCancellation(Request $request): JsonResponse
    {
        return $this->getPage(
            'refund_policy',
            'Refund & Cancellation Policy'
        );
    }

    /**
     * Get Terms & Conditions.
     */
    public function termsAndConditions(Request $request): JsonResponse
    {
        return $this->getPage(
            'terms_and_conditions',
            'Terms & Conditions'
        );
    }

    /**
     * Get About Us.
     */
    public function aboutUs(Request $request): JsonResponse
    {
        return $this->getPage(
            'about_us',
            'About Us'
        );
    }

    /**
     * Fetch content from pages table.
     */
    private function getPage(
        string $column,
        string $title
    ): JsonResponse {

        $page = DB::connection('application')
            ->table('pages')
            ->select([
                'id',
                $column,
                'updated_at',
            ])
            ->first();

        if (!$page) {

            return response()->json([
                'success' => false,
                'message' => 'Page content not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => $title . ' fetched successfully.',
            'data' => [
                'id' => $page->id,
                'title' => $title,
                'content' => $page->{$column},
                'updated_at' => $page->updated_at,
            ],
        ]);
    }
}
