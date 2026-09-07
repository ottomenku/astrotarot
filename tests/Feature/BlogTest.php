<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlogTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_can_read_published_posts_with_facebook_tags(): void
    {
        $post = BlogPost::factory()->create([
            'slug' => 'facebook-cikk',
            'title_hu' => 'Holdfogyatkozás és a tarot',
            'excerpt_hu' => 'Rövid Facebook leírás a cikkhez.',
        ]);

        $this->get(route('blog.index'))
            ->assertOk()
            ->assertSee('Holdfogyatkozás és a tarot', false)
            ->assertSee('og:type', false);

        $this->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('property="og:type"', false)
            ->assertSee('article', false)
            ->assertSee('Holdfogyatkozás és a tarot', false)
            ->assertSee('Rövid Facebook leírás a cikkhez.', false)
            ->assertSee('facebook.com/sharer', false);
    }

    public function test_guests_cannot_view_drafts(): void
    {
        $post = BlogPost::factory()->draft()->create(['slug' => 'piszkozat']);

        $this->get(route('blog.show', $post))->assertNotFound();
        $this->get(route('blog.index'))->assertDontSee('piszkozat');
    }

    public function test_admin_can_preview_drafts(): void
    {
        $admin = User::factory()->admin()->create();
        $post = BlogPost::factory()->draft()->create([
            'slug' => 'piszkozat',
            'title_hu' => 'Még nem élő cikk',
        ]);

        $this->actingAs($admin)
            ->get(route('blog.show', $post))
            ->assertOk()
            ->assertSee('Még nem élő cikk', false);
    }

    public function test_logged_in_user_can_like_and_comment(): void
    {
        $user = User::factory()->create();
        $post = BlogPost::factory()->create(['slug' => 'lajk-teszt']);

        $this->actingAs($user)
            ->post(route('blog.like', $post))
            ->assertRedirect();

        $this->assertDatabaseHas('blog_likes', [
            'blog_post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('blog.like', $post))
            ->assertRedirect();

        $this->assertDatabaseMissing('blog_likes', [
            'blog_post_id' => $post->id,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('blog.comments.store', $post), ['body' => 'Szép cikk, köszönöm.'])
            ->assertRedirect();

        $this->assertDatabaseHas('blog_comments', [
            'blog_post_id' => $post->id,
            'user_id' => $user->id,
            'body' => 'Szép cikk, köszönöm.',
        ]);
    }

    public function test_guest_cannot_like_or_comment(): void
    {
        $post = BlogPost::factory()->create();

        $this->post(route('blog.like', $post))->assertRedirect(route('login'));
        $this->post(route('blog.comments.store', $post), ['body' => 'Hello'])->assertRedirect(route('login'));
    }

    public function test_user_can_delete_own_comment(): void
    {
        $user = User::factory()->create();
        $post = BlogPost::factory()->create();
        $comment = BlogComment::query()->create([
            'blog_post_id' => $post->id,
            'user_id' => $user->id,
            'body' => 'Törlendő',
        ]);

        $this->actingAs($user)
            ->delete(route('blog.comments.destroy', [$post, $comment]))
            ->assertRedirect();

        $this->assertDatabaseMissing('blog_comments', ['id' => $comment->id]);
    }

    public function test_admin_can_create_a_post(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.blog.store'), [
                'title_hu' => 'Új tarot cikk',
                'excerpt_hu' => 'Facebook szöveg',
                'body_hu' => '<p>Első bekezdés.</p>',
                'publish' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('blog_posts', [
            'title_hu' => 'Új tarot cikk',
            'slug' => 'uj-tarot-cikk',
        ]);
        $this->assertNotNull(BlogPost::query()->where('slug', 'uj-tarot-cikk')->value('published_at'));
    }

    public function test_non_admin_cannot_manage_blog(): void
    {
        $user = User::factory()->role(Role::Pro)->create();

        $this->actingAs($user)
            ->get(route('admin.blog.index'))
            ->assertForbidden();
    }
}
