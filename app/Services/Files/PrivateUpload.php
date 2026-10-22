<?php

namespace App\Services\Files;

use App\Models\PrivateFile;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class PrivateUpload
{
    public function store(UploadedFile $upload, User $owner, User $actor, string $purpose): PrivateFile
    {
        $path = $upload->store('hris/'.$owner->id.'/'.$purpose, 'local');
        abort_unless(is_string($path), 503, 'File storage is unavailable. Please try again.');
        try {
            $file = PrivateFile::create(['user_id' => $owner->id, 'actor_id' => $actor->id, 'purpose' => $purpose,
                'original_name' => mb_substr(basename($upload->getClientOriginalName()), 0, 255), 'path' => $path,
                'mime' => $upload->getMimeType(), 'size' => $upload->getSize(), 'checksum' => hash_file('sha256', $upload->getRealPath())]);
            Audit::record('file.uploaded', $file, $actor->id);

            return $file;
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }
    }
}
