# Stylized Anchor Link

This plugin provides a Gutenberg block allowing users to search for published posts and insert them as styled anchor links in the editor.

Includes a custom WP-CLI command to find and list post IDs that use this block.

## Block Features
- Search for posts by title or ID
- Paginated results
- Recent posts shown by default
- Styled "Read More" links with proper formatting

## WP-CLI Command Usage

Basic usage:
```
wp dmg-read-more search
```

With date range:
```
wp dmg-read-more search --date-before="2024-05-11" --date-after="2023-04-11"
```

With batch size for performance tuning:
```
wp dmg-read-more search --batch-size=10000
```

Limit total results:
```
wp dmg-read-more search --limit=100
```

Combined parameters:
```
wp dmg-read-more search --date-after="2023-01-01" --batch-size=5000 --limit=200
```

If no dates are given, the command defaults to searching the last 30 days.

Optimized for large databases—processes results in batches with a progress indicator to prevent timeouts or excessive memory use. Uses temporary tables when available for maximum performance with millions of records.
