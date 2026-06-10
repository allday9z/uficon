<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LobConfig extends Model
{
    protected $table      = 'lob_config';
    protected $primaryKey = 'lc_id';

    protected $fillable = [
        'lc_lob',
        'lc_header_image_desktop',
        'lc_header_image_mobile',
        'lc_banner_action',
    ];
}
