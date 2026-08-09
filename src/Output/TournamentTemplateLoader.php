<?php

declare(strict_types=1);

namespace Monatsblitz\Output;

class TournamentTemplateLoader
{
    public function load(string $template_name): array
    {
        $template_post = get_page_by_title($template_name, OBJECT, 'post');
        $template_content = '';

        if ($template_post && !is_wp_error($template_post)) {
            $template_content = $template_post->post_content;
        } else {
            $template_path = MB_PLUGIN_PATH . 'templates/post-template.html';
            if (file_exists($template_path)) {
                $template_content = file_get_contents($template_path);
            }
        }

        return [
            'content' => $template_content,
            'post' => $template_post,
        ];
    }
}
