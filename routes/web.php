<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/panel')->name('home');

Route::livewire('/panel', 'pages::panel.index')
    ->name('dashboard');
