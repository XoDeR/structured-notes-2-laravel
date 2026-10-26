# Structured Notes 2

A tree-structured note keeper: nodes form a tree, topics (rich text with tags, images and file attachments) live in nodes. Topics can be private or public, copied, moved, linked into several nodes, favorited and searched.

**Laravel 13**, **Inertia 3 + Vue 3**, **shadcn-vue** and **Tailwind CSS 4**.

## Stack

- Laravel 13 (PHP 8.3+), Fortify auth (2FA, passkeys, password reset)
- PostgreSQL 17 in Docker Compose; Laravel itself runs locally
- UUID primary keys everywhere
- [spatie/laravel-permission](https://github.com/spatie/laravel-permission) for roles (`admin`, `user`) and permissions
- [spatie/laravel-medialibrary](https://github.com/spatie/laravel-medialibrary) for topic images and attachments (private disk, served through an authorized route)
- TipTap editor, content sanitized server-side with `symfony/html-sanitizer`

## Getting started

```bash
docker compose up -d --wait        # PostgreSQL on localhost:5432
composer install
cp .env.example .env && php artisan key:generate
php artisan migrate --seed
npm install
composer dev                        # or: php artisan serve + npm run dev
```

The seeder creates the roles, the root node and an admin account:
`admin@example.com` / `password` (override with `ADMIN_EMAIL` / `ADMIN_PASSWORD` before seeding, and change it after the first login).

There is no self-registration: administrators create accounts under **Users**.

## Rules worth knowing

- A public node/topic can never live inside a private node.
- Private content is visible only to its owner and to admins (`manage all content`).
- Deleting a node deletes the topics stored in it; topics that are only _linked_ there are unlinked.
- Images and files can be added once a topic has been saved.
- Public topics have a read-only page at `/t/{uuid}` that works for guests.

## Tests and checks

```bash
php artisan test        # uses the structured_notes_testing database from compose
vendor/bin/pint --test
npm run check && npm run types:check
```
