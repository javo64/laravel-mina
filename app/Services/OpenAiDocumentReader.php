<?php

namespace App\Services;

use App\Models\OpenAiSetting;
use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;

class OpenAiDocumentReader
{
    public function analyze(UploadedFile $file): array
    {
        $setting = OpenAiSetting::current();
        if (! $setting->is_active || ! $setting->hasApiKey()) {
            throw ValidationException::withMessages([
                'document' => 'La integración con OpenAI no está configurada o se encuentra desactivada.',
            ]);
        }

        $products = Product::where('is_active', true)->where('type', 'Producto')
            ->orderBy('name')->get(['id', 'code', 'name', 'unit', 'stock']);
        $catalog = $products->map(fn (Product $product) => [
            'id' => $product->id, 'code' => $product->code,
            'name' => $product->name, 'unit' => $product->unit,
        ])->values()->all();

        $mime = $file->getMimeType() ?: $file->getClientMimeType();
        $dataUrl = 'data:'.$mime.';base64,'.base64_encode($file->getContent());
        $documentInput = $mime === 'application/pdf'
            ? ['type' => 'input_file', 'filename' => $file->getClientOriginalName(), 'file_data' => $dataUrl]
            : ['type' => 'input_image', 'image_url' => $dataUrl, 'detail' => 'high'];

        try {
            $response = Http::withToken($setting->api_key)
                ->withOptions(['verify' => resource_path('certificates/cacert.pem')])
                ->acceptJson()->timeout(90)->retry(2, 500, throw: false)
                ->post('https://api.openai.com/v1/responses', [
                'model' => $setting->model,
                'store' => false,
                'reasoning' => ['effort' => 'low'],
                'input' => [[
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => "Lee este documento de recepción de almacén. Extrae cada producto y su cantidad recibida. Relaciona cada línea solamente con el catálogo proporcionado. Usa el código exacto cuando aparezca; de lo contrario elige el producto únicamente si el nombre coincide con alta confianza. Si no existe coincidencia segura, devuelve product_id null. No inventes productos ni cantidades.\n\nCATÁLOGO:\n".json_encode($catalog, JSON_UNESCAPED_UNICODE),
                        ],
                        $documentInput,
                    ],
                ]],
                'text' => [
                    'format' => [
                        'type' => 'json_schema',
                        'name' => 'warehouse_document',
                        'strict' => true,
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'document_number' => ['type' => ['string', 'null']],
                                'items' => [
                                    'type' => 'array',
                                    'items' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'product_id' => ['type' => ['integer', 'null']],
                                            'document_description' => ['type' => 'string'],
                                            'quantity' => ['type' => 'integer', 'minimum' => 1],
                                            'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                                        ],
                                        'required' => ['product_id', 'document_description', 'quantity', 'confidence'],
                                        'additionalProperties' => false,
                                    ],
                                ],
                            ],
                            'required' => ['document_number', 'items'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                'max_output_tokens' => 2000,
                ]);
        } catch (ConnectionException $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'document' => 'No se pudo establecer una conexión segura con OpenAI. Verifica la conexión a internet e inténtalo nuevamente.',
            ]);
        }

        if ($response->failed()) {
            report(new \RuntimeException('OpenAI document analysis failed: '.$response->status().' '.$response->body()));
            throw ValidationException::withMessages([
                'document' => 'OpenAI no pudo analizar el documento. Verifica la credencial e inténtalo nuevamente.',
            ]);
        }

        $outputText = $response->json('output_text');
        if (! $outputText) {
            foreach ($response->json('output', []) as $output) {
                foreach ($output['content'] ?? [] as $content) {
                    if (($content['type'] ?? null) === 'output_text') {
                        $outputText = $content['text'] ?? null;
                        break 2;
                    }
                }
            }
        }

        $result = json_decode((string) $outputText, true);
        if (! is_array($result)) {
            throw ValidationException::withMessages([
                'document' => 'No se pudo interpretar la respuesta del análisis.',
            ]);
        }

        $byId = $products->keyBy('id');
        $items = collect($result['items'] ?? [])->map(function (array $item) use ($byId) {
            $product = isset($item['product_id']) ? $byId->get((int) $item['product_id']) : null;

            return [
                'product_id' => $product?->id,
                'product_code' => $product?->code,
                'product_name' => $product?->name,
                'unit' => $product?->unit,
                'stock' => $product?->stock,
                'document_description' => $item['document_description'] ?? '',
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'confidence' => (float) ($item['confidence'] ?? 0),
                'matched' => (bool) $product,
            ];
        })->values()->all();

        return ['document_number' => $result['document_number'] ?? null, 'items' => $items];
    }
}
