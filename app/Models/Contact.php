<?php

namespace App\Models;

use App\Traits\HasUUID;
use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $user_id
 * @property string $source_type
 * @property string $source_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @use HasFactory<ContactFactory>
 */
class Contact extends Model
{
    use HasFactory;
    use HasUUID;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'user_id',
        'source_type',
        'source_id',
    ];

    /**
     * Scope a query to only include active users.
     *
     * @param  Builder  $query
     * @param  string  $user_id
     * @return Builder
     */
    public function scopeForUser($query, $user_id)
    {
        return $query->where('user_id', $user_id);
    }

    /**
     * Get the user that owns the contact.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the owner model of the contact.
     */
    public function source(): MorphTo
    {
        return $this->morphTo('source', 'source_type', 'source_id', 'id');
    }
}
