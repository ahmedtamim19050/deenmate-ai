<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuranImportLog extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DRY_RUN = 'dry_run';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'error_details' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function source()
    {
        return $this->belongsTo(QuranDataSource::class, 'quran_data_source_id');
    }
}
