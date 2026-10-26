<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Topic;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttachmentController extends Controller
{
    public function store(Request $request, Topic $topic): RedirectResponse
    {
        $this->authorize('update', $topic);

        $request->validate(['file' => ['required', 'file', 'max:'.$this->maxKilobytes()]]);

        $topic->addMediaFromRequest('file')->toMediaCollection(Topic::ATTACHMENTS);

        return back();
    }

    /**
     * Image upload used by the rich text editor; responds with the url to embed.
     */
    public function image(Request $request, Topic $topic): JsonResponse
    {
        $this->authorize('update', $topic);

        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.$this->maxKilobytes()],
        ]);

        $media = $topic->addMediaFromRequest('image')->toMediaCollection(Topic::IMAGES);

        return response()->json(['url' => route('media.show', $media, absolute: false)]);
    }

    public function destroy(Media $media): RedirectResponse
    {
        $topic = $media->model;
        abort_unless($topic instanceof Topic, 404);

        $this->authorize('update', $topic);

        $media->delete();

        return back();
    }

    private function maxKilobytes(): int
    {
        return intdiv((int) config('media-library.max_file_size'), 1024);
    }
}
