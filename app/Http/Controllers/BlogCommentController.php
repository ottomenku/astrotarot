<?php

namespace App\Http\Controllers;

use App\Models\BlogComment;
use App\Models\BlogPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BlogCommentController extends Controller
{
    public function store(Request $request, BlogPost $post): RedirectResponse
    {
        abort_unless($post->isPublished(), 404);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:2000'],
        ]);

        $post->comments()->create([
            'user_id' => $request->user()->id,
            'body' => trim(strip_tags($data['body'])),
        ]);

        return redirect()
            ->route('blog.show', $post)
            ->withFragment('comments')
            ->with('status', __('messages.blog_comment_saved'));
    }

    public function destroy(BlogPost $post, BlogComment $comment): RedirectResponse
    {
        abort_unless($comment->blog_post_id === $post->id, 404);
        abort_unless($comment->canDelete(auth()->user()), 403);

        $comment->delete();

        return redirect()
            ->route('blog.show', $post)
            ->withFragment('comments')
            ->with('status', __('messages.blog_comment_deleted'));
    }
}
