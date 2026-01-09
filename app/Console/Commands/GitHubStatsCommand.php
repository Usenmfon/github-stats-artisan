<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

use function Laravel\Prompts\error;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;

class GitHubStatsCommand extends Command
{
    protected $signature = 'github:stats';

    protected $description = 'Get exact GitHub stats using GraphQL (streaks, commits, calendar)';

    public function handle(): int
    {
        $username = text(
            label: 'Enter GitHub username',
            placeholder: 'e.g. usenmfon',
            required: true
        );

        $token = config('services.github.token');

        if (! $token) {
            error('GitHub token not configured. Add GITHUB_TOKEN to .env');

            return 1;
        }

        $data = $this->fetchContributionData($username, $token);

        if (! $data) {
            error('Could not fetch contribution data.');

            return 1;
        }

        info("📊 GitHub Contribution Stats — {$username}");

        $this->components->twoColumnDetail(
            'Total Contributions (last year)',
            $data['total']
        );

        $this->components->twoColumnDetail(
            'Current Streak',
            $data['current_streak'].' days'
        );

        $this->components->twoColumnDetail(
            'Longest Streak',
            $data['longest_streak'].' days'
        );

        $this->displayContributionGraph($data['weeks']);

        $this->displayGitHubStyleHeatmap($data['weeks']);

        return 0;
    }

    protected function fetchContributionData(string $username, string $token): ?array
    {
        $query = <<<'GRAPHQL'
query ($login: String!) {
  user(login: $login) {
    contributionsCollection {
      contributionCalendar {
        totalContributions
        weeks {
          contributionDays {
            date
            contributionCount
          }
        }
      }
    }
  }
}
GRAPHQL;

        $response = Http::withToken($token)
            ->post('https://api.github.com/graphql', [
                'query' => $query,
                'variables' => ['login' => $username],
            ]);

        if ($response->failed()) {
            return null;
        }

        $calendar =
            $response->json('data.user.contributionsCollection.contributionCalendar');

        $days = collect($calendar['weeks'])
            ->flatMap(fn ($w) => $w['contributionDays'])
            ->map(fn ($d) => [
                'date' => Carbon::parse($d['date']),
                'count' => $d['contributionCount'],
            ]);

        return [
            'total' => $calendar['totalContributions'],
            'current_streak' => $this->calculateCurrentStreak($days),
            'longest_streak' => $this->calculateLongestStreak($days),
            'weeks' => $calendar['weeks'],
        ];
    }

    protected function calculateCurrentStreak(Collection $days): int
    {
        $streak = 0;

        foreach ($days->sortByDesc('date') as $day) {
            if ($day['count'] > 0) {
                $streak++;
            } else {
                break;
            }
        }

        return $streak;
    }

    protected function calculateLongestStreak(Collection $days): int
    {
        $longest = 0;
        $current = 0;

        foreach ($days->sortBy('date') as $day) {
            if ($day['count'] > 0) {
                $current++;
                $longest = max($longest, $current);
            } else {
                $current = 0;
            }
        }

        return $longest;
    }

    protected function displayContributionGraph(array $weeks): void
    {
        info('🟩 Contribution Heatmap (Recent Weeks)');

        $rows = collect($weeks)
            ->take(-12) // last 12 weeks
            ->map(function ($week) {
                $total = collect($week['contributionDays'])
                    ->sum('contributionCount');

                $bar = str_repeat('█', min(20, (int) ($total / 5)));

                return [
                    Carbon::parse($week['contributionDays'][0]['date'])->format('M d'),
                    $total,
                    $bar,
                ];
            })
            ->values()
            ->all();

        table(
            headers: ['Week', 'Contributions', 'Graph'],
            rows: $rows
        );
    }

    protected function displayGitHubStyleHeatmap(array $weeks): void
    {
        info('🟩 GitHub Contribution Graph (Last 12 Weeks)');
        info('Sun → Sat');

        $levels = [
            0 => '  ',
            1 => '░░',
            5 => '▒▒',
            10 => '▓▓',
            20 => '██',
        ];

        $rows = array_fill(0, 7, []);

        foreach (array_slice($weeks, -12) as $week) {
            foreach ($week['contributionDays'] as $index => $day) {
                $count = $day['contributionCount'];

                $block = match (true) {
                    $count === 0 => $levels[0],
                    $count < 5 => $levels[1],
                    $count < 10 => $levels[5],
                    $count < 20 => $levels[10],
                    default => $levels[20],
                };

                $rows[$index][] = $block;
            }
        }

        foreach ($rows as $row) {
            $this->line(implode(' ', $row));
        }

        $this->newLine();
        info('Legend:  ░ low   ▒ medium   ▓ high   █ very high');
    }
}
