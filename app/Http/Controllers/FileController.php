<?php

namespace App\Http\Controllers;

set_time_limit(900);

use App\Jobs\ApiRequest;
use App\Models\File;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class FileController extends Controller
{
    public function delete(Request $request, $type, $fileName)
    {
        $user = $request->user();
        $path = strval($user->id) . '/' . $type . '/' . $fileName;
        $file = File::where('route', $path)->first();
        if ($file) {
            $file->delete();
        }
        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return response()->json([
                'status' => 'Success',
                'message' => 'Deleted.'
            ]);
        } else {
            return response()->json([
                'status' => 'Error',
                'message' => 'File not exist.'
            ]);
        }
    }

    public function verifyFile(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'path' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error',
                'errors' => $validator->errors()->toArray()
            ], 400);
        }
        $path = $request->path;
        $fileExist = Storage::disk('public')->exists($path);
        return response()->json([
            'fileStatus' => $fileExist
        ]);
    }

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
        $url = asset('storage/' . strval($user->id) . '/' . $type . '/' . $filename);
        return response()->json([
            'url' => $url
        ]);
    }

    public function generateVoice(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'text' => 'required',
            'fileName' => 'required',
            'newFileName' => 'string|required',
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
        if (Storage::disk('public')->exists(strval($user->id) . '/' . 'clonedVoices/' . $newFileName)) {
            return response()->json([
                'message' => 'Error',
                'errors' => 'You cannot use this name for the cloned voice because it already exists.'
            ], 400);
        }

        $url = asset('storage/' . strval($user->id) . '/' . 'originalVoices/' . $fileName);// Storage::disk('local')->url(strval($user->id) . '/' . 'originalVoices/' . $fileName);
        $path = Storage::disk('public')->path('') . strval($user->id) . '/' . 'clonedVoices/' . $newFileName; // strval($user->id) . '/' . 'clonedVoices/' . $newFileName;
        $postRoute = \Config::get('values.app_url') . '/api/upload/clonedVoices';
        $job = new ApiRequest('itemsVoice', [
            'text' => $text,
            'url' => $url,
            'fileName' => $newFileName,
            'path' => $path,
            'postRoute' => $postRoute,
            'gain' => $gain
        ]);
        dispatch($job);
        return response()->json([
            'status' => 'Success',
            'message' => 'Added to queue.',
            'path' => $path
        ]);
        /*        $response = Http::timeout(900)->post(\Config::get('values.ai_url') . 'itemsVoice/', [
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
                }*/
    }

    public function generateVideo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'clonedVoiceName' => 'required',
            'originalVideoName' => 'required',
            'newFileName' => 'string|required'
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
        if (Storage::disk('public')->exists(strval($user->id) . '/' . 'videosCloned/' . $newFileName)) {
            return response()->json([
                'message' => 'Error',
                'errors' => 'You cannot use this name for the cloned video because it already exists'
            ], 400);
        }
        $clonedVoice = asset('storage/' . strval($user->id) . '/' . 'clonedVoices/' . $clonedVoice);
        $originalVideo = asset('storage/' . strval($user->id) . '/' . 'videosOriginal/' . $originalVideoName);
        $path = Storage::disk('public')->path('') . strval($user->id) . '/' . 'videosCloned/' . $newFileName;
        $postRoute = \Config::get('values.app_url') . '/api/upload/videosCloned';
        $job = new ApiRequest('itemsVideo', [
            'urlClonedVoice' => $clonedVoice,
            'urlOriginalVideo' => $originalVideo,
            'fileName' => $newFileName,
            'path' => $path,
            'postRoute' => $postRoute
        ]);
        dispatch($job);
        return response()->json([
            'status' => 'Success',
            'message' => 'Added to queue.',
            'path' => $path
        ]);
        /*        $response = Http::timeout(900)->post(\Config::get('values.ai_url') . 'itemsVideo/', [
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
                }*/
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
        if (Storage::disk('local')->exists(strval($request->user()->id) . '/' . $type . '/' . basename($file->getClientOriginalName(), '.part'))) {
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
            if (Storage::disk('local')->exists($s3Path)) {

                Storage::disk('local')->delete($s3Path);

            }
            $upload = Storage::disk('public')->writeStream($s3Path, Storage::disk('local')->readStream("saved/$name"));
            if ($upload === false) {
                throw new Exception("Couldn't upload file");
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
