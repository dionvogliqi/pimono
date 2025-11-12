<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// Register broadcasting auth routes secured by Sanctum
Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('private-user.{id}', function (User $user, int $id): bool {
    return $user->id === $id;
});
