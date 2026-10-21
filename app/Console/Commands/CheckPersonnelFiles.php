<?php

namespace App\Console\Commands;

use App\Models\PrivateFile;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CheckPersonnelFiles extends Command
{
    protected $signature = 'hris:check-files {--checksums : Verify SHA-256 checksums as well as file existence}';

    protected $description = 'Read-only private-file repair report; lists missing or mismatched file IDs without exposing document content';

    public function handle(): int
    {
        $problems = 0;
        foreach (PrivateFile::orderBy('id')->lazyById(100) as $file) {
            if (! Storage::disk('local')->exists($file->path)) {
                $this->warn('Missing file ID '.$file->id.' (owner '.$file->user_id.')');
                $problems++;
            } elseif ($this->option('checksums') && hash_file('sha256', Storage::disk('local')->path($file->path)) !== $file->checksum) {
                $this->warn('Checksum mismatch for file ID '.$file->id);
                $problems++;
            }
        }
        $this->info("{$problems} file repair issues found.");

        return $problems ? self::FAILURE : self::SUCCESS;
    }
}
