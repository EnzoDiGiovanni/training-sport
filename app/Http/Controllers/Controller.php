<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

abstract class Controller
{
    protected function user(): User
    {
        /** @var User */
        return Auth::user();
    }

    /** 404 si la ressource n'appartient pas à l'utilisateur connecté. */
    protected function own(?int $ownerId): void
    {
        abort_unless($ownerId === $this->user()->id, 404);
    }
}
