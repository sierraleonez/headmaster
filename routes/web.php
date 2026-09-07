<?php

use App\Http\Controllers\BoardColumnController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LogEntryController;
use App\Http\Controllers\ProjectController;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    // Everyone lands on their root board.
    Route::get('dashboard', [ProjectController::class, 'home'])->name('dashboard');

    Route::get('p/{project}', [ProjectController::class, 'show'])->name('projects.show');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');
    Route::patch('p/{project}', [ProjectController::class, 'update'])->name('projects.update');
    Route::delete('p/{project}', [ProjectController::class, 'destroy'])->name('projects.destroy');

    Route::post('p/{project}/columns', [BoardColumnController::class, 'store'])->name('columns.store');
    Route::post('p/{project}/columns/reorder', [BoardColumnController::class, 'reorder'])->name('columns.reorder');
    Route::patch('columns/{column}', [BoardColumnController::class, 'update'])->name('columns.update');
    Route::delete('columns/{column}', [BoardColumnController::class, 'destroy'])->name('columns.destroy');

    Route::post('columns/{column}/items', [ItemController::class, 'store'])->name('items.store');
    Route::patch('items/{item}', [ItemController::class, 'update'])->name('items.update');
    Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
    Route::post('items/{item}/move', [ItemController::class, 'move'])->name('items.move');

    Route::post('p/{project}/entries', [LogEntryController::class, 'store'])->name('entries.store');
    Route::patch('entries/{entry}', [LogEntryController::class, 'update'])->name('entries.update');
    Route::delete('entries/{entry}', [LogEntryController::class, 'destroy'])->name('entries.destroy');

    Route::get('chat', [ChatController::class, 'index'])->name('chat.index');
    Route::get('chat/{conversation}', [ChatController::class, 'show'])->name('chat.show');
    Route::post('chat', [ChatController::class, 'store'])->name('chat.store');
    Route::delete('chat/{conversation}', [ChatController::class, 'destroy'])->name('chat.destroy');
    Route::post('chat/messages/{message}/apply', [ChatController::class, 'apply'])->name('chat.apply');
});

require __DIR__.'/settings.php';
