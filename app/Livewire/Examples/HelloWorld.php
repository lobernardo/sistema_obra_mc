<?php

namespace App\Livewire\Examples;

use Livewire\Component;

class HelloWorld extends Component
{
    public string $message = 'Hello, Livewire!';

    public function render()
    {
        return view('livewire.examples.hello-world');
    }
}
