<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UsersController extends Controller
{
    public function authorization(Request $request): JsonResponse
    {
        if (!$request->email || !$request->password) {
            return response()->json(['success' => false, 'message' => 'Login failed'], 401);
        }

        if (Auth::attempt(['email' => $request->email, 'password' => $request->password])) {
            $user = Auth::user();
            $token = $user->createToken('api')->plainTextToken;
            return response()->json(['success' => true, 'message' => 'Success', 'token' => $token], 200);
        } else {
            return response()->json(['success' => false, 'message' => 'Login failed'], 401);
        }
    }

    public function registration(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:3',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Validation failed'], 422);
        }

        $user = new User;
        $user->first_name = $request->first_name;
        $user->last_name = $request->last_name;
        $user->email = $request->email;
        $user->password = bcrypt($request->password);
        $user->save();

        $token = $user->createToken('api')->plainTextToken;

        return response()->json(['success' => true, 'message' => 'Success', 'token' => $token], 200);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete(); // Получаем токен пользователя и делаем его неактивным

        return response()->json(['success' => true, 'message' => 'Logout'], 200);
    }

    public function sharedFiles(Request $request): JsonResponse
    {
        $user = auth()->user();
        $userFiles = $user->files()->wherePivot('is_admin', false)->get();

        $response = $userFiles->map(fn(File $file) => [
            'name' => $file->name,
            'url' => request()->getHost() . '/files/' . $file->file_id,
            'file_id' => $file->file_id
        ]
        );

        return response()->json($response->all());
    }
}
