<?php

namespace App\Events;

use App\Models\ChatMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $chatMessage;

    public function __construct(ChatMessage $chatMessage)
    {
        $this->chatMessage = $chatMessage;
    }

    public function broadcastOn()
    {
        // Channel khusus transaksi, agar hanya customer & driver terkait yang dapat pesan
        return new PrivateChannel('chat.transaction.' . $this->chatMessage->transaction_id);
    }

    public function broadcastWith()
    {
        return [
            'id' => $this->chatMessage->id,
            'transaction_id' => $this->chatMessage->transaction_id,
            'sender_id' => $this->chatMessage->sender_id,
            'receiver_id' => $this->chatMessage->receiver_id,
            'message' => $this->chatMessage->message,
            'sent_by' => $this->chatMessage->sent_by,
            'created_at' => $this->chatMessage->created_at,
        ];
    }
}
