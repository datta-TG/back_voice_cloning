<?php

namespace App\Http\Controllers;

set_time_limit(900);

use App\Models\File;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileController extends Controller
{
    public function preview(Request $request, $type)
    {
        $validator = Validator::make($request->all(), [
            'fileName' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        $user = $request->user();
        $filename = $request->fileName;
        $url = Storage::disk('s3')->temporaryUrl(strval($user->id) . '/' . $type . '/' . $filename, now()->addMinutes(5));
        return response()->json([
            'url' => $url
        ]);
    }

    public function generateVoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required',
            'fileName' => 'required',
            'newFileName' => 'string',
            'gain' => 'numeric|between:0,20'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        $text = $request->text;
        $fileName = $request->fileName;
        $newFileName = $request->newFileName . '.wav';
        $gain = $request->gain;
        $user = $request->user();
        if (Storage::disk('s3')->exists(strval($user->id) . '/' . 'clonedVoices/' . $newFileName)) {
            return response()->json([
                'message' => 'Error',
                'errors' => 'You cannot use this name for the cloned voice because it already exists'
            ], 400);
        }
        $url = Storage::disk('s3')->temporaryUrl(strval($user->id) . '/' . 'originalVoices/' . $fileName, now()->addMinutes(5));
        $path = strval($user->id) . '/' . 'clonedVoices/' . $newFileName;
        $postRoute = \Config::get('values.app_url') . '/api/upload/clonedVoices';
        $response = Http::timeout(900)->post(\Config::get('values.ai_url') . 'itemsVoice/', [
            'text' => $text,
            'url' => $url,
            'fileName' => $newFileName,
            'path' => $path,
            'postRoute' => $postRoute,
            'gain' => $gain
        ]);
        if ($response) {
            return response()->json([
                'url' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(5))
            ]);
        } else {
            return response()->json([
                'message' => 'Error',
                'errors' => 'Failed to create voice'
            ]);
        }
    }

    public function generateVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clonedVoiceName' => 'required',
            'originalVideoName' => 'required',
            'newFileName' => 'string'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        $clonedVoice = $request->clonedVoiceName;
        $originalVideoName = $request->originalVideoName;
        $newFileName = $request->newFileName . '.mp4';
        $user = $request->user();
        if (Storage::disk('s3')->exists(strval($user->id) . '/' . 'videosCloned/' . $newFileName)) {
            return response()->json([
                'message' => 'Error',
                'errors' => 'You cannot use this name for the cloned video because it already exists'
            ], 400);
        }
        $clonedVoice = Storage::disk('s3')->temporaryUrl(strval($user->id) . '/' . 'clonedVoices/' . $clonedVoice, now()->addMinutes(5));
        $originalVideo = Storage::disk('s3')->temporaryUrl(strval($user->id) . '/' . 'videosOriginal/' . $originalVideoName, now()->addMinutes(5), [
            'ResponseContentType' => 'application/octet-stream',
            'ResponseContentDisposition' => 'attachment; filename=' . $originalVideoName,
        ]);
        $path = strval($user->id) . '/' . 'videosCloned/' . $newFileName;
        $postRoute = \Config::get('values.app_url') . '/api/upload/videosCloned';
        $response = Http::timeout(900)->post(\Config::get('values.ai_url') . 'itemsVideo/', [
            'urlClonedVoice' => $clonedVoice,
            'urlOriginalVideo' => $originalVideo,
            'fileName' => $newFileName,
            'path' => $path,
            'postRoute' => $postRoute
        ]);
        if ($response) {
            return response()->json([
                'url' => Storage::disk('s3')->temporaryUrl($path, now()->addMinutes(5))
            ]);
        } else {
            return response()->json([
                'message' => 'Error',
                'errors' => 'Failed to create video'
            ]);
        }
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
            'file' => 'required'
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        $file = $request->file('file');
        if (Storage::disk('s3')->exists(strval($request->user()->id) . '/' . $type . '/' . basename($file->getClientOriginalName(), '.part'))) {
            return response()->json([
                'message' => 'Error',
                'errors' => 'You cannot use this name for the file because it already exists'
            ], 400);
        }
        if (!Storage::disk('local')->exists('chunks')) {
            Storage::disk('local')->makeDirectory('chunks');
        }
        $path = Storage::disk('local')->path("chunks/{$file->getClientOriginalName()}");
        \Illuminate\Support\Facades\File::append($path, $file->get());
        if ($request->has('is_last') && $request->boolean('is_last')) {
            $name = basename($path, '.part');
            if (!Storage::disk('local')->exists('saved')) {
                Storage::disk('local')->makeDirectory('saved');
            }
            $target = Storage::disk('local')->path('saved');
            \Illuminate\Support\Facades\File::move($path, "$target/$name");
            $s3Path = strval($request->user()->id) . '/' . $type . '/' . $name;
            if (Storage::disk('s3')->exists($s3Path)) {

                Storage::disk('s3')->delete($s3Path);

            }
            $upload = Storage::disk('s3')->writeStream($s3Path, Storage::disk('local')->readStream("saved/$name"));
            if ($upload === false) {
                throw new Exception("Couldn't upload file to S3");
            }
            File::create([
                'name' => $name,
                'route' => $s3Path,
                'type' => $type,
                'user_id' => $request->user()->id
            ]);


            $delete = Storage::disk('local')->delete("saved/$name");

            if ($delete === false) {
                throw new Exception("File could not be deleted from the local filesystem");
            }
        }
        return response()->json([
            'uploaded' => true
        ]);

        /*        $userFile = new File();
                $result = $userFile->upload($file, $type, $request->user()->id);
                $request->user()->files()->save($userFile);
                return response()->json([
                    'result' => $result
                ]);*/
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
