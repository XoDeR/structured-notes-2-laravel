<?php

namespace App\Http\Controllers;

use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class FavoriteController extends Controller
{
    public function toggle(Request $request, Topic $topic): RedirectResponse
    {
        $this->authorize('view', $topic);

        $request->user()->favoriteTopics()->toggle($topic->id);

        return back();
    }
}
