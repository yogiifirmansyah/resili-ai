<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feedback extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'customer_name',
        'feedback_text',
        'sentiment',
        'category',
        'status_ai',
    ];

    protected $attributes = [
        'status_ai' => 'pending',
    ];
}
