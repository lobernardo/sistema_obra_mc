<?php

use App\Livewire\Examples\HelloWorld;
use Livewire\Livewire;

test('a trivial livewire component mounts and renders', function () {
    Livewire::test(HelloWorld::class)
        ->assertSet('message', 'Hello, Livewire!')
        ->assertSee('Hello, Livewire!')
        ->assertStatus(200);
});
