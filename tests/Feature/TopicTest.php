<?php

namespace Tests\Feature;

use App\Models\Topic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class TopicTest extends NotesTestCase
{
    public function test_create_sanitizes_content_and_syncs_tags(): void
    {
        $alice = $this->user();
        $node = $this->node($alice, public: true);

        $this->actingAs($alice)->post(route('topics.store'), [
            'node_id' => $node->id,
            'title' => 'Hello',
            'content' => '<p onclick="x()">Hi</p><script>alert(1)</script>',
            'is_public' => true,
            'tags' => ['php', ' laravel ', 'php', ''],
        ])->assertRedirect();

        $topic = Topic::firstOrFail();
        $this->assertStringNotContainsString('script', $topic->content);
        $this->assertStringNotContainsString('onclick', $topic->content);
        $this->assertStringContainsString('Hi', $topic->content);
        $this->assertEqualsCanonicalizing(['laravel', 'php'], $topic->tags()->pluck('value')->all());
        $this->assertSame($alice->id, $topic->user_id);
    }

    public function test_public_topic_cannot_be_created_in_a_private_node(): void
    {
        $alice = $this->user();
        $node = $this->node($alice);

        $this->actingAs($alice)->post(route('topics.store'), [
            'node_id' => $node->id,
            'title' => 'Hello',
            'is_public' => true,
        ])->assertSessionHasErrors('is_public');

        $this->assertDatabaseCount('topics', 0);
    }

    public function test_only_visible_topics_are_listed_for_a_node(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $node = $this->node($alice, public: true);
        $this->topic($alice, $node, public: true, title: 'Open');
        $this->topic($alice, $node, public: false, title: 'Secret');

        $this->actingAs($bob)->get(route('notes.index', ['node' => $node->id]))
            ->assertInertia(fn ($page) => $page->has('topics', 1)->where('topics.0.title', 'Open')->where('topics.0.is_owner', false));

        $this->actingAs($alice)->get(route('notes.index', ['node' => $node->id]))
            ->assertInertia(fn ($page) => $page->has('topics', 2));
    }

    public function test_selected_topic_is_hidden_from_users_who_cannot_see_it(): void
    {
        $alice = $this->user();
        $node = $this->node($alice, public: true);
        $secret = $this->topic($alice, $node, title: 'Secret');

        $this->actingAs($this->user())->get(route('notes.index', ['node' => $node->id, 'topic' => $secret->id]))
            ->assertInertia(fn ($page) => $page->where('topic', null));
    }

    public function test_only_owner_or_admin_can_edit_and_delete(): void
    {
        $alice = $this->user();
        $node = $this->node($alice, public: true);
        $topic = $this->topic($alice, $node, public: true);

        $this->actingAs($this->user())->patch(route('topics.update', $topic), ['title' => 'x'])->assertForbidden();
        $this->actingAs($this->user())->delete(route('topics.destroy', $topic), ['node' => $node->id])->assertForbidden();

        $this->actingAs($this->admin)->patch(route('topics.update', $topic), ['title' => 'By admin', 'is_public' => true])->assertRedirect();
        $this->assertSame('By admin', $topic->fresh()->title);

        $this->actingAs($alice)->delete(route('topics.destroy', $topic).'?node='.$node->id)->assertRedirect();
        $this->assertNull(Topic::find($topic->id));
    }

    public function test_deleting_a_link_only_detaches_it(): void
    {
        $alice = $this->user();
        $home = $this->node($alice, public: true, title: 'Home');
        $other = $this->node($alice, public: true, title: 'Other');
        $topic = $this->topic($alice, $home, public: true);
        $topic->nodes()->attach($other->id, ['is_link' => true]);

        $this->actingAs($alice)->delete(route('topics.destroy', $topic).'?node='.$other->id)->assertRedirect();

        $this->assertNotNull(Topic::find($topic->id));
        $this->assertSame([$home->id], $topic->fresh()->nodes->pluck('id')->all());
    }

    public function test_favorites_toggle_per_user(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $node = $this->node($alice, public: true);
        $topic = $this->topic($alice, $node, public: true);

        $this->actingAs($bob)->post(route('topics.favorite', $topic))->assertRedirect();
        $this->assertTrue($bob->favoriteTopics()->whereKey($topic->id)->exists());
        $this->assertFalse($alice->favoriteTopics()->whereKey($topic->id)->exists());

        $this->actingAs($bob)->post(route('topics.favorite', $topic));
        $this->assertFalse($bob->favoriteTopics()->whereKey($topic->id)->exists());
    }

    public function test_attachments_are_stored_served_to_authorized_users_and_removed(): void
    {
        Storage::fake('local');
        $alice = $this->user();
        $node = $this->node($alice, public: true);
        $topic = $this->topic($alice, $node, public: false);

        $this->actingAs($alice)
            ->post(route('topics.attachments.store', $topic), ['file' => UploadedFile::fake()->create('doc.pdf', 20, 'application/pdf')])
            ->assertRedirect();

        $media = $topic->fresh()->getFirstMedia(Topic::ATTACHMENTS);
        $this->assertNotNull($media);

        $this->actingAs($alice)->get(route('media.show', $media))->assertOk();

        auth()->logout();
        $this->get(route('media.show', $media))->assertNotFound();
        $this->actingAs($this->user())->get(route('media.show', $media))->assertNotFound();

        $this->actingAs($alice)->delete(route('media.destroy', $media))->assertRedirect();
        $this->assertCount(0, $topic->fresh()->getMedia(Topic::ATTACHMENTS));
    }

    public function test_images_return_an_embeddable_url_and_public_ones_are_visible_to_guests(): void
    {
        Storage::fake('local');
        $alice = $this->user();
        $node = $this->node($alice, public: true);
        $topic = $this->topic($alice, $node, public: true);

        $url = $this->actingAs($alice)
            ->postJson(route('topics.images.store', $topic), ['image' => UploadedFile::fake()->image('p.png')])
            ->assertOk()->json('url');

        auth()->logout();
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
    }

    public function test_non_images_are_rejected_by_the_image_endpoint(): void
    {
        Storage::fake('local');
        $alice = $this->user();
        $topic = $this->topic($alice, $this->node($alice, public: true));

        $this->actingAs($alice)
            ->postJson(route('topics.images.store', $topic), ['image' => UploadedFile::fake()->create('x.svg', 1, 'image/svg+xml')])
            ->assertUnprocessable();
    }

    public function test_public_page_is_visible_to_guests_only_for_public_topics(): void
    {
        $alice = $this->user();
        $node = $this->node($alice, public: true);
        $public = $this->topic($alice, $node, public: true, title: 'Public one', content: '<p>Body</p>');
        $private = $this->topic($alice, $node, public: false);

        $this->get(route('topics.show', $public))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('topics/Show')->where('topic.title', 'Public one'));

        $this->get(route('topics.show', $private))->assertNotFound();
        $this->actingAs($alice)->get(route('topics.show', $private))->assertOk();
    }
}
