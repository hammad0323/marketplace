<?php

/**
 * Global search: queries products across every marketplace type in a
 * single pass, so results carry their own marketplace badge
 * (Handmade / Business Shop / Official Store) regardless of source.
 */
class SearchController
{
    public function index(): void
    {
        $term = trim($_GET['q'] ?? '');
        $results = $term !== '' ? (new Product())->search($term) : [];

        View::render('search/index', [
            'title' => $term !== '' ? "Search results for \"{$term}\"" : 'Search',
            'term' => $term,
            'results' => $results,
        ], 'main');
    }
}
