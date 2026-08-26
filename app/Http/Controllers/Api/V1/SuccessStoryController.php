<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SuccessStory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SuccessStoryController extends Controller
{
    /**
     * Get approved success stories.
     */
    public function index(Request $request): JsonResponse
    {
        $stories = SuccessStory::query()
            ->where('status', 1)
            ->orderByDesc('id')
            ->get();

        $data = $stories->map(function (SuccessStory $story) {
            return $this->formatStory($story);
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'Success stories fetched successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Get success stories submitted by the logged-in member.
     */
    public function myStories(Request $request): JsonResponse
    {
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $stories = SuccessStory::query()
            ->where('user_id', $member->id)
            ->orderByDesc('id')
            ->get();

        $data = $stories->map(function ($story) {
            return [
                'id' => $story->id,
                'groom_name' => $story->groom_name,
                'bride_name' => $story->bride_name,
                'detail' => $story->detail,

                'photo' => $story->photo
                    ? asset('photos/ss/' . $story->photo)
                    : null,

                'status' => (int) $story->status,

                'status_label' => match ((int) $story->status) {
                    1 => 'approved',
                    2 => 'rejected',
                    default => 'pending',
                },
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Your success stories fetched successfully.',
            'data' => [
                'stories' => $data,
            ],
        ]);
    }

    /**
     * Get a single success story belonging to the logged-in member.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
    |--------------------------------------------------------------------------
    | Find story belonging to logged-in member
    |--------------------------------------------------------------------------
    */

        $story = SuccessStory::query()
            ->where('id', $id)
            ->where('user_id', $member->id)
            ->first();

        /*
    |--------------------------------------------------------------------------
    | Story not found
    |--------------------------------------------------------------------------
    |
    | We intentionally return 404 here instead of revealing whether
    | the story exists for another member.
    |
    */

        if (!$story) {
            return response()->json([
                'success' => false,
                'message' => 'Success story not found.',
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'success' => true,
            'message' => 'Success story fetched successfully.',
            'data' => [
                'id' => $story->id,

                'groom_name' => $story->groom_name,

                'bride_name' => $story->bride_name,

                'detail' => $story->detail,

                'photo' => $story->photo
                    ? asset('photos/ss/' . $story->photo)
                    : null,

                'status' => (int) $story->status,

                'status_label' => match ((int) $story->status) {
                    1 => 'approved',
                    2 => 'rejected',
                    default => 'pending',
                },
            ],
        ]);
    }

    /**
     * Submit a new success story.
     */
    public function store(Request $request): JsonResponse
    {
        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |--------------------------------------------------------------------------
        | Validate request
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'groom_name' => [
                'required',
                'string',
                'max:255',
            ],

            'bride_name' => [
                'required',
                'string',
                'max:255',
            ],

            'detail' => [
                'required',
                'string',
                'max:5000',
            ],

            'photo' => [
                'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        /*
        |--------------------------------------------------------------------------
        | Photo directory
        |--------------------------------------------------------------------------
        */

        $photoDirectory = public_path('photos/ss');

        if (!File::exists($photoDirectory)) {
            File::makeDirectory(
                $photoDirectory,
                0755,
                true
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Generate photo filename
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | ss-1756123456-a8f31c.jpg
        |
        */

        $file = $request->file('photo');

        $extension = strtolower(
            $file->getClientOriginalExtension()
        );

        $filename =
            'ss-' .
            time() .
            '-' .
            Str::lower(Str::random(5)) .
            '.' .
            $extension;

        /*
        |--------------------------------------------------------------------------
        | Store photo
        |--------------------------------------------------------------------------
        */

        $file->move(
            $photoDirectory,
            $filename
        );

        /*
        |--------------------------------------------------------------------------
        | Create success story
        |--------------------------------------------------------------------------
        */

        try {

            $story = SuccessStory::create([
                'groom_name' => $validated['groom_name'],
                'bride_name' => $validated['bride_name'],
                'detail' => $validated['detail'],

                'photo' => $filename,

                'user_id' => $member->id,

                /*
                |--------------------------------------------------------------------------
                | New stories require admin approval
                |--------------------------------------------------------------------------
                */

                'status' => 0,
            ]);
        } catch (\Throwable $e) {

            /*
            |--------------------------------------------------------------------------
            | Remove uploaded photo if DB insert fails
            |--------------------------------------------------------------------------
            */

            $photoPath =
                $photoDirectory . DIRECTORY_SEPARATOR . $filename;

            if (File::exists($photoPath)) {
                File::delete($photoPath);
            }

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Unable to submit success story.',
            ], 500);
        }

        return response()->json([
            'success' => true,
            'message' =>
            'Success story submitted successfully. It will be visible after admin approval.',

            'data' => $this->formatStory($story, true),

        ], 201);
    }

    /**
     * Format success story response.
     */
    private function formatStory(
        SuccessStory $story,
        bool $includeStatus = false
    ): array {

        $data = [
            'id' => $story->id,

            'groom_name' =>
            $story->groom_name,

            'bride_name' =>
            $story->bride_name,

            'detail' =>
            $story->detail,

            'photo' =>
            $story->photo_url,
        ];

        if ($includeStatus) {

            $data['status'] =
                $this->statusLabel($story->status);
        }

        return $data;
    }

    /**
     * Convert database status into readable value.
     */
    private function statusLabel(?int $status): string
    {
        return match ((int) $status) {
            1 => 'approved',
            default => 'pending',
        };
    }

    /**
     * Update a success story belonging to the logged-in member.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        /*
    |--------------------------------------------------------------------------
    | Get authenticated member
    |--------------------------------------------------------------------------
    */

        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
    |--------------------------------------------------------------------------
    | Find member's story
    |--------------------------------------------------------------------------
    */

        $story = SuccessStory::query()
            ->where('id', $id)
            ->where('user_id', $member->id)
            ->first();

        if (!$story) {
            return response()->json([
                'success' => false,
                'message' => 'Success story not found.',
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Approved stories cannot be edited
    |--------------------------------------------------------------------------
    */

        if ((int) $story->status === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Approved success stories cannot be edited.',
            ], 403);
        }

        /*
    |--------------------------------------------------------------------------
    | Validate request
    |--------------------------------------------------------------------------
    */

        $validated = $request->validate([
            'groom_name' => [
                'required',
                'string',
                'max:255',
            ],

            'bride_name' => [
                'required',
                'string',
                'max:255',
            ],

            'detail' => [
                'required',
                'string',
            ],

            'photo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        /*
    |--------------------------------------------------------------------------
    | Update basic information
    |--------------------------------------------------------------------------
    */

        $story->groom_name = $validated['groom_name'];
        $story->bride_name = $validated['bride_name'];
        $story->detail = $validated['detail'];

        /*
    |--------------------------------------------------------------------------
    | Handle new photo
    |--------------------------------------------------------------------------
    */

        if ($request->hasFile('photo')) {

            $file = $request->file('photo');

            if (!$file->isValid()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Uploaded photo is invalid.',
                ], 422);
            }

            /*
        |--------------------------------------------------------------------------
        | Delete old photo
        |--------------------------------------------------------------------------
        */

            if (!empty($story->photo)) {

                $oldPhotoPath =
                    public_path('photos/ss/' . $story->photo);

                if (is_file($oldPhotoPath)) {
                    @unlink($oldPhotoPath);
                }
            }

            /*
        |--------------------------------------------------------------------------
        | Generate new filename
        |--------------------------------------------------------------------------
        */

            $extension = strtolower(
                $file->getClientOriginalExtension()
            );

            $filename =
                'ss-' .
                $story->id .
                '-' .
                time() .
                '.' .
                $extension;

            /*
        |--------------------------------------------------------------------------
        | Ensure directory exists
        |--------------------------------------------------------------------------
        */

            $directory = public_path('photos/ss');

            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            /*
        |--------------------------------------------------------------------------
        | Move file
        |--------------------------------------------------------------------------
        */

            $file->move(
                $directory,
                $filename
            );

            $story->photo = $filename;
        }

        /*
    |--------------------------------------------------------------------------
    | Reset rejected story to pending
    |--------------------------------------------------------------------------
    */

        if ((int) $story->status === 2) {
            $story->status = 0;
        }

        /*
    |--------------------------------------------------------------------------
    | Save
    |--------------------------------------------------------------------------
    */

        $story->save();

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'success' => true,
            'message' => 'Success story updated successfully.',

            'data' => [
                'id' => $story->id,

                'groom_name' =>
                $story->groom_name,

                'bride_name' =>
                $story->bride_name,

                'detail' =>
                $story->detail,

                'photo' => $story->photo
                    ? asset('photos/ss/' . $story->photo)
                    : null,

                'status' =>
                (int) $story->status,

                'status_label' => match ((int) $story->status) {
                    1 => 'approved',
                    2 => 'rejected',
                    default => 'pending',
                },
            ],
        ]);
    }

    /**
     * Delete a success story belonging to the logged-in member.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        /*
    |--------------------------------------------------------------------------
    | Get authenticated member
    |--------------------------------------------------------------------------
    */

        $member = $request->user();

        if (!$member) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
    |--------------------------------------------------------------------------
    | Find member's story
    |--------------------------------------------------------------------------
    */

        $story = SuccessStory::query()
            ->where('id', $id)
            ->where('user_id', $member->id)
            ->first();

        if (!$story) {
            return response()->json([
                'success' => false,
                'message' => 'Success story not found.',
            ], 404);
        }

        /*
    |--------------------------------------------------------------------------
    | Approved stories cannot be deleted
    |--------------------------------------------------------------------------
    */

        if ((int) $story->status === 1) {
            return response()->json([
                'success' => false,
                'message' => 'Approved success stories cannot be deleted.',
            ], 403);
        }

        /*
    |--------------------------------------------------------------------------
    | Delete photo
    |--------------------------------------------------------------------------
    */

        if (!empty($story->photo)) {

            $photoPath = public_path(
                'photos/ss/' . $story->photo
            );

            if (is_file($photoPath)) {
                @unlink($photoPath);
            }
        }

        /*
    |--------------------------------------------------------------------------
    | Delete database record
    |--------------------------------------------------------------------------
    */

        $story->delete();

        /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

        return response()->json([
            'success' => true,
            'message' => 'Success story deleted successfully.',
        ]);
    }
}
