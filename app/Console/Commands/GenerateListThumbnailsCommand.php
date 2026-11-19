<?php

namespace App\Console\Commands;

use App\Models\listImage;
use App\Services\ListImageThumbnail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/** One-off / repeatable: make thumbnails for marketplace photos uploaded before thumbnails existed. */
class GenerateListThumbnailsCommand extends Command
{
    protected $signature = 'list-images:thumbnails {--force : Re-create thumbnails that already exist}';

    protected $description = 'Create small list-screen thumbnails for marketplace photos (originals are not changed)';

    public function handle(): int
    {
        $made = $skipped = $failed = 0;

        listImage::query()->orderBy('id')->chunkById(100, function ($images) use (&$made, &$skipped, &$failed) {
            foreach ($images as $image) {
                $thumb = ListImageThumbnail::pathFor($image->path);

                if (! $this->option('force') && $thumb && Storage::disk('public')->exists($thumb)) {
                    $skipped++;
                    continue;
                }

                ListImageThumbnail::make($image->path) ? $made++ : $failed++;
            }
        });

        $this->info("Thumbnails created: {$made}, already there: {$skipped}, could not read: {$failed}");

        return self::SUCCESS;
    }
}
