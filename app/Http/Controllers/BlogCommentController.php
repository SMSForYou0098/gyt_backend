<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogComment;
use Auth;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{

    public function store(Request $request, $blog_id)
    {
        $newComment = new BlogComment();
        $newComment->user_id = Auth::id();
        $newComment->blog_id = $blog_id;
        $newComment->comment = $request->comment;

        if ($request->id) {
            $newComment->replier_id = $request->id;
        }

        $newComment->replirs = $request->replirs ?? null;
        $newComment->save();

        if ($request->id) {
            $parent = BlogComment::find($request->id);

            if ($parent) {
                $existingReplies = $parent->replirs ?? [];
                $existingReplies[] = $newComment->id;
                $parent->replirs = $existingReplies;
                $parent->save();
            }
        }

        return response()->json([
            'status' => true,
            'message' => 'Comment stored successfully',
            'data' => $newComment
        ], 200);
    }

    public function show(string $id)
    {
        $comments = BlogComment::with(['userData:id,name,photo'])
            ->where('blog_id', $id)
            ->get();


        if ($comments->isEmpty()) {
            return response()->json(['status' => false, 'message' => 'No comments found'], 200);
        }

        return response()->json([
            'status' => true,
            'data' => $comments
        ], 200);
    }

    public function destroy(string $id)
    {
        $BlogData = BlogComment::where('id', $id)->firstOrFail();

        // Delete all replies if exist
        if (!empty($BlogData->replirs) && is_array($BlogData->replirs)) {
            BlogComment::whereIn('id', $BlogData->replirs)->delete();
        }

        // Delete main comment
        $BlogData->delete();

        return response()->json([
            'status' => true,
            'message' => 'Comment and its replies deleted successfully',
        ], 200);
    }

    public function toggleLike(Request $request, $id)
    {
        $userId = auth()->id();
        $comment = BlogComment::findOrFail($id);

        $likes = $comment->likes ?? [];
        $likeAction = filter_var($request->like, FILTER_VALIDATE_BOOLEAN);

        if ($likeAction) {
            if (!in_array($userId, $likes)) {
                $likes[] = $userId;
            }
            $message = 'Liked';
        } else {
            $likes = array_values(array_diff($likes, [$userId]));
            $message = 'Unliked';
        }

        $comment->likes = $likes;
        $comment->save();

        return response()->json([
            'status' => true,
            'message' => $message,
            'total_likes' => count($likes),
            'likes' => $likes,
            'liked_by_you' => in_array($userId, $likes),
        ]);
    }

    public function mostLikedCommentWithBlog()
    {
        $comments = BlogComment::with('blog:id,title,thumbnail')
            ->get()
            ->map(function ($comment) {
                $comment->likes_count = is_array($comment->likes) ? count($comment->likes) : 0;
                return $comment;
            })
            ->sortByDesc('likes_count') // First sort by likes
            // ->sortByDesc('created_at')  // Then by latest
            ->take(5)                   // Get top 5
            ->values();                 // Reindex

        if ($comments->isEmpty()) {
            return response()->json([
                'status' => false,
                'message' => 'No liked comments found',
            ], 200);
        }

        $response = $comments->map(function ($comment) {
            return [
                'comment_id' => $comment->id,
                'comment' => $comment->comment,
                'likes_count' => $comment->likes_count,
                'likes' => $comment->likes ?? [],
                'blog' => $comment->blog,
            ];
        });

        return response()->json([
            'status' => true,
            'top_liked_comments' => $response,
        ]);
    }
}
