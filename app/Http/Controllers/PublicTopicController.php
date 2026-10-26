<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PublicTopicController extends Controller
{
    /**
     * Read-only page for a topic, reachable by guests when the topic is public.
     */
    public function show(Request $request, Topic $topic): Response
    {
        abort_unless(Gate::forUser($request->user())->allows('view', $topic), 404);

        $topic->load(['tags', 'owner:id,name']);

        return Inertia::render('topics/Show', [
            'topic' => [
                'id' => $topic->id,
                'title' => $topic->title,
                'content' => $topic->content,
                'owner' => $topic->owner->name,
                'updated_at' => $topic->updated_at,
                'tags' => $topic->tags->pluck('value')->sort()->values(),
                'attachments' => $topic->getMedia(Topic::ATTACHMENTS)->map(fn ($m) => [
                    'id' => $m->id,
                    'name' => $m->file_name,
                    'size' => $m->size,
                    'url' => route('media.show', $m),
                ])->values(),
            ],
        ]);
    }
}
