<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\View\View;

class BlogController extends Controller
{
    public function index(): View
    {
        $posts = BlogPost::query()
            ->published()
            ->withCount(['likes', 'comments'])
            ->latest('published_at')
            ->paginate(9);

        return view('blog.index', [
            'posts' => $posts,
            'assetBase' => asset('templates/art-deco'),
        ]);
    }

    public function show(BlogPost $post): View
    {
        abort_unless($post->isPublished() || optional(auth()->user())->isAdmin(), 404);

        $post->load(['comments.user', 'author']);
        $post->loadCount(['likes', 'comments']);

        if (! auth()->check()) {
            session(['url.intended' => route('blog.show', $post)]);
        }

        return view('blog.show', [
            'post' => $post,
            'liked' => $post->likedBy(auth()->user()),
            'assetBase' => asset('templates/art-deco'),
            'shareUrl' => route('blog.show', $post),
        ]);
    }
}
