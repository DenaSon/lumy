<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel')->name('home');

Route::livewire('/panel', 'pages::panel.index')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';
