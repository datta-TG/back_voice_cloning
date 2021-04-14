<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class File extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'route',
        'type',
        'user_id'
    ];
    private static $config = array(
        'disk' => 'public',
        'maxUploadFileSize' => null,
        'allowFileTypes' => ['mp4', 'wav', 'jpg'],
        'directories' => ['originalVoices', 'clonedVoices', 'videosOriginal', 'videosCloned']
    );

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function upload($file, $type, $userId)
    {
        $disk = self::$config['disk'];
        if (in_array($file->getClientOriginalExtension(), self::$config['allowFileTypes'])) {
            if (in_array($type, self::$config['directories'])) {
                $path = strval($userId) . '/' . $type . '/';
                Storage::disk($disk)->putFileAs($path, $file, $file->getClientOriginalName());
                $fullPath = $path . $file->getClientOriginalName();
                $this->name = $file->getClientOriginalName();
                $this->route = $fullPath;
                $this->type = $type;
                return $fullPath;
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
        $directory = explode('/', $type)[1];
        if (in_array($directory, self::$config['directories'])) {
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
