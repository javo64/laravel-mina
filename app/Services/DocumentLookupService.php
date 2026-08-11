<?php

namespace App\Services;

use App\Models\DocumentApiSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class DocumentLookupService
{
    public function lookup(string $document): array
    {
        $setting = DocumentApiSetting::current();
        if (! $setting->is_active || ! $setting->hasToken()) {
            throw ValidationException::withMessages([
                'document_number' => 'La API de consulta de documentos no está configurada o está desactivada.',
            ]);
        }

        $documentType = strlen($document) === 8 ? 'dni' : 'ruc';
        $usesDocumentInUrl = str_contains($setting->url, '{document}');
        $url = $usesDocumentInUrl
            ? str_replace(['{type}', '{document}'], [$documentType, $document], $setting->url)
            : rtrim($setting->url, '/').'/api/'.$documentType;

        try {
            $request = Http::withToken($setting->token)
                ->withOptions(['verify' => resource_path('certificates/cacert.pem')])
                ->acceptJson()->asJson()->timeout(30)->retry(2, 400, throw: false);
            $response = $usesDocumentInUrl
                ? $request->get($url)
                : $request->post($url, [$documentType => $document]);
        } catch (ConnectionException $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'document_number' => 'No se pudo conectar con la API de documentos.',
            ]);
        }

        if ($response->failed() || $response->json('success') === false) {
            $apiMessage = $response->json('message');
            throw ValidationException::withMessages([
                'document_number' => $response->status() === 404
                    ? 'La ruta configurada no existe en el proveedor de la API.'
                    : ($apiMessage ?: 'La API no pudo completar la consulta. Verifica la URL y el token.'),
            ]);
        }

        $payload = $response->json();
        $data = is_array($payload) ? (Arr::get($payload, 'data') ?: $payload) : [];
        $name = $this->first($data, ['razonSocial', 'razon_social', 'nombreCompleto', 'nombre_completo', 'nombre', 'full_name']);
        if (! $name) {
            $name = trim(implode(' ', array_filter([
                $this->first($data, ['nombres']),
                $this->first($data, ['apellidoPaterno', 'apellido_paterno']),
                $this->first($data, ['apellidoMaterno', 'apellido_materno']),
            ])));
        }
        if (! $name) {
            throw ValidationException::withMessages([
                'document_number' => 'La API respondió, pero no devolvió un nombre o razón social reconocible.',
            ]);
        }

        return [
            'document_type' => strtoupper($documentType),
            'document_number' => $document,
            'name' => $name,
            'trade_name' => $this->first($data, ['nombreComercial', 'nombre_comercial', 'trade_name']),
            'address' => $this->first($data, ['direccion', 'address', 'domicilioFiscal', 'domicilio_fiscal']),
            'district' => $this->first($data, ['distrito', 'district']),
            'province' => $this->first($data, ['provincia', 'province']),
            'department' => $this->first($data, ['departamento', 'department']),
        ];
    }

    private function first(array $data, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = Arr::get($data, $key);
            if (is_scalar($value) && trim((string) $value) !== '') {
                return trim((string) $value);
            }
        }

        return null;
    }
}
