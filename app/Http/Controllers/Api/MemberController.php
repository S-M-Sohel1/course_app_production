<?php

namespace App\Http\Controllers\Api;

use App\Models\Member;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use App\Models\Order;

class MemberController extends Controller
{
    /**
     * Create User\
     * @param Request $request
     * @return User 
     */
    public function login(Request $request)
    {



        try {
            //Validated
            $validateUser = Validator::make(
                $request->all(),
                [
                    'avatar' => 'required',

                    'open_id' => 'required',
                    'name' => 'required',
                    'email' => 'required',
                    //  'password' => 'required'
                ]
            );

            if ($validateUser->fails()) {
                return response()->json([
                    'status' => false,
                    'message' => 'validation error',
                    'errors' => $validateUser->errors()
                ], 401);
            }

            $validated = $validateUser->validated();
            $map = [];


            $map['open_id'] = $validated['open_id'];

            $user = Member::where($map)->first();


            if (empty($user->id)) {
                $validated["token"] = md5(uniqid() . rand(1000, 9999));
                $validated['created_at'] = Carbon::now();

                //  $validated['password'] = Hash::make($validated['password']);
                $userID = Member::insertGetId($validated);
                $userInfo = Member::where('id', '=', $userID)->first();
                $accesstoken = $userInfo->createToken(uniqid())->plainTextToken;
                $userInfo->access_token = $accesstoken;
                Member::where('id', '=', $userID)->update(['access_token' => $accesstoken]);

                return response()->json([
                    'code' => 200,
                    'msg' => 'User Created Successfully',
                    'data' => $userInfo
                ], 200);
            }

            $accesstoken = $user->createToken(uniqid())->plainTextToken;
            $user->access_token = $accesstoken;
            Member::where('id', '=', $user->id)->update(['access_token' => $accesstoken]);

            return response()->json([
                'code' => 200,
                'msg' => 'Userlogged in Successfully',
                'data' => $user
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'code' => 500,
                'msg' => $th->getMessage()
            ], 500);
        }
    }
    public function getUserId(Request $request)
    {
        $user = $request->user(); 

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired token.'
            ], 401);
        }

        return response()->json([
            'code'=>200,
            'success' => true,
            'data' => $user->id,
        ],200);
    }

    public function update_photo(Request $request)
    {
        $user = Auth::user();
        
        // Store file in S3 bucket instead of local storage
        $filepath = $request->avatar->store('avatars', 's3');
        
        Member::where('id', '=', $user->id)->update(['avatar' => $filepath]);
        
        // Return full S3 URL
        $fullUrl = Storage::disk('s3')->url($filepath);
        
        return response()->json([
            'code' => 200,
            'msg' => 'Photo updated successfully',
            'data' => $fullUrl
        ], 200);
    }

    public function memberPayment(Request $request)
    {
        $user = Auth::user();
        $payment = Order::join('courses', 'orders.course_id', '=', 'courses.id')
            ->join('members', 'courses.user_token', '=', 'members.token')
            ->where('orders.user_token', $user->token)
            ->where('orders.status', 1)
            ->select(
                'courses.id',
                'orders.total_amount as price',
                'courses.name',
                'members.name as teacher_name',
                'courses.thumbnail',
                'orders.transaction_id'
            )
            ->get();
        return response()->json([
            'code' => 200,
            'msg' => 'Payment List',
            'data' => $payment
        ], 200);
    }

    public function changeName(Request $request)
    {
        $user = Auth::user();
        $validateUser = Validator::make($request->all(), [
            'name' => 'required|string|max:20',
        ]);
        if ($validateUser->fails()) {
            return response()->json([
                'code' => 400,
                'msg' => 'Validation error',
            ], 400);
        }
        $user->name = $request->name;
        Member::where('id', '=', $user->id)->update(['name' => $request->name]);
        return response()->json([
            'code' => 200,
            'msg' => 'Name changed successfully',
        ], 200);
    }
    public function changeDescription(Request $request)
    {
        $user = Auth::user();
        $validateUser = Validator::make($request->all(), [
            'description' => 'required|string|max:50',
        ]);
        if ($validateUser->fails()) {
            return response()->json([
                'code' => 400,
                'msg' => 'Validation error',
            ], 400);
        }
        $user->description = $request->description;
        Member::where('id', '=', $user->id)->update(['description' => $request->description]);
        return response()->json([
            'code' => 200,
            'msg' => 'Description changed successfully',
        ], 200);
    }
}
