<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RequestQuestion extends Model
{
    use HasFactory;

    public const TYPE_REMARK = 'remark';
    public const TYPE_RADIO = 'radio';
    public const TYPE_DATE_RANGE = 'date_range';
    public const TYPE_CHECKBOX = 'checkbox';
    public const TYPE_TASK_TITLE = 'task_title';

    protected $fillable = [
        'request_type_id', 'question_text', 'question_type', 'sort_order',
        'is_required', 'start_label', 'end_label',
    ];

    protected function casts(): array
    {
        return ['is_required' => 'boolean'];
    }

    public function requestType()
    {
        return $this->belongsTo(RequestType::class);
    }

    public function options()
    {
        return $this->hasMany(RequestQuestionOption::class)->orderBy('sort_order');
    }

    public function typeLabel(): string
    {
        return match ($this->question_type) {
            self::TYPE_REMARK => 'Remark',
            self::TYPE_RADIO => 'Radio Button',
            self::TYPE_DATE_RANGE => 'Date Range',
            self::TYPE_CHECKBOX => 'Checkbox',
            self::TYPE_TASK_TITLE => 'Task Title',
            default => ucfirst($this->question_type),
        };
    }
}
