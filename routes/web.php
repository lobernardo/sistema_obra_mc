<?php

use App\Enums\RoleSlug;
use App\Livewire\Auth\LoginForm;
use App\Livewire\Gestao\Dashboard as GestaoDashboard;
use App\Livewire\Gestao\KanbanReadOnly;
use App\Livewire\Gestao\PedidoDetalhe as GestaoPedidoDetalhe;
use App\Livewire\Gestao\TodosPedidos as GestaoTodosPedidos;
use App\Livewire\Kanban\KanbanBoard;
use App\Livewire\Obra\Acompanhamento;
use App\Livewire\Obra\NovaSolicitacao;
use App\Livewire\Obra\PedidoDetalhe;
use App\Livewire\Suprimentos\PedidoDetalhe as SuprimentosPedidoDetalhe;
use App\Livewire\Suprimentos\TodosPedidos;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/home');

Route::middleware('guest')->group(function () {
    Route::get('/login', LoginForm::class)->name('login');
});

Route::middleware(['auth', 'active'])->group(function () {
    /*
     * Role-scoped landing page: each papel is sent straight to its main
     * screen (the AS IS `getRoleHomePath` behaviour). A user without a
     * recognised papel has no screen to land on and is denied.
     */
    Route::get('/home', function () {
        return match (Auth::user()->role?->slug) {
            RoleSlug::Obra->value => redirect()->route('obra.pedidos.index'),
            RoleSlug::Suprimentos->value => redirect()->route('suprimentos.kanban'),
            RoleSlug::Gestao->value => redirect()->route('gestao.dashboard'),
            default => abort(403, 'Perfil de acesso não reconhecido.'),
        };
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

    Route::middleware('can:is-suprimentos')->prefix('suprimentos')->name('suprimentos.')->group(function () {
        Route::get('/pedidos', TodosPedidos::class)->name('pedidos.index');
        Route::get('/pedidos/{pedido}', SuprimentosPedidoDetalhe::class)->name('pedidos.show');
        Route::get('/kanban', KanbanBoard::class)->name('kanban');
    });

    Route::middleware('can:is-gestao')->prefix('gestao')->name('gestao.')->group(function () {
        Route::get('/dashboard', GestaoDashboard::class)->name('dashboard');
        Route::get('/pedidos', GestaoTodosPedidos::class)->name('pedidos.index');
        Route::get('/pedidos/{pedido}', GestaoPedidoDetalhe::class)->name('pedidos.show');
        Route::get('/kanban', KanbanReadOnly::class)->name('kanban');
    });
});
