<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One complete, checksummed version of an edition's content. */
class QuranTranslationRevision extends Model
{
    public const KIND_BASELINE = 'baseline';

    public const KIND_IMPORT = 'import';

    public const KIND_ROLLBACK = 'rollback';

    protected $guarded = ['id'];

    public function edition()
    {
        return $this->belongsTo(QuranTranslationEdition::class, 'quran_translation_edition_id');
    }

    public function importLog()
    {
        return $this->belongsTo(QuranImportLog::class, 'quran_import_log_id');
    }
}
