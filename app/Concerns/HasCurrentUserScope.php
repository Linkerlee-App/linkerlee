<?php

namespace App\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

trait HasCurrentUserScope
{
    public function scopeFilterByCurrentUser(Builder $query): Builder
    {
        return $query->where($this->getTable().'.user_id', Auth::id());
    }
}
