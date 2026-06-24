<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Http;

class RecaptchaV3 implements ValidationRule
{
    protected float $threshold;

    public function __construct(float $threshold = 0.5)
    {
        $this->threshold = $threshold;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $secret = config('services.recaptcha.secret_key');

        if (empty($secret)) {
            // No reCAPTCHA configured — skip validation (dev mode)
            return;
        }

        if (empty($value) || !is_string($value)) {
            $fail('reCAPTCHA verification failed. Please try again.');
            return;
        }

        $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
            'secret'   => $secret,
            'response' => $value,
        ]);

        $body = $response->json();

        if (!($body['success'] ?? false)) {
            $fail('reCAPTCHA verification failed. Please try again.');
            return;
        }

        $score = (float) ($body['score'] ?? 0);
        if ($score < $this->threshold) {
            $fail('Suspicious activity detected. Please try again.');
        }
    }
}
