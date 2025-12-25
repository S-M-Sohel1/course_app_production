<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $messageData;

    public function __construct(public Message $message)
    {
        $this->messageData = [
            'id' => $message->id,
            'chat_id' => $message->chat_id,
            'sender_id' => $message->sender_id,
            'receiver_id' => (int) $message->receiver_id,
            'type' => $message->type,
            'created_at' => $message->created_at->toDateTimeString(),
        ];
    }

    public function broadcastWith()
    {
        return $this->messageData;
    }

    // Broadcast to this channel
    public function broadcastOn()
    {
        // Private channel example: private-chat.1
        return new Channel('chat.' . $this->messageData['receiver_id']);
    }
    public function broadcastAs()
    {
        return 'MessageSent';
    }
    // public function broadcastWith()
    // {
    //     return [
    //         'chat_id' => $this->message->chat_id,
    //         'sender_id' => $this->message->sender_id,
    //         'receiver_id' => $this->message->receiver_id,
    //         'message' => $this->message->message,
    //         'created_at' => $this->message->toDateTimeString(),


    //     ];
    // }
}