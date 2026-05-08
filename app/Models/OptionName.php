<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OptionName extends Model
{
    protected $table = 'option_names';

    protected $fillable = ['name', 'sort_order'];
}
