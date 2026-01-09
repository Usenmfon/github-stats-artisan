# GitHub Stats Artisan Command

A small but powerful **Laravel Artisan command** that uses **Laravel Prompts** to interactively ask for a GitHub username and display useful GitHub statistics directly in the terminal.

This project is ideal for learning how to:
- Build custom Artisan commands
- Use Laravel Prompts for interactive CLI experiences
- Consume the GitHub public API
- Present data visually in the terminal

---

## ✨ Features

- 🔍 Prompt for a GitHub username using **Laravel Prompts**
- 📦 Display total **public repositories**
- 🔢 Show **approximate commit count** (from recent activity)
- 🧠 Detect **most used programming language**
- 📊 Render a simple **weekly commit graph** in the terminal
- ⚡ Fast, lightweight, and API-friendly
- 🧪 Uses only GitHub public endpoints (no authentication required)

---

## 🛠 Requirements

- PHP **8.1+**
- Laravel **10+**
- Internet connection (for GitHub API access)

---

## 📦 Installation

Clone the repository:

```bash
git clone https://github.com/usenmfon/github-stats-artisan.git
cd github-stats-artisan
```
## Install dependencies:
```
composer install
```

## Generate the application key:
```
php artisan key:generate
```

## Run the Artisan command:
```
php artisan github:stats
```

## You will be prompted to enter a GitHub username:
```
Enter GitHub username: octocat
```

## 📟 Example Output
```
GitHub Stats for: octocat
---------------------------------
Public Repositories: 8
Approximate Commits (last 7 days): 24
Most Used Language: JavaScript

Weekly Commit Activity:
Mon ████
Tue ██████
Wed ██
Thu █████
Fri ███
Sat █
Sun ████
```

🧠 How It Works

1. Laravel Prompts collects the GitHub username interactively

2. GitHub public API endpoints are queried:

* User profile

* Repositories

* Recent public events

3. Repository languages are aggregated to determine the most used language

4. Commit activity is grouped by weekday

5. Output is rendered using terminal-friendly formatting

## 🧩 Command Structure
```
app/
└── Console/
    └── Commands/
        └── GithubStatsCommand.php
```

## COPY ENV (ADD GITHUB TOKEN)
```
cp .env.example .env
```

## ⚠️ Limitations

* Commit counts are approximate

* Private repositories and commits are excluded

* GitHub API rate limits apply to unauthenticated requests

## 🧪 Testing

```
Run the command manually:

php artisan github:stats
```

## 📄 License

This project is open-source and licensed under the MIT License.
