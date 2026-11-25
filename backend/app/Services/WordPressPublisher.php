<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Optional WordPress Headless CMS publisher.
 * SICKSAINT operates as an independent internal CMS.
 * If WordPress credentials and API URL are not configured,
 * this service gracefully skips without blocking editorial publishing.
 */
class WordPressPublisher
{
    public function isConfigured(): bool
    {
        $url = (string) env('WORDPRESS_API_URL', env('WORDPRESS_SITE_URL', ''));
        $username = (string) env('WORDPRESS_USERNAME', '');
        $password = (string) env('WORDPRESS_APPLICATION_PASSWORD', '');

        return !empty($url) && !empty($username) && !empty($password);
    }

    private function baseUrl(): ?string
    {
        $url = rtrim((string) env('WORDPRESS_API_URL', ''), '/');
        if ($url === '') {
            $site = rtrim((string) env('WORDPRESS_SITE_URL', ''), '/');
            if ($site === '') {
                return null;
            }
            $url = str_ends_with($site, '/wp-json/wp/v2') ? $site : $site . '/wp-json/wp/v2';
        }
        if (!str_starts_with($url, 'https://') && !str_starts_with($url, 'http://localhost')) {
            return null;
        }
        if (!preg_match('~/wp-json/wp/v2$~', $url)) {
            $url .= '/wp-json/wp/v2';
        }
        return $url;
    }

    private function client()
    {
        $username = (string) env('WORDPRESS_USERNAME', '');
        $password = (string) env('WORDPRESS_APPLICATION_PASSWORD', '');
        if ($username === '' || $password === '') {
            return null;
        }
        return Http::withBasicAuth($username, $password)->acceptJson()->asJson()->timeout(5)->connectTimeout(2);
    }

    /**
     * Optionally create a pending post in WordPress if configured.
     * Returns the remote post ID if successful, or null if skipped/failed.
     */
    public function createPending(array $submission): ?int
    {
        if (!$this->isConfigured()) {
            return null;
        }

        try {
            $baseUrl = $this->baseUrl();
            $client = $this->client();
            if (!$baseUrl || !$client) {
                return null;
            }

            $response = $client->post($baseUrl . '/posts', [
                'title' => $submission['title'],
                'excerpt' => $submission['excerpt'] ?? '',
                'content' => $submission['content'],
                'status' => 'pending',
                'slug' => str($submission['slug'] ?? $submission['title'])->slug()->limit(180),
            ]);

            if ($response->successful() && is_int($response->json('id'))) {
                return $response->json('id');
            }
        } catch (Throwable $e) {
            Log::info('[WordPressPublisher] Optional WP pending sync skipped: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Optionally publish the pending post in WordPress if configured.
     * Returns the remote slug if successful, or null if skipped/failed.
     */
    public function publish(array $submission): ?string
    {
        if (!$this->isConfigured() || empty($submission['wordpressPostId'])) {
            return null;
        }

        try {
            $baseUrl = $this->baseUrl();
            $client = $this->client();
            if (!$baseUrl || !$client) {
                return null;
            }

            $response = $client->put($baseUrl . '/posts/' . $submission['wordpressPostId'], [
                'status' => 'publish',
            ]);

            if ($response->successful() && is_string($response->json('slug'))) {
                return $response->json('slug');
            }
        } catch (Throwable $e) {
            Log::info('[WordPressPublisher] Optional WP publish sync skipped: ' . $e->getMessage());
        }

        return null;
    }
}
