<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];
    private static $config = array(
        'disk' => 's3',
        'maxUploadFileSize' => null,
        'allowFileTypes' => ['mp4', 'wav', 'jpg'],
        'directories' => ['originalVoices', 'clonedVoices', 'videosOriginal', 'videosCloned']
    );

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function upload($file, $type)
    {
        $disk = self::$config['disk'];
        if (in_array($file->getClientOriginalExtension(), self::$config['allowFileTypes'])) {
            if (in_array($type, self::$config['directories'])) {
                Storage::disk($disk)->putFileAs($type . '/', $file, $file->getClientOriginalName());
                return $type . '/' . $file->getClientOriginalName();
            } else {
                return
                    [
                        'status' => 'error',
                        'message' => 'folder dont exist',
                    ];
            }
        } else {
            return
                [
                    'status' => 'error',
                    'message' => 'not allowed file type',
                ];
        }

    }

    public static function list($type): array
    {
        $disk = self::$config['disk'];
        if (in_array($type, self::$config['directories'])) {
            return array_map(function ($path) {
                return basename($path);
            }, Storage::disk($disk)->files($type));
        } else {
            return
                [
                    'status' => 'error',
                    'message' => 'folder dont exist',
                ];
        }

    }

}
