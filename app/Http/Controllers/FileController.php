<?php

namespace App\Http\Controllers;

set_time_limit(900);

use App\Models\File;
use http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use function GuzzleHttp\Psr7\str;
use function Symfony\Component\String\u;

class FileController extends Controller
{
    public function generateVoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required',
            'fileName' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        $text = $request->text;
        $fileName = $request->fileName;
        $user = $request->user();
        $url = Storage::disk('s3')->temporaryUrl(strval($user->id) . '/' . 'originalVoices/' . $fileName, now()->addMinutes(5));
        $path = strval($user->id) . '/' . 'clonedVoices/' . $fileName;
        $postRoute = \Config::get('values.app_url') . '/api/upload/clonedVoices';
        $response = Http::timeout(900)->post(\Config::get('values.ai_url') . 'itemsVoice', [
            'text' => $text,
            'url' => $url,
            'fileName' => $fileName,
            'path' => $path,
            'postRoute' => $postRoute
        ]);
        return response()->json([
            'url' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(5))
        ]);
    }

    public function generateVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clonedVoiceName' => 'required',
            'originalVideoName' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        $user = $request->user();
        $clonedVoice = Storage::disk('s3')->temporaryUrl(strval($user->id) . '/' . 'clonedVoices/' . $request->clonedVoiceName, now()->addMinutes(5));
        $originalVideo = Storage::disk('s3')->temporaryUrl(strval($user->id) . '/' . 'videosOriginal/' . $request->originalVideoName, now()->addMinutes(5), [
            'ResponseContentType' => 'application/octet-stream',
            'ResponseContentDisposition' => 'attachment; filename=' . $request->originalVideoName,
        ]);
        $path =strval($user->id) . '/' . 'videosCloned/' . $request->originalVideoName;
        $postRoute = \Config::get('values.app_url') . '/api/upload/videosCloned';
        $response = Http::timeout(900)->post(\Config::get('values.ai_url') . 'itemsVideo', [
            'urlClonedVoice' => $clonedVoice,
            'urlOriginalVideo' => $originalVideo,
            'fileName' => $request->originalVideoName,
            'path' => $path,
            'postRoute' => $postRoute
        ]);
        return response()->json([
            'url' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(5))
        ]);
    }

    public function list(Request $request, $type)
    {
        $user = $request->user();
        /*        $filteredFiles = File::where('user_id', $user->id)->where('type', $type)->select('name')->get();
                $array = array();
                foreach ($filteredFiles as $file) {
                    array_push($array, $file->name);
                }*/
        return response()->json([
            'result' => File::list(strval($user->id) . '/' . $type)
        ]);
    }

    public function upload(Request $request, $type)
    {
        $validator = Validator::make($request->all(), [
            'file' => 'required|mimes:wav,mp4,jpg'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        $file = $request->file('file');
        $userFile = new File();
        $result = $userFile->upload($file, $type, $request->user()->id);
        $request->user()->files()->save($userFile);
        return response()->json([
            'result' => $result
        ]);
    }

    public function getFiles(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'files' => $user->files
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $name = $request->name;
        $file = new File();
        $file->name = $name;
        $file->route = '';
        $user = $user->files()->save($file);
        return response()->json([
            'message' => 'Successfully created',
            'file' => $file
        ]);
    }

    public function show(Request $request, $id)
    {
        $file = File::findOrFail($id);
        return response()->json([
            'file' => $file
        ]);
    }

    public function update(Request $request, $id)
    {
        $file = File::findOrFail($id);
        $newName = $request->newName;
        $file->name = $newName;
        $file->save();
        return response()->json([
            'message' => 'Successfully name updated',
            'writing' => $file
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $file = File::findOrFail($id);
        $file->delete();
        return response()->json([
            'status' => 'success',
            'message' => 'fileDeleted',
        ]);
    }
}
