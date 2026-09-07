<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel')->name('home');

Route::livewire('/panel', 'pages::panel.index')
    ->name('dashboard');

Route::livewire('/panel/content', 'pages::panel.content.index')
    ->name('content.index');
