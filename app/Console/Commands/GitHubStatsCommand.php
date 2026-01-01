<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;
use function Laravel\Prompts\error;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class GitHubStatsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'github:stats';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Get Github user stats: repos, commits, top language';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $username = text(
            label: 'Enter GitHub username',
            placeholder: 'e.g. usenmfon',
            required: true,
            // validate: fn (string $value) => strlen($value) < 1 || strlen($value) ? 'Username must be 1-39 characters.' : null
        );

        /** @var Response $response */
        $response = Http::withHeaders([
            'Accept' => 'application/vnd.github.v3+json',
            'User-Agent' => 'Laravel CLI',
        ])->get("https://api.github.com/users/{$username}");

        if($response->failed()) {
            error("User '{$username}' not found or GitHub API error.");
            return 1;
        }

        $user = $response->json();

        info("📊 Stats for GitHub user: {$user['login']}");

        $publicRepos = $user['public_repos'] ?? 0;
        $this->components->twoColumnDetail('Public Repos', $publicRepos);

        $contributionsResponse = Http::get("https://api.github.com/users/{$username}/events/public");

    if ($contributionsResponse->failed()) {
        $commitCount = 0;
    } else {
        $events = $contributionsResponse->json();

        $pushEvents = collect($events)->where('type', 'PushEvent');
        $commitCount = $pushEvents->count() * 2; // 2 commits per push on average
    }

    $this->components->twoColumnDetail('Total Commits (approx)', $commitCount);

    // Most used language (same as before)
    $reposResponse = Http::get("https://api.github.com/users/{$username}/repos?per_page=100");

    if ($reposResponse->failed()) {
        $this->components->twoColumnDetail('Top Language', 'Could not fetch repos');
    } else {
        $repos = $reposResponse->json();
        $languageBytes = [];

        foreach ($repos as $repo) {
            if ($repo['language']) {
                $language = $repo['language'];
                $languageBytes[$language] = ($languageBytes[$language] ?? 0) + 1;
            }
        }

        if (empty($languageBytes)) {
            $this->components->twoColumnDetail('Top Language', 'None');
        } else {
            arsort($languageBytes);
            $topLanguage = array_key_first($languageBytes);
            $this->components->twoColumnDetail('Top Language', $topLanguage);
        }
    }

    // Commit graph (same as before)
    $this->displayCommitGraph($username);

    return 0;
    }

    protected function displayCommitGraph(string $username): void
    {
        info('📅 Weekly Commit Activity (last 52 weeks):');

        $response = Http::get("https://api.github.com/users/{$username}/events/public");
        if ($response->failed()) {
            error('Could not fetch commit activity.');
            return;
        }

        $events = $response->json();

        // Group by week (YYYY-WW)
        $weekly = collect($events)
    ->where('type', 'PushEvent')
    ->map(fn ($event) => [
        'week' => now()->parse($event['created_at'])->format('Y-\WW'),
        'count' => 1, // 1 push ≈ 1 commit for graph
    ])
    ->groupBy('week')
    ->map->sum('count')
    ->sortKeys()
    ->take(52);

        if ($weekly->isEmpty()) {
            $this->info('No recent commits found.');
            return;
        }

        // Render simple text graph
        $max = $weekly->max();
        $rows = $weekly->map(function ($count, $week) use ($max) {
            $bar = str_repeat('█', max(1, (int) round($count / max(1, $max) * 20)));
            return [$week, $count, $bar];
        })->values()->all();

        table(
            headers: ['Week', 'Commits', 'Graph'],
            rows: $rows
        );
    }

}
