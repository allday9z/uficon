<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class BadgePreset extends Model
{
    protected $table      = 'badge_preset';
    protected $primaryKey = 'bp_id';

    protected $fillable = ['bp_text','bp_hex_color','bp_purpose','bp_sort_order','bp_is_active'];
    protected $casts    = ['bp_is_active' => 'boolean', 'bp_sort_order' => 'integer'];

    /** Auto-create preset when a new badge text is saved on a product */
    public static function syncFromProduct(string $badgeText): self
    {
        return static::firstOrCreate(
            ['bp_text' => $badgeText],
            ['bp_hex_color' => '#BF4800', 'bp_sort_order' => 99, 'bp_is_active' => true]
        );
    }
}
