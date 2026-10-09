<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class Dislike_commentaire implements shouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public $commentaire;
    public function __construct($data)
    {
        //
        $this->commentaire = $data;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn()
    {
        return new PrivateChannel('dislike_commentaire.'.$this->commentaire->recette_id);
    }
    public function broadcastWith()
    {
        return [
            'commentaire' => $this->commentaire,
        ];
    }

    public function broadcastAs(){
        return "dislike.commentaire";
    }
}
