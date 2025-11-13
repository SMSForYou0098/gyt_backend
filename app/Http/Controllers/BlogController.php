<?php

namespace App\Http\Controllers;

use App\Models\Blog;
use App\Models\BlogComment;
use App\Models\Category;
use App\Models\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


class BlogController extends Controller
{

    public function index()
    {
        $blogs = Blog::with(['userData' => function ($query) {
            $query->select('id', 'name');
        }])->select('id', 'title', 'thumbnail', 'created_at', 'categories', 'user_id', 'status')->get();


        $blogs->transform(function ($blog) {
            $categoryIds = collect($blog->categories ?? [])->flatten()->toArray();

            $categories = Category::whereIn('id', $categoryIds)
                ->get(['id', 'title']);

            $blog->categories = $categories;

            return $blog;
        });

        return response()->json([
            'status' => true,
            'data' => $blogs
        ]);
    }

    public function statusData()
    {
        $blogs = Blog::with(['userData' => function ($query) {
            $query->select('id', 'name');
        }])->where('status', 1)->select('id', 'title', 'thumbnail', 'created_at', 'categories', 'user_id', 'content')->get();


        $blogs->transform(function ($blog) {
            $categoryIds = collect($blog->categories ?? [])->flatten()->toArray();

            $categories = \App\Models\Category::whereIn('id', $categoryIds)
                ->get(['id', 'title']);

            $blog->categories = $categories;

            $blog->content_length = strlen(strip_tags($blog->content ?? ''));
            unset($blog->content);

            return $blog;
        });

        return response()->json([
            'status' => true,
            'data' => $blogs
        ]);
    }

