# CISR

Multilingual blog (EN / AR / FR with RTL) on Kirby 5. Styled as a hand-built shadcn/USGC cross — JetBrains Mono, hairline borders, table-first layout. Vanilla-JS panel swap, no Node/npm.

## Run locally

```
php -S localhost:8765 router.php
```

Open <http://localhost:8765/>.

## First-time panel setup

1. Open <http://localhost:8765/panel> — it redirects to `/panel/installation`.
2. Fill in the form to create the first admin user.
3. Log in. From the dashboard:
   - **Site → Home**: edit the Intro paragraph per language (use the language tabs at the top).
   - **Site → Articles**: click the `+` button on the *Drafts* or *Published* section to add a new article. The Body field uses the Blocks editor (heading / text / list / quote / code / image / line / markdown).
   - Each article has Date, SKU, Summary fields. SKU is shared across languages; everything else is translated per-language tab.

## Edit

Templates: `site/templates/`. Per-page content snippets: `site/snippets/page/{home,article,articles,default}.php` — these are shared between full pages and partial swaps. UI snippets: `site/snippets/ui/`. Block renderers: `site/snippets/blocks/`.

## Rebuild CSS

After editing classes:

```
./build.sh
```

## Theme

Toggle dark/light via the `◐` button in the sidebar foot. The choice is stored in localStorage. To change palette colors, edit `assets/css/tailwind.src.css` (`:root` for HIGHK light, `.dark` for RETICLE).

---

Kirby is © 2009 Bastian Allgeier — see <https://getkirby.com/license>.
