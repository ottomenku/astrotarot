<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Support\HtmlSanitizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BlogPostController extends Controller
{
    public function index(): View
    {
        return view('admin.blog.index', [
            'posts' => BlogPost::query()->latest('id')->withCount(['likes', 'comments'])->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.form', [
            'post' => new BlogPost(['published_at' => now()]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $post = BlogPost::query()->create($this->validated($request));
        $this->storeCover($request, $post);

        return redirect()
            ->route('admin.blog.edit', $post)
            ->with('status', __('admin.blog.saved'));
    }

    public function edit(BlogPost $post): View
    {
        $post->load(['comments.user']);

        return view('admin.blog.form', [
            'post' => $post,
        ]);
    }

    public function update(Request $request, BlogPost $post): RedirectResponse
    {
        $post->update($this->validated($request, $post));
        $this->storeCover($request, $post);

        return redirect()
            ->route('admin.blog.edit', $post)
            ->with('status', __('admin.blog.saved'));
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->deleteCover($post);
        $post->delete();

        return redirect()
            ->route('admin.blog.index')
            ->with('status', __('admin.blog.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?BlogPost $post = null): array
    {
        $data = $request->validate([
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', Rule::unique('blog_posts', 'slug')->ignore($post?->id)],
            'title_hu' => ['required', 'string', 'max:160'],
            'title_en' => ['nullable', 'string', 'max:160'],
            'excerpt_hu' => ['nullable', 'string', 'max:300'],
            'excerpt_en' => ['nullable', 'string', 'max:300'],
            'body_hu' => ['required', 'string', 'max:50000'],
            'body_en' => ['nullable', 'string', 'max:50000'],
            'publish' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'cover' => ['nullable', 'image', 'max:4096'],
        ]);

        $slug = $data['slug'] ?? '';
        if ($slug === '') {
            $slug = Str::slug($data['title_hu']);
        }
        if ($slug === '') {
            $slug = 'cikk';
        }
        $base = $slug;
        $i = 2;
        while (BlogPost::query()->where('slug', $slug)->when($post, fn ($q) => $q->where('id', '!=', $post->id))->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        $publish = $request->boolean('publish');
        $publishedAt = $data['published_at'] ?? null;

        return [
            'user_id' => $request->user()->id,
            'slug' => $slug,
            'title_hu' => $data['title_hu'],
            'title_en' => $data['title_en'] ?? null,
            'excerpt_hu' => $data['excerpt_hu'] ?? null,
            'excerpt_en' => $data['excerpt_en'] ?? null,
            'body_hu' => HtmlSanitizer::articleBody($data['body_hu']),
            'body_en' => HtmlSanitizer::articleBody($data['body_en'] ?? ''),
            'published_at' => $publish
                ? ($publishedAt ? \Illuminate\Support\Carbon::parse($publishedAt) : ($post?->published_at ?? now()))
                : null,
        ];
    }

    private function storeCover(Request $request, BlogPost $post): void
    {
        if (! $request->hasFile('cover')) {
            return;
        }

        $file = $request->file('cover');
        if (! $file instanceof UploadedFile) {
            return;
        }

        // Keep covers off /blog so nginx and PHP's built-in server do not treat that URL as a directory.
        $dir = public_path('media/blog-covers');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $ext = strtolower((string) ($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg'));
        if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $ext = 'jpg';
        }
        $name = $post->id.'-'.Str::random(8).'.'.$ext;
        $file->move($dir, $name);
        $relative = 'media/blog-covers/'.$name;
        $this->deleteCover($post);
        $post->update(['cover_path' => $relative]);
    }

    private function deleteCover(BlogPost $post): void
    {
        if (! is_string($post->cover_path) || $post->cover_path === '') {
            return;
        }
        $full = public_path($post->cover_path);
        if (is_file($full)) {
            @unlink($full);
        }
    }
}
