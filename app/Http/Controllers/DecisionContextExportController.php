<?php

namespace App\Http\Controllers;

use App\ContentIntelligence\DecisionContextExport;
use Symfony\Component\HttpFoundation\Response;

final class DecisionContextExportController
{
    public function __invoke(string $format, DecisionContextExport $export): Response
    {
        abort_unless(in_array($format, ['json', 'markdown'], true), 404);

        $context = $export->build();

        abort_if($context['account'] === null, 404, 'Instagram account not found.');

        $content = $format === 'json'
            ? $export->toJson($context)
            : $export->toMarkdown($context);

        return response($content, 200, [
            'Content-Type' => $format === 'json'
                ? 'application/json; charset=UTF-8'
                : 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$export->filename($context, $format).'"',
            'Cache-Control' => 'no-store, private',
        ]);
    }
}
