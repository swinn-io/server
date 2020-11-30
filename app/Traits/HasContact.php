<?php

namespace App\Traits;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasContact
{
    /**
     * Register a deleted model event with the dispatcher.
     *
     * @param \Closure|string $callback
     *
     * @return void
     */
    abstract public static function deleted($callback);

    /**
     * Define a polymorphic one-to-many relationship.
     *
     * @param string $related
     * @param string $name
     * @param string $type
     * @param string $id
     * @param string $localKey
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    abstract public function morphMany($related, $name, $type = null, $id = null, $localKey = null);

    /**
     * Boot the HasContacts trait for the model.
     *
     * @return void
     */
    public static function bootHasContacts()
    {
        static::deleted(function (self $model) {
            $model->contacts()->delete();
        });
    }

    /**
     * Get all attached contacts to the model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'source');
    }
}
