<?php

namespace App\Http\Controllers;

use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;

class BlogLikeController extends Controller
{
    public function __invoke(BlogPost $post): RedirectResponse
    {
        abort_unless($post->isPublished(), 404);

        $user = auth()->user();
        $existing = $post->likes()->where('user_id', $user->id)->first();
        if ($existing) {
            $existing->delete();
        } else {
            $post->likes()->create(['user_id' => $user->id]);
        }

        return back();
    }
}
