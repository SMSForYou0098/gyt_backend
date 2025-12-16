<?php

namespace App\Http\Controllers;

use App\Models\ContentMaster;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ContentMasterController extends Controller
{
  
    private function getValidationRules(int $userId, ?int $excludeId = null): array
    {
        return [
            'user_id' => 'required|exists:users,id',
            'title' => [
                'required',
                'string',
                'max:255',
                function ($attribute, $value, $fail) use ($userId, $excludeId) {
                    $query = ContentMaster::where('user_id', $userId)
                        ->where('title', $value);

                    if ($excludeId) {
                        $query->where('id', '!=', $excludeId);
                    }

                    if ($query->exists()) {
                        $fail('A content with this title already exists for this user.');
                    }
                },
            ],
            'content' => 'nullable|string',
            'type' => 'required|in:note,description',
        ];
    }

    public function index(Request $request)
    {
        try {
            $query = ContentMaster::query();

            // Filter by user_id if provided
            if ($request->has('user_id')) {
                $query->where('user_id', $request->user_id);
            }

            $contents = $query->orderBy('created_at', 'desc')->get();

            return response()->json([
                'status' => true,
                'data' => $contents
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch content masters',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            $request->validate($this->getValidationRules($request->user_id));

            $contentMaster = ContentMaster::create([
                'user_id' => $request->user_id,
                'title' => $request->title,
                'content' => $request->content,
                'type' => $request->type,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Content created successfully',
                'data' => $contentMaster
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $contentMaster = ContentMaster::with('user:id,name,email')->find($id);

            if (!$contentMaster) {
                return response()->json([
                    'status' => false,
                    'message' => 'Content not found'
                ], 404);
            }

            return response()->json([
                'status' => true,
                'data' => $contentMaster
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $contentMaster = ContentMaster::find($id);

            if (!$contentMaster) {
                return response()->json([
                    'status' => false,
                    'message' => 'Content not found'
                ], 404);
            }

            // Get rules and make title optional for updates
            $rules = $this->getValidationRules($contentMaster->user_id, $contentMaster->id);
            $rules['user_id'] = 'sometimes|exists:users,id';
            array_unshift($rules['title'], 'sometimes');

            $request->validate($rules);

            $contentMaster->update([
                'title' => $request->title ?? $contentMaster->title,
                'content' => $request->content ?? $contentMaster->content,
                'type' => $request->type ?? $contentMaster->type,
            ]);

            return response()->json([
                'status' => true,
                'message' => 'Content updated successfully',
                'data' => $contentMaster
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $contentMaster = ContentMaster::find($id);

            if (!$contentMaster) {
                return response()->json([
                    'status' => false,
                    'message' => 'Content not found'
                ], 404);
            }

            $contentMaster->delete();

            return response()->json([
                'status' => true,
                'message' => 'Content deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to delete content',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getByUser($userId)
    {
        try {
            if (empty($userId)) {
                return response()->json([
                    'status' => false,
                    'message' => 'User ID is required'
                ], 400);
            }
            $contents = ContentMaster::where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => true,
                'data' => $contents
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to fetch user contents',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
