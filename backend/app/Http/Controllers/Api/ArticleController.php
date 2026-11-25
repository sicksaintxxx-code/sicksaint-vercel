<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\FirestoreStore;
use App\Services\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ArticleController extends Controller
{
    private const STANDARD_CATEGORIES = [
        ['slug' => 'world', 'name' => 'World', 'description' => 'Sovereign statecraft, geopolitical corridors, and foreign intelligence architecture.'],
        ['slug' => 'technology', 'name' => 'Technology', 'description' => 'Autonomous compute enclaves, cryptographic infrastructure, and foundation intelligence.'],
        ['slug' => 'politics', 'name' => 'Politics', 'description' => 'Legislative analysis, institutional power, and executive governance.'],
        ['slug' => 'business', 'name' => 'Business', 'description' => 'Industrial capital, sovereign wealth allocations, and market structures.'],
        ['slug' => 'ai', 'name' => 'AI & Computation', 'description' => 'Subterranean GPU sanctuaries, inference economics, and synthetic reasoning.'],
        ['slug' => 'finance', 'name' => 'Global Finance', 'description' => 'Shadow banking, liquidity corridors, and currency architectures.'],
        ['slug' => 'fashion', 'name' => 'Fashion & Tailoring', 'description' => 'Neo-brutalist patternmaking, archival textiles, and tactile luxury.'],
        ['slug' => 'culture', 'name' => 'Culture', 'description' => 'Underground movements, tactile cinema, and counter-cultural analysis.'],
        ['slug' => 'gaming', 'name' => 'Gaming', 'description' => 'Interactive simulations, engine architecture, and virtual economies.'],
        ['slug' => 'sports', 'name' => 'Sports', 'description' => 'Strategic athletic competition, biomechanics, and sports governance.'],
        ['slug' => 'science', 'name' => 'Science', 'description' => 'Astrophysics, molecular biology, and quantum engineering dossiers.'],
        ['slug' => 'automotive', 'name' => 'Automotive', 'description' => 'Industrial mechanical design, coachbuilding, and propulsion.'],
        ['slug' => 'travel', 'name' => 'Travel', 'description' => 'Solitary expeditionary field dispatches and borderlands exploration.'],
        ['slug' => 'photography', 'name' => 'Photography', 'description' => 'Analog optical documentary work, silver halide, and visual journalism.'],
        ['slug' => 'architecture', 'name' => 'Architecture', 'description' => 'Monolithic brutalism, civic sanctuaries, and tactile stone construction.'],
        ['slug' => 'health', 'name' => 'Health', 'description' => 'Human longevity, cellular resilience, and biological longevity studies.'],
        ['slug' => 'film', 'name' => 'Film & Cinema', 'description' => 'Celluloid cinema, auteur theory, and underground screen culture.'],
        ['slug' => 'music', 'name' => 'Music & Sonic', 'description' => 'Acoustic experimentation, modular synthesis, and auditory criticism.'],
        ['slug' => 'lifestyle', 'name' => 'Tactile Lifestyle', 'description' => 'Archival design objects, analog tools, and disciplined living.'],
    ];

    public function __construct(
        private FirestoreStore $store,
        private HtmlSanitizer $sanitizer
    ) {}

    /**
     * Public listing of published articles only.
     * Never exposes drafts, rejected submissions, or private contributor data.
     */
    public function index(Request $request)
    {
        $category = $request->string('category')->trim()->lower()->toString() ?: null;
        $tag = $request->string('tag')->trim()->lower()->toString() ?: null;
        $format = $request->string('format')->trim()->upper()->toString() ?: null;
        $search = $request->string('search')->trim()->lower()->toString() ?: null;

        $page = max(1, $request->integer('page', 1));
        $perPage = min(100, max(1, $request->integer('per_page', $request->integer('limit', 20))));

        // Fetch all published submissions
        try {
            $all = $this->store->query('submissions', [['status', 'EQUAL', 'PUBLISHED']], 'publishedAt', true, 1000);
        } catch (\Throwable) {
            $all = $this->loadSeededArticles($category);
        }

        // Apply filters in memory for flexibility across Firestore emulator and live
        $filtered = array_values(array_filter($all, function ($art) use ($category, $tag, $format, $search) {
            if ($category && Str::lower($art['categorySlug'] ?? '') !== $category) {
                return false;
            }
            if ($format && Str::upper($art['format'] ?? '') !== $format) {
                return false;
            }
            if ($tag) {
                $tags = array_map(fn ($t) => Str::lower(trim($t)), $art['tags'] ?? []);
                if (!in_array($tag, $tags, true)) {
                    return false;
                }
            }
            if ($search) {
                $haystack = Str::lower(($art['title'] ?? '') . ' ' . ($art['subtitle'] ?? '') . ' ' . ($art['excerpt'] ?? ''));
                if (!str_contains($haystack, $search)) {
                    return false;
                }
            }
            return true;
        }));

        $total = count($filtered);
        $totalPages = (int) ceil($total / $perPage);
        $paged = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        $articles = array_map(fn ($item) => $this->shapePublicArticle($item), $paged);

        return response()->json([
            'success' => true,
            'data' => $articles,
            'pagination' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    /**
     * Public article details by slug.
     * Returns 404 for missing or unpublished records.
     */
    public function show(string $slug)
    {
        $slug = Str::lower(trim($slug));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return response()->json(['error' => 'Invalid article slug format.'], 400);
        }

        // Query by slug
        try {
            $matches = $this->store->query('submissions', [
                ['slug', 'EQUAL', $slug],
                ['status', 'EQUAL', 'PUBLISHED'],
            ], null, false, 5);

            // If not matched by slug, check publishedSlug
            if (empty($matches)) {
                $matches = $this->store->query('submissions', [
                    ['publishedSlug', 'EQUAL', $slug],
                    ['status', 'EQUAL', 'PUBLISHED'],
                ], null, false, 5);
            }
        } catch (\Throwable) {
            $matches = [];
            $seeded = $this->findSeededArticleBySlug($slug);
            if ($seeded) {
                return response()->json([
                    'success' => true,
                    'data' => $seeded,
                    'related' => [],
                ]);
            }
        }

        if (empty($matches)) {
            return response()->json(['error' => 'Article not found.'], 404);
        }

        $record = $matches[0];
        $article = $this->shapePublicArticle($record, true);

        // Fetch up to 3 related published articles from same category
        $categorySlug = $record['categorySlug'] ?? null;
        $related = [];
        if ($categorySlug) {
            $catArticles = $this->store->query('submissions', [
                ['categorySlug', 'EQUAL', $categorySlug],
                ['status', 'EQUAL', 'PUBLISHED'],
            ], 'publishedAt', true, 10);

            $relatedFiltered = array_values(array_filter($catArticles, fn ($item) => ($item['id'] ?? '') !== $record['id']));
            $related = array_map(fn ($item) => $this->shapePublicArticle($item, false), array_slice($relatedFiltered, 0, 3));
        }

        return response()->json([
            'success' => true,
            'data' => $article,
            'related' => $related,
        ]);
    }

    /**
     * Public categories taxonomy.
     */
    public function categories()
    {
        // Calculate count of published articles per category
        $published = $this->store->query('submissions', [['status', 'EQUAL', 'PUBLISHED']], null, false, 1000);
        $counts = [];
        foreach ($published as $art) {
            $cat = $art['categorySlug'] ?? 'culture';
            $counts[$cat] = ($counts[$cat] ?? 0) + 1;
        }

        $categories = array_map(function ($cat) use ($counts) {
            return [
                'id' => 'cat-' . $cat['slug'],
                'slug' => $cat['slug'],
                'name' => $cat['name'],
                'description' => $cat['description'],
                'count' => $counts[$cat['slug']] ?? 0,
            ];
        }, self::STANDARD_CATEGORIES);

        return response()->json([
            'success' => true,
            'data' => $categories,
        ]);
    }

    /**
     * Public tags list from published articles.
     */
    public function tags()
    {
        $published = $this->store->query('submissions', [['status', 'EQUAL', 'PUBLISHED']], null, false, 1000);
        $tagCounts = [];

        foreach ($published as $art) {
            foreach (($art['tags'] ?? []) as $t) {
                $clean = Str::lower(trim($t));
                if ($clean !== '') {
                    $tagCounts[$clean] = ($tagCounts[$clean] ?? 0) + 1;
                }
            }
        }

        $tags = [];
        foreach ($tagCounts as $slug => $count) {
            $tags[] = [
                'id' => 'tag-' . $slug,
                'slug' => $slug,
                'name' => ucfirst($slug),
                'count' => $count,
            ];
        }

        usort($tags, fn ($a, $b) => $b['count'] <=> $a['count']);

        return response()->json([
            'success' => true,
            'data' => $tags,
        ]);
    }

    /**
     * Shape a submission record into a safe, public Article object.
     * Strips all internal metadata and ensures private contributor data is not leaked.
     */
    private function shapePublicArticle(array $s, bool $includeCleanContent = true): array
    {
        $authorId = $s['authorId'] ?? ($s['author']['id'] ?? 'usr-editorial');
        $authorUser = !empty($s['authorId']) ? ($this->store->get('users', $s['authorId']) ?? []) : ($s['author'] ?? []);
        $categorySlug = $s['categorySlug'] ?? 'culture';
        $categoryName = $this->categoryName($categorySlug);

        $content = $s['content'] ?? '';
        $wordCount = str_word_count(strip_tags($content));
        $readingTimeMinutes = max(1, (int) ceil($wordCount / 200));

        $slug = $s['slug'] ?? $s['publishedSlug'] ?? Str::slug($s['title']);

        return [
            'id' => $s['id'],
            'title' => $s['title'],
            'slug' => $slug,
            'subtitle' => $s['subtitle'] ?? null,
            'excerpt' => $s['excerpt'] ?? null,
            'content' => $includeCleanContent ? $this->sanitizer->clean($content) : null,
            'coverImageUrl' => $s['coverImageUrl'] ?? null,
            'coverImageCredit' => $s['coverImageCredit'] ?? null,
            'category' => [
                'id' => 'cat-' . $categorySlug,
                'slug' => $categorySlug,
                'name' => $categoryName,
            ],
            'categorySlug' => $categorySlug,
            'tags' => $s['tags'] ?? [],
            'format' => $s['format'] ?? 'DISPATCH',
            'status' => 'PUBLISHED',
            'featured' => !empty($s['featured']),
            'readingTimeMinutes' => $readingTimeMinutes,
            'seoTitle' => $s['seoTitle'] ?? $s['title'],
            'seoDescription' => $s['seoDescription'] ?? $s['excerpt'] ?? $s['title'],
            'publishedAt' => $s['publishedAt'] ?? $s['updatedAt'] ?? now()->toIso8601String(),
            'author' => [
                'id' => $authorId,
                'name' => $authorUser['name'] ?? 'Editorial Staff',
                'username' => $authorUser['username'] ?? 'editor',
                'avatarUrl' => $authorUser['avatarUrl'] ?? null,
                'bio' => $authorUser['bio'] ?? null,
                'title' => $authorUser['title'] ?? null,
            ],
        ];
    }

    private function categoryName(string $slug): string
    {
        foreach (self::STANDARD_CATEGORIES as $cat) {
            if ($cat['slug'] === $slug) {
                return $cat['name'];
            }
        }
        return ucfirst($slug);
    }

    private function loadSeededArticles(?string $category = null): array
    {
        $dir = storage_path('app/seeders/editorial_dispatches');
        if (!is_dir($dir)) return [];

        if ($category) {
            $file = "{$dir}/articles_{$category}.json";
            if (file_exists($file)) {
                $decoded = json_decode(file_get_contents($file), true);
                return is_array($decoded) ? $decoded : [];
            }
        }

        // Aggregate a balanced sample from each genre file
        $results = [];
        $files = glob("{$dir}/articles_*.json") ?: [];
        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            if (is_array($content)) {
                $results = array_merge($results, array_slice($content, 0, 10));
            }
        }
        return $results;
    }

    private function findSeededArticleBySlug(string $slug): ?array
    {
        $dir = storage_path('app/seeders/editorial_dispatches');
        if (!is_dir($dir)) return null;

        $files = glob("{$dir}/articles_*.json") ?: [];
        foreach ($files as $file) {
            $content = json_decode(file_get_contents($file), true);
            if (is_array($content)) {
                foreach ($content as $item) {
                    if (($item['slug'] ?? '') === $slug) {
                        return $item;
                    }
                }
            }
        }
        return null;
    }
}
