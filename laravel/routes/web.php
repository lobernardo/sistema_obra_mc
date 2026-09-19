<?php

use App\Livewire\Auth\LoginForm;
use App\Livewire\Obra\Acompanhamento;
use App\Livewire\Obra\NovaSolicitacao;
use App\Livewire\Obra\PedidoDetalhe;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', LoginForm::class)->name('login');
});

Route::middleware('auth')->group(function () {
    Route::get('/home', function () {
        return 'OK';
    })->name('home');

    Route::post('/logout', function () {
        Auth::guard('web')->logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('login');
    })->name('logout');

    Route::middleware('can:is-obra')->prefix('obra')->name('obra.')->group(function () {
        Route::get('/nova-solicitacao', NovaSolicitacao::class)->name('nova-solicitacao');
        Route::get('/pedidos', Acompanhamento::class)->name('pedidos.index');
        Route::get('/pedidos/{pedido}', PedidoDetalhe::class)->name('pedidos.show');
    });
});
