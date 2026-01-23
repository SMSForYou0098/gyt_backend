<?php

namespace App\Http\Controllers;

use App\Models\Influencer;
use Illuminate\Http\Request;

class InfluencerController extends Controller
{
    /**
     * Display a listing of all influencers.
     */
    public function index(Request $request)
    {
        try {
            $query = Influencer::select(['id', 'name', 'email', 'phone']);

            // Search by name, email, or social media handle
            if ($request->has('search')) {
                $search = $request->input('search');
                $query->where('name', 'like', "%$search%")
                    ->orWhere('email', 'like', "%$search%")
                    ->orWhere('social_media_handle', 'like', "%$search%");
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by platform
            if ($request->has('platform')) {
                $query->where('platform', $request->input('platform'));
            }

            $influencers = $query->get();

            return response()->json([
                'success' => true,
                'message' => 'Influencers retrieved successfully',
                'data' => $influencers
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving influencers',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created influencer in database.
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|unique:influencers',
                'phone' => 'nullable|string|max:20',
                'bio' => 'nullable|string',
                'social_media_handle' => 'nullable|string|max:255',
                'platform' => 'nullable|string|in:instagram,twitter,facebook,youtube,tiktok,other',
                'followers' => 'nullable|integer|min:0',
                'status' => 'nullable|string|in:active,inactive|default:active',
            ]);

            $influencer = Influencer::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Influencer created successfully',
                'data' => $influencer
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating influencer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified influencer.
     */
    public function show($id)
    {
        try {
            $influencer = Influencer::with(['events', 'bookings', 'masterBookings'])->findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Influencer retrieved successfully',
                'data' => $influencer
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Influencer not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving influencer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified influencer in database.
     */
    public function update(Request $request, $id)
    {
        try {
            $influencer = Influencer::findOrFail($id);

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'sometimes|email|unique:influencers,email,' . $id,
                'phone' => 'nullable|string|max:20',
                'bio' => 'nullable|string',
                'social_media_handle' => 'nullable|string|max:255',
                'platform' => 'nullable|string|in:instagram,twitter,facebook,youtube,tiktok,other',
                'followers' => 'nullable|integer|min:0',
                'status' => 'nullable|string|in:active,inactive',
            ]);

            $influencer->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Influencer updated successfully',
                'data' => $influencer
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Influencer not found',
            ], 404);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating influencer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified influencer from database (soft delete).
     */
    public function destroy($id)
    {
        try {
            $influencer = Influencer::findOrFail($id);
            $influencer->delete();

            return response()->json([
                'success' => true,
                'message' => 'Influencer deleted successfully',
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Influencer not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting influencer',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore a soft-deleted influencer.
     */
    public function restore($id)
    {
        try {
            $influencer = Influencer::onlyTrashed()->findOrFail($id);
            $influencer->restore();

            return response()->json([
                'success' => true,
                'message' => 'Influencer restored successfully',
                'data' => $influencer
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Influencer not found',
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error restoring influencer',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
