<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestQuestionOption extends Model
{
    use HasFactory;

    protected $fillable = ['request_question_id', 'option_text', 'sort_order', 'allows_other_text'];

    protected function casts(): array
    {
        return ['allows_other_text' => 'boolean'];
    }

    public function question()
    {
        return $this->belongsTo(RequestQuestion::class, 'request_question_id');
    }
}
