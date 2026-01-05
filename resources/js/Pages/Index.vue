<template>
    <div class="min-h-screen bg-gradient-to-br from-red-50 to-orange-50">
        <div class="container mx-auto px-4 py-8 max-w-4xl">
            <div class="bg-white rounded-lg shadow-lg p-6 mb-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-red-600">
                            🍕 Assistente de Pedidos
                        </h1>
                        <p class="text-gray-600 mt-1">
                            Virtual Order Assistant
                        </p>
                    </div>
                    <div v-if="orderComplete" class="bg-green-100 text-green-800 px-4 py-2 rounded-full text-sm font-semibold">
                        Pedido Completo ✓
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-lg overflow-hidden flex flex-col" style="height: 600px;">
                <div ref="messagesContainer" class="flex-1 overflow-y-auto p-6 space-y-4">
                    <div v-if="messages.length === 0" class="flex justify-center items-center h-full">
                        <div class="text-center">
                            <div class="text-6xl mb-4">🍕</div>
                            <p class="text-gray-600 text-lg">Olá! Como posso ajudá-lo hoje?</p>
                            <p class="text-gray-500 mt-2">Como posso ajudá-lo hoje?</p>
                        </div>
                    </div>

                    <div
                        v-for="(message, index) in messages"
                        :key="index"
                        :class="[
                            'flex',
                            message.role === 'user' ? 'justify-end' : 'justify-start'
                        ]"
                    >
                        <div
                            :class="[
                                'max-w-xs lg:max-w-md px-4 py-3 rounded-lg',
                                message.role === 'user'
                                    ? 'bg-red-600 text-white'
                                    : 'bg-gray-100 text-gray-800'
                            ]"
                        >
                            <p class="whitespace-pre-wrap">{{ message.content }}</p>
                            <span class="text-xs opacity-70 mt-1 block">
                                {{ formatTime(message.timestamp) }}
                            </span>
                        </div>
                    </div>

                    <div v-if="loading" class="flex justify-start">
                        <div class="bg-gray-100 text-gray-800 px-4 py-3 rounded-lg">
                            <div class="flex space-x-2">
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                                <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.4s"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="border-t p-4 bg-gray-50">
                    <form @submit.prevent="sendMessage" class="flex space-x-2">
                        <input
                            v-model="inputMessage"
                            type="text"
                            placeholder="Digite sua mensagem..."
                            class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-500 focus:border-transparent"
                            :disabled="loading || orderComplete"
                        />
                        <button
                            type="submit"
                            :disabled="loading || !inputMessage.trim() || orderComplete"
                            class="bg-red-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-red-700 disabled:bg-gray-400 disabled:cursor-not-allowed transition-colors"
                        >
                            Enviar
                        </button>
                    </form>
                </div>
            </div>

            <div v-if="orderComplete && orderData" class="mt-6 bg-white rounded-lg shadow-lg p-6">
                <h2 class="text-2xl font-bold text-gray-800 mb-4">📋 Resumo do Pedido</h2>
                
                <div v-if="orderData.cliente" class="mb-6 pb-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">👤 Cliente</h3>
                    <p class="text-gray-600"><strong>Nome:</strong> {{ orderData.cliente.nome }}</p>
                    <p v-if="orderData.cliente.telefone" class="text-gray-600"><strong>Telefone:</strong> {{ orderData.cliente.telefone }}</p>
                    <p v-if="orderData.cliente.observacoes" class="text-gray-600"><strong>Observações:</strong> {{ orderData.cliente.observacoes }}</p>
                </div>

                <div class="mb-6 pb-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">📦 Itens do Pedido</h3>
                    <div v-for="(item, index) in orderData.itens" :key="index" class="mb-4 p-4 bg-gray-50 rounded-lg">
                        <p class="font-semibold text-gray-800">{{ item.quantidade }}x {{ item.nome_produto }}</p>
                        <p v-if="item.categoria" class="text-sm text-gray-600">Categoria: {{ item.categoria }}</p>
                        <p v-if="item.tamanho" class="text-sm text-gray-600">Tamanho: {{ item.tamanho }}</p>
                        <div v-if="item.sabores && item.sabores.length > 0" class="mt-2">
                            <p class="text-sm font-medium text-gray-700">Sabores:</p>
                            <ul class="list-disc list-inside text-sm text-gray-600">
                                <li v-for="(sabor, sIndex) in item.sabores" :key="sIndex">{{ sabor }}</li>
                            </ul>
                        </div>
                        <div v-if="item.complementos && item.complementos.length > 0" class="mt-2">
                            <p class="text-sm font-medium text-gray-700">Complementos:</p>
                            <ul class="list-disc list-inside text-sm text-gray-600">
                                <li v-for="(comp, cIndex) in item.complementos" :key="cIndex">
                                    {{ comp.nome_complemento }}: {{ comp.opcao_escolhida }}
                                    <span v-if="comp.valor_adicional && comp.valor_adicional > 0"> (+ R$ {{ comp.valor_adicional.toFixed(2) }})</span>
                                </li>
                            </ul>
                        </div>
                        <p v-if="item.observacoes_item" class="text-sm text-gray-600 mt-2"><em>{{ item.observacoes_item }}</em></p>
                        <p v-if="item.preco_total_item" class="text-sm font-semibold text-gray-800 mt-2">
                            Preço: R$ {{ item.preco_total_item.toFixed(2) }}
                        </p>
                    </div>
                </div>

                <div class="mb-6 pb-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">🚚 Entrega</h3>
                    <p class="text-gray-600">
                        <strong>Tipo:</strong> {{ orderData.tipo_atendimento === 'entrega' ? 'Entrega em domicílio' : 'Retirada no balcão' }}
                    </p>
                    <div v-if="orderData.endereco_entrega" class="mt-2 text-gray-600">
                        <p><strong>Endereço:</strong></p>
                        <p>{{ orderData.endereco_entrega.rua }}, {{ orderData.endereco_entrega.numero }}</p>
                        <p v-if="orderData.endereco_entrega.complemento">{{ orderData.endereco_entrega.complemento }}</p>
                        <p>{{ orderData.endereco_entrega.bairro }}, {{ orderData.endereco_entrega.cidade }}</p>
                        <p v-if="orderData.endereco_entrega.cep">CEP: {{ orderData.endereco_entrega.cep }}</p>
                        <p v-if="orderData.endereco_entrega.ponto_referencia">Ponto de referência: {{ orderData.endereco_entrega.ponto_referencia }}</p>
                    </div>
                </div>

                <div class="mb-6 pb-6 border-b">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">💳 Pagamento</h3>
                    <p class="text-gray-600"><strong>Forma:</strong> {{ formatPaymentMethod(orderData.forma_pagamento) }}</p>
                    <p v-if="orderData.troco_para" class="text-gray-600">
                        <strong>Troco para:</strong> R$ {{ orderData.troco_para.toFixed(2) }}
                    </p>
                </div>

                <div v-if="orderData.valor_total_pedido || orderData.taxa_entrega" class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">💰 Valores</h3>
                    <p v-if="orderData.taxa_entrega" class="text-gray-600">
                        <strong>Taxa de entrega:</strong> R$ {{ orderData.taxa_entrega.toFixed(2) }}
                    </p>
                    <p v-if="orderData.valor_total_pedido" class="text-xl font-bold text-red-600 mt-2">
                        <strong>Total:</strong> R$ {{ orderData.valor_total_pedido.toFixed(2) }}
                    </p>
                </div>

                <div v-if="orderData.observacoes_gerais" class="mb-6">
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">📝 Observações Gerais</h3>
                    <p class="text-gray-600">{{ orderData.observacoes_gerais }}</p>
                </div>

                <div class="mt-4 p-4 bg-green-50 rounded-lg">
                    <p class="text-green-800 font-semibold">
                        ✓ Pedido enviado com sucesso! Você receberá uma confirmação em breve.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue';
