<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\NotesController;
use App\Http\Controllers\PublicTopicController;
use App\Http\Controllers\TopicController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/notes')->name('home');

// Readable by guests when the topic is public; visibility is enforced in the controllers.
Route::get('t/{topic}', [PublicTopicController::class, 'show'])->name('topics.show');
Route::get('media/{media}', [MediaController::class, 'show'])->name('media.show');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('notes', [NotesController::class, 'index'])->name('notes.index');

    Route::post('nodes', [NodeController::class, 'store'])->name('nodes.store');
    Route::patch('nodes/{node}', [NodeController::class, 'update'])->name('nodes.update');
    Route::patch('nodes/{node}/move', [NodeController::class, 'move'])->name('nodes.move');
    Route::delete('nodes/{node}', [NodeController::class, 'destroy'])->name('nodes.destroy');

    Route::post('topics', [TopicController::class, 'store'])->name('topics.store');
    Route::patch('topics/{topic}', [TopicController::class, 'update'])->name('topics.update');
    Route::delete('topics/{topic}', [TopicController::class, 'destroy'])->name('topics.destroy');
    Route::post('topics/{topic}/paste', [TopicController::class, 'paste'])->name('topics.paste');
    Route::post('topics/{topic}/favorite', [FavoriteController::class, 'toggle'])->name('topics.favorite');

    Route::post('topics/{topic}/attachments', [AttachmentController::class, 'store'])->name('topics.attachments.store');
    Route::post('topics/{topic}/images', [AttachmentController::class, 'image'])->name('topics.images.store');
    Route::delete('media/{media}', [AttachmentController::class, 'destroy'])->name('media.destroy');

    Route::middleware('can:manage users')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});

require __DIR__.'/settings.php';
