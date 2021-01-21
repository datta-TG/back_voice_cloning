<?php

namespace App\Http\Controllers;

use App\Models\File;
use http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

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
        $url = Storage::disk('s3')->temporaryUrl('originalVoices/' . $fileName, now()->addMinutes(5));
        $path = 'clonedVoices/' . $fileName;
        $postRoute = \Config::get('values.app_url') . '/api/upload/clonedVoices';
        $response = Http::post(\Config::get('values.ai_url') . '/definir_ruta', [
            'text' => $text,
            'url' => $url,
            'fileName' => $fileName,
            'path' => $path,
            'postRoute' => $postRoute
        ]);
/*        $string = strval($response);
        json_decode($string);*/
        return response()->json([
            'url' => Storage::disk('s3')->temporaryUrl('clonedVoices/' . $fileName, now()->addMinutes(5))
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
        $clonedVoice = Storage::disk('s3')->temporaryUrl('clonedVoices/' . $request->clonedVoiceName, now()->addMinutes(5));
        $originalVideo = Storage::disk('s3')->temporaryUrl('videosOriginal/' . $request->originalVideoName, now()->addMinutes(5));
        $path = 'videosCloned/' . $request->originalVideoName;
        $postRoute = \Config::get('values.app_url') . '/api/upload/videosCloned';
        $response = Http::post(\Config::get('values.ai_url') . '/definir_ruta', [ //falta definirlo
            'urlClonedVoice' => $clonedVoice,
            'urlOriginalVideo' => $originalVideo,
            'fileName' => $request->originalVideoName,
            'path' => $path,
            'postRoute' => $postRoute
        ]);
/*        $string = strval($response);
        return json_decode($string);*/
        return response()->json([
            'url' => Storage::disk('s3')->temporaryUrl('videosCloned/' . $request->originalVideoName, now()->addMinutes(5))
        ]);
    }
    public function list(Request $request, $type)
    {
        return response()->json([
            'result' => File::list($type)
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
        return response()->json([
            'result' => File::upload($file, $type)
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
