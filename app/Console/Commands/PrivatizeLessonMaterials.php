<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PrivatizeLessonMaterials extends Command
{
    protected $signature = 'materials:privatize';

    protected $description = 'Move legacy lesson files to private storage after verifying their contents';

    public function handle(): int
    {
        $public = Storage::disk('public');
        $private = Storage::disk('local');
        $moved = 0;
        foreach ($public->allFiles('lessons') as $path) {
            $contents = $public->get($path);
            if ($private->exists($path)) {
                if ($private->get($path) !== $contents) {
                    $this->error("File conflict: {$path}. Original preserved.");

                    return self::FAILURE;
                }
            } elseif (! $private->put($path, $contents) || $private->get($path) !== $contents) {
                $this->error("Copy verification failed: {$path}. Original preserved.");

                return self::FAILURE;
            }
            if (! $public->delete($path)) {
                $this->error("Private copy verified, but public copy could not be removed: {$path}.");

                return self::FAILURE;
            }
            $moved++;
        }
        $this->info("Moved {$moved} lesson materials to private storage.");

        return self::SUCCESS;
    }
}