import axios from 'axios';
import { Head } from '@inertiajs/vue3';

const messages = ref([]);
const inputMessage = ref('');
const loading = ref(false);
const orderComplete = ref(false);
const orderData = ref(null);
const sessionId = ref(null);
const messagesContainer = ref(null);

onMounted(() => {
    if (!sessionId.value) {
        sessionId.value = generateSessionId();
    }
});

const generateSessionId = () => {
    return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
};

const sendMessage = async () => {
    if (!inputMessage.value.trim() || loading.value) {
        return;
    }

    const userMessage = inputMessage.value.trim();
    inputMessage.value = '';

    messages.value.push({
        role: 'user',
        content: userMessage,
        timestamp: new Date(),
    });

    loading.value = true;
    scrollToBottom();

    try {
        const response = await axios.post('/api/chat', {
            message: userMessage,
            session_id: sessionId.value,
        });

        messages.value.push({
            role: 'assistant',
            content: response.data.response,
            timestamp: new Date(),
        });

        if (response.data.order_complete) {
            orderComplete.value = true;
            orderData.value = response.data.order_data;
        }

        if (response.data.session_id) {
            sessionId.value = response.data.session_id;
        }

        scrollToBottom();
    } catch (error) {
        console.error('Error sending message:', error);
        messages.value.push({
            role: 'assistant',
            content: 'Desculpe, ocorreu um erro ao processar sua mensagem. Por favor, tente novamente.',
            timestamp: new Date(),
        });
    } finally {
        loading.value = false;
    }
};

const scrollToBottom = () => {
    nextTick(() => {
        if (messagesContainer.value) {
            messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
        }
    });
};

const formatTime = (date) => {
    return new Date(date).toLocaleTimeString('en-US', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatPaymentMethod = (method) => {
    const methods = {
        'dinheiro': 'Dinheiro',
        'pix': 'PIX',
        'cartao_credito': 'Cartão de Crédito',
        'cartao_debito': 'Cartão de Débito',
        'link_pagamento': 'Link de Pagamento',
    };
    return methods[method] || method;
};
</script>

<style scoped>
.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}

.overflow-y-auto::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.overflow-y-auto::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.overflow-y-auto::-webkit-scrollbar-thumb:hover {
    background: #555;
}
</style>