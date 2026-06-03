<?php

namespace App\Traits;

trait ApiResponse
{
    /**
     * Format sukses standar SAD (JSend)
     */
    protected function success($data = [], $message = null, $code = 200)
    {
        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * Format error standar SAD
     */
    protected function error($message, $code = 400, $data = null)
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            'code' => $code, // SAD meminta code ditampilkan di body response [cite: 147]
            'data' => $data,
        ], $code);
    }

    /**
     * Alias for success()
     */
    protected function successResponse($data = [], $message = null, $code = 200)
    {
        return $this->success($data, $message, $code);
    }

    /**
     * Alias for error()
     */
    protected function errorResponse($message, $code = 400, $data = null)
    {
        return $this->error($message, $code, $data);
    }
}
