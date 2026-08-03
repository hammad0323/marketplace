<?php
/**
 * Tiny view renderer. Renders a view file into $content, then wraps it
 * in the requested layout so each marketplace can have its own theme
 * (layout + CSS) while sharing the same rendering mechanism.
 */

class View
{
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data, EXTR_SKIP);

        $viewFile = __DIR__ . "/../views/{$view}.php";
        if (!file_exists($viewFile)) {
            throw new RuntimeException("View not found: {$view}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        $layoutFile = __DIR__ . "/../views/layouts/{$layout}.php";
        if (!file_exists($layoutFile)) {
            throw new RuntimeException("Layout not found: {$layout}");
        }

        require $layoutFile;
    }

    public static function partial(string $view, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        require __DIR__ . "/../views/{$view}.php";
    }
}
