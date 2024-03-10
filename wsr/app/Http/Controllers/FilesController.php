<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FilesController extends Controller
{
    public function uploadFiles(Request $request): JsonResponse
    {
        $user = auth()->user(); // Получаем текущего пользователя

        if ($request->hasFile('files')) {
            $files = $request->file('files');
            $messages = [];

            foreach ($files as $key => $file) {
                try {
                    $originalFileName = $file->getClientOriginalName();
                    $fileName = pathinfo($originalFileName, PATHINFO_FILENAME);
                    $extension = $file->getClientOriginalExtension();
                    $fileId = Str::random(10);

                    $i = 0;
                    while (file_exists(base_path('uploads/' . $fileName . ($i > 0 ? ' (' . $i . ')' : '') . '.' . $extension))) {
                        $i++;
                    }

                    $fileName = $fileName . ($i > 0 ? ' (' . $i . ')' : '') . '.' . $extension;

                    $file->move(base_path('uploads'), $fileName);

                    $fileModel = File::create([
                        'file_id' => $fileId,
                        'name' => $fileName,
                    ]);

                    $user->files()->attach($fileModel->id, ['is_admin' => true]);
                    $messages[] = [
                        'success' => true,
                        'message' => 'Success',
                        'name' => $fileName,
                        'url' => request()->getHost() . '/files/' . $fileId,
                        'file_id' => $fileId
                    ];
                } catch (\Throwable $exception) {
                    $messages[] = [
                        'success' => false,
                        'message' => 'File not loaded',
                    ];
                    continue;
                }
            }

            return response()->json($messages);
        }

        return response()->json(['success' => false, 'message' => 'File not loaded']);
    }

    public function updateFile(Request $request, string $fileId): JsonResponse
    {
        $fileModel = File::where('file_id', $fileId)->first();
        if (!$fileModel) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $fileName = $request->get('name');

        if (!$fileName) {
            return response()->json(['success' => false, 'message' => ['name' => ['Invalid name']]], 422);
        }

        $oldName = $fileModel->name;
        $extension = pathinfo($oldName, PATHINFO_EXTENSION);
        $fileName = "$fileName.$extension";
        $fileModel->name = $fileName ;
        $fileModel->save();
        rename(base_path('uploads') . '/' . $oldName, base_path('uploads') . '/' . $fileName);

        return response()->json(['success' => true, 'message' => 'Renamed']);
    }

    public function deleteFile(Request $request, string $fileId): JsonResponse
    {
        $fileModel = File::where('file_id', $fileId)->first();
        if (!$fileModel) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $fileName = $fileModel->name;
        $fileModel->delete();
        unlink(base_path('uploads') . '/' . $fileName);

        return response()->json(['success' => true, 'message' => 'File already deleted']);
    }

    public function downloadFile(Request $request, string $fileId): BinaryFileResponse|JsonResponse
    {
        $fileModel = File::where('file_id', $fileId)->first();
        if (!$fileModel) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $fileName = $fileModel->name;
        return response()->file(base_path('uploads') . '/' . $fileName);
    }

    public function setFileAccesses(Request $request, string $fileId): JsonResponse
    {
        $user = auth()->user();
        /** @var File|null $fileModel */
        $fileModel = File::where('file_id', $fileId)->first();

        if (!$fileModel) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($fileModel->getAdminUser()->id !== $user->id) {
            return response()->json(['message' => 'Not access'], 403);
        }

        $newUserEmail = $request->get('email');

        if (!$newUserEmail) {
            return response()->json(['success' => false, 'message' => ['email' => ['Invalid email']]], 422);
        }

        $newUser = User::where('email', $newUserEmail)->first();

        if (!$newUser) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $fileModel->users()->toggle([$newUser->id => ['is_admin' => false]]);

        $fileUsers = $fileModel->users()->get();

        $response = $fileUsers->map(fn(User $user) => [
            'fullname' => $user->last_name . ' ' . $user->first_name,
            'email' => $user->email,
            'type' => $fileModel->getAdminUser()->id === $user->id ? 'author' : 'co-author'
        ]);

        return response()->json($response->all());
    }

    public function deleteFileAccesses(Request $request, string $fileId)
    {
        $user = auth()->user();
        /** @var File|null $fileModel */
        $fileModel = File::where('file_id', $fileId)->first();

        if (!$fileModel) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($fileModel->getAdminUser()->id !== $user->id) {
            return response()->json(['message' => 'Not access'], 403);
        }

        $deletedUserEmail = $request->get('email');

        if (!$deletedUserEmail) {
            return response()->json(['success' => false, 'message' => ['email' => ['Invalid email']]], 422);
        }

        $deletedUser = User::where('email', $deletedUserEmail)->first();

        if (!$deletedUser) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $fileModel->users()->detach([$deletedUser->id]);

        $fileUsers = $fileModel->users()->get();

        $response = $fileUsers->map(fn(User $user) => [
            'fullname' => $user->last_name . ' ' . $user->first_name,
            'email' => $user->email,
            'type' => $fileModel->getAdminUser()->id === $user->id ? 'author' : 'co-author'
        ]);

        return response()->json($response->all());
    }

    public function getUserFiles(Request $request): JsonResponse
    {
        $user = auth()->user();
        $userFiles = $user->files()->get();

        $response = $userFiles->map(function (File $file) {
            $fileUsers = $file->users()->get();
           return [
               'name' => $file->name,
               'url' => request()->getHost() . '/files/' . $file->file_id,
               'file_id' => $file->file_id,
               'access' => $fileUsers->map(fn(User $user) => [
                   'fullname' => $user->last_name . ' ' . $user->first_name,
                   'email' => $user->email,
                   'type' => $file->getAdminUser()->id === $user->id ? 'author' : 'co-author'
               ])->all()
           ];
        });

        return response()->json($response->all());
    }
}
