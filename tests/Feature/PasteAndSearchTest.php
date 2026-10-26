<?php

namespace Tests\Feature;

use App\Models\Topic;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PasteAndSearchTest extends NotesTestCase
{
    public function test_move_relocates_the_topic_and_keeps_link_flag(): void
    {
        $alice = $this->user();
        $from = $this->node($alice, public: true, title: 'From');
        $to = $this->node($alice, public: true, title: 'To');
        $topic = $this->topic($alice, $from, public: true);

        $this->actingAs($alice)->post(route('topics.paste', $topic), [
            'mode' => 'move', 'node_id' => $to->id, 'from_node_id' => $from->id,
        ])->assertRedirect(route('notes.index', ['node' => $to->id, 'topic' => $topic->id]));

        $this->assertSame([$to->id], $topic->fresh()->nodes->pluck('id')->all());
    }

    public function test_link_adds_a_second_placement(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $from = $this->node($alice, public: true);
        $to = $this->node($bob, public: true);
        $topic = $this->topic($alice, $from, public: true);

        $this->actingAs($bob)->post(route('topics.paste', $topic), ['mode' => 'link', 'node_id' => $to->id])->assertRedirect();

        $placement = $topic->fresh()->nodes()->where('nodes.id', $to->id)->first();
        $this->assertTrue((bool) $placement->pivot->is_link);
        $this->assertCount(2, $topic->fresh()->nodes);
    }

    public function test_only_the_owner_can_move_a_topic(): void
    {
        $alice = $this->user();
        $from = $this->node($alice, public: true);
        $to = $this->node($alice, public: true);
        $topic = $this->topic($alice, $from, public: true);

        $this->actingAs($this->user())->post(route('topics.paste', $topic), [
            'mode' => 'move', 'node_id' => $to->id, 'from_node_id' => $from->id,
        ])->assertForbidden();
    }

    public function test_public_topic_cannot_be_pasted_into_a_private_node(): void
    {
        $alice = $this->user();
        $public = $this->node($alice, public: true);
        $private = $this->node($alice);
        $topic = $this->topic($alice, $public, public: true);

        $this->actingAs($alice)->post(route('topics.paste', $topic), ['mode' => 'link', 'node_id' => $private->id])
            ->assertSessionHasErrors('is_public');
    }

    public function test_copy_duplicates_content_tags_and_files_for_the_pasting_user(): void
    {
        Storage::fake('local');
        $alice = $this->user();
        $bob = $this->user();
        $from = $this->node($alice, public: true);
        $to = $this->node($bob, public: true);
        $topic = $this->topic($alice, $from, public: true, title: 'Original');
        $topic->syncTags(['one']);
        $image = $topic->addMedia(UploadedFile::fake()->image('a.png'))->toMediaCollection(Topic::IMAGES);
        $topic->update(['content' => '<img src="/media/'.$image->id.'">']);

        $this->actingAs($bob)->post(route('topics.paste', $topic), [
            'mode' => 'copy', 'node_id' => $to->id, 'from_node_id' => $from->id,
        ])->assertRedirect();

        $copy = Topic::where('user_id', $bob->id)->firstOrFail();
        $newImage = $copy->getFirstMedia(Topic::IMAGES);

        $this->assertSame('Original', $copy->title);
        $this->assertSame(['one'], $copy->tags()->pluck('value')->all());
        $this->assertNotNull($newImage);
        $this->assertStringContainsString('/media/'.$newImage->id, $copy->content);
        $this->assertStringNotContainsString($image->id, $copy->content);
    }

    public function test_copy_into_the_same_node_gets_a_suffix(): void
    {
        $alice = $this->user();
        $node = $this->node($alice, public: true);
        $topic = $this->topic($alice, $node, public: true, title: 'Doc');

        $this->actingAs($alice)->post(route('topics.paste', $topic), [
            'mode' => 'copy', 'node_id' => $node->id, 'from_node_id' => $node->id,
        ]);

        $this->assertDatabaseHas('topics', ['title' => 'Doc (copy)']);
    }

    public function test_search_by_text_respects_visibility_and_modes(): void
    {
        $alice = $this->user();
        $bob = $this->user();
        $node = $this->node($alice, public: true, title: 'Docs');
        $this->topic($alice, $node, public: true, title: 'Cooking pasta', content: '<p>boil water and salt</p>');
        $this->topic($alice, $node, public: false, title: 'Secret pasta plans');

        $search = fn ($params) => $this->actingAs($bob)->get(route('notes.index', $params));

        $search(['q' => 'pasta'])->assertInertia(fn ($page) => $page
            ->has('search.results', 1)
            ->where('search.results.0.title', 'Cooking pasta')
            ->where('search.results.0.path', 'Notes › Docs'));

        $search(['q' => 'water salt', 'mode' => 'all'])->assertInertia(fn ($page) => $page->has('search.results', 1));
        $search(['q' => 'water salt', 'mode' => 'phrase'])->assertInertia(fn ($page) => $page->has('search.results', 0));
        $search(['q' => 'water', 'title_only' => 1])->assertInertia(fn ($page) => $page->has('search.results', 0));
        $search(['q' => '100%'])->assertInertia(fn ($page) => $page->has('search.results', 0));
    }

    public function test_search_by_tag_and_favorites(): void
    {
        $alice = $this->user();
        $node = $this->node($alice, public: true);
        $tagged = $this->topic($alice, $node, public: true, title: 'Tagged');
        $tagged->syncTags(['recipe']);
        $faved = $this->topic($alice, $node, public: true, title: 'Faved');
        $alice->favoriteTopics()->attach($faved->id);

        $this->actingAs($alice)->get(route('notes.index', ['tag' => 'recipe']))
            ->assertInertia(fn ($page) => $page->has('search.results', 1)->where('search.results.0.title', 'Tagged'));

        $this->actingAs($alice)->get(route('notes.index', ['fav' => 1]))
            ->assertInertia(fn ($page) => $page->has('search.results', 1)->where('search.results.0.title', 'Faved'));
    }
}
