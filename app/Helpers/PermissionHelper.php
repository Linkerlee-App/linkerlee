<?php

namespace App\Helpers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class PermissionHelper
{
    public static function userIsOwnerOfModal(Model $model, ?int $userId = null): bool
    {
        $userId = $userId ?? Auth::id();

        return (int) $model->user_id === $userId;
    }
}
