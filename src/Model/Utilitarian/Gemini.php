<?php

namespace App\Utilitarian;

class Gemini
{
    private $apiKey;
    private $model;

    public function __construct($apiKey = null, $model = 'gemini-2.0-flash-exp')
    {
        $this->apiKey = $apiKey ?? $_ENV['GEMINI_API_KEY'];
        $this->model = $model;

        if (!$this->apiKey) {
            throw new \Exception("Gemini API Key is not configured.");
        }
    }

    /**
     * Analiza un PDF usando Gemini enviándolo como base64.
     *
     * @param string $filePath Ruta del archivo PDF
     * @param string $prompt   Instrucción a Gemini
     * @return string          Texto devuelto por Gemini
     */
    public function analyzePDF(string $filePath, string $prompt)
    {
        if (!file_exists($filePath)) {
            throw new \Exception("El archivo no existe: $filePath");
        }

        $fileSize = filesize($filePath);
        $maxSize = 20 * 1024 * 1024; // 20MB límite recomendado

        if ($fileSize > $maxSize) {
            throw new \Exception("El archivo PDF es demasiado grande (" . round($fileSize / 1024 / 1024, 2) . "MB). Máximo permitido: 20MB");
        }

        $pdfBase64 = base64_encode(file_get_contents($filePath));

        $payload = [
            "contents" => [
                [
                    "parts" => [
                        [
                            "inline_data" => [
                                "mime_type" => "application/pdf",
                                "data" => $pdfBase64
                            ]
                        ],
                        [
                            "text" => $prompt
                        ]
                    ]
                ]
            ]
        ];

        return $this->makeRequest($payload);
    }

    /**
     * Enviar texto simple a Gemini.
     */
    public function ask(string $prompt)
    {
        $payload = [
            "contents" => [
                [
                    "parts" => [
                        ["text" => $prompt]
                    ]
                ]
            ]
        ];

        return $this->makeRequest($payload);
    }

    /**
     * Ejecuta la llamada HTTP a Gemini
     */
    private function makeRequest(array $payload)
    {
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}";

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
            CURLOPT_POSTFIELDS     => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120, // 2 minutos timeout
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($error) {
            throw new \Exception("Error al conectar con Gemini: " . $error);
        }

        if ($httpCode !== 200) {
            throw new \Exception("Gemini API respondió con código HTTP $httpCode: $response");
        }

        $json = json_decode($response, true);

        // Manejo de errores de la API
        if (isset($json['error'])) {
            throw new \Exception("Gemini API Error: " . $json['error']['message']);
        }

        return $json["candidates"][0]["content"]["parts"][0]["text"] ?? "⚠️ No se recibió texto.";
    }
}
