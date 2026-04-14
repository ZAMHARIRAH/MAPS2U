<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportArchive extends Model
{
    use HasFactory;

    public const TYPE_BRANCHES = 'branches';
    public const TYPE_LOCATIONS = 'locations';

    protected $fillable = [
        'report_type',
        'archive_year',
        'payload',
        'archived_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'archived_at' => 'datetime',
    ];
}
