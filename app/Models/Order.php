<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'conversation_history',
        'order_data',
        'status',
        'webhook_url',
        'webhook_sent',
    ];

    protected $casts = [
        'conversation_history' => 'array',
        'order_data' => 'array',
        'webhook_sent' => 'boolean',
    ];

    public function isComplete(): bool
    {
        $order = $this->order_data ?? [];
        
        return $this->status === 'completed' && 
               isset($order['cliente']) && 
               isset($order['itens']) && 
               is_array($order['itens']) && 
               count($order['itens']) > 0 &&
               isset($order['tipo_atendimento']) && 
               isset($order['forma_pagamento']);
    }
}