    public function store(Request $request)
    {
        try {

            $content = $request->content;
            $titleSlug = strtolower(str_replace(' ', '_', $request->title));
            $folder = 'blog/thumbnail/' . $titleSlug;
            // Extract and save base64 images from content
            $content = $this->saveContentImages($content, $titleSlug);

            $BlogData = new Blog();
            $BlogData->user_id = Auth::id();
            $BlogData->title = $request->title;
            $BlogData->content = $content;
            $BlogData->status = filter_var($request->status, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $BlogData->meta_title = $request->metaTitle;
            $BlogData->meta_description = $request->metaDescription;
            $BlogData->meta_keyword = $request->metaKeywords;

            $BlogData->categories = is_string($request->categories)
                ? json_decode($request->categories)
                : $request->categories;

            $BlogData->tags = is_string($request->tags)
                ? json_decode($request->tags)
                : $request->tags;

            if ($request->hasFile('thumbnail')) {
                $BlogData->thumbnail = $this->storeFile($request->file('thumbnail'), $folder);
            }

            $BlogData->save();

            return response()->json([
                'status' => true,
                'message' => 'Blog created successfully',
                'data' => $BlogData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to create Blog',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    private function saveContentImages($content, $titleSlug)
    {
        if (!$content) return $content;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $content);
        libxml_clear_errors();

        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if (strpos($src, 'data:image') === 0) {
                // Extract mime type and data
                preg_match('/^data:image\/(\w+);base64,/', $src, $type);
                $data = substr($src, strpos($src, ',') + 1);
                $data = base64_decode($data);

                $extension = $type[1] ?? 'png';
                $fileName = 'blogimg-' . time() . '-' . uniqid() . '.' . $extension;
                $folder = 'uploads/blog/content_images/' . $titleSlug;
                $filePath = $folder . '/' . $fileName;

                Storage::disk('public')->put($filePath, $data);

                // Set correct URL for the <img src="">
                $img->setAttribute('src', Storage::disk('public')->url($filePath));
            }
        }

        // Return cleaned-up HTML
        $body = $dom->getElementsByTagName('body')->item(0);
        $newContent = '';
        foreach ($body->childNodes as $child) {
            $newContent .= $dom->saveHTML($child);
        }

        return $newContent;
    }

    public function show(string $id)
    {
        $BlogData = Blog::with(['userData' => function ($query) {
            $query->select('id', 'name');
        }])->where('id', $id)->firstOrFail();

        if (!$BlogData) {
            return response()->json(['status' => false, 'message' => 'BlogData not found'], 404);
        }

        $BlogData->view_count = ($BlogData->view_count ?? 0) + 1;
        $BlogData->save();

        return response()->json([
            'status' => true,
            'data' => $BlogData,
            'categories' => $BlogData->category_relation,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        try {
            $BlogData = Blog::findOrFail($id);

            $titleSlug = strtolower(str_replace(' ', '_', $request->title));
            $folder = 'blog/thumbnail/' . $titleSlug;

            $content = $this->saveContentImages($request->content, $titleSlug);

            $BlogData->user_id = Auth::id();
            $BlogData->title = $request->title;
            $BlogData->content = $content;
            $BlogData->status = filter_var($request->status, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $BlogData->meta_title = $request->metaTitle;
            $BlogData->meta_description = $request->metaDescription;
            $BlogData->meta_keyword = $request->metaKeywords;

            $BlogData->categories = is_string($request->categories)
                ? json_decode($request->categories)
                : $request->categories;

            $BlogData->tags = is_string($request->tags)
                ? json_decode($request->tags)
                : $request->tags;

            if ($request->hasFile('thumbnail')) {
                if (!empty($BlogData->thumbnail)) {
                    $oldPath = str_replace('/storage/', '', parse_url($BlogData->thumbnail, PHP_URL_PATH));
                    Storage::disk('public')->delete($oldPath);
                }
                $BlogData->thumbnail = $this->storeFile($request->file('thumbnail'), $folder);
            }

            $BlogData->save();

            return response()->json([
                'status' => true,
                'message' => 'Blog updated successfully',
                'data' => $BlogData,
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => false,
                'message' => 'Failed to update Blog',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy(string $id)
    {
        $BlogData = Blog::where('id', $id)->firstOrFail();
        if (!$BlogData) {
            return response()->json(['status' => false, 'message' => 'BlogData not found'], 404);
        }

        $BlogData->delete();
        return response()->json(['status' => true, 'message' => 'BlogData deleted successfully'], 200);
    }

    private function storeFile($file, $folder, $disk = 'public')
    {
        if (!$file) return null;

        $filename = uniqid() . '_' . $file->getClientOriginalName();
        $path = $file->storeAs('uploads/' . $folder, $filename, $disk);
        return Storage::disk($disk)->url($path);
    }

    public function cetegoryData($id)
    {
        if (!$id) {
            return response()->json(['status' => false, 'message' => 'Blog ID is required'], 200);
        }

        $blog = Blog::find($id);

        if (!$blog) {
            return response()->json(['status' => false, 'message' => 'Blog not found'], 200);
        }

        $categoryIds = $blog->categories ?? [];

        if (empty($categoryIds)) {
            return response()->json(['status' => false, 'message' => 'No categories found for this blog'], 200);
        }

        $relatedBlogs = Blog::where('id', '!=', $id)
            ->where(function ($query) use ($categoryIds) {
                foreach ($categoryIds as $catId) {
                    $query->orWhereJsonContains('categories', $catId);
                }
            })
            ->with(['userData:id,name'])
            ->select('id', 'title', 'thumbnail', 'categories', 'user_id', 'created_at', 'content')
            ->latest()
            ->take(6)
            ->get();

        $relatedBlogs->transform(function ($blog) {
            $blog->user_name = $blog->userData->name ?? null;

            $catIds = $blog->categories ?? [];
            $categoryTitles = Category::whereIn('id', $catIds)->get(['id', 'title']);

            $blog->categories = $categoryTitles->map(fn($cat) => [
                'id' => $cat->id,
                'title' => $cat->title,
            ]);

            // Calculate content length and remove content
            $blog->content_length = strlen(strip_tags($blog->content ?? ''));
            unset($blog->content);

            unset($blog->userData);

            return $blog;
        });

        return response()->json([
            'status' => true,
            'data' => $relatedBlogs,
        ], 200);
    }


    public function deshbordData(Request $request)
    {
        $totalPosts = Blog::count();
        $totalComments = BlogComment::count();
        $totalUsers = User::count();
        $activeBlogs = Blog::where('status', 1)->count();
        $blogCategories = Category::count();
        $inactiveBlogs = Blog::where('status', 0)->count();

        $lastUpdated = now()->format('n/j/Y, g:i:s A'); // Example: 7/14/2025, 1:24:17 PM

        return response()->json([
            'status' => true,
            'data' => [
                'total_posts' => $totalPosts,
                'total_comments' => $totalComments,
                'total_users' => $totalUsers,
                'active_blogs' => $activeBlogs,
                'blog_categories' => $blogCategories,
                'inactive_blogs' => $inactiveBlogs,
                'last_updated' => $lastUpdated,
            ]
        ]);
    }

    public function topViewedBlogs()
    {

        $blogs = Blog::whereNotNull('view_count')
            ->orderByDesc('view_count')
            ->with(['userData:id,name'])
            ->take(10)
            ->get(['id', 'title', 'thumbnail', 'view_count', 'user_id', 'created_at']);

        // Attach user name
        $blogs->transform(function ($blog) {
            $blog->user_name = $blog->userData->name ?? null;
            unset($blog->userData);
            return $blog;
        });

        return response()->json([
            'status' => true,
            'message' => 'Top 10 most viewed blogs',
            'data' => $blogs
        ]);
    }

    public function chartStats(Request $request)
    {
        $year = $request->get('year', Carbon::now()->year); // Default: current year

        // Generate month labels (Jan - Dec)
        $months = collect(range(1, 12))->map(function ($month) {
            return Carbon::create()->month($month)->format('M');
        });

        // Get posts count grouped by month for given year
        $posts = DB::table('blogs')
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as count'))
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        // Get comments count grouped by month for given year
        $comments = DB::table('blog_comments')
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as count'))
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        // Get users count grouped by month for given year
        $users = DB::table('users')
            ->select(DB::raw('MONTH(created_at) as month'), DB::raw('count(*) as count'))
            ->whereYear('created_at', $year)
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        // Helper to build 12-month array with 0 default
        $getMonthlyData = function ($dataArray) {
            return collect(range(1, 12))->map(function ($month) use ($dataArray) {
                return $dataArray[$month] ?? 0;
            });
        };

        return response()->json([
            'status' => true,
            'year' => $year,
            'options' => [
                'xaxis' => ['categories' => $months],
            ],
            'series' => [
                [
                    'name' => 'Posts',
                    'data' => $getMonthlyData($posts),
                ],
                [
                    'name' => 'Comments',
                    'data' => $getMonthlyData($comments),
                ],
                [
                    'name' => 'Users',
                    'data' => $getMonthlyData($users),
                ]
            ]
        ]);
    }

    public function getMostUsedCategory()
    {
        $allCategoryIds = [];

        $blogs = Blog::select('categories')->get();

        foreach ($blogs as $blog) {
            $ids = json_decode($blog->categories, true);
            if (is_array($ids)) {
                $allCategoryIds = array_merge($allCategoryIds, $ids);
            }
        }

        if (empty($allCategoryIds)) {
            return response()->json(['status' => false, 'message' => 'No categories found.']);
        }

        $counts = array_count_values($allCategoryIds);
        arsort($counts);

        $mostUsedCategoryId = array_key_first($counts);

        $category = Category::find($mostUsedCategoryId);

        if (!$category) {
            return response()->json(['status' => false, 'message' => 'Category not found']);
        }

        return response()->json([
            'status' => true,
            'category_id' => $category->id,
            'category_title' => $category->title,
            'used_count' => $counts[$mostUsedCategoryId]
        ]);
    }
}
