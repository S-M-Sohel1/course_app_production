<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use Illuminate\Support\Facades\Cache;
use App\Models\Member;
use App\Models\TeacherProfile;
use Exception;

class CourseController extends Controller
{
    public function courseList(Request $request)
    {


        $courses = Course::select('name', 'thumbnail', 'price', 'lesson_num', 'price', 'id')->get();


        return response()->json([
            'code' => 200,
            'msg' => 'Course List',
            'data' => $courses
        ], 200);
    }
    public function courseNewestList(Request $request)
    {

        //sort by created_at desc
        $courses = Course::select('name', 'thumbnail', 'price', 'lesson_num', 'price', 'id')->orderBy('created_at', 'desc')->get();


        return response()->json([
            'code' => 200,
            'msg' => 'Course List',
            'data' => $courses
        ], 200);
    }

    public function coursePopularList(Request $request)
    {

        //sort by follow desc
        $courses = Course::select('name', 'thumbnail', 'price', 'lesson_num', 'price', 'id')->orderBy('follow', 'desc')->get();


        return response()->json([
            'code' => 200,
            'msg' => 'Course List',
            'data' => $courses
        ], 200);
    }

    public function courseDetail(Request $request)
    {
        $id = $request->id;
        try {
            $result = Course::where('id', $id)->select(
                'id',
                'name',
                'user_token',
                'description',
                'price',
                'lesson_num',
                'video_length',
                'follow',
                'thumbnail',
                'score'
            )->first();

            return response()->json([
                'code' => 200,
                'msg' => 'Course Detail',
                'data' => $result
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'msg' => 'Course Detail Load Failed',
                'data' => []
            ], 500);
        }
    }

    public function coursesBought(Request $request)
    {

        $user = $request->user();

        $courses = Course::join('orders', 'courses.id', '=', 'orders.course_id')
            ->where('orders.user_token', '=', $user->token)
            ->where('orders.status', '=', 1)
            ->select('courses.name', 'courses.thumbnail', 'courses.price', 'courses.lesson_num', 'courses.id')
            ->get();

        return response()->json([
            'code' => 200,
            'msg' => 'The courses you have bought',
            'data' => $courses
        ], 200);
    }
    public function coursesSearchDefault(Request $request)
    {
        $user = request()->user();
        $result = Course::where('recommended', '=', '1')
            ->select('name', 'thumbnail', 'price', 'lesson_num', 'price', 'id')->get();

        return response()->json(
            [
                'code' => 200,
                'msg' => 'Recommended Courses',
                'data' => $result
            ],
            200
        );


    }

    public function coursesSearch(Request $request)
    {
        $user = request()->user();
        $search = $request->search;
        $result = Course::where('name', 'like', '%' . $search . '%')
            ->select('name', 'thumbnail', 'price', 'lesson_num', 'price', 'id')->get();

        return response()->json(
            [
                'code' => 200,
                'msg' => 'Search Courses',
                'data' => $result
            ],
            200
        );


    }

    public function authorCourseList(Request $request)
    {
        $token = $request->token;
        //grab errors with try catch block
        try {


            $courses = Course::where('user_token', '=', $token)
                ->select('name', 'thumbnail', 'price', 'lesson_num', 'price', 'id')->get();
            return response()->json([
                'code' => 200,
                'msg' => 'Author Course List',
                'data' => $courses
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => []
            ], 500);
        }
    }

    public function courseAuthor(Request $request)
    {
        $token = $request->token;

        /*  $courses = Course::join('orders', 'courses.id', '=', 'orders.course_id')
        ->where('orders.user_token', '=', $user->token)
        ->where('orders.status', '=', 1)
        ->select('courses.name', 'courses.thumbnail', 'courses.price', 'courses.lesson_num', 'courses.id')
        ->get(); */
        //grab errors with try catch block
        try {

            $author = TeacherProfile::join('members', 'teacher_profiles.user_token', '=', 'members.token')
                ->where('teacher_profiles.user_token', '=', $token)
                ->select(
                    'members.name',
                    'members.description',
                    'teacher_profiles.avatar',
                    'teacher_profiles.cover',
                    'teacher_profiles.rating',
                    'teacher_profiles.downloads',
                    'teacher_profiles.total_students',
                    'teacher_profiles.job',
                    'members.token'
                )
                ->first();


            return response()->json([
                'code' => 200,
                'msg' => 'Author Info',
                'data' => $author
            ], 200);
        } catch(\Exception $e){
            return response()->json([
                'code' => 500,
                'msg' => $e->getMessage(),
                'data' => []
            ], 200);
        }
    }

    //course purchased check
    public function coursePurchaseStatus(Request $request)
    {

        $user = $request->user();
        $courseId = $request->id;

        $course = Course::join('orders', 'courses.id', '=', 'orders.course_id')
            ->where('orders.user_token', '=', $user->token)
            ->where('orders.status', '=', 1)
            ->where('courses.id', '=', $courseId)
            ->select('courses.id')
            ->first();
        if (empty($course)) {
            return response()->json([
                'code' => 404,
                'status' => '0',
            ], 200);
        }


        return response()->json([
            'code' => 200,
            'status' => '1',
        ], 200);
    }

}


