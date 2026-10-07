<?php
/**
 * Handles user authentication - registration, login, and session management
 * 
 * Note: We're keeping it simple with email/password auth for now, but we could
 * easily add social logins or 2FA later if needed.
 */

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * Create a new user account
     * 
     * @param Request $request User registration data
     * @return \Illuminate\Http\JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function register(Request $request)
    {
        // Validate the incoming request - keeping it simple but secure
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email|max:100',
            'password' => 'required|min:8|confirmed',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string|max:255',
            'role' => 'sometimes|in:customer,driver,owner,staff',
        ]);

        try {
            // Create the user with validated data
            $newUser = new User();
            $newUser->name = trim($validated['name']);
            $newUser->email = strtolower(trim($validated['email']));
            $newUser->password = Hash::make($validated['password']);
            $newUser->phone = $validated['phone'] ?? null;
            $newUser->address = $validated['address'] ?? null;
            $newUser->role = $validated['role'] ?? 'customer';
            $newUser->status = 'active'; // Will require email verification later
            $newUser->save();

            if ($newUser->role === 'driver') {
                Driver::firstOrCreate(
                    ['user_id' => $newUser->id],
                    [
                        'status' => 'offline',
                        'is_verified' => false,
                    ]
                );
            }

            // Generate API token for immediate use
            $authToken = $newUser->createToken('api_auth')->plainTextToken;

            // TODO: Send welcome email in the background
            // $this->dispatch(new SendWelcomeEmail($newUser));

            return response()->json([
                'status' => 'success',
                'message' => 'Account created successfully!',
                'data' => [
                    'user' => $newUser->makeHidden(['password']),
                    'token' => $authToken
                ]
            ], 201);
            
        } catch (\Exception $e) {
            // Log the actual error for debugging
            \Log::error('Registration error: ' . $e->getMessage());
            
            // Return a generic error message to the client
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create account. Please try again.'
            ], 500);
        }
    }

    /**
     * Authenticate user and return API token
     * 
     * @param Request $request Login credentials
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Basic validation
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Find user by email (case insensitive)
        $user = User::whereRaw('LOWER(email) = ?', [strtolower($credentials['email'])])->first();

        // Check if user exists and password is correct
        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            // Use same message for both cases to prevent user enumeration
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid login credentials.'
            ], 401);
        }

        // Check if account is active
        if ($user->status !== 'active') {
            $message = match($user->status) {
                'pending' => 'Please verify your email address first.',
                'suspended' => 'Your account has been suspended. Contact support.',
                default => 'Your account is not active.'
            };
            
            return response()->json([
                'status' => 'error',
                'message' => $message
            ], 403);
        }

        try {
            // Revoke old tokens (optional, for security)
            $user->tokens()->delete();
            
            // Create new token
            $token = $user->createToken('api_auth')->plainTextToken;
            
            // Update last login time
            $user->last_login_at = now();
            $user->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Welcome back, ' . $user->name . '!',
                'data' => [
                    'user' => $user->makeHidden(['password']),
                    'token' => $token
                ]
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Login error: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process login. Please try again.'
            ], 500);
        }
    }

    /**
     * Log the user out (invalidate the token)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            // Get the current token and delete it
            $token = $request->user()->currentAccessToken();
            
            if ($token) {
                $token->delete();
            }
            
            // Alternative: Delete all user tokens (for all devices)
            // $request->user()->tokens()->delete();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Successfully logged out.'
            ]);
            
        } catch (\Exception $e) {
            \Log::error('Logout error: ' . $e->getMessage());
            
            // Even if logout fails, we still return success to the client
            return response()->json([
                'status' => 'success',
                'message' => 'Logged out successfully.'
            ]);
        }
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load(['driverProfile', 'restaurants']),
        ]);
    }

    /**
     * Update user profile
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'address' => 'sometimes|string',
            'latitude' => 'sometimes|numeric',
            'longitude' => 'sometimes|numeric',
            'profile_image' => 'sometimes|string',
        ]);

        $user->update($request->only([
            'name', 'phone', 'address', 'latitude', 'longitude', 'profile_image'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => $user,
        ]);
    }

    /**
     * Change password
     */
    public function changePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = $request->user();

        if (!Hash::check($request->current_password, $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Password changed successfully',
        ]);
    }
}
