<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel')->name('home');

Route::livewire('/panel', 'pages::panel.index')
    ->name('dashboard');

Route::livewire('/panel/content', 'pages::panel.content.index')
    ->name('content.index');

Route::livewire('/panel/content/hooks', 'pages::panel.content.hooks')
    ->name('content.hooks');

Route::livewire('/panel/content/{content}/annotate', 'pages::panel.content.annotate')
    ->name('content.annotate');

Route::livewire('/panel/intelligence', 'pages::panel.intelligence.index')
    ->name('intelligence.index');

Route::livewire('/panel/intelligence/analysis', 'pages::panel.intelligence.analysis')
    ->name('intelligence.analysis');
