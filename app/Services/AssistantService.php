<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AssistantService
{
    private string $apiKey;
    private string $model;
    private string $webhookUrl; 
    private string $orderWebhookUrl;
    private string $trainingUrl;

    public function __construct()
    {
        $this->apiKey = config('services.openai.api_key') ?? env('OPENAI_API_KEY', '');
        $this->model = config('services.openai.model', 'gpt-4o-mini') ?? 'gpt-4o-mini';
        $this->webhookUrl = config('services.webhook.url') ?? env('WEBHOOK_URL', '') ?? '';
        $this->orderWebhookUrl = config('services.order_webhook.url') ?? env('ORDER_WEBHOOK_URL', '') ?? '';
        $this->trainingUrl = config('services.training.url') ?? env('TRAINING_URL', '');
    }

    public function getSystemPrompt(): string
    {
        return "Você é um assistente de pedidos.
Sua função é conversar com o cliente via WhatsApp, montar o pedido completo (itens, complementos, endereço, pagamento) e, ao final, chamar a função criar_pedido com todos os dados estruturados.

REGRAS IMPORTANTES:
1. Seja sempre educado, amigável e prestativo
2. Faça perguntas uma de cada vez para não sobrecarregar o cliente
3. Use emojis ocasionalmente para tornar a conversa mais amigável 🍕
4. NUNCA chame a função criar_pedido sem antes mostrar o resumo do pedido e receber uma confirmação explícita do cliente
5. Ao finalizar, você DEVE chamar a função criar_pedido com todos os dados coletados

PASSOS DA CONVERSA:
1. Saudação e identificação: Perguntar nome do cliente (se ainda não tiver)
2. Entender intenção: Se o cliente quer fazer pedido, perguntar sobre cardápio, ou dúvidas gerais
3. Construção do pedido:
   - Perguntar categoria (promoção, pizza avulsa, bebida, sobremesa etc.)
   - Tamanho (família, grande, média, etc., conforme cardápio)
   - Sabores (respeitando quantidade de sabores permitidos e complementos obrigatórios)
   - Massa (fina/média/etc.)
   - Borda (tipos + valores adicionais)
   - Bebidas extras (opcionais/obrigatórias conforme produto)
   - Quantidade de cada item
4. Dados do cliente:
   - Nome (se ainda não tiver)
   - Tipo de atendimento: Entrega ou Retirada no balcão
   - Se entrega: Endereço completo (rua, número, bairro, complemento, ponto de referência, cidade)
   - Observações do pedido (opcional)
5. Pagamento:
   - Forma de pagamento (dinheiro, Pix, cartão crédito/débito, link, etc.)
   - Se dinheiro, perguntar se precisa de troco e para quanto
6. Resumo e confirmação:
   - Exibir resumo do pedido (itens, valores, taxa de entrega se houver)
   - Perguntar: \"Posso confirmar o seu pedido assim?\"
7. Finalização:
   - Se o cliente confirmar: Chame a função criar_pedido
   - Se o cliente pedir alteração: Ajuste os itens e só chame a função após confirmação final

CARDÁPIO E PRODUTOS:
A base de conhecimento completa (cardápio, regras, produtos, tamanhos, complementos obrigatórios e opcionais, preços e promoções) será fornecida no contexto adicional abaixo.

IMPORTANTE: Você DEVE anotar o pedido e chamar a função criar_pedido quando o cliente confirmar. Não apenas mande para o site, mas registre o pedido através da função.";
    }

    private function getTrainingContent(): string
    {
        try {
            $cacheKey = 'training_content_' . md5($this->trainingUrl);
            $cached = cache()->get($cacheKey);
            
            if ($cached !== null) {
                return $cached;
            }

            $response = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'text/html,application/json,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'User-Agent' => 'Mozilla/5.0 (compatible; Laravel AssistantService)',
                ])
                ->get($this->trainingUrl);

            if ($response->successful()) {
                $content = $response->body();
                
                $jsonData = json_decode($content, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($jsonData)) {
                    $content = $this->formatTrainingData($jsonData);
                } else {
                    $content = $this->extractTextFromHtml($content);
                }
                
                cache()->put($cacheKey, $content, now()->addHour());
                
                return $content;
            } else {
                Log::warning('Failed to fetch training content', [
                    'url' => $this->trainingUrl,
                    'status' => $response->status(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Error fetching training content', [
                'url' => $this->trainingUrl,
                'message' => $e->getMessage(),
            ]);
        }

        return '';
    }

    private function formatTrainingData(array $data): string
    {
        $formatted = "BASE DE CONHECIMENTO - CARDÁPIO E REGRAS:\n\n";
        
        if (isset($data['content']) || isset($data['text']) || isset($data['menu'])) {
            $content = $data['content'] ?? $data['text'] ?? $data['menu'] ?? '';
            if (is_string($content)) {
                return $formatted . $content;
            }
            if (is_array($content)) {
                return $formatted . json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }
        }
        
        return $formatted . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function extractTextFromHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', '', $html);
        $html = preg_replace('/<style\b[^<]*(?:(?!<\/style>)<[^<]*)*<\/style>/mi', '', $html);
        
        $text = strip_tags($html);
        
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }

    public function getCreateOrderTool(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => 'criar_pedido',
                'description' => 'Finaliza um pedido de pizza feito pelo cliente via WhatsApp e retorna todos os dados estruturados para o backend registrar o pedido no sistema.',
                'parameters' => [
                    'type' => 'object',
                    'required' => ['cliente', 'itens', 'tipo_atendimento', 'forma_pagamento'],
                    'properties' => [
                        'cliente' => [
                            'type' => 'object',
                            'required' => ['nome'],
                            'properties' => [
                                'nome' => [
                                    'type' => 'string',
                                    'description' => 'Nome do cliente.'
                                ],
                                'telefone' => [
                                    'type' => 'string',
                                    'description' => 'Telefone do cliente em formato internacional ou nacional. Se disponível, usar o telefone do WhatsApp.'
                                ],
                                'observacoes' => [
                                    'type' => 'string',
                                    'description' => 'Observações gerais do cliente sobre o pedido.',
                                    'nullable' => true
                                ]
                            ]
                        ],
                        'tipo_atendimento' => [
                            'type' => 'string',
                            'enum' => ['entrega', 'retirada'],
                            'description' => 'Se o cliente pediu entrega em domicílio ou retirada no balcão.'
                        ],
                        'endereco_entrega' => [
                            'type' => 'object',
                            'description' => 'Preencha apenas se tipo_atendimento for \'entrega\'.',
                            'required' => [],
                            'properties' => [
                                'rua' => ['type' => 'string'],
                                'numero' => ['type' => 'string'],
                                'bairro' => ['type' => 'string'],
                                'complemento' => ['type' => 'string'],
                                'ponto_referencia' => ['type' => 'string'],
                                'cidade' => ['type' => 'string'],
                                'cep' => ['type' => 'string']
                            ]
                        ],
                        'itens' => [
                            'type' => 'array',
                            'description' => 'Lista de itens do pedido.',
                            'items' => [
                                'type' => 'object',
                                'required' => ['nome_produto', 'quantidade'],
                                'properties' => [
                                    'produto_id' => [
                                        'type' => 'string',
                                        'description' => 'ID do produto no sistema, se conhecido. Caso não saiba, deixe vazio e use o nome_produto.'
                                    ],
                                    'nome_produto' => [
                                        'type' => 'string',
                                        'description' => 'Nome do produto conforme aparece no cardápio. Ex.: \'>> Pizza Familia (12 fatias) + Refri - PROMO\'.'
                                    ],
                                    'categoria' => [
                                        'type' => 'string',
                                        'description' => 'Categoria do produto. Ex.: \'Promoções\', \'Bebidas\', \'Sobremesa\', etc.',
                                        'nullable' => true
                                    ],
                                    'tamanho' => [
                                        'type' => 'string',
                                        'description' => 'Tamanho da pizza, quando aplicável. Ex.: \'Família\', \'Grande\', \'8 fatias\', etc.',
                                        'nullable' => true
                                    ],
                                    'sabores' => [
                                        'type' => 'array',
                                        'description' => 'Lista de sabores escolhidos para este item, quando aplicável.',
                                        'items' => ['type' => 'string']
                                    ],
                                    'complementos' => [
                                        'type' => 'array',
                                        'description' => 'Complementos escolhidos (bordas, massas, bebidas extras, etc.).',
                                        'items' => [
                                            'type' => 'object',
                                            'required' => ['nome_complemento'],
                                            'properties' => [
                                                'nome_complemento' => [
                                                    'type' => 'string',
                                                    'description' => 'Nome do grupo de complemento. Ex.: \'Borda recheada?\', \'Massa fina ou média?\'.'
                                                ],
                                                'opcao_escolhida' => [
                                                    'type' => 'string',
                                                    'description' => 'Opção escolhida pelo cliente. Ex.: \'Cheddar\', \'Sem borda :)\', \'Fina\'.'
                                                ],
                                                'valor_adicional' => [
                                                    'type' => 'number',
                                                    'description' => 'Valor adicional deste complemento, se houver.',
                                                    'nullable' => true
                                                ]
                                            ]
                                        ]
                                    ],
                                    'observacoes_item' => [
                                        'type' => 'string',
                                        'description' => 'Alguma observação específica para este item. Ex.: \'cortar em 12 pedaços\', \'tirar cebola\'.',
                                        'nullable' => true
                                    ],
                                    'quantidade' => [
                                        'type' => 'integer',
                                        'description' => 'Quantidade deste item.',
                                        'minimum' => 1
                                    ],
                                    'preco_unitario' => [
                                        'type' => 'number',
                                        'description' => 'Preço unitário estimado do item (sem taxa de entrega). A IA pode usar o preço base do cardápio.',
                                        'nullable' => true
                                    ],
                                    'preco_total_item' => [
                                        'type' => 'number',
                                        'description' => 'Preço total do item (quantidade x unitário + complementos). Opcional.',
                                        'nullable' => true
                                    ]
                                ]
                            ]
                        ],
                        'forma_pagamento' => [
                            'type' => 'string',
                            'description' => 'Forma de pagamento escolhida pelo cliente. Ex.: \'dinheiro\', \'pix\', \'cartao_credito\', \'cartao_debito\', \'link_pagamento\'.'
                        ],
                        'troco_para' => [
                            'type' => 'number',
                            'description' => 'Se pagamento em dinheiro, informar o valor para o qual o cliente precisa de troco. Ex.: 100.00.',
                            'nullable' => true
                        ],
                        'taxa_entrega' => [
                            'type' => 'number',
                            'description' => 'Taxa de entrega estimada, se a IA tiver esse dado. Caso não saiba, pode deixar null.',
                            'nullable' => true
                        ],
                        'valor_total_pedido' => [
                            'type' => 'number',
                            'description' => 'Valor total do pedido (somatório de itens + taxa de entrega). Opcional, pode ser recalculado no backend.',
                            'nullable' => true
                        ],
                        'origem' => [
                            'type' => 'string',
                            'description' => 'Canal de origem do pedido. Ex.: \'whatsapp_ia_donvitto\'.',
                            'default' => 'whatsapp_ia_donvitto'
                        ],
                        'observacoes_gerais' => [
                            'type' => 'string',
                            'description' => 'Observações gerais sobre o pedido, caso o cliente tenha comentado algo relevante.',
                            'nullable' => true
                        ]
                    ]
                ]
            ]
        ];
    }

    public function chat(string $message, string $sessionId): array
    {
        if (empty($this->apiKey)) {
            Log::error('OpenAI API Key not configured');
            return [
                'response' => 'Erro de configuração: A chave da API OpenAI não está configurada. Por favor, verifique o arquivo .env e adicione OPENAI_API_KEY.',
                'order_complete' => false,
            ];
        }

        $order = Order::firstOrCreate(
            ['session_id' => $sessionId],
            [
                'conversation_history' => [],
                'order_data' => [],
                'status' => 'in_progress',
                'webhook_sent' => false,
            ]
        );

        $conversationHistory = $order->conversation_history ?? [];
        $conversationHistory[] = [
            'role' => 'user',
            'content' => $message,
        ];

        $systemPrompt = $this->getSystemPrompt();
        $trainingContent = $this->getTrainingContent();
        
        if (!empty($trainingContent)) {
            $systemPrompt .= "\n\n" . $trainingContent;
        }

        $messages = [
            [
                'role' => 'system',
                'content' => $systemPrompt,
            ],
        ];

        foreach ($conversationHistory as $msg) {
            if (isset($msg['role']) && isset($msg['content'])) {
                $messages[] = $msg;
            }
        }

        try {
            $requestPayload = [
                'model' => $this->model,
                'messages' => $messages,
                'temperature' => 0.7,
                'max_tokens' => 1000,
                'tools' => [$this->getCreateOrderTool()],
                'tool_choice' => 'auto',
            ];

            Log::info('Sending request to OpenAI', [
                'model' => $this->model,
                'messages_count' => count($messages),
            ]);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post('https://api.openai.com/v1/chat/completions', $requestPayload);

            if (!$response->successful()) {
                $errorBody = $response->body();
                $errorData = json_decode($errorBody, true);
                
                Log::error('OpenAI API Error', [
                    'status' => $response->status(),
                    'body' => $errorBody,
                    'error' => $errorData['error'] ?? null,
                ]);

                $errorMessage = 'Desculpe, ocorreu um erro ao processar sua mensagem.';
                
                if (isset($errorData['error']['message'])) {
                    $errorMessage = 'Erro: ' . $errorData['error']['message'];
                    
                    if (str_contains($errorData['error']['message'], 'Invalid API key')) {
                        $errorMessage = 'Erro: Chave da API OpenAI inválida. Por favor, verifique a configuração no arquivo .env.';
                    } elseif (str_contains($errorData['error']['message'], 'insufficient_quota')) {
                        $errorMessage = 'Erro: Cota da API OpenAI esgotada. Por favor, verifique sua conta OpenAI.';
                    } elseif (str_contains($errorData['error']['message'], 'rate_limit')) {
                        $errorMessage = 'Erro: Limite de requisições excedido. Por favor, tente novamente em alguns instantes.';
                    }
                }

                return [
                    'response' => $errorMessage,
                    'order_complete' => false,
                ];
            }

            $data = $response->json();
            
            if (!isset($data['choices']) || empty($data['choices'])) {
                Log::error('OpenAI API: No choices in response', ['data' => $data]);
                return [
                    'response' => 'Desculpe, não recebi uma resposta válida da API. Por favor, tente novamente.',
                    'order_complete' => false,
                ];
            }

            $messageResponse = $data['choices'][0]['message'] ?? [];
            $assistantMessage = $messageResponse['content'] ?? null;
            $toolCalls = $messageResponse['tool_calls'] ?? [];

            Log::info('OpenAI API Response', [
                'has_content' => !empty($assistantMessage),
                'has_tool_calls' => !empty($toolCalls),
                'tool_calls_count' => count($toolCalls),
            ]);

            $orderComplete = false;
            $orderData = null;

            if (!empty($toolCalls)) {
                foreach ($toolCalls as $toolCall) {
                    if (isset($toolCall['function']['name']) && $toolCall['function']['name'] === 'criar_pedido') {
                        $functionArguments = json_decode($toolCall['function']['arguments'] ?? '{}', true);
                        
                        if (!empty($functionArguments)) {
                            $orderData = $this->processOrderData($functionArguments, $sessionId);
                            $order->order_data = $orderData;
                            $order->status = 'completed';
                            $orderComplete = true;
                            
                            $this->sendOrderToWebhook($order, $orderData);
                        }
                    }
                }

                $conversationHistory[] = [
                    'role' => 'assistant',
                    'content' => $assistantMessage,
                    'tool_calls' => $toolCalls,
                ];

                if ($orderComplete) {
                    foreach ($toolCalls as $toolCall) {
                        if (isset($toolCall['function']['name']) && $toolCall['function']['name'] === 'criar_pedido') {
                            $conversationHistory[] = [
                                'role' => 'tool',
                                'tool_call_id' => $toolCall['id'] ?? null,
                                'content' => json_encode(['status' => 'success', 'message' => 'Pedido criado com sucesso!']),
                            ];
                        }
                    }

                    $assistantMessage = $assistantMessage ?? 'Pedido confirmado com sucesso! 🍕\n\nSeu pedido foi registrado e será processado em breve.';
                }
            } else {
                $conversationHistory[] = [
                    'role' => 'assistant',
                    'content' => $assistantMessage ?? 'Desculpe, não consegui processar sua mensagem.',
                ];
            }

            $order->conversation_history = $conversationHistory;
            $order->save();

            $finalResponse = $assistantMessage ?? 'Desculpe, não consegui processar sua mensagem.';
            
            if (empty($finalResponse) && !empty($toolCalls)) {
                $finalResponse = 'Processando seu pedido...';
            }

            return [
                'response' => $finalResponse,
                'order_complete' => $orderComplete,
                'order_data' => $orderData,
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('OpenAI API Connection Error', [
                'message' => $e->getMessage(),
            ]);

            return [
                'response' => 'Erro de conexão com a API OpenAI. Verifique sua conexão com a internet e tente novamente.',
                'order_complete' => false,
            ];
        } catch (\Exception $e) {
            Log::error('AssistantService Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return [
                'response' => 'Desculpe, ocorreu um erro inesperado: ' . $e->getMessage() . '. Por favor, verifique os logs para mais detalhes.',
                'order_complete' => false,
            ];
        }
    }

    private function processOrderData(array $functionArguments, string $sessionId): array
    {
        $estabelecimentoId = config('services.order.estabelecimento_id') ?? env('ESTABELECIMENTO_ID', 1);
        $canal = config('services.order.canal') ?? env('ORDER_CHANNEL', 'whatsapp_ia_donvitto');
        
        return [
            'estabelecimento_id' => (int) $estabelecimentoId,
            'canal' => $canal,
            'cliente' => $functionArguments['cliente'] ?? [],
            'tipo_atendimento' => $functionArguments['tipo_atendimento'] ?? 'entrega',
            'endereco_entrega' => $functionArguments['endereco_entrega'] ?? null,
            'itens' => $functionArguments['itens'] ?? [],
            'forma_pagamento' => $functionArguments['forma_pagamento'] ?? '',
            'troco_para' => $functionArguments['troco_para'] ?? null,
            'taxa_entrega' => $functionArguments['taxa_entrega'] ?? null,
            'valor_total_pedido' => $functionArguments['valor_total_pedido'] ?? null,
            'origem' => $functionArguments['origem'] ?? $canal,
            'observacoes_gerais' => $functionArguments['observacoes_gerais'] ?? null,
            'meta' => [
                'whatsapp_conversation_id' => $sessionId,
                'whatsapp_user_id' => $functionArguments['cliente']['telefone'] ?? null,
                'timestamp' => now()->toIso8601String(),
            ],
        ];
    }

    private function sendOrderToWebhook(Order $order, array $orderData): void
    {
        $webhookUrl = $this->orderWebhookUrl ?: $this->webhookUrl;
        
        if (empty($webhookUrl) || $order->webhook_sent) {
            return;
        }

        try {
            $headers = [
                'Content-Type' => 'application/json',
            ];

            $webhookToken = config('services.order_webhook.token') ?? env('ORDER_WEBHOOK_TOKEN');
            if ($webhookToken) {
                $headers['Authorization'] = 'Bearer ' . $webhookToken;
            }

            $response = Http::timeout(10)
                ->withHeaders($headers)
                ->post($webhookUrl, $orderData);

            if ($response->successful()) {
                $order->webhook_sent = true;
                $order->webhook_url = $webhookUrl;
                $order->save();

                Log::info('Order webhook sent successfully', [
                    'session_id' => $order->session_id,
                    'webhook_url' => $webhookUrl,
                ]);
            } else {
                Log::error('Order webhook failed', [
                    'session_id' => $order->session_id,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Order webhook exception', [
                'session_id' => $order->session_id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    public function getOrderStatus(string $sessionId): ?array
    {
        $order = Order::where('session_id', $sessionId)->first();

        if (!$order) {
            return null;
        }

        return [
            'status' => $order->status,
            'order_data' => $order->order_data,
            'is_complete' => $order->isComplete(),
            'webhook_sent' => $order->webhook_sent,
        ];
    }
}

