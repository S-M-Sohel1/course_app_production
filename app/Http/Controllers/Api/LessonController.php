<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Lesson;


class LessonController extends Controller
{
    public function lessonList(Request $request)
    {
       
       $id=$request->id;
       try{
        $result= Lesson::where('course_id','=',$id)->select(
            'id',
            'name',
            'thumbnail',
            'video',
            'description',
            'hls_processing'
        )->get();

        return response()->json([
            'code' => 200,
            'msg' => 'Lesson List',
            'data' => $result
        ], 200);
        

       }
       catch(\Exception $e){
          return response()->json([
            'code' => 500,
            'msg' => 'Lesson List Load Failed',
            'data' => $e->getMessage()
          ], 500);
         
       }
        
    }

    //for a single lesson
    public function lessonDetail(Request $request)
    {
       
       $id=$request->id;
       try{
        $result= Lesson::where('id','=',$id)->select(
            'name',
            'thumbnail',
            'video',
            'description',
            'hls_processing'
        )->get();

        return response()->json([
            'code' => 200,
            'msg' => 'Lesson Detail',
            'data' => $result
        ], 200);
        

       }
       catch(\Exception $e){
          return response()->json([
            'code' => 500,
            'msg' => 'Lesson List Load Failed',
            'data' => $e->getMessage()
          ], 500);
         
       }
        
    }

  
}
