<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\Member;
use App\Models\Chat;
use App\Models\Message;

use function Pest\Laravel\json;

class MessageController extends Controller
{
    //fetch all users
    public function users(Request $request)
    {
        $current_user = Auth::user()->id;
        $users = Member::where('id', '!=', $current_user)->where('role','teacher')->get();
        //$users = Member::where('id', '!=', $current_user)->get();
        return response()->json([
            'code' => 200,
            'msg' => "Users fetch successfully",
            'data' => $users
        ], 200);
    }

    //send message to others

    public function sendMessage(Request $request)
    {
        try {
            $request->validate([
                'message' => "required",
                'receiver_id' => "required|exists:members,id",
                'type' => "required|in:text,video,photo",
            ]);
            $chat = Chat::Where(function ($query) use ($request) {
                $query->where('sender_id', Auth::user()->id)->where('receiver_id', $request->receiver_id);
            })->orWhere(function ($query) use ($request) {
                $query->where('sender_id', $request->receiver_id)->where('receiver_id', Auth::user()->id);
            })->first();
            if (!$chat) {
                Chat::create([
                    'sender_id' => Auth::user()->id,
                    'receiver_id' => $request->receiver_id
                ]);
            }
            $msg = Message::create([
                'receiver_id' => $request->receiver_id,
                'sender_id' => Auth::user()->id,
                'message' => $request->message,
                'type' => $request->type,
                'chat_id' => $chat->id,
            ]);

        broadcast(new MessageSent($msg))->toOthers();

            return response()->json([
                'code' => 200,
                'msg' => "message sent successfully",
                'data' => $msg,
            ], 200);
        } catch (\Throwable $e) {
            return response()->json(
                [
                    'code' => 500,
                    'msg' => "failed to sent message",
                    'data' => $e->getMessage(),
                ],500
            );
        }
    }

    //get message from other person

    public function getMessage($id)
    {
        $messages = Message::where(function ($query) use ($id) {
            $query->where('sender_id', Auth::user()->id)->where('receiver_id', $id);
        })->orWhere(function($query)use($id){
            $query->where('sender_id',$id)->where('receiver_id',Auth::user()->id);
        })->get();

        $messages=$messages->map(function($message){
            $message->is_me=$message->sender_id==Auth::user()->id;
            return $message;
        });
        return response()->json($messages);
    }
}
