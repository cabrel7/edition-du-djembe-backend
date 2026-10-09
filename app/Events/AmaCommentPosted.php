<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;

use Illuminate\Broadcasting\InteractsWithSockets;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use App\Models\Ama_comment;
use Illuminate\Queue\SerializesModels;
// App\Events\Ama_commentPosted.php
class AmaCommentPosted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $comment;

    public function __construct(Ama_comment $comment)
    {
        $this->comment = $comment->load('user');
    }

   public function broadcastOn()
{
    return new PrivateChannel('ama-session.' . $this->comment->ama_session_id);
}


    public function broadcastWith()
    {
        return [
            'id' => $this->comment->id,
            'message' => $this->comment->message,
            'user' => [
                'id' => $this->comment->user->id,
                'name' => $this->comment->user->name,
                'prenom' => $this->comment->user->prenom,
                'photo' => $this->comment->user->avatar,
            ],
            'created_at' => $this->comment->created_at->toDateTimeString()
        ];
    }
}

