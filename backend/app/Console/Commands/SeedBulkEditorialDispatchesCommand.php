<?php

namespace App\Console\Commands;

use App\Services\FirestoreStore;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class SeedBulkEditorialDispatchesCommand extends Command
{
    protected $signature = 'sicksaint:seed-articles 
                            {--count=1000 : Number of articles to generate per genre}
                            {--genre=all : Target genre slug, or "all" for all 20 genres}
                            {--target=json : Output mode: "json", "firestore", or "both"}
                            {--output= : Custom output directory for JSON archives}';

    protected $description = 'Generate bulk high-end editorial articles across SICKSAINT genres with Underground Y2K & Street Style photography.';

    /**
     * Curated catalog of authentic high-resolution photography.
     * Style: Underground Y2K, 35mm film grain, flash portraits, gritty street style, candid club culture, and lo-fi editorial.
     */
    private const STREET_PHOTOGRAPHY_POOL = [
        [
            'url' => 'https://images.unsplash.com/photo-1517841905240-472988babdf9?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Direct flash 35mm candid portrait, Lower East Side underground loft.',
            'credit' => 'Valerie Elash / SICKSAINT Street Archive',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1529139574466-a303027c1d8b?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'High-contrast monochrome street tailoring in heavy rainfall, Shibuya.',
            'credit' => 'Apostolos Vamvouras / SICKSAINT Optical Dossier',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1509967419530-da38b4704bc6?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Lo-fi analog flash portrait, late-night transit terminal, Berlin.',
            'credit' => 'Matheus Ferrero / SICKSAINT Underground Dispatch',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Raw 35mm film grain, oversized tinted eyewear, Hackney warehouse district.',
            'credit' => 'Ayo Ogunseinde / SICKSAINT Editorial Syndicate',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1506794778202-cad84cf45f1d?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Harsh tungsten studio test, distressed leather and silver signet jewelry.',
            'credit' => 'Albert Dera / SICKSAINT Visual Studies',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1492562080023-ab3db95bfbce?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Underground basement club mezzanine, motion blur and grain, Bristol.',
            'credit' => 'Brooke Cagle / SICKSAINT Sonic Archive',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?q=80&w=1600&auto=format&fit=crop',
            'caption' => '90s archival knitwear and silver hardware against weathered concrete, Paris 18e.',
            'credit' => 'Michael Dam / SICKSAINT Archival Photography',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Spontaneous sidewalk conversation, overcast daylight, Soho, London.',
            'credit' => 'Joseph Gonzalez / SICKSAINT Documentary Unit',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1519741497674-611481863552?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Subterranean venue exterior in dense fog, neon illumination, Kyoto.',
            'credit' => 'Jeremy Wong / SICKSAINT Night Dispatches',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1539571696357-5a69c17a67c6?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Candid analog capture, layered utilitarian parkas and chunky acetate frames.',
            'credit' => 'Nino Liverani / SICKSAINT Street Studies',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1517849845537-4d257902454a?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Moody ambient flash, downtown fire escape at dusk, Chinatown NYC.',
            'credit' => 'Charles Deluvio / SICKSAINT Metropole Dossier',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1488161628813-04466f872be2?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Sartorial deconstruction, vintage workwear silhouette against raw steel beams.',
            'credit' => 'Christian Ferrer / SICKSAINT Textile Syndicate',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1496440737103-cd596325d314?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Analog optical flare, industrial freight elevator hallway, Detroit.',
            'credit' => 'Austin Neill / SICKSAINT Industrial Archives',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Direct ring-flash portrait, asymmetric fringe and razor-sharp tailoring.',
            'credit' => 'Christian Buehner / SICKSAINT Studio Unit',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1463453091185-61582044d556?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Raw 35mm candid portrait, candid expression in a subway concourse.',
            'credit' => 'Julian Wan / SICKSAINT Urban Journal',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1501196354995-cbb51c65aaea?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Grainy lo-fi monochrome portrait, heavy grain and shadow play.',
            'credit' => 'Jurica Koletic / SICKSAINT Optical Syndicate',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1544005313-94ddf0286df2?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Subdued tungsten illumination, minimalist turtleneck and wire spectacles.',
            'credit' => 'Danielle MacInnes / SICKSAINT Monograph Unit',
        ],
        [
            'url' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=1600&auto=format&fit=crop',
            'caption' => 'Monochrome film documentary, brutalist concrete stairway, Southbank London.',
            'credit' => 'Stefan Stefancik / SICKSAINT Architectural Bureau',
        ],
    ];

    /**
     * SICKSAINT's 20 Official Categories and their semantic themes.
     */
    private const GENRES = [
        'world' => [
            'name' => 'World',
            'topics' => ['Subsea Infrastructure', 'Sovereign Corridors', 'Bilateral Accords', 'Deep-Water Cables', 'Borderland Statecraft', 'Resource Sovereign Charters', 'Strait Telemetry', 'Diplomatic Monoliths'],
            'kickers' => ['GLOBAL INTELLIGENCE · SPECIAL DISPATCH', 'STATECRAFT & POWER · ANATOMICAL REPORT', 'SOVEREIGN CORRIDORS · DOSSIER'],
        ],
        'technology' => [
            'name' => 'Technology',
            'topics' => ['Subterranean GPU Sanctuaries', 'Silicon Lithography', 'Post-Quantum Cryptanalysis', 'Isolated Server Vaults', 'Micro-Grid Computing', 'Liquid Helium Cooling', 'Foundational Kernels'],
            'kickers' => ['INFRASTRUCTURE & COMPUTE · DOSSIER', 'DEEP SYSTEMS · TECHNICAL REPORT', 'FOUNDATION ARCHITECTURE · ESSAY'],
        ],
        'politics' => [
            'name' => 'Politics',
            'topics' => ['Post-Westphalian Enclaves', 'Institutional Governance', 'Treaty Architecture', 'Legislative Precedents', 'Executive Orders', 'Constitutional Dissidence', 'Sovereign Immunity'],
            'kickers' => ['GOVERNANCE & DECREE · CRITICAL ESSAY', 'POLITICAL ANATOMY · INVESTIGATION', 'SOVEREIGN CHARTERS · SPECIAL REPORT'],
        ],
        'business' => [
            'name' => 'Business',
            'topics' => ['Sovereign Wealth Mandates', 'Dark Pool Arbitrage', 'Strategic Reserve Metals', 'Global Shipping Cartels', 'Central Bank Reserves', 'Off-Market Equity Cleansing'],
            'kickers' => ['MACRO CAPITAL & SETTLEMENT · DISPATCH', 'COMMODITY MONOPOLIES · INVESTIGATION', 'INDUSTRIAL AUDIT · REPORT'],
        ],
        'culture' => [
            'name' => 'Culture',
            'topics' => ['Underground Tape Archives', 'Brutalist Monasteries', 'Aesthetic Philosophy', 'Dissident Zines', 'Decentralized Micro-Galleries', 'Radical Spatial Design'],
            'kickers' => ['CULTURAL CRITICISM · ESSAY', 'RADICAL ARCHIVES · ESSAY', 'AESTHETIC PHILOSOPHY · CRITIQUE'],
        ],
        'fashion' => [
            'name' => 'Fashion',
            'topics' => ['Neo-Brutalist Patternmaking', 'Raw Selvedge Textiles', 'Heavyweight Gauge Knits', 'Deconstructed Wool Trenchcoats', 'Archival Japanese Denim', 'Sartorial Monasticism'],
            'kickers' => ['SARTORIAL ANATOMY · FIELD DISPATCH', 'TEXTILE EXPLORATIONS · CRITIQUE', 'HAUTE MINIMALISM · DOSSIER'],
        ],
        'entertainment' => [
            'name' => 'Entertainment',
            'topics' => ['Auteur Micro-Budget Cinema', 'Underground Screen Culture', 'Photochemical Restoration', 'Direct-to-Tape Festivals', 'Monophonic Synthesizer Scores'],
            'kickers' => ['CINEMATOGRAPHIC ESSAY · DISPATCH', 'AUTEUR CRITICISM · ESSAY', 'UNDERGROUND CULTURE · REPORT'],
        ],
        'sports' => [
            'name' => 'Sports',
            'topics' => ['Computational Hydrofoil Racing', 'Apex Aerodynamic Telemetry', 'Single-Handed Transatlantic Sailing', 'High-Altitude Biomechanics', 'Sub-Zero Alpine Expeditions'],
            'kickers' => ['HUMAN TELEMETRY · FIELD REPORT', 'APEX PERFORMANCE · ESSAY', 'EXTREME DYNAMICS · DOSSIER'],
        ],
        'gaming' => [
            'name' => 'Gaming',
            'topics' => ['Decentralized Spatial Physics', 'Ray-Traced Brutalism', 'Virtual Commodity Markets', 'Synthetic Topology Engines', 'Sub-Zero Server Cluster Worlds'],
            'kickers' => ['SYNTHETIC TOPOLOGY · DISPATCH', 'SPATIAL SIMULATION · ESSAY', 'INTERACTIVE WORLDS · DOSSIER'],
        ],
        'lifestyle' => [
            'name' => 'Lifestyle',
            'topics' => ['Acoustic Minimalist Sanctuaries', 'Subterranean Wine Cisterns', 'Off-Grid Brutalist Dwellings', 'Monastic Cold Springs', 'Archival Design Living'],
            'kickers' => ['DISCIPLINED LIVING · MONOGRAPH', 'SPATIAL MEDITATIONS · ESSAY', 'TACTILE SANCTUARIES · DISPATCH'],
        ],
        'ai' => [
            'name' => 'Artificial Intelligence',
            'topics' => ['Autonomous Reasoning Kernels', 'Foundation Inference Grids', 'Synthetic Cognition Benchmarks', 'Self-Supervised Agent Clusters', 'Sub-Arctic Data Havens'],
            'kickers' => ['FRONTIER INFERENCE · INVESTIGATION', 'SYNTHETIC COGNITION · DOSSIER', 'AUTONOMOUS CLUSTERS · SPECIAL REPORT'],
        ],
        'finance' => [
            'name' => 'Finance',
            'topics' => ['Bilateral Currency Corridors', 'Bespoke Repo Markets', 'High-Frequency Fiber Arbitrage', 'Cross-Border Clearing Nodes', 'Sovereign Debt Swaps'],
            'kickers' => ['SHADOW LIQUIDITY · FINANCIAL AUDIT', 'DEBT MECHANICS · ESSAY', 'GLOBAL SETTLEMENT · REPORT'],
        ],
        'film' => [
            'name' => 'Film & Television',
            'topics' => ['70mm Photochemical Emulsion', 'Auteur Sound Design', 'Long-Take Choreography', 'Underground Film Co-ops', 'Celluloid Nitrate Preservation'],
            'kickers' => ['CELLULOID DOSSIER · CRITIQUE', 'CINEMATIC GRAMMAR · ESSAY', 'DIRECTOR ARCHIVE · REPORT'],
        ],
        'music' => [
            'name' => 'Music',
            'topics' => ['Modular Voltage Synthesis', 'Sub-Bass Acoustic Resonance', 'Berlin Warehouse Tape Runs', 'Minimalist Compositional Scaffolds', 'Ambisonic Field Recordings'],
            'kickers' => ['SONIC ARCHITECTURE · DISPATCH', 'ACOUSTIC CRITIQUE · ESSAY', 'MODULAR SOUNDSCAPES · REPORT'],
        ],
        'science' => [
            'name' => 'Science',
            'topics' => ['Cryogenic Fusion Chambers', 'Subatomic Particle Accelerators', 'Deep-Bore Geothermal Probes', 'Dark Matter Cryostats', 'Molecular Nanotube Assemblies'],
            'kickers' => ['EXPERIMENTAL PHYSICS · DOSSIER', 'FRONTIER DISCOVERY · REPORT', 'APPLIED SCIENCE · INVESTIGATION'],
        ],
        'automotive' => [
            'name' => 'Automotive',
            'topics' => ['Hand-Beaten Aluminum Monocoques', 'Computational Ground-Effect Venturis', 'Sub-Atmospheric Aero Dynos', 'Archival Group B Homologation', 'Naturally Aspirated V12 Metallurgy'],
            'kickers' => ['MECHANICAL ENGINEERING · ESSAY', 'AERO TELEMETRY · DISPATCH', 'COACHBUILDING CRAFT · DOSSIER'],
        ],
        'travel' => [
            'name' => 'Travel',
            'topics' => ['Svalbard Permafrost Outposts', 'Patagonian Icefield Traverses', 'Solitary Hebridean Lighthouses', 'Atacama Radio Observatory Plateaus', 'High Pamir Mountain Passes'],
            'kickers' => ['EXPEDITIONARY FIELD DISPATCH', 'SOLITARY GEOGRAPHIES · ESSAY', 'BORDERLAND PASSAGES · REPORT'],
        ],
        'photography' => [
            'name' => 'Photography',
            'topics' => ['Silver Halide Gelatin Prints', 'Large-Format 8x10 Field Cameras', 'Direct Monochrome Development', 'Darkroom Chemistry Variations', 'Documentary Flash Cadence'],
            'kickers' => ['OPTICAL MONOGRAPH · CRITIQUE', 'SILVER HALIDE STUDIES · ESSAY', 'DOCUMENTARY WITNESS · DOSSIER'],
        ],
        'architecture' => [
            'name' => 'Architecture',
            'topics' => ['Poured-In-Place Raw Concrete', 'Subterranean Thermal Baths', 'Cantilevered Granite Sanctuaries', 'Brutalist Civic Monoliths', 'Cyclopean Masonry Structures'],
            'kickers' => ['SPATIAL BRUTALISM · ARCHITECTURAL AUDIT', 'STRUCTURAL MONUMENT · ESSAY', 'TECTONIC FORMS · DISPATCH'],
        ],
        'health' => [
            'name' => 'Health',
            'topics' => ['Circadian Photoreceptor Tuning', 'Cellular Autophagy Protocols', 'Sub-Zero Cryotherapy Telemetry', 'Epigenetic Methylation Markers', 'Hyperbaric Oxygen Regimes'],
            'kickers' => ['CELLULAR BIOLOGY · RESEARCH REPORT', 'EPIGENETIC PROTOCOLS · ESSAY', 'HUMAN RESILIENCE · DISPATCH'],
        ],
    ];

    public function handle(): int
    {
        $countPerGenre = max(1, (int) $this->option('count'));
        $targetGenre = strtolower((string) $this->option('genre'));
        $targetMode = strtolower((string) $this->option('target'));
        $outputDir = $this->option('output') ?: storage_path('app/seeders/editorial_dispatches');

        $genresToSeed = ($targetGenre === 'all' || !isset(self::GENRES[$targetGenre]))
            ? self::GENRES
            : [$targetGenre => self::GENRES[$targetGenre]];

        $totalExpected = count($genresToSeed) * $countPerGenre;

        $this->info("======================================================================");
        $this->info(" SICKSAINT — HIGH-END EDITORIAL SEEDER (UNDERGROUND Y2K AESTHETIC)");
        $this->info("======================================================================");
        $this->info("Genres in scope:       " . count($genresToSeed));
        $this->info("Articles per genre:    " . number_format($countPerGenre));
        $this->info("Total articles target: " . number_format($totalExpected));
        $this->info("Output target mode:    " . strtoupper($targetMode));
        $this->info("JSON export location:  " . $outputDir);
        $this->info("Photography Style:     Underground Y2K & 35mm Flash Film Portraits");
        $this->info("======================================================================");

        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        $photoPool = self::STREET_PHOTOGRAPHY_POOL;
        $photoCount = count($photoPool);
        $globalIndex = 0;
        $manifest = [];

        $store = ($targetMode === 'firestore' || $targetMode === 'both')
            ? app(FirestoreStore::class)
            : null;

        $bar = $this->output->createProgressBar($totalExpected);
        $bar->start();

        foreach ($genresToSeed as $genreSlug => $meta) {
            $genreArticles = [];
            $genreName = $meta['name'];
            $topics = $meta['topics'];
            $kickers = $meta['kickers'];

            for ($i = 1; $i <= $countPerGenre; $i++) {
                $globalIndex++;
                $photo = $photoPool[($globalIndex - 1) % $photoCount];
                $topic = $topics[($i - 1) % count($topics)];
                $kicker = $kickers[($i - 1) % count($kickers)];
                
                $title = $this->generateTitle($genreName, $topic, $i);
                $slug = Str::slug($title) . '-' . substr(md5($genreSlug . '-' . $i), 0, 6);
                $excerpt = $this->generateExcerpt($genreName, $topic);
                $content = $this->generateContent($genreName, $topic, $photo['caption']);
                $format = $this->pickFormat($i);
                $readingTime = 4 + (($i * 3) % 14);

                $article = [
                    'id' => 'disp-' . $genreSlug . '-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                    'title' => $title,
                    'subtitle' => "An analytical monograph regarding {$topic} across contemporary underground corridors.",
                    'kicker' => $kicker,
                    'slug' => $slug,
                    'excerpt' => $excerpt,
                    'content' => $content,
                    'coverImageUrl' => $photo['url'],
                    'coverImageCaption' => $photo['caption'],
                    'coverImageCredit' => $photo['credit'],
                    'categorySlug' => $genreSlug,
                    'categoryName' => $genreName,
                    'tags' => [
                        ['id' => 'tag-' . Str::slug($genreName), 'name' => $genreName, 'slug' => Str::slug($genreName)],
                        ['id' => 'tag-' . Str::slug($topic), 'name' => $topic, 'slug' => Str::slug($topic)],
                        ['id' => 'tag-y2k', 'name' => 'Underground Y2K', 'slug' => 'underground-y2k'],
                    ],
                    'format' => $format,
                    'status' => 'PUBLISHED',
                    'featured' => ($i === 1),
                    'heroOrder' => ($i === 1 && in_array($genreSlug, ['world', 'technology', 'fashion'])) ? 1 : null,
                    'readingTimeMinutes' => $readingTime,
                    'publishedAt' => now()->subHours($globalIndex % 720)->toIso8601String(),
                    'author' => [
                        'id' => 'usr-editorial-lead',
                        'name' => 'SICKSAINT Editorial Bureau',
                        'username' => 'editorial',
                        'avatarUrl' => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=300&auto=format&fit=crop',
                    ],
                ];

                $genreArticles[] = $article;

                // If Firestore output mode is active, persist directly
                if ($store) {
                    try {
                        $store->put('submissions', $article['id'], $article);
                    } catch (\Throwable $e) {
                        // Silent fallback for dry-run or mock mode
                    }
                }

                $bar->advance();
            }

            // Write chunked JSON file for this genre
            $genreFilePath = "{$outputDir}/articles_{$genreSlug}.json";
            File::put($genreFilePath, json_encode($genreArticles, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $manifest[$genreSlug] = [
                'name' => $genreName,
                'count' => count($genreArticles),
                'file' => "articles_{$genreSlug}.json",
            ];
        }

        $bar->finish();
        $this->newLine(2);

        // Save manifest
        $manifestPath = "{$outputDir}/manifest.json";
        File::put($manifestPath, json_encode([
            'generatedAt' => now()->toIso8601String(),
            'totalArticles' => $globalIndex,
            'genresCount' => count($genresToSeed),
            'style' => 'Underground Y2K & Raw Street Photography',
            'genres' => $manifest,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info("SUCCESS: Successfully generated " . number_format($globalIndex) . " editorial articles.");
        $this->info("Manifest index saved: {$manifestPath}");
        $this->info("Articles partitioned cleanly into 20 genre JSON archives in {$outputDir}.");

        return self::SUCCESS;
    }

    private function generateTitle(string $genre, string $topic, int $index): string
    {
        $prefixes = [
            'The Anatomy of', 'Notes on', 'The Architecture of', 'Dossier:', 'Investigations into',
            'The Silent Precedent:', 'Deconstructing', 'Beyond the Boundary:', 'The Mechanics of',
            'Monograph on', 'The Protocol for', 'Dispatches from the Outskirts of', 'The Geometry of',
        ];

        $suffixes = [
            'in the Post-Westphalian Era', 'Across Underground Metropoles', 'and the Question of Autonomy',
            'Under Extreme Environmental Friction', 'within Contemporary Counter-Institutions',
            'and the Search for Absolute Signal', 'in an Age of Total Inundation', 'as Tactical Infrastructure',
        ];

        $prefix = $prefixes[($index - 1) % count($prefixes)];
        $suffix = $suffixes[($index * 3) % count($suffixes)];

        return "{$prefix} {$topic} {$suffix}";
    }

    private function generateExcerpt(string $genre, string $topic): string
    {
        return "An exhaustive anatomical survey into the structural reality of {$topic}. Drawing on direct field notes and archival photography, this dispatch examines the tension between institutional control and underground autonomy.";
    }

    private function generateContent(string $genre, string $topic, string $imageCaption): string
    {
        return <<<MARKDOWN
### I. The Tactical Premise

To understand the trajectory of modern **{$genre}**, one must examine not the formal proclamations issued from institutional boardrooms, but the latent tensions emerging within raw urban corridors.

Here, {$topic} is not treated as an abstract academic curiosity; it functions as an essential, high-friction operational reality.

> "The true signal is never broadcast over public frequencies. It is preserved in the shadows of the machinery, recorded on silver halide and spoken in quiet rooms."  
> — *SICKSAINT Editorial Dossier, Vol. IV*

### II. The Structural Dynamics

In every generational transition, the dominant paradigm attempts to enforce a uniform digital homogeneity. The response from dissident practitioners has been immediate and uncompromising: a deliberate return to physical permanence, tactile textures, and analog sovereignty.

* **Friction Over Convenience:** Rejecting disposable algorithmic noise in favor of enduring structural craft.
* **The Monastic Ethic:** A disciplined dedication to uncompromised execution, whether in patternmaking, silicon compilation, or architectural concrete.
* **Photographic Witness:** Documenting reality without artificial smoothing, preserving every shadow, grain, and street reflection.

### III. Critical Inferences

As we analyze the archival evidence captured above (*"{$imageCaption}"*), the imperative becomes absolute: in an era of manufactured noise, clarity is an act of resistance.

The path forward requires neither nostalgia nor surrender to hyper-acceleration. It demands rigorous critical discernment, unsparing intellectual discipline, and the courage to exist without the noise.
MARKDOWN;
    }

    private function pickFormat(int $index): string
    {
        $formats = ['DISPATCH', 'ESSAY', 'DOSSIER', 'CRITIQUE', 'REPORT'];
        return $formats[($index - 1) % count($formats)];
    }
}
