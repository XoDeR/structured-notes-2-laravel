<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\Topic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaController extends Controller
{
    /**
     * Streams a topic file, honouring the topic's visibility (public, or owner/manager only).
     */
    public function show(Request $request, Media $media): StreamedResponse
    {
        $topic = $media->model;
        abort_unless($topic instanceof Topic, 404);
        abort_unless(Gate::forUser($request->user())->allows('view', $topic), 404);

        $isImage = $media->collection_name === Topic::IMAGES;

        return Storage::disk($media->disk)->response(
            $media->getPathRelativeToRoot(),
            $media->file_name,
            [
                'Content-Type' => $media->mime_type ?? 'application/octet-stream',
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            ],
            $isImage ? 'inline' : 'attachment',
        );
    }
}
