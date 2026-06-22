<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Events\ChatMessageSent;

class ChatController extends Controller
{
    // Ambil semua pesan chat untuk transaksi tertentu
    public function index($transaction_id)
    {
        $messages = ChatMessage::where('transaction_id', $transaction_id)
            ->orderBy('created_at', 'asc')
            ->with([
                'sender:id,name',
                'receiver:id,name,plate_number,vehicle_type'
            ])
            ->get();

        $messages = $messages->map(function ($msg) {
            $msgArr = $msg->toArray();
            if (isset($msgArr['receiver'])) {
                $msgArr['receiver']['plate_number'] = $msg->receiver->plate_number ?? null;
                $msgArr['receiver']['vehicle_type'] = $msg->receiver->vehicle_type ?? null;
            }
            return $msgArr;
        });

        // Ambil info driver dan customer dari order, selalu tampilkan order_info
        $orderInfo = null;
        $order = Order::find($transaction_id);
        if ($order) {
            $customer = User::find($order->user_id);
            $driver = User::find($order->driver_id);
            $orderInfo = [
                'transaction_id' => $order->id,
                'customer_id' => $customer ? $customer->id : null,
                'customer_name' => $customer ? $customer->name : null,
                'driver_id' => $driver ? $driver->id : null,
                'driver_name' => $driver ? $driver->name : null,
                'driver_phone' => $driver ? $driver->phone : null,
                'driver_plate_number' => $driver ? $driver->plate_number : null,
                'driver_vehicle_type' => $driver ? $driver->vehicle_type : null,
            ];
        }

        return response()->json([
            'messages' => $messages,
            'order_info' => $orderInfo
        ]);
    }

    // Kirim pesan chat
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'transaction_id' => 'required|exists:orders,id',
            'sender_id' => 'required|exists:users,id',
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string',
            'sent_by' => 'required|in:customer,driver',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $chat = ChatMessage::create($request->only([
            'transaction_id', 'sender_id', 'receiver_id', 'message', 'sent_by'
        ]));

        // Broadcast pesan ke channel private
        broadcast(new ChatMessageSent($chat))->toOthers();

        return response()->json(['message' => 'Pesan terkirim', 'data' => $chat], 201);
    }
}
